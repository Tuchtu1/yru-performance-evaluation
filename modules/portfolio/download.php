<?php

/**
 * modules/portfolio/download.php
 * ดาวน์โหลดไฟล์ผลงาน
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login
requireAuth();

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$portfolio_id = $_GET['id'] ?? 0;

if (!$portfolio_id) {
    flash_error('ไม่พบไฟล์ที่ต้องการดาวน์โหลด');
    redirect('modules/portfolio/index.php');
    exit;
}

// ดึงข้อมูลผลงาน
$stmt = $db->prepare("
    SELECT p.*, u.full_name_th as owner_name
    FROM portfolios p
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.portfolio_id = ?
");
$stmt->execute([$portfolio_id]);
$portfolio = $stmt->fetch();

if (!$portfolio) {
    flash_error('ไม่พบผลงานที่ต้องการดาวน์โหลด');
    redirect('modules/portfolio/index.php');
    exit;
}

// ตรวจสอบว่ามีไฟล์หรือไม่
if (!$portfolio['file_path'] || !$portfolio['file_name']) {
    flash_error('ผลงานนี้ไม่มีไฟล์แนบ');
    redirect('modules/portfolio/view.php?id=' . $portfolio_id);
    exit;
}

// ตรวจสอบสิทธิ์การเข้าถึง
$can_download = false;

if ($portfolio['user_id'] == $user_id) {
    // เจ้าของผลงาน
    $can_download = true;
} elseif ($portfolio['is_shared'] == 1) {
    // ผลงานที่แชร์
    $can_download = true;
} elseif (can('portfolio.view_all')) {
    // Admin หรือ Manager
    $can_download = true;
}

if (!$can_download) {
    flash_error('คุณไม่มีสิทธิ์ดาวน์โหลดไฟล์นี้');
    redirect('modules/portfolio/index.php');
    exit;
}

// สร้าง path ของไฟล์
$file_path = UPLOAD_PATH . $portfolio['file_path'];

// ตรวจสอบว่าไฟล์มีอยู่จริงหรือไม่
if (!file_exists($file_path)) {
    flash_error('ไม่พบไฟล์ในระบบ');
    redirect('modules/portfolio/view.php?id=' . $portfolio_id);
    exit;
}

// Log activity
log_activity('download_portfolio', 'portfolios', $portfolio_id, [
    'file_name' => $portfolio['file_name'],
    'owner' => $portfolio['owner_name']
]);

// ส่งไฟล์ให้ดาวน์โหลด
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($portfolio['file_name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// ล้าง output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// อ่านไฟล์และส่งออก
readfile($file_path);
exit;
