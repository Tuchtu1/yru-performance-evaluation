<?php

/**
 * modules/auth/check-session.php
 * ตรวจสอบและจัดการ Session
 * YRU Performance Evaluation System
 */

// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(dirname(__DIR__)));
}

require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';

/**
 * ตรวจสอบและจัดการ Session
 */
class SessionManager
{
    private $db;
    private $timeout;

    public function __construct()
    {
        $this->db = getDB();
        $this->timeout = SESSION_TIMEOUT;

        // เริ่ม session ถ้ายังไม่มี
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * ตรวจสอบว่า session ยังใช้งานได้หรือไม่
     */
    public function isValid()
    {
        // ตรวจสอบว่ามี user ใน session หรือไม่
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
            return false;
        }

        // ตรวจสอบ session timeout
        if ($this->isTimedOut()) {
            return false;
        }

        // ตรวจสอบ IP address (optional - ป้องกัน session hijacking)
        if ($this->isIpChanged()) {
            return false;
        }

        // ตรวจสอบ User Agent (optional - ป้องกัน session hijacking)
        if ($this->isUserAgentChanged()) {
            return false;
        }

        // ตรวจสอบว่า user ยังคงเป็น active ในฐานข้อมูล
        if (!$this->isUserActive()) {
            return false;
        }

        // อัปเดตเวลาล่าสุดที่ใช้งาน
        $this->updateLastActivity();

        return true;
    }

    /**
     * ตรวจสอบว่า session หมดเวลาหรือไม่
     */
    private function isTimedOut()
    {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return false;
        }

        $inactive_time = time() - $_SESSION['last_activity'];

        if ($inactive_time > $this->timeout) {
            return true;
        }

