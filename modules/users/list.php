<?php

/**
 * modules/users/list.php
 * รายการผู้ใช้งานในระบบ
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('users.view');

$db = getDB();

// รับค่า filter และ search
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;

// สร้าง WHERE clause
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(u.username LIKE ? OR u.full_name_th LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($role_filter) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($department_filter) {
    $where[] = "u.department_id = ?";
    $params[] = $department_filter;
}

if ($status_filter) {
    if ($status_filter === 'active') {
        $where[] = "u.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where[] = "u.is_active = 0";
    }
}

$where_clause = implode(' AND ', $where);

// นับจำนวนทั้งหมด
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM users u
    WHERE $where_clause
");
$stmt->execute($params);
$total = $stmt->fetchColumn();

// คำนวณ pagination
$pagination = paginate($total, $page, $per_page);

// ดึงข้อมูลผู้ใช้
$stmt = $db->prepare("
    SELECT 
        u.*,
        d.department_name_th as department_name,
        pt.type_name_th as type_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
    WHERE $where_clause
    ORDER BY u.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// ดึงข้อมูลสำหรับ filters - สร้าง roles array
$roles = [
    ['role_code' => 'admin', 'role_name' => 'ผู้ดูแลระบบ'],
    ['role_code' => 'manager', 'role_name' => 'ผู้บริหาร'],
    ['role_code' => 'staff', 'role_name' => 'บุคลากร']
];

$stmt = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name_th");
$departments = $stmt->fetchAll();

// สถิติ
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_users,
        COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_last_30_days
    FROM users
");
$stats = $stmt->fetch();

$page_title = 'จัดการผู้ใช้งาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">จัดการผู้ใช้งาน</h1>
                <p class="mt-1 text-sm text-gray-500">
                    จัดการบัญชีผู้ใช้และกำหนดสิทธิ์การเข้าถึง
                </p>
            </div>
            <?php if (can('users.create')): ?>
                <a href="<?php echo url('modules/users/add.php'); ?>" class="btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    เพิ่มผู้ใช้งาน
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ผู้ใช้ทั้งหมด</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($stats['total_users']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ใช้งานอยู่</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($stats['active_users']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ไม่ใช้งาน</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($stats['inactive_users']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ล็อกอิน 30 วัน</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($stats['active_last_30_days']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <input type="text" name="search" value="<?php echo e($search); ?>"
                            placeholder="ค้นหา ชื่อผู้ใช้, ชื่อ-นามสกุล, อีเมล..." class="form-input w-full">
                    </div>

                    <!-- Role Filter -->
                    <select name="role" class="form-select">
                        <option value="">บทบาททั้งหมด</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_code']; ?>"
                                <?php echo $role_filter === $role['role_code'] ? 'selected' : ''; ?>>
                                <?php echo e($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Department Filter -->
                    <select name="department" class="form-select">
                        <option value="">หน่วยงานทั้งหมด</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>"
                                <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo e($dept['department_name_th']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Status Filter -->
                    <select name="status" class="form-select">
                        <option value="">สถานะทั้งหมด</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>
                            ใช้งานอยู่
                        </option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>
                            ไม่ใช้งาน
                        </option>
                    </select>
                </div>

                <div class="flex justify-between">
                    <a href="<?php echo url('modules/users/list.php'); ?>" class="btn-secondary">
                        ล้างตัวกรอง
                    </a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        ค้นหา
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                ผู้ใช้งาน
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                หน่วยงาน
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                บทบาท
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                สถานะ
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                ล็อกอินล่าสุด
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                จัดการ
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="mt-2">ไม่พบข้อมูลผู้ใช้งาน</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div
                                                    class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-semibold">
                                                    <?php echo mb_substr($user['full_name_th'], 0, 2); ?>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo e($user['full_name_th']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo e($user['username']); ?> | <?php echo e($user['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($user['department_name'] ?: '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php
                                    if ($user['role'] === 'admin') echo 'bg-purple-100 text-purple-800';
                                    elseif ($user['role'] === 'manager') echo 'bg-blue-100 text-blue-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                            <?php echo e($role_names[$user['role']] ?? $user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo $user['is_active'] ? 'ใช้งานอยู่' : 'ไม่ใช้งาน'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                        <?php echo $user['last_login'] ? time_ago($user['last_login']) : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <div class="flex items-center justify-center space-x-2">
                                            <?php if (can('users.view')): ?>
                                                <a href="<?php echo url('modules/users/activity-log.php?user_id=' . $user['user_id']); ?>"
                                                    class="text-blue-600 hover:text-blue-900" title="ประวัติการใช้งาน">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (can('users.edit')): ?>
                                                <a href="<?php echo url('modules/users/edit.php?id=' . $user['user_id']); ?>"
                                                    class="text-yellow-600 hover:text-yellow-900" title="แก้ไข">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (can('users.manage_permissions')): ?>
                                                <a href="<?php echo url('modules/users/permissions.php?user_id=' . $user['user_id']); ?>"
                                                    class="text-purple-600 hover:text-purple-900" title="จัดการสิทธิ์">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (can('users.delete') && $user['user_id'] != $current_user['user_id']): ?>
                                                <a href="<?php echo url('modules/users/delete.php?id=' . $user['user_id']); ?>"
                                                    class="text-red-600 hover:text-red-900" title="ลบ"
                                                    onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้?')">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['last_page'] > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            แสดง <?php echo number_format($pagination['from']); ?>
                            ถึง <?php echo number_format($pagination['to']); ?>
                            จาก <?php echo number_format($pagination['total']); ?> รายการ
                        </div>

                        <div class="flex space-x-2">
                            <?php if ($pagination['current_page'] > 1): ?>
                                <a href="?page=<?php echo ($pagination['current_page'] - 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                                    ก่อนหน้า
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['last_page'], $pagination['current_page'] + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>"
                                    class="px-3 py-2 text-sm border rounded-md <?php echo $i === $pagination['current_page'] ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                                <a href="?page=<?php echo ($pagination['current_page'] + 1); ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>"
                                    class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                                    ถัดไป
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>