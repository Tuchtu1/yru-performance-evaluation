<?php

/**
 * api/auth.php
 * Authentication API
 * API สำหรับการยืนยันตัวตนและจัดการ Session
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';

// ฟังก์ชันสำหรับส่ง JSON response
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

// ตรวจสอบ HTTP Method
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// รับข้อมูล JSON
$input = json_decode(file_get_contents('php://input'), true);

try {
    $db = getDB();

    switch ($action) {

        // ==================== Login ====================
        case 'login':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';

            if (empty($username) || empty($password)) {
                sendResponse(false, 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', null, 400);
            }

            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // อัพเดทเวลา login
                $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $update->execute([$user['user_id']]);

                // บันทึก activity log
                $log = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
                $log->execute([$user['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                // สร้าง Session
                $_SESSION['user'] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name_th' => $user['full_name_th'],
                    'full_name_en' => $user['full_name_en'],
                    'personnel_type' => $user['personnel_type'],
                    'department_id' => $user['department_id'],
                    'position' => $user['position'],
                    'role' => $user['role']
                ];

                sendResponse(true, 'เข้าสู่ระบบสำเร็จ', [
                    'user' => $_SESSION['user'],
                    'redirect' => url('modules/dashboard/index.php')
                ]);
            } else {
                sendResponse(false, 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', null, 401);
            }
            break;

        // ==================== Logout ====================
        case 'logout':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            if (isset($_SESSION['user'])) {
                $user_id = $_SESSION['user']['user_id'];

                // บันทึก activity log
                $log = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
                $log->execute([$user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            }

            session_destroy();
            sendResponse(true, 'ออกจากระบบสำเร็จ', ['redirect' => url('modules/auth/login.php')]);
            break;

        // ==================== Check Session ====================
        case 'check':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            if (isset($_SESSION['user'])) {
                sendResponse(true, 'Session active', ['user' => $_SESSION['user']]);
            } else {
                sendResponse(false, 'Session expired', null, 401);
            }
            break;

        // ==================== Change Password ====================
        case 'change-password':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            if (!isset($_SESSION['user'])) {
                sendResponse(false, 'Unauthorized', null, 401);
            }

            $user_id = $_SESSION['user']['user_id'];
            $current_password = $input['current_password'] ?? '';
            $new_password = $input['new_password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                sendResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วน', null, 400);
            }

            if ($new_password !== $confirm_password) {
                sendResponse(false, 'รหัสผ่านใหม่ไม่ตรงกัน', null, 400);
            }

            if (strlen($new_password) < 6) {
                sendResponse(false, 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร', null, 400);
            }

            // ตรวจสอบรหัสผ่านเดิม
            $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!password_verify($current_password, $user['password'])) {
                sendResponse(false, 'รหัสผ่านปัจจุบันไม่ถูกต้อง', null, 400);
            }

            // อัพเดทรหัสผ่านใหม่
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update->execute([$hashed_password, $user_id]);

            // บันทึก activity log
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'change_password', ?, ?)");
            $log->execute([$user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            sendResponse(true, 'เปลี่ยนรหัสผ่านสำเร็จ');
            break;

        // ==================== Reset Password Request ====================
        case 'reset-request':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            $email = trim($input['email'] ?? '');

            if (empty($email)) {
                sendResponse(false, 'กรุณากรอกอีเมล', null, 400);
            }

            $stmt = $db->prepare("SELECT user_id, username, full_name_th FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // สร้าง reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // TODO: เก็บ token ในฐานข้อมูล (ต้องสร้างตาราง password_resets)
                // TODO: ส่งอีเมลพร้อม link reset password

                sendResponse(true, 'ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว');
            } else {
                // Security: ไม่บอกว่าอีเมลไม่มีในระบบ
                sendResponse(true, 'ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว');
            }
            break;

        // ==================== Get User Profile ====================
        case 'profile':
            if ($method !== 'GET') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            if (!isset($_SESSION['user'])) {
                sendResponse(false, 'Unauthorized', null, 401);
            }

            $user_id = $_SESSION['user']['user_id'];

            $stmt = $db->prepare("
                SELECT u.*, d.department_name_th, d.department_name_en
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch();

            if ($profile) {
                unset($profile['password']); // ไม่ส่ง password
                sendResponse(true, 'Success', ['profile' => $profile]);
            } else {
                sendResponse(false, 'User not found', null, 404);
            }
            break;

        // ==================== Update Profile ====================
        case 'update-profile':
            if ($method !== 'POST') {
                sendResponse(false, 'Method not allowed', null, 405);
            }

            if (!isset($_SESSION['user'])) {
                sendResponse(false, 'Unauthorized', null, 401);
            }

            $user_id = $_SESSION['user']['user_id'];
            $full_name_th = trim($input['full_name_th'] ?? '');
            $full_name_en = trim($input['full_name_en'] ?? '');
            $email = trim($input['email'] ?? '');
            $position = trim($input['position'] ?? '');

            if (empty($full_name_th) || empty($email)) {
                sendResponse(false, 'กรุณากรอกข้อมูลให้ครบถ้วน', null, 400);
            }

            // ตรวจสอบอีเมลซ้ำ
            $check = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $check->execute([$email, $user_id]);
            if ($check->fetch()) {
                sendResponse(false, 'อีเมลนี้ถูกใช้งานแล้ว', null, 400);
            }

            $update = $db->prepare("
                UPDATE users 
                SET full_name_th = ?, full_name_en = ?, email = ?, position = ?
                WHERE user_id = ?
            ");
            $update->execute([$full_name_th, $full_name_en, $email, $position, $user_id]);

            // อัพเดท Session
            $_SESSION['user']['full_name_th'] = $full_name_th;
            $_SESSION['user']['full_name_en'] = $full_name_en;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['position'] = $position;

            // บันทึก activity log
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'update_profile', ?, ?)");
            $log->execute([$user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            sendResponse(true, 'อัพเดทข้อมูลส่วนตัวสำเร็จ', ['user' => $_SESSION['user']]);
            break;

        default:
            sendResponse(false, 'Invalid action', null, 400);
    }
} catch (PDOException $e) {
    error_log("Auth API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาดในระบบ', null, 500);
} catch (Exception $e) {
    error_log("Auth API Error: " . $e->getMessage());
    sendResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
