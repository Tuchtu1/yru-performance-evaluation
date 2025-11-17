<?php

/**
 * api/notifications.php
 * Notifications API
 * API สำหรับจัดการการแจ้งเตือน
 */

// เปิด error reporting สำหรับ debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดเพื่อไม่ให้ขัดจังหวะ JSON
ini_set('log_errors', 1);

// Set JSON header ก่อนอื่นเสมอ
header('Content-Type: application/json; charset=utf-8');

// Wrap ทั้งหมดใน try-catch
try {
    // กำหนด APP_ROOT (ถ้ายังไม่ได้กำหนด)
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__));
    }

    // โหลด config
    if (!file_exists(APP_ROOT . '/config/app.php')) {
        throw new Exception('Config file not found: ' . APP_ROOT . '/config/app.php');
    }
    require_once APP_ROOT . '/config/app.php';

    if (!file_exists(APP_ROOT . '/config/database.php')) {
        throw new Exception('Database config file not found');
    }
    require_once APP_ROOT . '/config/database.php';

    // เริ่ม session หลังโหลด config
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configuration Error',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

        // ==================== List Notifications ====================
        case 'list':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;
            $unread_only = ($_GET['unread_only'] ?? 'false') === 'true';

            $where = "WHERE user_id = ?";
            $params = [$current_user['user_id']];

            if ($unread_only) {
                $where .= " AND is_read = 0";
            }

            // นับจำนวนทั้งหมด
            $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications $where");
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];

            // ดึงข้อมูล
            $stmt = $db->prepare("
                SELECT *
                FROM notifications
                $where
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([...$params, $limit, $offset]);
            $notifications = $stmt->fetchAll();

            // นับจำนวนที่ยังไม่ได้อ่าน
            $unread_stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
            $unread_stmt->execute([$current_user['user_id']]);
            $unread_count = $unread_stmt->fetch()['unread'];

            sendResponse(true, 'Success', [
                'notifications' => $notifications,
                'unread_count' => $unread_count,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;

        // ==================== Get Unread Count ====================
        case 'unread-count':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$current_user['user_id']]);
            $result = $stmt->fetch();

            sendResponse(true, 'Success', ['count' => (int)$result['count']]);
            break;

        // ==================== Mark as Read ====================
        case 'mark-read':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $notification_id = (int)($input['notification_id'] ?? 0);

            if ($notification_id <= 0) {
                sendResponse(false, 'Invalid notification ID', null, 400);
            }

            // ตรวจสอบสิทธิ์
            $check = $db->prepare("SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ?");
            $check->execute([$notification_id, $current_user['user_id']]);

            if (!$check->fetch()) {
                sendResponse(false, 'Notification not found', null, 404);
            }

            $stmt = $db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW()
                WHERE notification_id = ?
            ");
            $stmt->execute([$notification_id]);

            sendResponse(true, 'ทำเครื่องหมายว่าอ่านแล้ว');
            break;

        // ==================== Mark All as Read ====================
        case 'mark-all-read':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $stmt = $db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$current_user['user_id']]);

            $affected = $stmt->rowCount();

            sendResponse(true, "ทำเครื่องหมาย $affected รายการว่าอ่านแล้ว");
            break;

        // ==================== Delete Notification ====================
        case 'delete':
            if ($method !== 'DELETE') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $notification_id = (int)($_GET['id'] ?? 0);

            if ($notification_id <= 0) {
                sendResponse(false, 'Invalid notification ID', null, 400);
            }

            // ตรวจสอบสิทธิ์
            $check = $db->prepare("SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ?");
            $check->execute([$notification_id, $current_user['user_id']]);

            if (!$check->fetch()) {
                sendResponse(false, 'Notification not found', null, 404);
            }

            $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = ?");
            $stmt->execute([$notification_id]);

            sendResponse(true, 'ลบการแจ้งเตือนสำเร็จ');
            break;

        // ==================== Delete All Read ====================
        case 'delete-all-read':
            if ($method !== 'DELETE') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
            $stmt->execute([$current_user['user_id']]);

            $affected = $stmt->rowCount();

            sendResponse(true, "ลบ $affected รายการสำเร็จ");
            break;

        // ==================== Create Notification (Internal Use) ====================
        case 'create':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            // เฉพาะระบบภายในหรือ admin เท่านั้นที่สร้างได้
            if ($current_user['role'] !== 'admin') {
                sendResponse(false, 'Forbidden', null, 403);
            }

            $user_id = (int)($input['user_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $message = trim($input['message'] ?? '');
            $type = $input['type'] ?? 'general';
            $related_id = (int)($input['related_id'] ?? 0);
            $related_type = $input['related_type'] ?? null;

            if ($user_id <= 0 || empty($title) || empty($message)) {
                sendResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วน', null, 400);
            }

            $stmt = $db->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, related_id, related_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $title, $message, $type, $related_id, $related_type]);

            $notification_id = $db->lastInsertId();

            sendResponse(true, 'สร้างการแจ้งเตือนสำเร็จ', ['notification_id' => $notification_id], 201);
            break;

        // ==================== Broadcast Notification ====================
        case 'broadcast':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            // เฉพาะ admin
            if ($current_user['role'] !== 'admin') {
                sendResponse(false, 'Forbidden', null, 403);
            }

            $title = trim($input['title'] ?? '');
            $message = trim($input['message'] ?? '');
            $target_role = $input['target_role'] ?? 'all'; // all, staff, manager
            $target_department = (int)($input['target_department'] ?? 0);

            if (empty($title) || empty($message)) {
                sendResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วน', null, 400);
            }

            // สร้าง WHERE clause สำหรับกลุ่มเป้าหมาย
            $where = "WHERE is_active = 1";
            $params = [];

            if ($target_role !== 'all') {
                $where .= " AND role = ?";
                $params[] = $target_role;
            }

            if ($target_department > 0) {
                $where .= " AND department_id = ?";
                $params[] = $target_department;
            }

            // ดึงรายชื่อผู้ใช้ที่จะส่ง
            $users_stmt = $db->prepare("SELECT user_id FROM users $where");
            $users_stmt->execute($params);
            $users = $users_stmt->fetchAll();

            $count = 0;

            // สร้างการแจ้งเตือนให้แต่ละคน
            $insert_stmt = $db->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, created_at)
                VALUES (?, ?, ?, 'broadcast', NOW())
            ");

            foreach ($users as $user) {
                $insert_stmt->execute([$user['user_id'], $title, $message]);
                $count++;
            }

            sendResponse(true, "ส่งการแจ้งเตือนถึง $count คน");
            break;

        // ==================== Get Notification Settings ====================
        case 'settings':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            // TODO: ดึงการตั้งค่าการแจ้งเตือนของผู้ใช้
            // ต้องสร้างตาราง notification_settings ก่อน

            $settings = [
                'email_enabled' => true,
                'push_enabled' => true,
                'evaluation_submitted' => true,
                'evaluation_approved' => true,
                'evaluation_rejected' => true,
                'evaluation_returned' => true,
                'system_updates' => true
            ];

            sendResponse(true, 'Success', ['settings' => $settings]);
            break;

        // ==================== Update Notification Settings ====================
        case 'update-settings':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $settings = $input['settings'] ?? [];

            // TODO: บันทึกการตั้งค่าลงฐานข้อมูล
            // ต้องสร้างตาราง notification_settings ก่อน

            sendResponse(true, 'อัพเดทการตั้งค่าสำเร็จ');
            break;

        // ==================== Get Recent Notifications (for Header) ====================
        case 'recent':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $limit = (int)($_GET['limit'] ?? 5);

            $stmt = $db->prepare("
                SELECT *
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$current_user['user_id'], $limit]);
            $notifications = $stmt->fetchAll();

            // นับจำนวนที่ยังไม่ได้อ่าน
            $unread_stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
            $unread_stmt->execute([$current_user['user_id']]);
            $unread_count = $unread_stmt->fetch()['unread'];

            sendResponse(true, 'Success', [
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
            break;

        default:
            sendResponse(false, 'Invalid action', null, 400);
    }
} catch (PDOException $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาดในระบบ', null, 500);
} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}


