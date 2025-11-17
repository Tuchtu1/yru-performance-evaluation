<?php
// api/evaluation.php
/**
 * Evaluation API
 * API สำหรับจัดการแบบประเมินผลการปฏิบัติงาน
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
$input = json_decode(file_get_contents('php://input'), true);

try {
    $db = getDB();

    switch ($action) {

        // ==================== List Evaluations ====================
        case 'list':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? ITEMS_PER_PAGE);
            $offset = ($page - 1) * $limit;
            $status = $_GET['status'] ?? '';

            $where = "WHERE e.user_id = ?";
            $params = [$current_user['user_id']];

            if (!empty($status)) {
                $where .= " AND e.status = ?";
                $params[] = $status;
            }

            // นับจำนวนทั้งหมด
            $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM evaluations e $where");
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];

            // ดึงข้อมูล
            $stmt = $db->prepare("
                SELECT e.*, ep.period_name, ep.year, ep.semester,
                       COUNT(DISTINCT em.em_id) as manager_count,
                       COUNT(DISTINCT CASE WHEN em.status = 'approved' THEN em.em_id END) as approved_count
                FROM evaluations e
                LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
                LEFT JOIN evaluation_managers em ON e.evaluation_id = em.evaluation_id
                $where
                GROUP BY e.evaluation_id
                ORDER BY e.updated_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([...$params, $limit, $offset]);
            $evaluations = $stmt->fetchAll();

            sendResponse(true, 'Success', [
                'evaluations' => $evaluations,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;

        // ==================== Get Evaluation Detail ====================
        case 'detail':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $evaluation_id = (int)($_GET['id'] ?? 0);

            if ($evaluation_id <= 0) {
                sendResponse(false, 'Invalid evaluation ID', null, 400);
            }

            // ดึงข้อมูลแบบประเมิน
            $stmt = $db->prepare("
                SELECT e.*, ep.period_name, ep.year, ep.semester,
                       u.full_name_th, u.personnel_type, u.position,
                       d.department_name_th
                FROM evaluations e
                LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
                LEFT JOIN users u ON e.user_id = u.user_id
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE e.evaluation_id = ? AND e.user_id = ?
            ");
            $stmt->execute([$evaluation_id, $current_user['user_id']]);
            $evaluation = $stmt->fetch();

            if (!$evaluation) {
                sendResponse(false, 'Evaluation not found', null, 404);
            }

            // ดึงรายละเอียดแต่ละด้าน
            $details_stmt = $db->prepare("
                SELECT ed.*, ea.aspect_name_th, ea.aspect_code,
                       et.topic_name_th, et.max_score
                FROM evaluation_details ed
                LEFT JOIN evaluation_aspects ea ON ed.aspect_id = ea.aspect_id
                LEFT JOIN evaluation_topics et ON ed.topic_id = et.topic_id
                WHERE ed.evaluation_id = ?
                ORDER BY ea.display_order, et.display_order
            ");
            $details_stmt->execute([$evaluation_id]);
            $details = $details_stmt->fetchAll();

            // ดึงผลงานที่แนบ
            $portfolio_stmt = $db->prepare("
                SELECT ep.*, wp.title, wp.description, wp.file_name
                FROM evaluation_portfolios ep
                LEFT JOIN work_portfolio wp ON ep.portfolio_id = wp.portfolio_id
                WHERE ep.evaluation_id = ?
            ");
            $portfolio_stmt->execute([$evaluation_id]);
            $portfolios = $portfolio_stmt->fetchAll();

            // ดึงผู้บริหารที่เลือก
            $managers_stmt = $db->prepare("
                SELECT em.*, u.full_name_th, u.position
                FROM evaluation_managers em
                LEFT JOIN users u ON em.manager_user_id = u.user_id
                WHERE em.evaluation_id = ?
                ORDER BY em.selection_order
            ");
            $managers_stmt->execute([$evaluation_id]);
            $managers = $managers_stmt->fetchAll();

            sendResponse(true, 'Success', [
                'evaluation' => $evaluation,
                'details' => $details,
                'portfolios' => $portfolios,
                'managers' => $managers
            ]);
            break;

        // ==================== Create Evaluation ====================
        case 'create':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $period_id = (int)($input['period_id'] ?? 0);

            if ($period_id <= 0) {
                sendResponse(false, 'กรุณาเลือกรอบการประเมิน', null, 400);
            }

            // ตรวจสอบว่ามีแบบประเมินในรอบนี้แล้วหรือไม่
            $check = $db->prepare("SELECT evaluation_id FROM evaluations WHERE period_id = ? AND user_id = ?");
            $check->execute([$period_id, $current_user['user_id']]);

            if ($check->fetch()) {
                sendResponse(false, 'คุณมีแบบประเมินในรอบนี้อยู่แล้ว', null, 400);
            }

            // สร้างแบบประเมินใหม่
            $stmt = $db->prepare("
                INSERT INTO evaluations (period_id, user_id, status, created_at)
                VALUES (?, ?, 'draft', NOW())
            ");
            $stmt->execute([$period_id, $current_user['user_id']]);
            $evaluation_id = $db->lastInsertId();

            // บันทึก activity log
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'create_evaluation', 'evaluations', ?)");
            $log->execute([$current_user['user_id'], $evaluation_id]);

            sendResponse(true, 'สร้างแบบประเมินสำเร็จ', ['evaluation_id' => $evaluation_id], 201);
            break;

        // ==================== Save Draft ====================
        case 'save-draft':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $evaluation_id = (int)($input['evaluation_id'] ?? 0);
            $details = $input['details'] ?? [];

            if ($evaluation_id <= 0) {
                sendResponse(false, 'Invalid evaluation ID', null, 400);
            }

            // ตรวจสอบสิทธิ์
            $check = $db->prepare("SELECT status FROM evaluations WHERE evaluation_id = ? AND user_id = ?");
            $check->execute([$evaluation_id, $current_user['user_id']]);
            $evaluation = $check->fetch();

            if (!$evaluation) {
                sendResponse(false, 'Evaluation not found', null, 404);
            }

            if ($evaluation['status'] !== 'draft' && $evaluation['status'] !== 'returned') {
                sendResponse(false, 'ไม่สามารถแก้ไขแบบประเมินนี้ได้', null, 400);
            }

            // ลบรายละเอียดเก่า
            $delete = $db->prepare("DELETE FROM evaluation_details WHERE evaluation_id = ?");
            $delete->execute([$evaluation_id]);

            $total_score = 0;

            // บันทึกรายละเอียดใหม่
            foreach ($details as $detail) {
                $stmt = $db->prepare("
                    INSERT INTO evaluation_details 
                    (evaluation_id, aspect_id, topic_id, score, self_assessment, evidence_description, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $evaluation_id,
                    $detail['aspect_id'],
                    $detail['topic_id'] ?? null,
                    $detail['score'] ?? 0,
                    $detail['self_assessment'] ?? '',
                    $detail['evidence_description'] ?? '',
                    $detail['notes'] ?? ''
                ]);

                $total_score += ($detail['score'] ?? 0);
            }

            // อัพเดทคะแนนรวม
            $update = $db->prepare("UPDATE evaluations SET total_score = ?, updated_at = NOW() WHERE evaluation_id = ?");
            $update->execute([$total_score, $evaluation_id]);

            sendResponse(true, 'บันทึกร่างสำเร็จ', ['total_score' => $total_score]);
            break;

        // ==================== Submit Evaluation ====================
        case 'submit':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $evaluation_id = (int)($input['evaluation_id'] ?? 0);
            $manager_ids = $input['manager_ids'] ?? [];

            if ($evaluation_id <= 0) {
                sendResponse(false, 'Invalid evaluation ID', null, 400);
            }

            if (count($manager_ids) !== 3) {
                sendResponse(false, 'กรุณาเลือกผู้บริหาร 3 คน', null, 400);
            }

            // ตรวจสอบสิทธิ์
            $check = $db->prepare("SELECT status FROM evaluations WHERE evaluation_id = ? AND user_id = ?");
            $check->execute([$evaluation_id, $current_user['user_id']]);
            $evaluation = $check->fetch();

            if (!$evaluation) {
                sendResponse(false, 'Evaluation not found', null, 404);
            }

            if ($evaluation['status'] !== 'draft' && $evaluation['status'] !== 'returned') {
                sendResponse(false, 'ไม่สามารถส่งแบบประเมินนี้ได้', null, 400);
            }

            // เริ่ม Transaction
            $db->beginTransaction();

            try {
                // อัพเดทสถานะ
                $update = $db->prepare("
                    UPDATE evaluations 
                    SET status = 'submitted', submitted_at = NOW(), updated_at = NOW()
                    WHERE evaluation_id = ?
                ");
                $update->execute([$evaluation_id]);

                // ลบผู้บริหารเก่า (ถ้ามี)
                $delete = $db->prepare("DELETE FROM evaluation_managers WHERE evaluation_id = ?");
                $delete->execute([$evaluation_id]);

                // เพิ่มผู้บริหารใหม่
                foreach ($manager_ids as $index => $manager_id) {
                    $stmt = $db->prepare("
                        INSERT INTO evaluation_managers 
                        (evaluation_id, manager_user_id, selection_order, status)
                        VALUES (?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$evaluation_id, $manager_id, $index + 1]);
                }

                // บันทึก history
                $history = $db->prepare("
                    INSERT INTO approval_history 
                    (evaluation_id, manager_user_id, action, previous_status, new_status)
                    VALUES (?, ?, 'submit', ?, 'submitted')
                ");
                $history->execute([$evaluation_id, $current_user['user_id'], $evaluation['status']]);

                // TODO: ส่งการแจ้งเตือนไปยังผู้บริหาร

                $db->commit();
                sendResponse(true, 'ส่งแบบประเมินสำเร็จ');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            // ลบ break; ออกเพราะ throw จะหยุดการทำงานอยู่แล้ว

            // ==================== Delete Evaluation ====================
        case 'delete':
            if ($method !== 'DELETE') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $evaluation_id = (int)($_GET['id'] ?? 0);

            if ($evaluation_id <= 0) {
                sendResponse(false, 'Invalid evaluation ID', null, 400);
            }

            // ตรวจสอบสิทธิ์
            $check = $db->prepare("SELECT status FROM evaluations WHERE evaluation_id = ? AND user_id = ?");
            $check->execute([$evaluation_id, $current_user['user_id']]);
            $evaluation = $check->fetch();

            if (!$evaluation) {
                sendResponse(false, 'Evaluation not found', null, 404);
            }

            // ลบได้เฉพาะสถานะ draft เท่านั้น
            if ($evaluation['status'] !== 'draft') {
                sendResponse(false, 'ไม่สามารถลบแบบประเมินนี้ได้', null, 400);
            }

            $delete = $db->prepare("DELETE FROM evaluations WHERE evaluation_id = ?");
            $delete->execute([$evaluation_id]);

            // บันทึก activity log
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'delete_evaluation', 'evaluations', ?)");
            $log->execute([$current_user['user_id'], $evaluation_id]);

            sendResponse(true, 'ลบแบบประเมินสำเร็จ');
            break;

        // ==================== Get Statistics ====================
        case 'statistics':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $stats = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
                    AVG(total_score) as average_score
                FROM evaluations 
                WHERE user_id = ?
            ");
            $stats->execute([$current_user['user_id']]);
            $statistics = $stats->fetch();

            sendResponse(true, 'Success', ['statistics' => $statistics]);
            break;

        default:
            sendResponse(false, 'Invalid action', null, 400);
    }
} catch (PDOException $e) {
    error_log("Evaluation API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาดในระบบ', null, 500);
} catch (Exception $e) {
    error_log("Evaluation API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
