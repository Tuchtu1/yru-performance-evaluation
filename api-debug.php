<?php

/**
 * api-debug.php
 * แสดง Error ทั้งหมดเพื่อ Debug
 */

// เปิด error reporting ทั้งหมด
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>🔍 API Debug Page</h1>";
echo "<pre>";

// 1. ตรวจสอบ PHP Version
echo "=== PHP VERSION ===\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// 2. ตรวจสอบ Path
echo "=== PATHS ===\n";
echo "Current Directory: " . __DIR__ . "\n";
echo "Script Filename: " . __FILE__ . "\n\n";

// 3. ตรวจสอบไฟล์ที่จำเป็น
echo "=== FILE CHECK ===\n";
$files_to_check = [
    'config/app.php',
    'config/database.php',
    'api/test.php',
    'api/notifications.php',
    'assets/js/notification.js'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    $exists = file_exists($full_path);
    $icon = $exists ? '✅' : '❌';
    echo "$icon $file: " . ($exists ? 'EXISTS' : 'NOT FOUND') . "\n";
    if ($exists) {
        echo "   Path: $full_path\n";
        echo "   Size: " . filesize($full_path) . " bytes\n";
    }
}
echo "\n";

// 4. ทดสอบโหลด config/app.php
echo "=== TESTING CONFIG LOAD ===\n";
try {
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', __DIR__);
    }
    echo "APP_ROOT defined: " . APP_ROOT . "\n";

    if (file_exists(APP_ROOT . '/config/app.php')) {
        echo "Loading config/app.php...\n";
        require_once APP_ROOT . '/config/app.php';
        echo "✅ Config loaded successfully!\n";
        echo "APP_URL: " . (defined('APP_URL') ? APP_URL : 'NOT DEFINED') . "\n";
        echo "APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'NOT DEFINED') . "\n";
    } else {
        echo "❌ config/app.php NOT FOUND!\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR loading config:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
echo "\n";

// 5. ทดสอบโหลด database.php
echo "=== TESTING DATABASE CONFIG ===\n";
try {
    if (file_exists(APP_ROOT . '/config/database.php')) {
        echo "Loading config/database.php...\n";
        require_once APP_ROOT . '/config/database.php';
        echo "✅ Database config loaded!\n";

        // ทดสอบเชื่อมต่อ database
        try {
            $db = getDB();
            echo "✅ Database connection successful!\n";
            $stmt = $db->query("SELECT COUNT(*) as count FROM notifications");
            $count = $stmt->fetch()['count'];
            echo "   Notifications count: $count\n";
        } catch (Exception $e) {
            echo "❌ Database connection failed:\n";
            echo "   " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ config/database.php NOT FOUND!\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR loading database config:\n";
    echo "Message: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. ตรวจสอบ Session
echo "=== SESSION INFO ===\n";
session_start();
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
echo "Session ID: " . session_id() . "\n";
echo "User Logged In: " . (isset($_SESSION['user']) ? 'YES' : 'NO') . "\n";
if (isset($_SESSION['user'])) {
    echo "User ID: " . $_SESSION['user']['user_id'] . "\n";
    echo "Username: " . $_SESSION['user']['username'] . "\n";
}
echo "\n";

// 7. ทดสอบ API Call (Internal)
echo "=== TESTING API INTERNALLY ===\n";
echo "Testing api/test.php...\n";
try {
    // Include the API file
    ob_start();
    include __DIR__ . '/api/test.php';
    $output = ob_get_clean();

    echo "✅ API executed successfully!\n";
    echo "Output:\n";
    echo $output . "\n";
} catch (Exception $e) {
    echo "❌ API execution failed:\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<hr>";
echo "<h2>🧪 Test Links</h2>";
echo "<ul>";
echo "<li><a href='api/test.php'>Test api/test.php</a></li>";
echo "<li><a href='api/notifications.php?action=unread-count'>Test api/notifications.php</a></li>";
echo "<li><a href='debug-notifications.php'>Full Debug Page</a></li>";
echo "<li><a href='index.php'>Go to Home</a></li>";
echo "</ul>";
