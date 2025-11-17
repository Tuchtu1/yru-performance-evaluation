<?php

/**
 * modules/users/edit.php
 * แก้ไขข้อมูลผู้ใช้งาน
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('users.edit');

$db = getDB();
$user_id = $_GET['id'] ?? 0;

// ดึงข้อมูลผู้ใช้
$stmt = $db->prepare("
    SELECT u.*
    FROM users u
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    flash_error('ไม่พบข้อมูลผู้ใช้');
    redirect('modules/users/list.php');
}

// ตรวจสอบสิทธิ์ในการแก้ไข
if (!isAdmin() && $current_user['user_id'] != $user_id) {
    flash_error('คุณไม่มีสิทธิ์แก้ไขผู้ใช้รายนี้');
    redirect('modules/users/list.php');
}

// Handle form submission
if (is_post() && verify_csrf(input('csrf_token'))) {
    try {
        $email = clean_string(input('email'));
        $full_name_th = clean_string(input('full_name_th'));
        $full_name_en = clean_string(input('full_name_en')) ?: null;
        $role = input('role');
        $personnel_type = input('personnel_type');
        $personnel_type_id = input('personnel_type_id') ?: null;
        $department_id = input('department_id') ?: null;
        $position = clean_string(input('position')) ?: null;
        $is_active = input('is_active', 1);
        $change_password = input('change_password');
        $new_password = input('new_password');
        $confirm_password = input('confirm_password');

        // Validation
        $errors = [];

        if (!is_valid_email($email)) {
            $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        }

        // ตรวจสอบ email ซ้ำ (ยกเว้นตัวเอง)
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'อีเมลนี้ถูกใช้แล้ว';
        }

        if (empty($full_name_th)) {
            $errors[] = 'กรุณากรอกชื่อ-นามสกุล (ไทย)';
        }

        if (empty($role)) {
            $errors[] = 'กรุณาเลือกบทบาท';
        }

        if (empty($personnel_type)) {
            $errors[] = 'กรุณาเลือกประเภทบุคลากร';
        }

        // ตรวจสอบรหัสผ่านถ้าต้องการเปลี่ยน
        if ($change_password) {
            if (empty($new_password) || strlen($new_password) < 6) {
                $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
            }

            if ($new_password !== $confirm_password) {
                $errors[] = 'รหัสผ่านไม่ตรงกัน';
            }
        }

        if (empty($errors)) {
            $db->beginTransaction();

            // อัปเดตข้อมูลพื้นฐาน
            $stmt = $db->prepare("
                UPDATE users SET
                    email = ?,
                    full_name_th = ?,
                    full_name_en = ?,
                    role = ?,
                    personnel_type = ?,
                    personnel_type_id = ?,
                    department_id = ?,
                    position = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");

            $stmt->execute([
                $email,
                $full_name_th,
                $full_name_en,
                $role,
                $personnel_type,
                $personnel_type_id,
                $department_id,
                $position,
                $is_active,
                $user_id
            ]);

            // อัปเดตรหัสผ่านถ้ามีการเปลี่ยน
            if ($change_password && !empty($new_password)) {
                $password_hash = hash_password($new_password);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$password_hash, $user_id]);
            }

            // Log activity
            log_activity('update', 'users', $user_id, [
                'email' => $email,
                'role' => $role,
                'is_active' => $is_active
            ]);

            $db->commit();

            flash_success('อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว');

            // ถ้าแก้ไขตัวเอง ให้อัปเดต session
            if ($user_id == $current_user['user_id']) {
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['full_name_th'] = $full_name_th;
                $_SESSION['user']['role'] = $role;
                redirect('modules/users/profile.php');
            } else {
                redirect('modules/users/list.php');
            }
        } else {
            foreach ($errors as $error) {
                flash_error($error);
            }
        }
    } catch (Exception $e) {
        $db->rollBack();
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        log_error('Edit User Error', ['error' => $e->getMessage(), 'user_id' => $user_id]);
    }
}

// ดึงข้อมูลสำหรับ form
$roles = [
    ['role_code' => 'admin', 'role_name' => 'ผู้ดูแลระบบ'],
    ['role_code' => 'manager', 'role_name' => 'ผู้บริหาร'],
    ['role_code' => 'staff', 'role_name' => 'บุคลากร']
];

$stmt = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name_th");
$departments = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM personnel_types WHERE is_active = 1 ORDER BY type_name_th");
$personnel_types = $stmt->fetchAll();

// Personnel type options
$personnel_type_options = [
    'academic' => 'สายวิชาการ',
    'support' => 'สายสนับสนุน',
    'lecturer' => 'อาจารย์'
];

$page_title = 'แก้ไขผู้ใช้งาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center space-x-3">
            <a href="<?php echo url('modules/users/list.php'); ?>" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">แก้ไขผู้ใช้งาน</h1>
                <p class="mt-1 text-sm text-gray-500">
                    อัปเดตข้อมูลผู้ใช้: <?php echo e($user['username']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Form -->
    <form method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>

        <!-- Account Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-lg font-semibold">ข้อมูลบัญชีผู้ใช้</h2>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Username (Read Only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ชื่อผู้ใช้
                        </label>
                        <input type="text" value="<?php echo e($user['username']); ?>"
                            class="form-input w-full bg-gray-100" readonly>
                        <p class="mt-1 text-sm text-gray-500">ไม่สามารถเปลี่ยนแปลงได้</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            อีเมล <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" required class="form-input w-full"
                            value="<?php echo e($user['email']); ?>">
                    </div>

                    <!-- User ID -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            รหัสผู้ใช้
                        </label>
                        <input type="text" value="<?php echo $user['user_id']; ?>" class="form-input w-full bg-gray-100"
                            readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-lg font-semibold">ข้อมูลส่วนตัว</h2>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Full Name Thai -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ชื่อ-นามสกุล (ไทย) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="full_name_th" required class="form-input w-full"
                            value="<?php echo e($user['full_name_th']); ?>">
                    </div>

                    <!-- Full Name English -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ชื่อ-นามสกุล (อังกฤษ)
                        </label>
                        <input type="text" name="full_name_en" class="form-input w-full"
                            value="<?php echo e($user['full_name_en']); ?>">
                    </div>

                    <!-- Position -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ตำแหน่ง
                        </label>
                        <input type="text" name="position" class="form-input w-full"
                            value="<?php echo e($user['position']); ?>">
                    </div>

                    <!-- Personnel Type (ENUM) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ประเภทบุคลากร <span class="text-red-500">*</span>
                        </label>
                        <select name="personnel_type" required class="form-select w-full">
                            <?php foreach ($personnel_type_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>"
                                    <?php echo $user['personnel_type'] === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Department -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            หน่วยงาน
                        </label>
                        <select name="department_id" class="form-select w-full">
                            <option value="">-- เลือกหน่วยงาน --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"
                                    <?php echo $user['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($dept['department_name_th']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Personnel Type ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ประเภทบุคลากร (รายละเอียด)
                        </label>
                        <select name="personnel_type_id" class="form-select w-full">
                            <option value="">-- เลือกประเภท --</option>
                            <?php foreach ($personnel_types as $pt): ?>
                                <option value="<?php echo $pt['personnel_type_id']; ?>"
                                    <?php echo $user['personnel_type_id'] == $pt['personnel_type_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($pt['type_name_th']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role and Status -->
        <?php if (isAdmin()): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="text-lg font-semibold">บทบาทและสถานะ</h2>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Role -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                บทบาท <span class="text-red-500">*</span>
                            </label>
                            <select name="role" required class="form-select w-full">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_code']; ?>"
                                        <?php echo $user['role'] === $role['role_code'] ? 'selected' : ''; ?>>
                                        <?php echo e($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                สถานะ <span class="text-red-500">*</span>
                            </label>
                            <select name="is_active" required class="form-select w-full">
                                <option value="1" <?php echo $user['is_active'] == 1 ? 'selected' : ''; ?>>
                                    ใช้งานอยู่
                                </option>
                                <option value="0" <?php echo $user['is_active'] == 0 ? 'selected' : ''; ?>>
                                    ไม่ใช้งาน
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
            <input type="hidden" name="is_active" value="<?php echo $user['is_active']; ?>">
            <input type="hidden" name="personnel_type" value="<?php echo $user['personnel_type']; ?>">
        <?php endif; ?>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-lg font-semibold">เปลี่ยนรหัสผ่าน</h2>
            </div>
            <div class="card-body space-y-4">
                <!-- Change Password Checkbox -->
                <div class="flex items-center">
                    <input type="checkbox" name="change_password" id="changePassword"
                        class="form-checkbox h-4 w-4 text-blue-600" onchange="togglePasswordFields()">
                    <label for="changePassword" class="ml-2 block text-sm text-gray-900">
                        ต้องการเปลี่ยนรหัสผ่าน
                    </label>
                </div>

                <div id="passwordFields" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
                    <!-- New Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            รหัสผ่านใหม่
                        </label>
                        <input type="password" name="new_password" class="form-input w-full"
                            placeholder="อย่างน้อย 6 ตัวอักษร">
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ยืนยันรหัสผ่านใหม่
                        </label>
                        <input type="password" name="confirm_password" class="form-input w-full"
                            placeholder="พิมพ์รหัสผ่านอีกครั้ง">
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-lg font-semibold">ข้อมูลเพิ่มเติม</h2>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">สร้างเมื่อ:</span>
                        <span class="ml-2 text-gray-900">
                            <?php echo thai_date($user['created_at'], 'datetime'); ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">อัปเดตล่าสุด:</span>
                        <span class="ml-2 text-gray-900">
                            <?php echo thai_date($user['updated_at'], 'datetime'); ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">ล็อกอินล่าสุด:</span>
                        <span class="ml-2 text-gray-900">
                            <?php echo $user['last_login'] ? thai_date($user['last_login'], 'datetime') : '-'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between">
            <div>
                <?php if (can('users.view_activity')): ?>
                    <a href="<?php echo url('modules/users/activity-log.php?user_id=' . $user_id); ?>"
                        class="btn-secondary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ดูประวัติการใช้งาน
                    </a>
                <?php endif; ?>
            </div>
            <div class="flex space-x-3">
                <a href="<?php echo url('modules/users/list.php'); ?>" class="btn-secondary">
                    ยกเลิก
                </a>
                <button type="submit" class="btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    บันทึก
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function togglePasswordFields() {
        const checkbox = document.getElementById('changePassword');
        const fields = document.getElementById('passwordFields');

        if (checkbox.checked) {
            fields.style.display = 'grid';
        } else {
            fields.style.display = 'none';
            // Clear password fields
            document.querySelector('input[name="new_password"]').value = '';
            document.querySelector('input[name="confirm_password"]').value = '';
        }
    }
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>