<?php

/**
 * modules/users/permissions.php
 * จัดการสิทธิ์การเข้าถึงของผู้ใช้
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('users.manage_permissions');

$db = getDB();
$user_id = $_GET['user_id'] ?? 0;

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

// ดึงสิทธิ์ทั้งหมดจาก permission.php
$all_permissions = PERMISSIONS;

// จัดกลุ่มสิทธิ์ตาม module
$grouped_permissions = [];
foreach ($all_permissions as $permission_code => $roles) {
    $parts = explode('.', $permission_code);
    $module = $parts[0];
    $action = $parts[1] ?? '';

    if (!isset($grouped_permissions[$module])) {
        $grouped_permissions[$module] = [];
    }

    $grouped_permissions[$module][] = [
        'code' => $permission_code,
        'action' => $action,
        'roles' => $roles
    ];
}

// Module names ภาษาไทย
$module_names = [
    'dashboard' => 'หน้าหลัก',
    'evaluation' => 'การประเมิน',
    'portfolio' => 'คลังผลงาน',
    'reports' => 'รายงาน',
    'users' => 'จัดการผู้ใช้',
    'config' => 'ตั้งค่าระบบ',
    'notifications' => 'การแจ้งเตือน',
    'approval' => 'การอนุมัติ',
    'system' => 'ระบบ'
];

// Action names ภาษาไทย
$action_names = [
    'view' => 'ดูข้อมูล',
    'view_own' => 'ดูข้อมูลของตัวเอง',
    'view_all' => 'ดูข้อมูลทั้งหมด',
    'view_department' => 'ดูข้อมูลหน่วยงาน',
    'view_organization' => 'ดูข้อมูลองค์กร',
    'create' => 'สร้าง',
    'edit' => 'แก้ไข',
    'edit_own' => 'แก้ไขของตัวเอง',
    'edit_all' => 'แก้ไขทั้งหมด',
    'delete' => 'ลบ',
    'delete_own' => 'ลบของตัวเอง',
    'delete_all' => 'ลบทั้งหมด',
    'submit' => 'ส่งแบบประเมิน',
    'approve' => 'อนุมัติ',
    'reject' => 'ไม่อนุมัติ',
    'return' => 'ส่งกลับ',
    'export' => 'ส่งออกรายงาน',
    'statistics' => 'สถิติ',
    'manage_roles' => 'จัดการบทบาท',
    'manage_permissions' => 'จัดการสิทธิ์',
    'view_activity' => 'ดูกิจกรรม',
    'claim' => 'ใช้ผลงาน',
    'share' => 'แชร์ผลงาน',
    'review' => 'พิจารณา',
    'history' => 'ประวัติ',
    'view_pending' => 'ดูรอพิจารณา',
    'broadcast' => 'ส่งแจ้งทั้งหมด',
    'manage_settings' => 'จัดการการตั้งค่า',
    'logs' => 'ดู Log',
    'backup' => 'สำรองข้อมูล',
    'restore' => 'กู้คืนข้อมูล',
    'maintenance' => 'บำรุงรักษา',
    'personnel_types' => 'ประเภทบุคลากร',
    'evaluation_aspects' => 'มิติการประเมิน',
    'evaluation_topics' => 'หัวข้อการประเมิน',
    'evaluation_periods' => 'รอบการประเมิน'
];

// ดึงสิทธิ์ปัจจุบันของ user จาก role
$user_permissions = [];
foreach ($all_permissions as $permission_code => $roles) {
    if (in_array($user['role'], $roles)) {
        $user_permissions[] = $permission_code;
    }
}

$page_title = 'จัดการสิทธิ์ผู้ใช้';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
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
                <h1 class="text-2xl font-bold text-gray-900">จัดการสิทธิ์ผู้ใช้</h1>
                <p class="mt-1 text-sm text-gray-500">
                    ดูและจัดการสิทธิ์การเข้าถึงของ <?php echo e($user['full_name_th']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- User Info Card -->
    <div class="card mb-6">
        <div class="card-body">
            <div class="flex items-center">
                <div
                    class="h-16 w-16 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-2xl font-semibold">
                    <?php echo mb_substr($user['full_name_th'], 0, 2); ?>
                </div>
                <div class="ml-4 flex-1">
                    <h2 class="text-xl font-semibold text-gray-900"><?php echo e($user['full_name_th']); ?></h2>
                    <div class="mt-1 flex items-center space-x-4 text-sm text-gray-500">
                        <span><?php echo e($user['username']); ?></span>
                        <span>•</span>
                        <span><?php echo e($user['email']); ?></span>
                        <span>•</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            <?php
                            if ($user['role'] === 'admin') echo 'bg-purple-100 text-purple-800';
                            elseif ($user['role'] === 'manager') echo 'bg-blue-100 text-blue-800';
                            else echo 'bg-gray-100 text-gray-800';
                            ?>">
                            <?php echo e($role_names[$user['role']] ?? $user['role']); ?>
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <a href="<?php echo url('modules/users/edit.php?id=' . $user_id); ?>" class="btn-secondary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        แก้ไขข้อมูล
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
        <div class="flex">
            <svg class="w-5 h-5 text-blue-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd" />
            </svg>
            <div class="text-sm text-blue-700">
                <p class="font-medium mb-1">หมายเหตุ:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>สิทธิ์การเข้าถึงถูกกำหนดโดยบทบาท (Role) ของผู้ใช้</li>
                    <li>ผู้ดูแลระบบ (Admin) มีสิทธิ์เข้าถึงทุกฟีเจอร์โดยอัตโนมัติ</li>
                    <li>หากต้องการเปลี่ยนสิทธิ์ สามารถเปลี่ยนบทบาทของผู้ใช้ได้</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Permissions by Module -->
    <?php foreach ($grouped_permissions as $module => $permissions): ?>
        <div class="card mb-6">
            <div class="card-header">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                        <?php echo $module_names[$module] ?? ucfirst($module); ?>
                    </h2>
                    <span class="text-sm text-gray-500">
                        <?php
                        $module_perms = array_filter($permissions, function ($p) use ($user_permissions) {
                            return in_array($p['code'], $user_permissions);
                        });
                        echo count($module_perms) . ' / ' . count($permissions);
                        ?> สิทธิ์
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    การดำเนินการ
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    รหัสสิทธิ์
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    บทบาทที่มีสิทธิ์
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    สถานะ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($permissions as $perm): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo $action_names[$perm['action']] ?? ucfirst($perm['action']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        <?php echo e($perm['code']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center flex-wrap gap-2">
                                            <?php foreach ($perm['roles'] as $role_code): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                                if ($role_code === 'admin') echo 'bg-purple-100 text-purple-800';
                                                elseif ($role_code === 'manager') echo 'bg-blue-100 text-blue-800';
                                                else echo 'bg-gray-100 text-gray-800';
                                        ?>">
                                                    <?php echo $role_names[$role_code] ?? ucfirst($role_code); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php if (in_array($perm['code'], $user_permissions)): ?>
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                มีสิทธิ์
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                ไม่มีสิทธิ์
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Summary Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-semibold">สรุปสิทธิ์</h2>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600">
                        <?php echo count($user_permissions); ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-2">จำนวนสิทธิ์ทั้งหมด</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600">
                        <?php
                        $enabled = array_filter($user_permissions, function ($p) use ($all_permissions) {
                            return isset($all_permissions[$p]);
                        });
                        echo count($enabled);
                        ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-2">สิทธิ์ที่ใช้งานได้</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600">
                        <?php echo count(array_unique(array_map(function ($p) {
                            return explode('.', $p)[0];
                        }, $user_permissions))); ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-2">จำนวน Module</div>
                </div>
            </div>

            <!-- Role Based Permissions Info -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-sm font-medium text-gray-900 mb-3">ข้อมูลบทบาท</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">บทบาทปัจจุบัน:</span>
                            <span class="ml-2 font-medium text-gray-900">
                                <?php echo $role_names[$user['role']] ?? $user['role']; ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600">รหัสบทบาท:</span>
                            <span class="ml-2 font-mono text-gray-900"><?php echo $user['role']; ?></span>
                        </div>
                        <div>
                            <span class="text-gray-600">สถานะบัญชี:</span>
                            <span class="ml-2">
                                <?php if ($user['is_active']): ?>
                                    <span class="text-green-600 font-medium">ใช้งานอยู่</span>
                                <?php else: ?>
                                    <span class="text-gray-600">ไม่ใช้งาน</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600">สร้างเมื่อ:</span>
                            <span class="ml-2 text-gray-900">
                                <?php echo thai_date($user['created_at'], 'short'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>