<?php
// modules/auth/login.php
session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';

// ถ้า login แล้วให้ไปหน้า dashboard
if (isset($_SESSION['user'])) {
    redirect('modules/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login สำเร็จ
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

                // อัพเดทเวลา login ล่าสุด
                $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $update->execute([$user['user_id']]);

                // บันทึก activity log
                $log = $db->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
                $log->execute([$user['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                redirect('modules/dashboard/index.php');
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?php echo APP_NAME; ?></title>
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

    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
        <div class="max-w-md w-full space-y-8">

            <!-- Logo & Title -->
            <div class="text-center">
                <div class="flex justify-center">
                    <div
                        class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    ระบบประเมินผลการปฏิบัติงาน
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    มหาวิทยาลัยราชภัฏยะลา
                </p>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8 space-y-6">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-1">เข้าสู่ระบบ</h3>
                    <p class="text-sm text-gray-500">กรุณากรอกข้อมูลเพื่อเข้าใช้งานระบบ</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd" />
                            </svg>
                            <p class="text-sm text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            ชื่อผู้ใช้
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input type="text" name="username" id="username" required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                placeholder="กรอกชื่อผู้ใช้">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            รหัสผ่าน
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" name="password" id="password" required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                placeholder="กรอกรหัสผ่าน">
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                จดจำฉันไว้
                            </label>
                        </div>
                        <a href="reset-password.php" class="text-sm text-blue-600 hover:text-blue-700">
                            ลืมรหัสผ่าน?
                        </a>
                    </div>

                    <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-[1.02]">
                        เข้าสู่ระบบ
                    </button>
                </form>
            </div>

            <!-- Help Info -->
            <div class="text-center">
                <p class="text-sm text-gray-500">
                    มีปัญหาในการเข้าใช้งาน?
                    <a href="mailto:support@yru.ac.th"
                        class="text-blue-600 hover:text-blue-700 font-medium">ติดต่อผู้ดูแลระบบ</a>
                </p>
                <p class="text-xs text-gray-400 mt-2">
                    © 2025 Yala Rajabhat University. All rights reserved.
                </p>
            </div>

        </div>
    </div>

</body>

</html>