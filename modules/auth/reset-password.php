<?php

/**
 * /modules/auth/reset-password.php
 * modules/auth/reset-password.php
 * หน้าเปลี่ยนรหัสผ่าน
 * YRU Performance Evaluation System
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/helpers.php';

// ต้อง login ก่อน
requireAuth();

$db = getDB();
$user = $_SESSION['user'];
$error = '';
$success = '';

// ประมวลผลเมื่อกด submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($current_password)) {
        $error = 'กรุณากรอกรหัสผ่านปัจจุบัน';
    } elseif (empty($new_password)) {
        $error = 'กรุณากรอกรหัสผ่านใหม่';
    } elseif (strlen($new_password) < 6) {
        $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($new_password !== $confirm_password) {
        $error = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        // ตรวจสอบรหัสผ่านปัจจุบัน
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $userData = $stmt->fetch();

        if (!password_verify($current_password, $userData['password'])) {
            $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        } else {
            // ตรวจสอบว่ารหัสผ่านใหม่ไม่ซ้ำกับรหัสผ่านเก่า
            if (password_verify($new_password, $userData['password'])) {
                $error = 'รหัสผ่านใหม่ต้องไม่เหมือนรหัสผ่านเก่า';
            } else {
                // อัปเดตรหัสผ่าน
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                    UPDATE users 
                    SET password = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");

                if ($stmt->execute([$hashed_password, $user['user_id']])) {
                    // บันทึก log
                    log_activity('change_password', 'users', $user['user_id'], [
                        'action' => 'Changed password successfully'
                    ]);

                    $success = 'เปลี่ยนรหัสผ่านสำเร็จ';

                    // Clear form
                    $_POST = [];
                } else {
                    $error = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
                }
            }
        }
    }
}

// Include header
$page_title = 'เปลี่ยนรหัสผ่าน';
include '../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4 max-w-2xl">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="<?php echo url('modules/dashboard/index.php'); ?>"
                            class="text-gray-700 hover:text-blue-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z">
                                </path>
                            </svg>
                            หน้าหลัก
                        </a>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-gray-500 md:ml-2">เปลี่ยนรหัสผ่าน</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h1 class="text-xl font-bold text-white flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                        </path>
                    </svg>
                    เปลี่ยนรหัสผ่าน
                </h1>
            </div>

            <!-- Form -->
            <div class="p-6">
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-green-700 font-medium"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info Box -->
                <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <div class="flex">
                        <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium mb-1">ข้อแนะนำในการตั้งรหัสผ่าน:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</li>
                                <li>ควรประกอบด้วยตัวอักษรพิมพ์เล็ก พิมพ์ใหญ่ ตัวเลข และอักขระพิเศษ</li>
                                <li>ไม่ควรใช้รหัสผ่านที่เคยใช้ในที่อื่น</li>
                                <li>เปลี่ยนรหัสผ่านเป็นประจำเพื่อความปลอดภัย</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" class="space-y-6">
                    <!-- Current Password -->
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                            รหัสผ่านปัจจุบัน <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="กรอกรหัสผ่านปัจจุบัน">
                            <button type="button" onclick="togglePassword('current_password')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                            รหัสผ่านใหม่ <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password" required minlength="6"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)">
                            <button type="button" onclick="togglePassword('new_password')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            ยืนยันรหัสผ่านใหม่ <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                            <button type="button" onclick="togglePassword('confirm_password')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-between pt-4 border-t">
                        <a href="<?php echo url('modules/dashboard/index.php'); ?>"
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            ยกเลิก
                        </a>
                        <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                            เปลี่ยนรหัสผ่าน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
        } else {
            field.type = 'password';
        }
    }

    // Password strength indicator (optional)
    document.getElementById('new_password').addEventListener('input', function() {
        const password = this.value;
        let strength = 0;

        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z\d]/.test(password)) strength++;

        // Update UI based on strength (you can add visual indicator here)
        console.log('Password strength:', strength);
    });
</script>

<?php include '../../includes/footer.php'; ?>