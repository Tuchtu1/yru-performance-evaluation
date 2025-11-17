<?php

/**
 * modules/users/add.php
 * เพิ่มผู้ใช้งานใหม่
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('users.create');

$db = getDB();

// Handle form submission
if (is_post() && verify_csrf(input('csrf_token'))) {
    try {
        $username = clean_string(input('username'));
        $email = clean_string(input('email'));
        $password = input('password');
        $confirm_password = input('confirm_password');
        $full_name_th = clean_string(input('full_name_th'));
        $full_name_en = clean_string(input('full_name_en')) ?: null;
        $role = input('role');
        $personnel_type = input('personnel_type');
        $personnel_type_id = input('personnel_type_id') ?: null;
        $department_id = input('department_id') ?: null;
        $position = clean_string(input('position')) ?: null;
        $is_active = input('is_active', 1);

        // Validation
        $errors = [];

        if (empty($username) || strlen($username) < 4) {
            $errors[] = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 4 ตัวอักษร';
        }

        // ตรวจสอบ username ซ้ำ
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
        }

        if (!is_valid_email($email)) {
            $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        }

        // ตรวจสอบ email ซ้ำ
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'อีเมลนี้ถูกใช้แล้ว';
        }

        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'รหัสผ่านไม่ตรงกัน';
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

        if (empty($errors)) {
            $db->beginTransaction();

            // เข้ารหัสรหัสผ่าน
            $password_hash = hash_password($password);

            // Insert user
            $stmt = $db->prepare("
                INSERT INTO users 
                (username, email, password, full_name_th, full_name_en, role, personnel_type,
                 personnel_type_id, department_id, position, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $username,
                $email,
                $password_hash,
                $full_name_th,
                $full_name_en,
                $role,
                $personnel_type,
                $personnel_type_id,
                $department_id,
                $position,
                $is_active
            ]);

            $new_user_id = $db->lastInsertId();

            // Log activity
            log_activity('create', 'users', $new_user_id, json_encode([
                'username' => $username,
                'email' => $email,
                'role' => $role
            ]));

            $db->commit();

            flash_success('เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
            redirect('modules/users/list.php');
        } else {
            foreach ($errors as $error) {
                flash_error($error);
            }
        }
    } catch (Exception $e) {
        $db->rollBack();
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        log_error('Add User Error', ['error' => $e->getMessage()]);
    }
}

// ดึงข้อมูลสำหรับ form
$roles = [
    ['role_code' => 'admin', 'role_name' => 'ผู้ดูแลระบบ', 'description' => 'มีสิทธิ์เข้าถึงและจัดการระบบทั้งหมด'],
    ['role_code' => 'manager', 'role_name' => 'ผู้บริหาร', 'description' => 'พิจารณาและอนุมัติแบบประเมิน'],
    ['role_code' => 'staff', 'role_name' => 'บุคลากร', 'description' => 'สร้างและส่งแบบประเมิน']
];

$stmt = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name_th");
$departments = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM personnel_types WHERE is_active = 1 ORDER BY type_name_th");
$personnel_types = $stmt->fetchAll();

// Personnel type options (ENUM)
$personnel_type_options = [
    'academic' => 'สายวิชาการ',
    'support' => 'สายสนับสนุน',
    'lecturer' => 'อาจารย์'
];

$page_title = 'เพิ่มผู้ใช้งาน';
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
                <h1 class="text-2xl font-bold text-gray-900">เพิ่มผู้ใช้งานใหม่</h1>
                <p class="mt-1 text-sm text-gray-500">
                    กรอกข้อมูลผู้ใช้งานและกำหนดบทบาท
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
                    <!-- Username -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ชื่อผู้ใช้ <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="username" required class="form-input w-full" placeholder="username"
                            value="<?php echo old('username'); ?>">
                        <p class="mt-1 text-sm text-gray-500">ใช้สำหรับเข้าสู่ระบบ (ภาษาอังกฤษและตัวเลข)</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            อีเมล <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" required class="form-input w-full"
                            placeholder="email@yru.ac.th" value="<?php echo old('email'); ?>">
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            รหัสผ่าน <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required class="form-input w-full"
                            placeholder="อย่างน้อย 6 ตัวอักษร">
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ยืนยันรหัสผ่าน <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="confirm_password" required class="form-input w-full"
                            placeholder="พิมพ์รหัสผ่านอีกครั้ง">
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
                            placeholder="ชื่อ-นามสกุล (ภาษาไทย)" value="<?php echo old('full_name_th'); ?>">
                    </div>

                    <!-- Full Name English -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ชื่อ-นามสกุล (อังกฤษ)
                        </label>
                        <input type="text" name="full_name_en" class="form-input w-full"
                            placeholder="Full Name (English)" value="<?php echo old('full_name_en'); ?>">
                    </div>

                    <!-- Position -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ตำแหน่ง
                        </label>
                        <input type="text" name="position" class="form-input w-full"
                            placeholder="เช่น อาจารย์, เจ้าหน้าที่" value="<?php echo old('position'); ?>">
                    </div>

                    <!-- Personnel Type (ENUM) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ประเภทบุคลากร <span class="text-red-500">*</span>
                        </label>
                        <select name="personnel_type" required class="form-select w-full">
                            <option value="">-- เลือกประเภท --</option>
                            <?php foreach ($personnel_type_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>"
                                    <?php echo old('personnel_type') === $value ? 'selected' : ''; ?>>
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
                                    <?php echo old('department_id') == $dept['department_id'] ? 'selected' : ''; ?>>
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
                                    <?php echo old('personnel_type_id') == $pt['personnel_type_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($pt['type_name_th']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role and Status -->
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
                            <option value="">-- เลือกบทบาท --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['role_code']; ?>"
                                    <?php echo old('role') === $role['role_code'] ? 'selected' : ''; ?>>
                                    <?php echo e($role['role_name']); ?>
                                    <?php if ($role['description']): ?>
                                        - <?php echo e($role['description']); ?>
                                    <?php endif; ?>
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
                            <option value="1" <?php echo old('is_active', 1) == 1 ? 'selected' : ''; ?>>
                                ใช้งานอยู่
                            </option>
                            <option value="0" <?php echo old('is_active') === '0' ? 'selected' : ''; ?>>
                                ไม่ใช้งาน
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Alert -->
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <svg class="w-5 h-5 text-blue-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium mb-1">หมายเหตุ:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>บทบาทจะกำหนดสิทธิ์การเข้าถึงฟีเจอร์ต่างๆ ในระบบ</li>
                                <li>คุณสามารถปรับเปลี่ยนข้อมูลได้ในภายหลัง</li>
                                <li>ระบบจะส่งอีเมลแจ้งข้อมูลการเข้าสู่ระบบไปยังผู้ใช้</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3">
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
    </form>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>