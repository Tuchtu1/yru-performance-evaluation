<?php

/**
 * /modules/configuration/notification-settings.php
 * ตั้งค่าการแจ้งเตือน (Notification Settings)
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requirePermission('config.notifications');

$page_title = 'ตั้งค่าการแจ้งเตือน';
$db = getDB();

// ==================== Handle Actions ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_settings':
            try {
                $settings = [
                    'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
                    'sms_enabled' => isset($_POST['sms_enabled']) ? 1 : 0,
                    'line_enabled' => isset($_POST['line_enabled']) ? 1 : 0,
                    'notify_submission' => isset($_POST['notify_submission']) ? 1 : 0,
                    'notify_approval' => isset($_POST['notify_approval']) ? 1 : 0,
                    'notify_rejection' => isset($_POST['notify_rejection']) ? 1 : 0,
                    'notify_reminder' => isset($_POST['notify_reminder']) ? 1 : 0,
                    'reminder_days' => $_POST['reminder_days'] ?? 7,
                    'line_token' => $_POST['line_token'] ?? '',
                    'smtp_host' => $_POST['smtp_host'] ?? '',
                    'smtp_port' => $_POST['smtp_port'] ?? '',
                    'smtp_username' => $_POST['smtp_username'] ?? '',
                    'smtp_password' => $_POST['smtp_password'] ?? '',
                ];

                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_by)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        updated_by = VALUES(updated_by),
                        updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute(['notification_' . $key, $value, $_SESSION['user']['user_id']]);
                }

                log_activity('update', 'notification_settings', null, $settings);
                flash_success('บันทึกการตั้งค่าสำเร็จ');
            } catch (PDOException $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('notification-settings.php');
            break;

        case 'test_notification':
            try {
                $type = $_POST['notification_type'];
                $success = false;

                switch ($type) {
                    case 'email':
                        $success = send_email(
                            $_SESSION['user']['email'],
                            'ทดสอบการส่งอีเมล',
                            'นี่คือข้อความทดสอบจากระบบประเมินผลการปฏิบัติงาน'
                        );
                        break;
                        // Add other notification types here
                }

                if ($success) {
                    flash_success('ส่งการทดสอบสำเร็จ');
                } else {
                    flash_error('ไม่สามารถส่งการทดสอบได้');
                }
            } catch (Exception $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('notification-settings.php');
            break;
    }
}

// ==================== Fetch Current Settings ====================

$settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'notification_%'");
while ($row = $stmt->fetch()) {
    $key = str_replace('notification_', '', $row['setting_key']);
    $settings[$key] = $row['setting_value'];
}

// Default values
$defaults = [
    'email_enabled' => 1,
    'sms_enabled' => 0,
    'line_enabled' => 0,
    'notify_submission' => 1,
    'notify_approval' => 1,
    'notify_rejection' => 1,
    'notify_reminder' => 1,
    'reminder_days' => 7,
    'line_token' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => ''
];

$settings = array_merge($defaults, $settings);

// Get notification statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM notifications
")->fetch();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">ตั้งค่าการแจ้งเตือน</h1>
            <p class="mt-2 text-sm text-gray-600">
                จัดการการตั้งค่าการแจ้งเตือนผ่านช่องทางต่างๆ
            </p>
        </div>
        <button onclick="openModal('testModal')" class="btn btn-outline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            ทดสอบการแจ้งเตือน
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">การแจ้งเตือนทั้งหมด</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total']); ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">ยังไม่ได้อ่าน</p>
                <p class="text-3xl font-bold text-orange-600"><?php echo number_format($stats['unread']); ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">วันนี้</p>
                <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['today']); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Settings Form -->
<form method="POST">
    <input type="hidden" name="action" value="update_settings">

    <!-- Notification Channels -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold">ช่องทางการแจ้งเตือน</h3>
        </div>
        <div class="card-body space-y-4">

            <!-- Email -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">อีเมล (Email)</h4>
                        <p class="text-sm text-gray-600">แจ้งเตือนผ่านอีเมล</p>
                    </div>
                </div>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="email_enabled"
                        <?php echo $settings['email_enabled'] ? 'checked' : ''; ?> class="form-checkbox w-6 h-6">
                </label>
            </div>

            <!-- SMS -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg opacity-60">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">SMS</h4>
                        <p class="text-sm text-gray-600">แจ้งเตือนผ่าน SMS <span class="badge badge-warning ml-2">เร็วๆ
                                นี้</span></p>
                    </div>
                </div>
                <label class="flex items-center cursor-not-allowed">
                    <input type="checkbox" name="sms_enabled" disabled class="form-checkbox w-6 h-6">
                </label>
            </div>

            <!-- LINE Notify -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">LINE Notify</h4>
                        <p class="text-sm text-gray-600">แจ้งเตือนผ่าน LINE</p>
                    </div>
                </div>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="line_enabled" <?php echo $settings['line_enabled'] ? 'checked' : ''; ?>
                        class="form-checkbox w-6 h-6">
                </label>
            </div>

        </div>
    </div>

    <!-- Notification Events -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold">เหตุการณ์ที่ต้องการแจ้งเตือน</h3>
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100">
                    <input type="checkbox" name="notify_submission"
                        <?php echo $settings['notify_submission'] ? 'checked' : ''; ?> class="form-checkbox">
                    <span class="ml-3 text-gray-900">เมื่อมีการส่งแบบประเมิน</span>
                </label>

                <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100">
                    <input type="checkbox" name="notify_approval"
                        <?php echo $settings['notify_approval'] ? 'checked' : ''; ?> class="form-checkbox">
                    <span class="ml-3 text-gray-900">เมื่อแบบประเมินได้รับการอนุมัติ</span>
                </label>

                <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100">
                    <input type="checkbox" name="notify_rejection"
                        <?php echo $settings['notify_rejection'] ? 'checked' : ''; ?> class="form-checkbox">
                    <span class="ml-3 text-gray-900">เมื่อแบบประเมินถูกปฏิเสธ</span>
                </label>

                <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100">
                    <input type="checkbox" name="notify_reminder"
                        <?php echo $settings['notify_reminder'] ? 'checked' : ''; ?> class="form-checkbox">
                    <span class="ml-3 text-gray-900">การแจ้งเตือนก่อนครบกำหนด</span>
                </label>

                <div class="ml-8 p-3 bg-blue-50 rounded-lg">
                    <label class="form-label text-sm">แจ้งเตือนก่อนครบกำหนด (วัน)</label>
                    <input type="number" name="reminder_days" value="<?php echo e($settings['reminder_days']); ?>"
                        min="1" max="30" class="form-input mt-1" style="max-width: 150px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Email Settings -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold">ตั้งค่า Email (SMTP)</h3>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" value="<?php echo e($settings['smtp_host']); ?>"
                        class="form-input" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Port</label>
                    <input type="number" name="smtp_port" value="<?php echo e($settings['smtp_port']); ?>"
                        class="form-input" placeholder="587">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" name="smtp_username" value="<?php echo e($settings['smtp_username']); ?>"
                        class="form-input" placeholder="user@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" name="smtp_password" value="<?php echo e($settings['smtp_password']); ?>"
                        class="form-input" placeholder="••••••••">
                </div>
            </div>
        </div>
    </div>

    <!-- LINE Notify Settings -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold">ตั้งค่า LINE Notify</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">LINE Notify Token</label>
                <input type="text" name="line_token" value="<?php echo e($settings['line_token']); ?>"
                    class="form-input" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                <p class="form-help">
                    <a href="https://notify-bot.line.me/my/" target="_blank" class="text-blue-600 hover:underline">
                        คลิกที่นี่เพื่อสร้าง LINE Notify Token
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end gap-3">
        <a href="<?php echo url('modules/dashboard/index.php'); ?>" class="btn btn-outline">ยกเลิก</a>
        <button type="submit" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            บันทึกการตั้งค่า
        </button>
    </div>
</form>

<!-- Test Notification Modal -->
<div id="testModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">ทดสอบการแจ้งเตือน</h3>
            <button onclick="closeModal('testModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="test_notification">
            <div class="modal-body space-y-4">
                <p class="text-gray-600">เลือกช่องทางที่ต้องการทดสอบ</p>

                <label
                    class="flex items-center p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500">
                    <input type="radio" name="notification_type" value="email" checked class="form-radio">
                    <span class="ml-3">Email</span>
                </label>

                <label
                    class="flex items-center p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500">
                    <input type="radio" name="notification_type" value="line" class="form-radio">
                    <span class="ml-3">LINE Notify</span>
                </label>

                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r">
                    <p class="text-sm text-yellow-700">
                        การแจ้งเตือนจะถูกส่งไปยังบัญชีของคุณเท่านั้น
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('testModal')" class="btn btn-outline">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">ทดสอบเลย</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>