// ==================== Helper Functions ====================

/**
 * สร้างการแจ้งเตือนอัตโนมัติ
 * เรียกใช้จากส่วนอื่นๆ ของระบบ
 */
function createNotification($db, $user_id, $title, $message, $type = 'general', $related_id = null, $related_type = null)
{
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, related_id, related_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $title, $message, $type, $related_id, $related_type]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Create Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * ส่งการแจ้งเตือนเมื่อแบบประเมินถูกส่ง
 */
function notifyEvaluationSubmitted($db, $evaluation_id, $manager_ids)
{
    try {
        // ดึงข้อมูลแบบประเมิน
        $eval_stmt = $db->prepare("
            SELECT e.*, u.full_name_th, ep.period_name
            FROM evaluations e
            INNER JOIN users u ON e.user_id = u.user_id
            INNER JOIN evaluation_periods ep ON e.period_id = ep.period_id
            WHERE e.evaluation_id = ?
        ");
        $eval_stmt->execute([$evaluation_id]);
        $evaluation = $eval_stmt->fetch();

        if ($evaluation) {
            $title = "แบบประเมินใหม่รอการพิจารณา";
            $message = "{$evaluation['full_name_th']} ได้ส่งแบบประเมิน {$evaluation['period_name']} รอการพิจารณาจากท่าน";

            foreach ($manager_ids as $manager_id) {
                createNotification($db, $manager_id, $title, $message, 'evaluation', $evaluation_id, 'submitted');
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Notify Evaluation Submitted Error: " . $e->getMessage());
        return false;
    }
}

/**
 * ส่งการแจ้งเตือนเมื่อแบบประเมินถูกอนุมัติ
 */
function notifyEvaluationApproved($db, $evaluation_id, $user_id, $manager_name)
{
    $title = "แบบประเมินของคุณได้รับการอนุมัติ";
    $message = "$manager_name ได้อนุมัติแบบประเมินของคุณแล้ว";
    return createNotification($db, $user_id, $title, $message, 'evaluation', $evaluation_id, 'approved');
}

/**
 * ส่งการแจ้งเตือนเมื่อแบบประเมินถูกส่งกลับ
 */
function notifyEvaluationReturned($db, $evaluation_id, $user_id, $manager_name, $comment)
{
    $title = "แบบประเมินของคุณถูกส่งกลับให้แก้ไข";
    $message = "$manager_name ได้ส่งแบบประเมินของคุณกลับให้แก้ไข: $comment";
    return createNotification($db, $user_id, $title, $message, 'evaluation', $evaluation_id, 'returned');
}

/**
 * ส่งการแจ้งเตือนเมื่อแบบประเมินถูกปฏิเสธ
 */
function notifyEvaluationRejected($db, $evaluation_id, $user_id, $manager_name, $comment)
{
    $title = "แบบประเมินของคุณไม่ได้รับการอนุมัติ";
    $message = "$manager_name ไม่อนุมัติแบบประเมินของคุณ: $comment";
    return createNotification($db, $user_id, $title, $message, 'evaluation', $evaluation_id, 'rejected');
}

/**
 * ส่งการแจ้งเตือนเมื่อใกล้ถึงกำหนดส่งแบบประเมิน
 */
function notifyDeadlineApproaching($db, $period_id, $days_left)
{
    try {
        // หาผู้ใช้ที่ยังไม่ส่งแบบประเมิน
        $users_stmt = $db->prepare("
            SELECT u.user_id
            FROM users u
            WHERE u.is_active = 1
            AND NOT EXISTS (
                SELECT 1 FROM evaluations e
                WHERE e.user_id = u.user_id 
                AND e.period_id = ?
                AND e.status IN ('submitted', 'approved')
            )
        ");
        $users_stmt->execute([$period_id]);
        $users = $users_stmt->fetchAll();

        $title = "เตือน: ใกล้ถึงกำหนดส่งแบบประเมิน";
        $message = "เหลือเวลาอีก $days_left วัน ในการส่งแบบประเมิน กรุณาดำเนินการให้เสร็จสิ้น";

        foreach ($users as $user) {
            createNotification($db, $user['user_id'], $title, $message, 'reminder', $period_id, 'deadline');
        }

        return true;
    } catch (Exception $e) {
        error_log("Notify Deadline Error: " . $e->getMessage());
        return false;
    }
}
