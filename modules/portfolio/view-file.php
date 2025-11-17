<?php

/**
 * modules/portfolio/view-file.php
 * แสดงไฟล์ในเบราว์เซอร์ (PDF, รูปภาพ)
 */

// กำหนด APP_ROOT
define('APP_ROOT', dirname(dirname(__DIR__)));

// โหลดไฟล์ config
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login
requireAuth();

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$portfolio_id = $_GET['id'] ?? 0;

// Debug: บันทึก log
error_log("View File Request - Portfolio ID: $portfolio_id, User ID: $user_id");

if (!$portfolio_id) {
    http_response_code(404);
    error_log("View File Error: No portfolio ID");
    die('ไม่พบไฟล์');
}

// ดึงข้อมูลผลงาน
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name_th as owner_name
        FROM portfolios p
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE p.portfolio_id = ?
    ");
    $stmt->execute([$portfolio_id]);
    $portfolio = $stmt->fetch();
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    die('เกิดข้อผิดพลาดในการโหลดข้อมูล');
}

if (!$portfolio) {
    http_response_code(404);
    error_log("View File Error: Portfolio not found - ID: $portfolio_id");
    die('ไม่พบผลงาน');
}

// ตรวจสอบว่ามีไฟล์หรือไม่
if (!$portfolio['file_path'] || !$portfolio['file_name']) {
    http_response_code(404);
    error_log("View File Error: No file attached - Portfolio ID: $portfolio_id");
    die('ไม่มีไฟล์แนบ');
}

// ตรวจสอบสิทธิ์การเข้าถึง
$can_view = false;

if ($portfolio['user_id'] == $user_id) {
    $can_view = true;
} elseif ($portfolio['is_shared'] == 1) {
    $can_view = true;
} elseif (can('portfolio.view_all')) {
    $can_view = true;
}

if (!$can_view) {
    http_response_code(403);
    error_log("View File Error: Access denied - Portfolio ID: $portfolio_id, User ID: $user_id");
    die('คุณไม่มีสิทธิ์ดูไฟล์นี้');
}

// สร้าง path ของไฟล์
$file_path = UPLOAD_PATH . $portfolio['file_path'];

error_log("File Path: $file_path");

// ตรวจสอบว่าไฟล์มีอยู่จริงหรือไม่
if (!file_exists($file_path)) {
    http_response_code(404);
    error_log("View File Error: File not found at path: $file_path");
    die('ไม่พบไฟล์ในระบบ (Path: ' . $file_path . ')');
}

// ดึงนามสกุลไฟล์
$file_ext = strtolower(pathinfo($portfolio['file_name'], PATHINFO_EXTENSION));

// กำหนด MIME type ตามนามสกุลไฟล์
$mime_types = [
    // Images
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'bmp' => 'image/bmp',

    // PDF
    'pdf' => 'application/pdf',

    // Documents (สำหรับอนาคต)
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt' => 'text/plain',
];

$mime_type = $mime_types[$file_ext] ?? 'application/octet-stream';

// ตรวจสอบว่าเป็นไฟล์ที่แสดงได้หรือไม่
$viewable_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'pdf'];
if (!in_array($file_ext, $viewable_types)) {
    http_response_code(400);
    error_log("View File Error: File type not viewable - Extension: $file_ext");
    die('ไฟล์ประเภทนี้ไม่สามารถแสดงในเบราว์เซอร์ได้');
}

// Log activity
try {
    log_activity('view_file', 'portfolios', $portfolio_id, [
        'file_name' => $portfolio['file_name'],
        'file_type' => $file_ext
    ]);
} catch (Exception $e) {
    error_log("Log Activity Error: " . $e->getMessage());
}

// ล้าง output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// ส่ง headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="' . basename($portfolio['file_name']) . '"');
header('Cache-Control: private, max-age=3600, must-revalidate');
header('Pragma: public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// สำหรับ PDF ให้เปิดในเบราว์เซอร์
if ($file_ext === 'pdf') {
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
}

// อ่านไฟล์และส่งออก
readfile($file_path);
exit;
