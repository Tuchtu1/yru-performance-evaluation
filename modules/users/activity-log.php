<?php

/**
 * modules/users/activity-log.php
 * แสดงประวัติการใช้งานของผู้ใช้
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('users.view_activity');

$db = getDB();
$user_id = $_GET['user_id'] ?? 0;

// ดึงข้อมูลผู้ใช้
$stmt = $db->prepare("
    SELECT u.*, d.department_name_th as department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    flash_error('ไม่พบข้อมูลผู้ใช้');
    redirect('modules/users/list.php');
}

// ตรวจสอบสิทธิ์
if (!isAdmin() && $current_user['user_id'] != $user_id) {
    flash_error('คุณไม่มีสิทธิ์ดูประวัติผู้ใช้รายนี้');
    redirect('modules/users/list.php');
}

// Filters
$action_type = $_GET['action_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;

// สร้าง WHERE clause
$where = ["user_id = ?"];
$params = [$user_id];

if ($action_type) {
    $where[] = "action = ?";
    $params[] = $action_type;
}

if ($date_from) {
    $where[] = "created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where[] = "created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$where_clause = implode(' AND ', $where);

// นับจำนวนทั้งหมด
$stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE $where_clause");
$stmt->execute($params);
$total = $stmt->fetchColumn();

// คำนวณ pagination
$pagination = paginate($total, $page, $per_page);

// ดึงข้อมูล activity logs
$stmt = $db->prepare("
    SELECT *
    FROM activity_logs
    WHERE $where_clause
    ORDER BY created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$activities = $stmt->fetchAll();

// ดึงประเภทกิจกรรมที่มี
$stmt = $db->prepare("
    SELECT DISTINCT action 
    FROM activity_logs 
    WHERE user_id = ?
    ORDER BY action
");
$stmt->execute([$user_id]);
$action_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// สถิติกิจกรรม
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_activities,
        COUNT(DISTINCT DATE(created_at)) as active_days,
        COUNT(CASE WHEN action = 'login' THEN 1 END) as login_count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as last_30_days
    FROM activity_logs
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Action type names ภาษาไทย
$action_names = [
    'login' => 'เข้าสู่ระบบ',
    'logout' => 'ออกจากระบบ',
    'create' => 'สร้างข้อมูล',
    'update' => 'แก้ไขข้อมูล',
    'delete' => 'ลบข้อมูล',
    'view' => 'ดูข้อมูล',
    'export' => 'ส่งออกข้อมูล',
    'upload' => 'อัปโหลดไฟล์',
    'download' => 'ดาวน์โหลดไฟล์',
    'submit' => 'ส่งข้อมูล',
    'approve' => 'อนุมัติ',
    'reject' => 'ไม่อนุมัติ',
    'toggle_status' => 'เปลี่ยนสถานะ',
    'status_change' => 'เปลี่ยนสถานะ'
];

// Action icons
$action_icons = [
    'login' => '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>',
    'logout' => '<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>',
    'create' => '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>',
    'update' => '<svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>',
    'delete' => '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>',
    'submit' => '<svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    'approve' => '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
    'view' => '<svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>',
    'default' => '<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
];

// Table names ภาษาไทย
$table_names = [
    'users' => 'ผู้ใช้งาน',
    'evaluations' => 'แบบประเมิน',
    'portfolios' => 'ผลงาน',
    'evaluation_aspects' => 'ด้านการประเมิน',
    'evaluation_topics' => 'หัวข้อประเมิน',
    'personnel_types' => 'ประเภทบุคลากร',
    'departments' => 'หน่วยงาน'
];

$page_title = 'ประวัติการใช้งาน';
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
                <h1 class="text-2xl font-bold text-gray-900">ประวัติการใช้งาน</h1>
                <p class="mt-1 text-sm text-gray-500">
                    กิจกรรมและการเข้าใช้งานของ <?php echo e($user['full_name_th']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- User Info Card -->
    <div class="card mb-6">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div
                        class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-lg font-semibold">
                        <?php echo mb_substr($user['full_name_th'], 0, 2); ?>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-lg font-semibold text-gray-900"><?php echo e($user['full_name_th']); ?></h2>
                        <div class="flex items-center space-x-3 text-sm text-gray-500">
                            <span><?php echo e($user['username']); ?></span>
                            <span>•</span>
                            <span><?php echo e($role_names[$user['role']] ?? $user['role']); ?></span>
                            <?php if ($user['department_name']): ?>
                                <span>•</span>
                                <span><?php echo e($user['department_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-right text-sm">
                    <div class="text-gray-500">ล็อกอินล่าสุด</div>
                    <div class="text-gray-900 font-medium">
                        <?php echo $user['last_login'] ? thai_date($user['last_login'], 'datetime') : '-'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-2xl font-bold text-blue-600">
                    <?php echo number_format($stats['total_activities']); ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">กิจกรรมทั้งหมด</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-2xl font-bold text-green-600">
                    <?php echo number_format($stats['login_count']); ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">จำนวนครั้งล็อกอิน</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-2xl font-bold text-purple-600">
                    <?php echo number_format($stats['active_days']); ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">วันที่ใช้งาน</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-2xl font-bold text-orange-600">
                    <?php echo number_format($stats['last_7_days']); ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">7 วันที่แล้ว</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body text-center">
                <div class="text-2xl font-bold text-indigo-600">
                    <?php echo number_format($stats['last_30_days']); ?>
                </div>
                <div class="text-sm text-gray-600 mt-1">30 วันที่แล้ว</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                <!-- Action Type -->
                <select name="action_type" class="form-select">
                    <option value="">ประเภทกิจกรรมทั้งหมด</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $action_type === $type ? 'selected' : ''; ?>>
                            <?php echo $action_names[$type] ?? ucfirst($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Date From -->
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-input"
                    placeholder="วันที่เริ่มต้น">

                <!-- Date To -->
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-input"
                    placeholder="วันที่สิ้นสุด">

                <!-- Submit -->
                <button type="submit" class="btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    กรอง
                </button>
            </form>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="card">
        <div class="card-header">
            <h2 class="text-lg font-semibold">ประวัติกิจกรรม</h2>
        </div>
        <div class="card-body">
            <?php if (empty($activities)): ?>
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">ไม่พบกิจกรรม</h3>
                    <p class="mt-1 text-sm text-gray-500">ไม่มีประวัติกิจกรรมในช่วงเวลาที่เลือก</p>
                </div>
            <?php else: ?>
                <div class="flow-root">
                    <ul class="-mb-8">
                        <?php foreach ($activities as $index => $activity): ?>
                            <li>
                                <div class="relative pb-8">
                                    <?php if ($index < count($activities) - 1): ?>
                                        <span class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200"
                                            aria-hidden="true"></span>
                                    <?php endif; ?>

                                    <div class="relative flex items-start space-x-3">
                                        <div>
                                            <div class="relative px-1">
                                                <div
                                                    class="h-10 w-10 bg-gray-100 rounded-full ring-8 ring-white flex items-center justify-center">
                                                    <?php echo $action_icons[$activity['action']] ?? $action_icons['default']; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div>
                                                <div class="text-sm">
                                                    <span class="font-medium text-gray-900">
                                                        <?php echo $action_names[$activity['action']] ?? ucfirst($activity['action']); ?>
                                                    </span>
                                                    <?php if ($activity['table_name']): ?>
                                                        <span class="text-gray-600">
                                                            •
                                                            <?php echo $table_names[$activity['table_name']] ?? $activity['table_name']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($activity['record_id']): ?>
                                                        <span class="text-gray-500">
                                                            #<?php echo $activity['record_id']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mt-0.5 text-sm text-gray-500">
                                                    <?php echo thai_date($activity['created_at'], 'datetime'); ?>
                                                    <span class="text-gray-400">•</span>
                                                    <?php echo time_ago($activity['created_at']); ?>
                                                </p>
                                            </div>
                                            <?php if ($activity['new_values'] || $activity['old_values']): ?>
                                                <div class="mt-2 text-xs">
                                                    <?php if ($activity['old_values']): ?>
                                                        <div class="text-gray-500">
                                                            <span class="font-medium">เก่า:</span>
                                                            <?php echo e(substr($activity['old_values'], 0, 100)); ?>
                                                            <?php if (strlen($activity['old_values']) > 100) echo '...'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($activity['new_values']): ?>
                                                        <div class="text-gray-700 mt-1">
                                                            <span class="font-medium">ใหม่:</span>
                                                            <?php echo e(substr($activity['new_values'], 0, 100)); ?>
                                                            <?php if (strlen($activity['new_values']) > 100) echo '...'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-2 text-xs text-gray-500 space-x-4">
                                                <?php if ($activity['ip_address']): ?>
                                                    <span>
                                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                                        </svg>
                                                        IP: <?php echo e($activity['ip_address']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($activity['user_agent']): ?>
                                                    <span title="<?php echo e($activity['user_agent']); ?>">
                                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                        </svg>
                                                        <?php
                                                        // แสดงเฉพาะส่วนสำคัญของ User Agent
                                                        if (strpos($activity['user_agent'], 'Chrome') !== false) {
                                                            echo 'Chrome';
                                                        } elseif (strpos($activity['user_agent'], 'Firefox') !== false) {
                                                            echo 'Firefox';
                                                        } elseif (strpos($activity['user_agent'], 'Safari') !== false) {
                                                            echo 'Safari';
                                                        } elseif (strpos($activity['user_agent'], 'Edge') !== false) {
                                                            echo 'Edge';
                                                        } else {
                                                            echo 'Browser';
                                                        }
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['last_page'] > 1): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                แสดง <?php echo number_format($pagination['from']); ?>
                                ถึง <?php echo number_format($pagination['to']); ?>
                                จาก <?php echo number_format($pagination['total']); ?> รายการ
                            </div>

                            <div class="flex space-x-2">
                                <?php if ($pagination['current_page'] > 1): ?>
                                    <a href="?user_id=<?php echo $user_id; ?>&page=<?php echo ($pagination['current_page'] - 1); ?>&action_type=<?php echo $action_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"
                                        class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                                        ก่อนหน้า
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['last_page'], $pagination['current_page'] + 2); $i++): ?>
                                    <a href="?user_id=<?php echo $user_id; ?>&page=<?php echo $i; ?>&action_type=<?php echo $action_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"
                                        class="px-3 py-2 text-sm border rounded-md <?php echo $i === $pagination['current_page'] ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                                    <a href="?user_id=<?php echo $user_id; ?>&page=<?php echo ($pagination['current_page'] + 1); ?>&action_type=<?php echo $action_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"
                                        class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                                        ถัดไป
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>