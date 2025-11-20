<?php

/**
 * config/app.php
 * การตั้งค่าแอปพลิเคชัน
 * YRU Performance Evaluation System
 */

// กำหนด Root Path (ตรวจสอบก่อนว่ามีการ define แล้วหรือยัง)
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ตั้งค่า URL // TODO: APP_URL -> .env
if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost:8000');
}

// ตั้งค่าทั่วไป
if (!defined('APP_NAME')) {
    define('APP_NAME', 'ระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา');
}
define('APP_NAME_EN', 'YRU Performance Evaluation System');
define('APP_VERSION', '1.0.0');

// ตั้งค่า Environment
define('ENVIRONMENT', 'development'); // development, staging, production

// ตั้งค่าเขตเวลา
date_default_timezone_set('Asia/Bangkok');

// ตั้งค่า Session (ตรวจสอบว่า session active หรือยัง)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // เปลี่ยนเป็น 1 ถ้าใช้ HTTPS
    // ✅ เพิ่มบรรทัดนี้
    session_start();
}
define('SESSION_TIMEOUT', 3600); // 1 ชั่วโมง

// ตั้งค่าการแสดงข้อผิดพลาด (ขึ้นอยู่กับ Environment)
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} elseif (ENVIRONMENT === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    // staging หรือ environment อื่นๆ
    error_reporting(E_ALL & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '1');
}

// ตั้งค่าการอัปโหลดไฟล์
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100 MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']);

// ตั้งค่าการแบ่งหน้า
define('ITEMS_PER_PAGE', 20);

// ตั้งค่าอีเมล
define('MAIL_FROM', 'noreply@yru.ac.th');
define('MAIL_FROM_NAME', 'ระบบประเมินผล มยร.');

// บทบาทผู้ใช้งาน
define('ROLE_ADMIN', 'admin');
define('ROLE_STAFF', 'staff');
define('ROLE_MANAGER', 'manager');

// ประเภทบุคลากร
define('PERSONNEL_ACADEMIC', 'academic');
define('PERSONNEL_SUPPORT', 'support');
define('PERSONNEL_LECTURER', 'lecturer');

// สถานะแบบประเมิน
define('STATUS_DRAFT', 'draft');
define('STATUS_SUBMITTED', 'submitted');
define('STATUS_UNDER_REVIEW', 'under_review');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_RETURNED', 'returned');

// สีสำหรับแสดงสถานะ
$status_colors = [
    'draft' => 'bg-gray-100 text-gray-800',
    'submitted' => 'bg-blue-100 text-blue-800',
    'under_review' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'returned' => 'bg-orange-100 text-orange-800'
];

// ชื่อสถานะภาษาไทย
$status_names = [
    'draft' => 'ร่าง',
    'submitted' => 'ส่งแล้ว',
    'under_review' => 'กำลังตรวจสอบ',
    'approved' => 'อนุมัติ',
    'rejected' => 'ไม่อนุมัติ',
    'returned' => 'ส่งกลับแก้ไข'
];

// ชื่อบทบาทภาษาไทย
$role_names = [
    'admin' => 'ผู้ดูแลระบบ',
    'staff' => 'บุคลากร',
    'manager' => 'ผู้บริหาร'
];

// ชื่อประเภทบุคลากรภาษาไทย
$personnel_names = [
    'academic' => 'สายวิชาการ',
    'support' => 'สายสนับสนุน',
    'lecturer' => 'อาจารย์'
];

// ฟังก์ชันช่วยต่างๆ
function asset($path)
{
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function url($path = '')
{
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Redirect to URL
 * รองรับทั้ง relative และ absolute path
 */
function redirect($path = '')
{
    // ถ้าเป็น full URL (มี http:// หรือ https://)
    if (preg_match('/^https?:\/\//', $path)) {
        header('Location: ' . $path);
        exit;
    }

    // ถ้าเป็น relative path (เริ่มต้นด้วย /)
    if (strpos($path, '/') === 0) {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    // ถ้าเป็นชื่อไฟล์เฉยๆ ใช้ current directory
    if (!empty($path) && strpos($path, '/') === false) {
        // ใช้ current script directory
        $currentDir = dirname($_SERVER['PHP_SELF']);
        header('Location: ' . $path);
        exit;
    }

    // Default: ไปที่ APP_URL + path
    $url = rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    header('Location: ' . $url);
    exit;
}

function old($key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function error($key)
{
    return $_SESSION['errors'][$key] ?? null;
}

function success($message)
{
    $_SESSION['success'] = $message;
}

function setError($key, $message)
{
    $_SESSION['errors'][$key] = $message;
}

function clearOldInput()
{
    unset($_SESSION['old']);
    unset($_SESSION['errors']);
}

// สร้างโฟลเดอร์ที่จำเป็น
$required_dirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'evaluations/',
    UPLOAD_PATH . 'portfolios/',
    UPLOAD_PATH . 'reports/',
    UPLOAD_PATH . 'temp/'
];

foreach ($required_dirs as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}
