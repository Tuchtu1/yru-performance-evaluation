<?php

/**
 * Landing Page
 * YRU Performance Evaluation System
 */

define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/config/app.php';
session_start();

// ถ้า login แล้วให้ไป dashboard
if (isset($_SESSION['user'])) {
    redirect('modules/dashboard/index.php');
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 via-white to-purple-50 min-h-screen">

    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-4xl w-full">

            <!-- Hero Section -->
            <div class="text-center mb-12">
                <div class="flex justify-center mb-6">
                    <div
                        class="w-24 h-24 bg-gradient-to-br from-blue-500 to-blue-600 rounded-3xl flex items-center justify-center shadow-2xl">
                        <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    ระบบประเมินผลการปฏิบัติงาน
                </h1>
                <p class="text-xl text-gray-600 mb-2">
                    มหาวิทยาลัยราชภัฏยะลา
                </p>
                <p class="text-sm text-gray-500">
                    Yala Rajabhat University Performance Evaluation System
                </p>
            </div>

            <!-- Features -->
            <div class="grid md:grid-cols-3 gap-6 mb-12">
                <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">ประเมินผลงาน</h3>
                    <p class="text-sm text-gray-600">ระบบประเมินผลการปฏิบัติงานอย่างเป็นระบบและโปร่งใส</p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">รายงานผล</h3>
                    <p class="text-sm text-gray-600">สร้างรายงานและวิเคราะห์ผลการประเมินแบบอัตโนมัติ</p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-shadow">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">จัดการบุคลากร</h3>
                    <p class="text-sm text-gray-600">บริหารจัดการข้อมูลบุคลากรและโครงสร้างองค์กร</p>
                </div>
            </div>

            <!-- CTA Button -->
            <div class="text-center">
                <a href="<?php echo url('modules/auth/login.php'); ?>"
                    class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-lg font-semibold rounded-xl shadow-lg hover:from-blue-700 hover:to-blue-800 transform hover:scale-105 transition-all">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    เข้าสู่ระบบ
                </a>
            </div>

            <!-- Footer -->
            <div class="mt-12 text-center text-sm text-gray-500">
                <p>© 2025 Yala Rajabhat University. All rights reserved.</p>
                <p class="mt-1">Version <?php echo APP_VERSION; ?></p>
            </div>

        </div>
    </div>

</body>

</html>