        return false;
    }

    /**
     * ตรวจสอบว่า IP address เปลี่ยนหรือไม่
     */
    private function isIpChanged()
    {
        $current_ip = $_SERVER['REMOTE_ADDR'];

        if (!isset($_SESSION['user_ip'])) {
            $_SESSION['user_ip'] = $current_ip;
            return false;
        }

        // ถ้า IP เปลี่ยน แสดงว่าอาจมีปัญหา
        return $_SESSION['user_ip'] !== $current_ip;
    }

    /**
     * ตรวจสอบว่า User Agent เปลี่ยนหรือไม่
     */
    private function isUserAgentChanged()
    {
        $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $current_user_agent;
            return false;
        }

        // ถ้า User Agent เปลี่ยน แสดงว่าอาจมีปัญหา
        return $_SESSION['user_agent'] !== $current_user_agent;
    }

    /**
     * ตรวจสอบว่า user ยังคงเป็น active ในฐานข้อมูล
     */
    private function isUserActive()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT is_active, role 
                FROM users 
                WHERE user_id = ?
            ");

            $stmt->execute([$_SESSION['user']['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active']) {
                return false;
            }

            // อัปเดต role ถ้ามีการเปลี่ยนแปลง
            if ($user['role'] !== $_SESSION['user']['role']) {
                $_SESSION['user']['role'] = $user['role'];
            }

            return true;
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * อัปเดตเวลาล่าสุดที่ใช้งาน
     */
    public function updateLastActivity()
    {
        $_SESSION['last_activity'] = time();

        // Regenerate session ID ทุกๆ 30 นาที (ป้องกัน session fixation)
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        }

        if (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * ทำลาย session
     */
    public function destroy($reason = 'logout')
    {
        // บันทึก log
        if (isset($_SESSION['user']['user_id'])) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, new_values, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $_SESSION['user']['user_id'],
                    'session_destroyed',
                    json_encode(['reason' => $reason]),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                error_log("Session destroy log error: " . $e->getMessage());
            }
        }

        // ลบ session variables ทั้งหมด
        $_SESSION = [];

        // ลบ session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // ทำลาย session
        session_destroy();
    }

    /**
     * รีเฟรช session ข้อมูลของ user
     */
    public function refreshUserData()
    {
        if (!isset($_SESSION['user']['user_id'])) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.*,
                    d.department_name_th,
                    d.department_name_en
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.department_id
                WHERE u.user_id = ?
            ");

            $stmt->execute([$_SESSION['user']['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                // อัปเดต session data
                $_SESSION['user'] = $user;
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Refresh user data error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ดึงข้อมูลเวลาที่เหลือของ session
     */
    public function getRemainingTime()
    {
        if (!isset($_SESSION['last_activity'])) {
            return $this->timeout;
        }

        $elapsed = time() - $_SESSION['last_activity'];
        $remaining = $this->timeout - $elapsed;

        return max(0, $remaining);
    }

    /**
     * ตรวจสอบว่าใกล้หมดเวลาหรือไม่ (น้อยกว่า 5 นาที)
     */
    public function isAboutToExpire()
    {
        return $this->getRemainingTime() < 300; // 5 minutes
    }
}

/**
 * ===============================================
 * API Endpoint สำหรับตรวจสอบ session (AJAX)
 * ===============================================
 */

// ถ้าเป็นการเรียกผ่าน AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $sessionManager = new SessionManager();
    $action = $_GET['action'];

    switch ($action) {
        case 'check':
            // ตรวจสอบ session
            $isValid = $sessionManager->isValid();

            echo json_encode([
                'success' => true,
                'valid' => $isValid,
                'remaining_time' => $isValid ? $sessionManager->getRemainingTime() : 0,
                'about_to_expire' => $isValid ? $sessionManager->isAboutToExpire() : false
            ]);
            break;

        case 'refresh':
            // รีเฟรช session
            $refreshed = $sessionManager->refreshUserData();
            $sessionManager->updateLastActivity();

            echo json_encode([
                'success' => $refreshed,
                'remaining_time' => $sessionManager->getRemainingTime()
            ]);
            break;

        case 'extend':
            // ขยายเวลา session
            if ($sessionManager->isValid()) {
                $sessionManager->updateLastActivity();

                echo json_encode([
                    'success' => true,
                    'remaining_time' => $sessionManager->getRemainingTime(),
                    'message' => 'Session extended successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Session is invalid'
                ]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }

    exit;
}

/**
 * ===============================================
 * Helper Functions
 * ===============================================
 */

/**
 * ตรวจสอบ session (ใช้ในหน้าต่างๆ)
 */
function validateSession()
{
    $sessionManager = new SessionManager();

    if (!$sessionManager->isValid()) {
        // Session ไม่ valid
        if (isset($_SESSION['user'])) {
            // มี session แต่หมดอายุ
            $reason = 'session_timeout';
        } else {
            // ไม่มี session
            $reason = 'not_authenticated';
        }

        // ทำลาย session
        $sessionManager->destroy($reason);

        // เก็บ URL ปัจจุบันเพื่อ redirect กลับมา
        if (!isset($_SESSION['redirect_after_login'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }

        // Redirect ไปหน้า login
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'expired' => true,
                'message' => 'Session expired. Please login again.'
            ]);
            exit;
        } else {
            header('Location: ' . url('modules/auth/login.php?expired=1'));
            exit;
        }
    }

    return true;
}

/**
 * ตรวจสอบว่าเป็น AJAX request หรือไม่
 */
function isAjaxRequest()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * ดึงข้อมูล SessionManager instance
 */
function getSessionManager()
{
    return new SessionManager();
}

/**
 * ===============================================
 * Auto-check session (สำหรับใส่ในทุกหน้า)
 * ===============================================
 */

// ถ้าไม่ใช่การเรียกผ่าน AJAX และไม่ใช่หน้า login
if (
    !isset($_GET['action']) &&
    !strpos($_SERVER['SCRIPT_NAME'], 'login.php')
) {

    // ตรวจสอบ session อัตโนมัติ
    // (uncomment บรรทัดนี้ถ้าต้องการให้ตรวจสอบอัตโนมัติในทุกหน้า)
    // validateSession();
}
