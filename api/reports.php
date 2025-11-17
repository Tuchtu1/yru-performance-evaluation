<?php

/**
 * api/reports.php
 * Reports API
 * API สำหรับสร้างและส่งออกรายงาน
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';

function sendResponse($success, $message, $data = null, $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบ Authentication
if (!isset($_SESSION['user'])) {
    sendResponse(false, 'Unauthorized', null, 401);
}

$current_user = $_SESSION['user'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = getDB();

    switch ($action) {

        // ==================== Individual Report ====================
        case 'individual':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $user_id = (int)($_GET['user_id'] ?? $current_user['user_id']);

            // ตรวจสอบสิทธิ์ (ดูได้เฉพาะตัวเอง หรือเป็น admin/manager)
            if ($user_id !== $current_user['user_id'] && !in_array($current_user['role'], ['admin', 'manager'])) {
                sendResponse(false, 'Forbidden', null, 403);
            }

            // ข้อมูลบุคลากร
            $user_stmt = $db->prepare("
                SELECT u.*, d.department_name_th, d.department_name_en
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE u.user_id = ?
            ");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();

            if (!$user) {
                sendResponse(false, 'User not found', null, 404);
            }
            unset($user['password']);

            // สถิติการประเมิน
            $stats_stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_evaluations,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    AVG(CASE WHEN status = 'approved' THEN total_score END) as average_score,
                    MAX(total_score) as max_score,
                    MIN(CASE WHEN status = 'approved' THEN total_score END) as min_score
                FROM evaluations
                WHERE user_id = ?
            ");
            $stats_stmt->execute([$user_id]);
            $statistics = $stats_stmt->fetch();

            // รายงานตามด้าน
            $aspects_stmt = $db->prepare("
                SELECT ea.aspect_name_th, ea.aspect_code,
                       AVG(ed.score) as avg_score,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count
                FROM evaluation_details ed
                INNER JOIN evaluations e ON ed.evaluation_id = e.evaluation_id
                INNER JOIN evaluation_aspects ea ON ed.aspect_id = ea.aspect_id
                WHERE e.user_id = ? AND e.status = 'approved'
                GROUP BY ed.aspect_id
                ORDER BY ea.display_order
            ");
            $aspects_stmt->execute([$user_id]);
            $by_aspects = $aspects_stmt->fetchAll();

            // ประวัติการประเมิน
            $history_stmt = $db->prepare("
                SELECT e.*, ep.period_name, ep.year, ep.semester
                FROM evaluations e
                LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
                WHERE e.user_id = ?
                ORDER BY e.submitted_at DESC
                LIMIT 10
            ");
            $history_stmt->execute([$user_id]);
            $history = $history_stmt->fetchAll();

            sendResponse(true, 'Success', [
                'user' => $user,
                'statistics' => $statistics,
                'by_aspects' => $by_aspects,
                'recent_history' => $history
            ]);
            break;

        // ==================== Department Report ====================
        case 'department':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            // เฉพาะ admin และ manager
            if (!in_array($current_user['role'], ['admin', 'manager'])) {
                sendResponse(false, 'Forbidden', null, 403);
            }

            $department_id = (int)($_GET['department_id'] ?? $current_user['department_id'] ?? 0);
            $period_id = (int)($_GET['period_id'] ?? 0);

            $where = "WHERE u.department_id = ?";
            $params = [$department_id];

            if ($period_id > 0) {
                $where .= " AND e.period_id = ?";
                $params[] = $period_id;
            }

            // ข้อมูลหน่วยงาน
            $dept_stmt = $db->prepare("SELECT * FROM departments WHERE department_id = ?");
            $dept_stmt->execute([$department_id]);
            $department = $dept_stmt->fetch();

            if (!$department) {
                sendResponse(false, 'Department not found', null, 404);
            }

            // สรุปภาพรวม
            $summary_stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT u.user_id) as total_staff,
                    COUNT(DISTINCT e.evaluation_id) as total_evaluations,
                    SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN e.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                    SUM(CASE WHEN e.status = 'draft' THEN 1 ELSE 0 END) as draft,
                    AVG(CASE WHEN e.status = 'approved' THEN e.total_score END) as avg_score
                FROM users u
                LEFT JOIN evaluations e ON u.user_id = e.user_id
                $where
            ");
            $summary_stmt->execute($params);
            $summary = $summary_stmt->fetch();

            // รายบุคคลในหน่วยงาน
            $staff_where = "WHERE u.department_id = ?";
            $staff_params = [$department_id];

            if ($period_id > 0) {
                $staff_where .= " AND (e.period_id = ? OR e.period_id IS NULL)";
                $staff_params[] = $period_id;
            }

            $staff_stmt = $db->prepare("
                SELECT u.user_id, u.full_name_th, u.personnel_type, u.position,
                       e.evaluation_id, e.status, e.total_score, e.submitted_at,
                       ep.period_name
                FROM users u
                LEFT JOIN evaluations e ON u.user_id = e.user_id
                LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
                $staff_where
                ORDER BY u.full_name_th
            ");
            $staff_stmt->execute($staff_params);
            $staff_list = $staff_stmt->fetchAll();

            // สถิติตามด้าน
            $aspects_stmt = $db->prepare("
                SELECT ea.aspect_name_th, ea.aspect_code,
                       AVG(ed.score) as avg_score,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count
                FROM evaluations e
                INNER JOIN users u ON e.user_id = u.user_id
                INNER JOIN evaluation_details ed ON e.evaluation_id = ed.evaluation_id
                INNER JOIN evaluation_aspects ea ON ed.aspect_id = ea.aspect_id
                $where AND e.status = 'approved'
                GROUP BY ed.aspect_id
                ORDER BY ea.display_order
            ");
            $aspects_stmt->execute($params);
            $by_aspects = $aspects_stmt->fetchAll();

            sendResponse(true, 'Success', [
                'department' => $department,
                'summary' => $summary,
                'staff_list' => $staff_list,
                'by_aspects' => $by_aspects
            ]);
            break;

        // ==================== Organization Report ====================
        case 'organization':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            // เฉพาะ admin
            if ($current_user['role'] !== 'admin') {
                sendResponse(false, 'Forbidden', null, 403);
            }

            $period_id = (int)($_GET['period_id'] ?? 0);

            $where = "";
            $params = [];

            if ($period_id > 0) {
                $where = "WHERE e.period_id = ?";
                $params[] = $period_id;
            }

            // สรุปภาพรวมองค์กร
            $summary_stmt = $db->prepare(
                "
                SELECT 
                    COUNT(DISTINCT u.user_id) as total_users,
                    COUNT(DISTINCT e.evaluation_id) as total_evaluations,
                    SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN e.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                    SUM(CASE WHEN e.status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                    SUM(CASE WHEN e.status = 'draft' THEN 1 ELSE 0 END) as draft,
                    AVG(CASE WHEN e.status = 'approved' THEN e.total_score END) as avg_score
                FROM users u
                LEFT JOIN evaluations e ON u.user_id = e.user_id
                " . ($where ? "WHERE " . substr($where, 6) : "")
            );
            $summary_stmt->execute($params);
            $summary = $summary_stmt->fetch();

            // สถิติตามหน่วยงาน
            $dept_where = $where ? "AND e.period_id = ?" : "";
            $dept_params = $period_id > 0 ? [$period_id] : [];

            $dept_stmt = $db->prepare("
                SELECT d.department_name_th, d.department_code,
                       COUNT(DISTINCT u.user_id) as staff_count,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count,
                       SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved,
                       AVG(CASE WHEN e.status = 'approved' THEN e.total_score END) as avg_score
                FROM departments d
                LEFT JOIN users u ON d.department_id = u.department_id
                LEFT JOIN evaluations e ON u.user_id = e.user_id $dept_where
                GROUP BY d.department_id
                ORDER BY d.department_name_th
            ");
            $dept_stmt->execute($dept_params);
            $by_departments = $dept_stmt->fetchAll();

            // สถิติตามประเภทบุคลากร
            $personnel_stmt = $db->prepare("
                SELECT u.personnel_type,
                       COUNT(DISTINCT u.user_id) as staff_count,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count,
                       AVG(CASE WHEN e.status = 'approved' THEN e.total_score END) as avg_score
                FROM users u
                LEFT JOIN evaluations e ON u.user_id = e.user_id $dept_where
                GROUP BY u.personnel_type
            ");
            $personnel_stmt->execute($dept_params);
            $by_personnel = $personnel_stmt->fetchAll();

            // สถิติตามด้านการประเมิน
            $aspects_stmt = $db->prepare("
                SELECT ea.aspect_name_th, ea.aspect_code,
                       AVG(ed.score) as avg_score,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count
                FROM evaluation_details ed
                INNER JOIN evaluations e ON ed.evaluation_id = e.evaluation_id
                INNER JOIN evaluation_aspects ea ON ed.aspect_id = ea.aspect_id
                " . ($where ? $where . " AND" : "WHERE") . " e.status = 'approved'
                GROUP BY ed.aspect_id
                ORDER BY ea.display_order
            ");
            $aspects_stmt->execute($params);
            $by_aspects = $aspects_stmt->fetchAll();

            // Trend ตามเดือน (6 เดือนล่าสุด)
            $trend_stmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(submitted_at, '%Y-%m') as month,
                    COUNT(*) as count,
                    AVG(total_score) as avg_score
                FROM evaluations
                WHERE status = 'approved' 
                  AND submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
                ORDER BY month
            ");
            $trend_stmt->execute();
            $trend = $trend_stmt->fetchAll();

            sendResponse(true, 'Success', [
                'summary' => $summary,
                'by_departments' => $by_departments,
                'by_personnel' => $by_personnel,
                'by_aspects' => $by_aspects,
                'trend' => $trend
            ]);
            break;

        // ==================== Comparison Report ====================
        case 'comparison':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $user_id = (int)($_GET['user_id'] ?? $current_user['user_id']);
            $period_ids = explode(',', $_GET['period_ids'] ?? '');

            // ตรวจสอบสิทธิ์
            if ($user_id !== $current_user['user_id'] && !in_array($current_user['role'], ['admin', 'manager'])) {
                sendResponse(false, 'Forbidden', null, 403);
            }

            // ข้อมูลผู้ใช้
            $user_stmt = $db->prepare("SELECT user_id, full_name_th, personnel_type FROM users WHERE user_id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();

            if (!$user) {
                sendResponse(false, 'User not found', null, 404);
            }

            $comparisons = [];

            foreach ($period_ids as $period_id) {
                $period_id = (int)$period_id;
                if ($period_id <= 0) continue;

                // ข้อมูลแบบประเมิน
                $eval_stmt = $db->prepare("
                    SELECT e.*, ep.period_name, ep.year, ep.semester
                    FROM evaluations e
                    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
                    WHERE e.user_id = ? AND e.period_id = ?
                ");
                $eval_stmt->execute([$user_id, $period_id]);
                $evaluation = $eval_stmt->fetch();

                if ($evaluation) {
                    // รายละเอียดตามด้าน
                    $details_stmt = $db->prepare("
                        SELECT ea.aspect_name_th, ea.aspect_code, SUM(ed.score) as total_score
                        FROM evaluation_details ed
                        INNER JOIN evaluation_aspects ea ON ed.aspect_id = ea.aspect_id
                        WHERE ed.evaluation_id = ?
                        GROUP BY ed.aspect_id
                        ORDER BY ea.display_order
                    ");
                    $details_stmt->execute([$evaluation['evaluation_id']]);
                    $details = $details_stmt->fetchAll();

                    $comparisons[] = [
                        'period' => $evaluation['period_name'],
                        'year' => $evaluation['year'],
                        'total_score' => $evaluation['total_score'],
                        'status' => $evaluation['status'],
                        'details' => $details
                    ];
                }
            }

            sendResponse(true, 'Success', [
                'user' => $user,
                'comparisons' => $comparisons
            ]);
            break;

        // ==================== Export Report ====================
        case 'export':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $report_type = $input['report_type'] ?? '';
            $format = $input['format'] ?? 'pdf'; // pdf, excel, csv
            $params = $input['params'] ?? [];

            // TODO: สร้างไฟล์รายงานตาม format ที่เลือก
            // ใช้ไลบรารีเช่น TCPDF สำหรับ PDF, PhpSpreadsheet สำหรับ Excel

            $filename = "report_" . $report_type . "_" . time() . "." . $format;
            $filepath = UPLOAD_PATH . "reports/" . $filename;

            // Placeholder: สร้างไฟล์จริง
            file_put_contents($filepath, "Report content here");

            sendResponse(true, 'รายงานถูกสร้างเรียบร้อยแล้ว', [
                'filename' => $filename,
                'download_url' => url('assets/uploads/reports/' . $filename)
            ]);
            break;

        // ==================== System Usage Statistics ====================
        case 'usage-statistics':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            // เฉพาะ admin
            if ($current_user['role'] !== 'admin') {
                sendResponse(false, 'Forbidden', null, 403);
            }

            // สถิติการใช้งานระบบ
            $days = (int)($_GET['days'] ?? 30);

            $login_stats = $db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as login_count
                FROM activity_logs
                WHERE action = 'login' 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $login_stats->execute([$days]);
            $logins = $login_stats->fetchAll();

            // Active users
            $active_users = $db->prepare("
                SELECT COUNT(DISTINCT user_id) as count
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $active_users->execute([$days]);
            $active = $active_users->fetch();

            // Most active users
            $top_users = $db->prepare("
                SELECT u.full_name_th, COUNT(*) as action_count
                FROM activity_logs al
                INNER JOIN users u ON al.user_id = u.user_id
                WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY al.user_id
                ORDER BY action_count DESC
                LIMIT 10
            ");
            $top_users->execute([$days]);
            $top = $top_users->fetchAll();

            sendResponse(true, 'Success', [
                'login_by_date' => $logins,
                'active_users' => $active['count'],
                'top_users' => $top
            ]);
            break;

        default:
            sendResponse(false, 'Invalid action', null, 400);
    }
} catch (PDOException $e) {
    error_log("Reports API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาดในระบบ', null, 500);
} catch (Exception $e) {
    error_log("Reports API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
