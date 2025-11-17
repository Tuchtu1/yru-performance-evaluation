<?php

/**
 * /modules/dashboard/admin-dashboard.php
 * Dashboard สำหรับผู้ดูแลระบบ (Admin)
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requireRole('admin');

$page_title = 'หน้าหลัก - ผู้ดูแลระบบ';
$db = getDB();

// ==================== Fetch System Statistics ====================

// Get overall system statistics
$system_stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
        (SELECT COUNT(*) FROM evaluations) as total_evaluations,
        (SELECT COUNT(*) FROM portfolios) as total_portfolios,
        (SELECT COUNT(*) FROM evaluation_periods) as total_periods,
        (SELECT COUNT(*) FROM personnel_types WHERE is_active = 1) as total_personnel_types,
        (SELECT COUNT(*) FROM evaluation_aspects WHERE is_active = 1) as total_aspects
")->fetch();

// Get evaluation statistics by status
$eval_by_status = $db->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM evaluations
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent activities
$recent_activities = $db->query("
    SELECT al.*, u.full_name_th
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetchAll();

// Get active users (last 7 days)
$active_users = $db->query("
    SELECT COUNT(DISTINCT user_id) as count
    FROM activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch()['count'];

// Get system health
$db_size = $db->query("
    SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.tables
    WHERE table_schema = '" . DB_NAME . "'
")->fetch()['size_mb'];

// Get user statistics by role
$users_by_role = $db->query("
    SELECT 
        role,
        COUNT(*) as count
    FROM users
    WHERE is_active = 1
    GROUP BY role
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Get evaluation trend (last 6 months)
$eval_trend = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM evaluations
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Get recent backups
$recent_backups = $db->query("
    SELECT * FROM system_backups
    ORDER BY created_at DESC
    LIMIT 3
")->fetchAll();

// Get notifications count
$notifications_count = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
    FROM notifications
")->fetch();

include '../../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl shadow-lg p-6 mb-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold mb-2">
                Admin Dashboard 🎛️
            </h1>
            <p class="text-gray-300">
                ยินดีต้อนรับ, <?php echo e($_SESSION['user']['full_name_th']); ?>
            </p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold"><?php echo number_format($active_users); ?></div>
                <div class="text-sm text-gray-300">Active Users (7d)</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold"><?php echo $db_size; ?> MB</div>
                <div class="text-sm text-gray-300">Database Size</div>
            </div>
        </div>
    </div>
</div>

<!-- Main Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-6">
    <!-- Total Users -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($system_stats['total_users']); ?>
        </div>
        <div class="text-sm text-gray-600">ผู้ใช้งาน</div>
    </div>

    <!-- Total Evaluations -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1">
            <?php echo number_format($system_stats['total_evaluations']); ?></div>
        <div class="text-sm text-gray-600">แบบประเมิน</div>
    </div>

    <!-- Total Portfolios -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1">
            <?php echo number_format($system_stats['total_portfolios']); ?></div>
        <div class="text-sm text-gray-600">ผลงาน</div>
    </div>

    <!-- Total Periods -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($system_stats['total_periods']); ?>
        </div>
        <div class="text-sm text-gray-600">รอบประเมิน</div>
    </div>

    <!-- Personnel Types -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1">
            <?php echo number_format($system_stats['total_personnel_types']); ?></div>
        <div class="text-sm text-gray-600">ประเภทบุคลากร</div>
    </div>

    <!-- Evaluation Aspects -->
    <div class="stat-card">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($system_stats['total_aspects']); ?>
        </div>
        <div class="text-sm text-gray-600">ด้านประเมิน</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-6">
    <div class="card-header">
        <h3 class="text-lg font-semibold">การจัดการระบบ</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
            <a href="<?php echo url('modules/users/list.php'); ?>"
                class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all text-center group">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div class="font-semibold text-sm text-gray-900">ผู้ใช้งาน</div>
            </a>

            <a href="<?php echo url('modules/configuration/personnel-type.php'); ?>"
                class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all text-center group">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="font-semibold text-sm text-gray-900">ประเภทบุคลากร</div>
            </a>

            <a href="<?php echo url('modules/configuration/evaluation-aspects.php'); ?>"
                class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all text-center group">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="font-semibold text-sm text-gray-900">ด้านประเมิน</div>
            </a>

            <a href="<?php echo url('modules/configuration/evaluation-period.php'); ?>"
                class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition-all text-center group">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="font-semibold text-sm text-gray-900">รอบประเมิน</div>
            </a>

            <a href="<?php echo url('modules/configuration/notification-settings.php'); ?>"
                class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-pink-500 hover:bg-pink-50 transition-all text-center group">
                <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div class="font-semibold text-sm text-gray-900">การแจ้งเตือน</div>
            </a>

            <a href="<?php echo url('modules/configuration/system-backup.php'); ?>"
                class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-all text-center group">
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                </div>
                <div class="font-semibold text-sm text-gray-900">สำรองข้อมูล</div>
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Evaluation Status Breakdown -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-lg font-semibold">สถานะแบบประเมิน</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-3 gap-4">
                    <?php
                    $status_config = [
                        'draft' => ['label' => 'ร่าง', 'color' => 'gray'],
                        'submitted' => ['label' => 'ส่งแล้ว', 'color' => 'blue'],
                        'under_review' => ['label' => 'กำลังตรวจสอบ', 'color' => 'yellow'],
                        'approved' => ['label' => 'อนุมัติ', 'color' => 'green'],
                        'rejected' => ['label' => 'ไม่อนุมัติ', 'color' => 'red'],
                        'returned' => ['label' => 'ส่งกลับแก้ไข', 'color' => 'orange']
                    ];

                    foreach ($status_config as $status => $config):
                        $count = $eval_by_status[$status] ?? 0;
                    ?>
                        <div class="text-center p-4 bg-<?php echo $config['color']; ?>-50 rounded-lg">
                            <div class="text-3xl font-bold text-<?php echo $config['color']; ?>-600 mb-2">
                                <?php echo number_format($count); ?>
                            </div>
                            <div class="text-sm text-gray-600"><?php echo $config['label']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Users by Role -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-lg font-semibold">ผู้ใช้งานแยกตามบทบาท</h3>
            </div>
            <div class="card-body">
                <div class="space-y-4">
                    <?php
                    $role_config = [
                        'admin' => ['label' => 'ผู้ดูแลระบบ', 'color' => 'red', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                        'manager' => ['label' => 'ผู้บริหาร', 'color' => 'blue', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                        'staff' => ['label' => 'บุคลากร', 'color' => 'green', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z']
                    ];

                    $total_users = array_sum($users_by_role);

                    foreach ($role_config as $role => $config):
                        $count = $users_by_role[$role] ?? 0;
                        $percentage = $total_users > 0 ? ($count / $total_users) * 100 : 0;
                    ?>
                        <div class="flex items-center">
                            <div
                                class="w-10 h-10 bg-<?php echo $config['color']; ?>-100 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-5 h-5 text-<?php echo $config['color']; ?>-600" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="<?php echo $config['icon']; ?>" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-900"><?php echo $config['label']; ?></span>
                                    <span
                                        class="text-sm font-semibold text-gray-900"><?php echo number_format($count); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-<?php echo $config['color']; ?>-500 h-2 rounded-full"
                                        style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Evaluation Trend -->
        <?php if (!empty($eval_trend)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">แนวโน้มการส่งแบบประเมิน (6 เดือนล่าสุด)</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-3">
                        <?php
                        $max_count = max(array_column($eval_trend, 'count'));
                        foreach ($eval_trend as $trend):
                            $percentage = $max_count > 0 ? ($trend['count'] / $max_count) * 100 : 0;
                            $thai_month = [
                                '01' => 'มกราคม',
                                '02' => 'กุมภาพันธ์',
                                '03' => 'มีนาคม',
                                '04' => 'เมษายน',
                                '05' => 'พฤษภาคม',
                                '06' => 'มิถุนายน',
                                '07' => 'กรกฎาคม',
                                '08' => 'สิงหาคม',
                                '09' => 'กันยายน',
                                '10' => 'ตุลาคม',
                                '11' => 'พฤศจิกายน',
                                '12' => 'ธันวาคม'
                            ];
                            list($year, $month) = explode('-', $trend['month']);
                            $display_month = $thai_month[$month] . ' ' . ($year + 543);
                        ?>
                            <div class="flex items-center">
                                <div class="w-32 text-sm text-gray-600"><?php echo $display_month; ?></div>
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <div class="flex-1 bg-gray-200 rounded-full h-8 mr-3">
                                            <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-8 rounded-full flex items-center justify-end pr-3"
                                                style="width: <?php echo max($percentage, 5); ?>%">
                                                <span
                                                    class="text-white text-sm font-semibold"><?php echo number_format($trend['count']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">

        <!-- System Health -->
        <div class="card bg-gradient-to-br from-green-500 to-green-600 text-white">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-semibold">สถานะระบบ</h4>
                    <div class="w-3 h-3 bg-green-300 rounded-full animate-pulse"></div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-green-100">ฐานข้อมูล</span>
                        <span class="font-semibold"><?php echo $db_size; ?> MB</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-green-100">การแจ้งเตือน</span>
                        <span class="font-semibold"><?php echo number_format($notifications_count['total']); ?></span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-green-100">Active Users</span>
                        <span class="font-semibold"><?php echo number_format($active_users); ?></span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-green-400">
                    <div class="text-sm text-green-100">สถานะ: <span class="font-semibold">ปกติ ✓</span></div>
                </div>
            </div>
        </div>

        <!-- Recent Backups -->
        <div class="card">
            <div class="card-header">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">สำรองข้อมูลล่าสุด</h3>
                    <a href="<?php echo url('modules/configuration/system-backup.php'); ?>"
                        class="text-sm text-blue-600 hover:text-blue-700">
                        จัดการ →
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($recent_backups)): ?>
                    <div class="text-center py-6 text-gray-500 text-sm">
                        ยังไม่มีการสำรองข้อมูล
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_backups as $backup): ?>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between mb-1">
                                    <span
                                        class="text-sm font-medium text-gray-900"><?php echo format_bytes($backup['file_size']); ?></span>
                                    <span class="badge badge-primary text-xs"><?php echo $backup['backup_type']; ?></span>
                                </div>
                                <div class="text-xs text-gray-500"><?php echo time_ago($backup['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-lg font-semibold">กิจกรรมล่าสุด</h3>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center py-6 text-gray-500 text-sm">
                        ไม่มีกิจกรรม
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full bg-blue-500 mt-2 flex-shrink-0"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900">
                                        <span class="font-medium"><?php echo e($activity['full_name_th'] ?? 'System'); ?></span>
                                        <?php echo e($activity['action']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo time_ago($activity['created_at']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-lg font-semibold">ลิงก์ด่วน</h3>
            </div>
            <div class="card-body">
                <div class="space-y-2">
                    <a href="<?php echo url('modules/reports/organization.php'); ?>"
                        class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition-colors">
                        <span class="text-sm text-gray-700">รายงานภาพรวม</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="<?php echo url('modules/configuration/form-builder.php'); ?>"
                        class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition-colors">
                        <span class="text-sm text-gray-700">สร้างฟอร์มประเมิน</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="#"
                        class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg transition-colors">
                        <span class="text-sm text-gray-700">คู่มือการใช้งาน</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>