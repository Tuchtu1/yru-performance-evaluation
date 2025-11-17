<?php

/**
 * Logout - ออกจากระบบ
 * ทำลาย Session และ Cookie แล้ว redirect ไป login
 */

session_start();

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';

// บันทึก activity log ก่อน logout
if (isset($_SESSION['user'])) {
    try {
        $db = getDB();

        $log = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, action, ip_address, user_agent, created_at)
            VALUES (?, 'logout', ?, ?, NOW())
        ");
        $log->execute([
            $_SESSION['user']['user_id'],
            get_client_ip(),
            get_browser_info()
        ]);
    } catch (Exception $e) {
        error_log("Logout log error: " . $e->getMessage());
    }
}

// เก็บข้อความ
$user_name = $_SESSION['user']['full_name_th'] ?? 'ผู้ใช้';

// ทำลาย Session
session_unset();
session_destroy();

// ลบ Session Cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// ลบ Remember Me Cookie (ถ้ามี)
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// เริ่ม Session ใหม่เพื่อแสดง flash message
session_start();
flash_success('ออกจากระบบเรียบร้อยแล้ว');

// Redirect ไป login page
redirect('modules/auth/login.php');
