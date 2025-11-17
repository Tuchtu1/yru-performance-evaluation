<?php

/**
 *api/portfolio.php
 * Portfolio API
 * API สำหรับจัดการคลังผลงาน
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

        // ==================== List Portfolio ====================
        case 'list':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? ITEMS_PER_PAGE);
            $offset = ($page - 1) * $limit;
            $aspect_id = (int)($_GET['aspect_id'] ?? 0);
            $search = $_GET['search'] ?? '';

            $where = "WHERE wp.user_id = ?";
            $params = [$current_user['user_id']];

            if ($aspect_id > 0) {
                $where .= " AND wp.aspect_id = ?";
                $params[] = $aspect_id;
            }

            if (!empty($search)) {
                $where .= " AND (wp.title LIKE ? OR wp.description LIKE ? OR wp.tags LIKE ?)";
                $search_term = "%$search%";
                $params[] = $search_term;
                $params[] = $search_term;
                $params[] = $search_term;
            }

            // นับจำนวนทั้งหมด
            $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM work_portfolio wp $where");
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];

            // ดึงข้อมูล
            $stmt = $db->prepare("
                SELECT wp.*, ea.aspect_name_th, ea.aspect_code,
                       (wp.max_usage_count - wp.current_usage_count) as remaining_usage
                FROM work_portfolio wp
                LEFT JOIN evaluation_aspects ea ON wp.aspect_id = ea.aspect_id
                $where
                ORDER BY wp.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([...$params, $limit, $offset]);
            $portfolios = $stmt->fetchAll();

            sendResponse(true, 'Success', [
                'portfolios' => $portfolios,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;

        // ==================== Get Portfolio Detail ====================
        case 'detail':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $portfolio_id = (int)($_GET['id'] ?? 0);

            if ($portfolio_id <= 0) {
                sendResponse(false, 'Invalid portfolio ID', null, 400);
            }

            $stmt = $db->prepare("
                SELECT wp.*, ea.aspect_name_th, ea.aspect_code,
                       (wp.max_usage_count - wp.current_usage_count) as remaining_usage
                FROM work_portfolio wp
                LEFT JOIN evaluation_aspects ea ON wp.aspect_id = ea.aspect_id
                WHERE wp.portfolio_id = ? AND wp.user_id = ?
            ");
            $stmt->execute([$portfolio_id, $current_user['user_id']]);
            $portfolio = $stmt->fetch();

            if (!$portfolio) {
                sendResponse(false, 'Portfolio not found', null, 404);
            }

            // ดึงประวัติการใช้งาน
            $usage_stmt = $db->prepare("
                SELECT ep.*, e.evaluation_id, e.status, evp.period_name, evp.year
                FROM evaluation_portfolios ep
                LEFT JOIN evaluations e ON ep.evaluation_id = e.evaluation_id
                LEFT JOIN evaluation_periods evp ON e.period_id = evp.period_id
                WHERE ep.portfolio_id = ?
                ORDER BY ep.created_at DESC
            ");
            $usage_stmt->execute([$portfolio_id]);
            $usage_history = $usage_stmt->fetchAll();

            sendResponse(true, 'Success', [
                'portfolio' => $portfolio,
                'usage_history' => $usage_history
            ]);
            break;

        // ==================== Create Portfolio ====================
        case 'create':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            // รับข้อมูลจาก multipart/form-data
            $aspect_id = (int)($_POST['aspect_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $work_type = trim($_POST['work_type'] ?? '');
            $work_date = $_POST['work_date'] ?? null;
            $max_usage_count = (int)($_POST['max_usage_count'] ?? 1);
            $tags = trim($_POST['tags'] ?? '');

            if ($aspect_id <= 0 || empty($title)) {
                sendResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วน', null, 400);
            }

            // จัดการไฟล์อัปโหลด
            $file_path = null;
            $file_name = null;
            $file_size = 0;

            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                // ตรวจสอบประเภทไฟล์
                if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
                    sendResponse(false, 'ประเภทไฟล์ไม่ได้รับอนุญาต', null, 400);
                }

                // ตรวจสอบขนาดไฟล์
                if ($file['size'] > MAX_FILE_SIZE) {
                    sendResponse(false, 'ขนาดไฟล์ใหญ่เกิน ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB', null, 400);
                }

                // สร้างชื่อไฟล์ใหม่
                $file_name = $file['name'];
                $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                $upload_path = UPLOAD_PATH . 'portfolios/';

                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }

                $file_path = $upload_path . $unique_name;

                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    sendResponse(false, 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์', null, 500);
                }

                $file_size = $file['size'];
                $file_path = 'portfolios/' . $unique_name; // เก็บ relative path
            }

            // บันทึกลงฐานข้อมูล
            $stmt = $db->prepare("
                INSERT INTO work_portfolio 
                (user_id, aspect_id, title, description, work_type, work_date, 
                 max_usage_count, current_usage_count, file_path, file_name, file_size, tags)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $current_user['user_id'],
                $aspect_id,
                $title,
                $description,
                $work_type,
                $work_date,
                $max_usage_count,
                $file_path,
                $file_name,
                $file_size,
                $tags
            ]);

            $portfolio_id = $db->lastInsertId();

            // บันทึก activity log
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'create_portfolio', 'work_portfolio', ?)");
            $log->execute([$current_user['user_id'], $portfolio_id]);

            sendResponse(true, 'เพิ่มผลงานสำเร็จ', ['portfolio_id' => $portfolio_id], 201);
            break;

        // ==================== Update Portfolio ====================
        case 'update':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $portfolio_id = (int)($_POST['portfolio_id'] ?? 0);
            $aspect_id = (int)($_POST['aspect_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $work_type = trim($_POST['work_type'] ?? '');
            $work_date = $_POST['work_date'] ?? null;
            $max_usage_count = (int)($_POST['max_usage_count'] ?? 1);
            $tags = trim($_POST['tags'] ?? '');

            if ($portfolio_id <= 0 || $aspect_id <= 0 || empty($title)) {
                sendResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วน', null, 400);
            }

            // ตรวจสอบสิทธิ์
            $check = $db->prepare("SELECT file_path FROM work_portfolio WHERE portfolio_id = ? AND user_id = ?");
            $check->execute([$portfolio_id, $current_user['user_id']]);
            $portfolio = $check->fetch();

            if (!$portfolio) {
                sendResponse(false, 'Portfolio not found', null, 404);
            }

            $file_path = $portfolio['file_path'];
            $file_name = null;
            $file_size = 0;

            // จัดการไฟล์ใหม่ (ถ้ามี)
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
                    sendResponse(false, 'ประเภทไฟล์ไม่ได้รับอนุญาต', null, 400);
                }

                if ($file['size'] > MAX_FILE_SIZE) {
                    sendResponse(false, 'ขนาดไฟล์ใหญ่เกิน ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB', null, 400);
                }

                // ลบไฟล์เก่า
                if ($file_path && file_exists(UPLOAD_PATH . $file_path)) {
                    unlink(UPLOAD_PATH . $file_path);
                }

                $file_name = $file['name'];
                $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                $upload_path = UPLOAD_PATH . 'portfolios/';
                $new_file_path = $upload_path . $unique_name;

                if (move_uploaded_file($file['tmp_name'], $new_file_path)) {
                    $file_path = 'portfolios/' . $unique_name;
                    $file_size = $file['size'];
                }
            }

            // อัพเดท
            $stmt = $db->prepare("
                UPDATE work_portfolio 
                SET aspect_id = ?, title = ?, description = ?, work_type = ?, work_date = ?,
                    max_usage_count = ?, tags = ?, file_path = ?, file_name = ?, file_size = ?,
                    updated_at = NOW()
                WHERE portfolio_id = ?
            ");
            $stmt->execute([
                $aspect_id,
                $title,
                $description,
                $work_type,
                $work_date,
                $max_usage_count,
                $tags,
                $file_path,
                $file_name,
                $file_size,
                $portfolio_id
            ]);

            // บันทึก activity log
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'update_portfolio', 'work_portfolio', ?)");
            $log->execute([$current_user['user_id'], $portfolio_id]);

            sendResponse(true, 'อัพเดทผลงานสำเร็จ');
            break;

        // ==================== Delete Portfolio ====================
        case 'delete':
            if ($method !== 'DELETE') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $portfolio_id = (int)($_GET['id'] ?? 0);

            if ($portfolio_id <= 0) {
                sendResponse(false, 'Invalid portfolio ID', null, 400);
            }

            // ตรวจสอบสิทธิ์
            $check = $db->prepare("SELECT file_path, current_usage_count FROM work_portfolio WHERE portfolio_id = ? AND user_id = ?");
            $check->execute([$portfolio_id, $current_user['user_id']]);
            $portfolio = $check->fetch();

            if (!$portfolio) {
                sendResponse(false, 'Portfolio not found', null, 404);
            }

            // ตรวจสอบว่ามีการใช้งานอยู่หรือไม่
            if ($portfolio['current_usage_count'] > 0) {
                sendResponse(false, 'ไม่สามารถลบผลงานที่มีการใช้งานอยู่ได้', null, 400);
            }

            // ลบไฟล์
            if ($portfolio['file_path'] && file_exists(UPLOAD_PATH . $portfolio['file_path'])) {
                unlink(UPLOAD_PATH . $portfolio['file_path']);
            }

            // ลบข้อมูล
            $delete = $db->prepare("DELETE FROM work_portfolio WHERE portfolio_id = ?");
            $delete->execute([$portfolio_id]);

            // บันทึก activity log
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id) VALUES (?, 'delete_portfolio', 'work_portfolio', ?)");
            $log->execute([$current_user['user_id'], $portfolio_id]);

            sendResponse(true, 'ลบผลงานสำเร็จ');
            break;

        // ==================== Claim Portfolio ====================
        case 'claim':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $evaluation_id = (int)($input['evaluation_id'] ?? 0);
            $portfolio_id = (int)($input['portfolio_id'] ?? 0);
            $detail_id = (int)($input['detail_id'] ?? 0);

            if ($evaluation_id <= 0 || $portfolio_id <= 0) {
                sendResponse(false, 'Invalid parameters', null, 400);
            }

            // ตรวจสอบผลงาน
            $check_portfolio = $db->prepare("
                SELECT max_usage_count, current_usage_count 
                FROM work_portfolio 
                WHERE portfolio_id = ? AND user_id = ?
            ");
            $check_portfolio->execute([$portfolio_id, $current_user['user_id']]);
            $portfolio = $check_portfolio->fetch();

            if (!$portfolio) {
                sendResponse(false, 'Portfolio not found', null, 404);
            }

            if ($portfolio['current_usage_count'] >= $portfolio['max_usage_count']) {
                sendResponse(false, 'ผลงานนี้ถูกใช้งานครบจำนวนแล้ว', null, 400);
            }

            // ตรวจสอบว่าเคลมไว้แล้วหรือยัง
            $check_claim = $db->prepare("
                SELECT link_id FROM evaluation_portfolios 
                WHERE evaluation_id = ? AND portfolio_id = ?
            ");
            $check_claim->execute([$evaluation_id, $portfolio_id]);

            if ($check_claim->fetch()) {
                sendResponse(false, 'ผลงานนี้ถูกเคลมไว้แล้ว', null, 400);
            }

            // เคลมผลงาน
            $claim = $db->prepare("
                INSERT INTO evaluation_portfolios 
                (evaluation_id, detail_id, portfolio_id, is_claimed, claimed_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $claim->execute([$evaluation_id, $detail_id, $portfolio_id]);

            // อัพเดทจำนวนการใช้งาน
            $update = $db->prepare("
                UPDATE work_portfolio 
                SET current_usage_count = current_usage_count + 1
                WHERE portfolio_id = ?
            ");
            $update->execute([$portfolio_id]);

            sendResponse(true, 'เคลมผลงานสำเร็จ');
            break;

        // ==================== Get Available Portfolios ====================
        case 'available':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $aspect_id = (int)($_GET['aspect_id'] ?? 0);

            $where = "WHERE wp.user_id = ? AND (wp.max_usage_count - wp.current_usage_count) > 0";
            $params = [$current_user['user_id']];

            if ($aspect_id > 0) {
                $where .= " AND wp.aspect_id = ?";
                $params[] = $aspect_id;
            }

            $stmt = $db->prepare("
                SELECT wp.*, ea.aspect_name_th,
                       (wp.max_usage_count - wp.current_usage_count) as remaining_usage
                FROM work_portfolio wp
                LEFT JOIN evaluation_aspects ea ON wp.aspect_id = ea.aspect_id
                $where
                ORDER BY wp.created_at DESC
            ");
            $stmt->execute($params);
            $portfolios = $stmt->fetchAll();

            sendResponse(true, 'Success', ['portfolios' => $portfolios]);
            break;

        // ==================== Get Statistics ====================
        case 'statistics':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $stats = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN current_usage_count = 0 THEN 1 ELSE 0 END) as unused,
                    SUM(CASE WHEN current_usage_count > 0 AND current_usage_count < max_usage_count THEN 1 ELSE 0 END) as partial_used,
                    SUM(CASE WHEN current_usage_count >= max_usage_count THEN 1 ELSE 0 END) as fully_used,
                    SUM(file_size) as total_size
                FROM work_portfolio 
                WHERE user_id = ?
            ");
            $stats->execute([$current_user['user_id']]);
            $statistics = $stats->fetch();

            // สถิติตามด้าน
            $by_aspect = $db->prepare("
                SELECT ea.aspect_name_th, COUNT(*) as count
                FROM work_portfolio wp
                LEFT JOIN evaluation_aspects ea ON wp.aspect_id = ea.aspect_id
                WHERE wp.user_id = ?
                GROUP BY wp.aspect_id
            ");
            $by_aspect->execute([$current_user['user_id']]);
            $aspect_stats = $by_aspect->fetchAll();

            sendResponse(true, 'Success', [
                'statistics' => $statistics,
                'by_aspect' => $aspect_stats
            ]);
            break;

        default:
            sendResponse(false, 'Invalid action', null, 400);
    }
} catch (PDOException $e) {
    error_log("Portfolio API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาดในระบบ', null, 500);
} catch (Exception $e) {
    error_log("Portfolio API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
