<?php

/**
 * api/test.php
 * Simple API Test File
 */

// เปิด error reporting เพื่อดู error
error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดเพื่อไม่ให้ขัดจังหวะ JSON
ini_set('log_errors', 1);

// Set JSON header ก่อนอื่นเสมอ
header('Content-Type: application/json; charset=utf-8');

try {
    // กำหนด APP_ROOT (ถ้ายังไม่ได้กำหนด)
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', dirname(__DIR__));
    }

    // ทดสอบว่าโหลด config ได้หรือไม่
    $config_loaded = false;
    if (file_exists(APP_ROOT . '/config/app.php')) {
        require_once APP_ROOT . '/config/app.php';
        $config_loaded = true;
    }

    // Response
    echo json_encode([
        'success' => true,
        'message' => 'API is working!',
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'APP_ROOT' => APP_ROOT,
            'config_loaded' => $config_loaded,
            'APP_URL' => defined('APP_URL') ? APP_URL : 'Not defined',
            'PHP_VERSION' => PHP_VERSION,
            'file_exists' => [
                'config/app.php' => file_exists(APP_ROOT . '/config/app.php'),
                'config/database.php' => file_exists(APP_ROOT . '/config/database.php'),
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
