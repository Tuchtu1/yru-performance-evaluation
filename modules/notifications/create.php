<?php

/**
 * modules/notifications/create.php
 * หน้าสร้างการแจ้งเตือนใหม่
 */

session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/helpers.php';

// ตรวจสอบสิทธิ์ (เฉพาะ Admin)
requireAuth();
requireAdmin('เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถสร้างการแจ้งเตือนได้');

$db = getDB();
$errors = [];

// ดึงรายชื่อผู้ใช้และหน่วยงาน
$users_stmt = $db->query("
    SELECT u.user_id, u.full_name_th, u.position, d.department_name_th, u.role
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.is_active = 1
    ORDER BY u.full_name_th
");
$users = $users_stmt->fetchAll();

$departments_stmt = $db->query("
    SELECT department_id, department_name_th 
    FROM departments 
    WHERE is_active = 1 
    ORDER BY department_name_th
");
$departments = $departments_stmt->fetchAll();

// ประเภทการแจ้งเตือน
$notification_types = [
    'general' => 'ทั่วไป',
    'evaluation' => 'เกี่ยวกับการประเมิน',
    'reminder' => 'การเตือนความจำ',
    'broadcast' => 'ประกาศ',
    'urgent' => 'เร่งด่วน'
];

// บันทึกการแจ้งเตือน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type = $_POST['type'] ?? 'general';
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $specific_users = $_POST['specific_users'] ?? [];
    $department_id = $_POST['department_id'] ?? null;
    $role = $_POST['role'] ?? null;

    // Validation
    if (empty($title)) {
        $errors[] = 'กรุณาระบุหัวข้อการแจ้งเตือน';
    }
    if (empty($message)) {
        $errors[] = 'กรุณาระบุข้อความการแจ้งเตือน';
    }
    if ($recipient_type === 'specific' && empty($specific_users)) {
        $errors[] = 'กรุณาเลือกผู้รับการแจ้งเตือนอย่างน้อย 1 คน';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // กำหนดผู้รับ
            $recipients = [];

            switch ($recipient_type) {
                case 'all':
                    // ส่งให้ทุกคน
                    $stmt = $db->query("SELECT user_id FROM users WHERE is_active = 1");
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;

                case 'department':
                    // ส่งตามหน่วยงาน
                    if ($department_id) {
                        $stmt = $db->prepare("SELECT user_id FROM users WHERE department_id = ? AND is_active = 1");
                        $stmt->execute([$department_id]);
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    break;

                case 'role':
                    // ส่งตามบทบาท
                    if ($role) {
                        $stmt = $db->prepare("SELECT user_id FROM users WHERE role = ? AND is_active = 1");
                        $stmt->execute([$role]);
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    break;

                case 'specific':
                    // ส่งให้คนเฉพาะ
                    $recipients = $specific_users;
                    break;
            }

            // บันทึกการแจ้งเตือนสำหรับแต่ละคน
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, ?, ?, ?)
            ");

            $success_count = 0;
            foreach ($recipients as $user_id) {
                $stmt->execute([$user_id, $title, $message, $type]);
                $success_count++;
            }

            // Log activity
            log_activity('create_notification', 'notifications', null, [
                'title' => $title,
                'type' => $type,
                'recipient_type' => $recipient_type,
                'recipient_count' => $success_count
            ]);

            $db->commit();

            flash_success("สร้างการแจ้งเตือนสำเร็จ ส่งถึง {$success_count} คน");
            redirect('modules/notifications/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Create Notification Error: ' . $e->getMessage());
            $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

$page_title = 'สร้างการแจ้งเตือนใหม่';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">สร้างการแจ้งเตือนใหม่</h1>
                <p class="text-gray-600 mt-2">ส่งการแจ้งเตือนให้กับผู้ใช้ในระบบ</p>
            </div>
            <a href="<?php echo url('modules/notifications/index.php'); ?>"
                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                ย้อนกลับ
            </a>
        </div>
    </div>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">พบข้อผิดพลาด:</h3>
                    <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="bg-white rounded-lg shadow">
        <?php echo csrf_field(); ?>

        <div class="p-6 space-y-6">
            <!-- ประเภทการแจ้งเตือน -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    ประเภทการแจ้งเตือน <span class="text-red-500">*</span>
                </label>
                <select name="type" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <?php foreach ($notification_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- หัวข้อ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    หัวข้อการแจ้งเตือน <span class="text-red-500">*</span>
                </label>
                <input type="text" name="title" required maxlength="255" value="<?php echo e($_POST['title'] ?? ''); ?>"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="ระบุหัวข้อการแจ้งเตือน">
            </div>

            <!-- ข้อความ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    ข้อความ <span class="text-red-500">*</span>
                </label>
                <textarea name="message" required rows="5"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="ระบุข้อความการแจ้งเตือน"><?php echo e($_POST['message'] ?? ''); ?></textarea>
            </div>

            <!-- ผู้รับ -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    ผู้รับการแจ้งเตือน <span class="text-red-500">*</span>
                </label>
                <div class="space-y-3">
                    <!-- ส่งให้ทุกคน -->
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="recipient_type" value="all" checked class="w-4 h-4 text-blue-600"
                            onchange="updateRecipientOptions()">
                        <div class="ml-3">
                            <p class="font-medium text-gray-900">ส่งให้ทุกคน</p>
                            <p class="text-sm text-gray-500">ส่งการแจ้งเตือนให้ผู้ใช้ทุกคนในระบบ</p>
                        </div>
                    </label>

                    <!-- ส่งตามหน่วยงาน -->
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="recipient_type" value="department" class="w-4 h-4 text-blue-600"
                            onchange="updateRecipientOptions()">
                        <div class="ml-3 flex-1">
                            <p class="font-medium text-gray-900">ส่งตามหน่วยงาน</p>
                            <p class="text-sm text-gray-500 mb-2">เลือกหน่วยงานที่ต้องการส่ง</p>
                            <select name="department_id" id="department_select" disabled
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">เลือกหน่วยงาน</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo e($dept['department_name_th']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </label>

                    <!-- ส่งตามบทบาท -->
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="recipient_type" value="role" class="w-4 h-4 text-blue-600"
                            onchange="updateRecipientOptions()">
                        <div class="ml-3 flex-1">
                            <p class="font-medium text-gray-900">ส่งตามบทบาท</p>
                            <p class="text-sm text-gray-500 mb-2">เลือกบทบาทที่ต้องการส่ง</p>
                            <select name="role" id="role_select" disabled
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">เลือกบทบาท</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                                <option value="manager">ผู้บริหาร</option>
                                <option value="staff">บุคลากร</option>
                            </select>
                        </div>
                    </label>

                    <!-- ส่งให้คนเฉพาะ -->
                    <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="recipient_type" value="specific" class="w-4 h-4 text-blue-600 mt-1"
                            onchange="updateRecipientOptions()">
                        <div class="ml-3 flex-1">
                            <p class="font-medium text-gray-900">เลือกผู้รับเอง</p>
                            <p class="text-sm text-gray-500 mb-2">เลือกผู้ใช้ที่ต้องการส่งการแจ้งเตือน</p>
                            <div id="specific_users_container" class="hidden">
                                <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-2">
                                    <?php foreach ($users as $user): ?>
                                        <label class="flex items-start hover:bg-gray-50 p-2 rounded">
                                            <input type="checkbox" name="specific_users[]"
                                                value="<?php echo $user['user_id']; ?>" class="w-4 h-4 text-blue-600 mt-1">
                                            <div class="ml-3 flex-1">
                                                <p class="text-sm font-medium text-gray-900">
                                                    <?php echo e($user['full_name_th']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?php echo e($user['position'] ?? ''); ?>
                                                    <?php if ($user['department_name_th']): ?>
                                                        • <?php echo e($user['department_name_th']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex items-center justify-between">
            <a href="<?php echo url('modules/notifications/index.php'); ?>" class="text-gray-600 hover:text-gray-900">
                ยกเลิก
            </a>
            <button type="submit"
                class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                <i class="fas fa-paper-plane mr-2"></i>
                ส่งการแจ้งเตือน
            </button>
        </div>
    </form>
</div>

<script>
    function updateRecipientOptions() {
        const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;

        // Department select
        const departmentSelect = document.getElementById('department_select');
        departmentSelect.disabled = recipientType !== 'department';
        if (recipientType === 'department') {
            departmentSelect.required = true;
        } else {
            departmentSelect.required = false;
        }

        // Role select
        const roleSelect = document.getElementById('role_select');
        roleSelect.disabled = recipientType !== 'role';
        if (recipientType === 'role') {
            roleSelect.required = true;
        } else {
            roleSelect.required = false;
        }

        // Specific users
        const specificContainer = document.getElementById('specific_users_container');
        if (recipientType === 'specific') {
            specificContainer.classList.remove('hidden');
        } else {
            specificContainer.classList.add('hidden');
        }
    }

    // Initialize on page load
    updateRecipientOptions();
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>