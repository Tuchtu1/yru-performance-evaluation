<?php

/**
 * debug-notifications.php
 * หน้าสำหรับ Debug Notification System
 */

session_start();
define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/config/app.php';

$debug_info = [
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
    'user_logged_in' => isset($_SESSION['user']) ? 'Yes' : 'No',
    'user_info' => $_SESSION['user'] ?? 'Not logged in',
    'app_root' => APP_ROOT,
    'app_url' => APP_URL,
    'api_url' => APP_URL . '/api/notifications.php',
    'file_exists' => [
        'api/notifications.php' => file_exists(APP_ROOT . '/api/notifications.php') ? '✅ Yes' : '❌ No',
        'api/test.php' => file_exists(APP_ROOT . '/api/test.php') ? '✅ Yes' : '❌ No',
        'assets/js/notification.js' => file_exists(APP_ROOT . '/assets/js/notification.js') ? '✅ Yes' : '❌ No',
    ],
    'server_info' => [
        'PHP Version' => PHP_VERSION,
        'Document Root' => $_SERVER['DOCUMENT_ROOT'],
        'Script Filename' => $_SERVER['SCRIPT_FILENAME'],
        'Request URI' => $_SERVER['REQUEST_URI'],
    ]
];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Notification System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
        }
    </style>
</head>

<body class="bg-gray-50 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold mb-4">🔍 Debug - Notification System</h1>
            <p class="text-gray-600">ข้อมูลสำหรับตรวจสอบและแก้ไขปัญหา</p>
        </div>

        <!-- PHP Debug Info -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">📋 PHP Configuration</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($debug_info as $key => $value): ?>
                    <?php if (is_array($value)): ?>
                        <div class="col-span-2">
                            <h3 class="font-semibold text-gray-700 mb-2"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:
                            </h3>
                            <div class="code-block">
                                <pre><?php echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                            </div>
                        </div>
                    <?php else: ?>
                        <div>
                            <span
                                class="font-semibold text-gray-700"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                            <span class="text-gray-900"><?php echo $value; ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- API Tests -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">🧪 API Connection Tests</h2>

            <div class="space-y-4">
                <!-- Test 1: API Test File -->
                <div>
                    <h3 class="font-semibold mb-2">Test 1: API Test File</h3>
                    <button onclick="testAPIFile()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Test api/test.php
                    </button>
                    <div id="test1-result" class="mt-2"></div>
                </div>

                <!-- Test 2: Notifications API -->
                <div>
                    <h3 class="font-semibold mb-2">Test 2: Notifications API</h3>
                    <button onclick="testNotificationsAPI()"
                        class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Test api/notifications.php
                    </button>
                    <div id="test2-result" class="mt-2"></div>
                </div>

                <!-- Test 3: JavaScript Config -->
                <div>
                    <h3 class="font-semibold mb-2">Test 3: JavaScript Config</h3>
                    <button onclick="testJSConfig()"
                        class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                        Test API Config
                    </button>
                    <div id="test3-result" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Live Test Results -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold mb-4">📊 Test Results</h2>
            <div id="all-results" class="code-block">
                <pre>กดปุ่ม "Test" เพื่อเริ่มทดสอบ...</pre>
            </div>
        </div>
    </div>

    <!-- Load API Config -->
    <script src="<?php echo url('config/api-config.php'); ?>"></script>

    <script>
        window.APP_CONFIG = {
            baseUrl: '<?php echo APP_URL; ?>',
            apiUrl: '<?php echo APP_URL; ?>/api/notifications.php',
            assetsUrl: '<?php echo APP_URL; ?>/assets'
        };
        console.log('📡 API Config Loaded (Inline):', window.APP_CONFIG);

        function displayResult(elementId, success, data) {
            const element = document.getElementById(elementId);
            const icon = success ? '✅' : '❌';
            const colorClass = success ? 'text-green-600' : 'text-red-600';

            element.innerHTML = `
                <div class="${colorClass} font-medium">${icon} ${success ? 'Success' : 'Failed'}</div>
                <div class="code-block mt-2 text-sm">
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;

            // Update all results
            updateAllResults();
        }

        function updateAllResults() {
            const results = {
                timestamp: new Date().toISOString(),
                app_config: window.APP_CONFIG || 'Not loaded',
                tests: []
            };

            ['test1-result', 'test2-result', 'test3-result'].forEach(id => {
                const element = document.getElementById(id);
                if (element.innerHTML) {
                    results.tests.push({
                        test: id,
                        result: element.innerText
                    });
                }
            });

            document.getElementById('all-results').innerHTML =
                `<pre>${JSON.stringify(results, null, 2)}</pre>`;
        }

        async function testAPIFile() {
            try {
                const url = '<?php echo APP_URL; ?>/api/test.php';
                console.log('Testing:', url);

                const response = await fetch(url);
                const data = await response.json();

                displayResult('test1-result', true, {
                    url: url,
                    status: response.status,
                    data: data
                });
            } catch (error) {
                displayResult('test1-result', false, {
                    error: error.message,
                    url: '<?php echo APP_URL; ?>/api/test.php'
                });
            }
        }

        async function testNotificationsAPI() {
            try {
                const url = '<?php echo APP_URL; ?>/api/notifications.php?action=unread-count';
                console.log('Testing:', url);

                const response = await fetch(url);
                const contentType = response.headers.get('content-type');

                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Not JSON response. Got: ${text.substring(0, 200)}`);
                }

                const data = await response.json();

                displayResult('test2-result', data.success, {
                    url: url,
                    status: response.status,
                    data: data
                });
            } catch (error) {
                displayResult('test2-result', false, {
                    error: error.message,
                    url: '<?php echo APP_URL; ?>/api/notifications.php'
                });
            }
        }

        function testJSConfig() {
            const hasConfig = typeof window.APP_CONFIG !== 'undefined';
            displayResult('test3-result', hasConfig, {
                config_loaded: hasConfig,
                app_config: window.APP_CONFIG || 'Not loaded',
                base_url: window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/')
            });
        }

        // Auto-run tests on page load
        window.addEventListener('DOMContentLoaded', () => {
            console.log('🚀 Debug page loaded');
            console.log('📡 APP_CONFIG:', window.APP_CONFIG);

            // Run all tests automatically
            setTimeout(() => {
                testAPIFile();
                setTimeout(() => testNotificationsAPI(), 500);
                setTimeout(() => testJSConfig(), 1000);
            }, 500);
        });
    </script>
</body>

</html>