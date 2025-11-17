<?php

/**
 * modules/dashboard/manager-dashboard.php
 * Dashboard สำหรับผู้บริหาร (Manager)
 */

require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/helpers.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
if (!isManager() && !isAdmin()) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('modules/dashboard/index.php');
    exit;
}

$page_title = 'หน้าหลัก - ผู้บริหาร';
$db = getDB();
$user_id = $_SESSION['user']['user_id'];

try {
    // Get active period
    $stmt = $db->query("SELECT * FROM evaluation_periods WHERE is_active = 1 LIMIT 1");
    $active_period = $stmt->fetch();

    // Get department statistics
    $stmt = $db->query("
        SELECT 
            COUNT(DISTINCT e.user_id) as total_staff,
            COUNT(DISTINCT e.evaluation_id) as total_evaluations,
            SUM(CASE WHEN e.status = 'submitted' THEN 1 ELSE 0 END) as pending_approval,
            SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN e.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            AVG(CASE WHEN e.status = 'approved' THEN e.total_score END) as avg_score
        FROM evaluations e
    ");
    $dept_stats = $stmt->fetch();

    // Ensure numeric values
    $dept_stats['total_staff'] = (int)($dept_stats['total_staff'] ?? 0);
    $dept_stats['total_evaluations'] = (int)($dept_stats['total_evaluations'] ?? 0);
    $dept_stats['pending_approval'] = (int)($dept_stats['pending_approval'] ?? 0);
    $dept_stats['approved'] = (int)($dept_stats['approved'] ?? 0);
    $dept_stats['rejected'] = (int)($dept_stats['rejected'] ?? 0);
    $dept_stats['avg_score'] = $dept_stats['avg_score'] ? (float)$dept_stats['avg_score'] : null;

    // Get pending evaluations for approval
    $stmt = $db->query("
        SELECT e.*, u.full_name_th, u.email, ep.period_name
        FROM evaluations e
        LEFT JOIN users u ON e.user_id = u.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE e.status IN ('submitted', 'under_review')
        ORDER BY e.submitted_at DESC
        LIMIT 10
    ");
    $pending_evals = $stmt->fetchAll();

    // Get recent approvals
    $stmt = $db->prepare("
        SELECT e.*, u.full_name_th, ep.period_name
        FROM evaluations e
        LEFT JOIN users u ON e.user_id = u.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE e.status IN ('approved', 'rejected')
        AND e.reviewed_by = ?
        ORDER BY e.reviewed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $approvals = $stmt->fetchAll();

    // Get staff performance summary
    $stmt = $db->query("
        SELECT 
            u.user_id,
            u.full_name_th,
            pt.type_name_th as personnel_type,
            COUNT(e.evaluation_id) as total_evals,
            SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_evals,
            AVG(CASE WHEN e.status = 'approved' THEN e.total_score END) as avg_score
        FROM users u
        LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        WHERE u.role = 'staff'
        GROUP BY u.user_id, u.full_name_th, pt.type_name_th
        ORDER BY avg_score DESC
        LIMIT 10
    ");
    $staff_performance = $stmt->fetchAll();

    // Get notifications
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_notifications = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Manager Dashboard Error: " . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $dept_stats = [
        'total_staff' => 0,
        'total_evaluations' => 0,
        'pending_approval' => 0,
        'approved' => 0,
        'rejected' => 0,
        'avg_score' => null
    ];
    $pending_evals = [];
    $approvals = [];
    $staff_performance = [];
    $recent_notifications = [];
}

include '../../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 mb-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold mb-2">
                Dashboard ผู้บริหาร 📊
            </h1>
            <p class="text-purple-100">
                <?php echo e($_SESSION['user']['full_name_th']); ?>
                <?php if ($active_period): ?>
                    • <?php echo e($active_period['period_name']); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($dept_stats['pending_approval'] > 0): ?>
            <div class="text-right">
                <div class="text-3xl font-bold"><?php echo number_format($dept_stats['pending_approval']); ?></div>
                <div class="text-sm text-purple-100">รอพิจารณา</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <!-- Total Staff -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($dept_stats['total_staff']); ?>
        </div>
        <div class="text-sm text-gray-600">จำนวนบุคลากร</div>
    </div>

    <!-- Pending Approval -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-orange-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($dept_stats['pending_approval']); ?>
        </div>
        <div class="text-sm text-gray-600">รอพิจารณา</div>
    </div>

    <!-- Approved -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($dept_stats['approved']); ?></div>
        <div class="text-sm text-gray-600">อนุมัติแล้ว</div>
    </div>

    <!-- Average Score -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-bar text-purple-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1">
            <?php echo $dept_stats['avg_score'] !== null ? number_format($dept_stats['avg_score'], 2) : '-'; ?>
        </div>
        <div class="text-sm text-gray-600">คะแนนเฉลี่ย</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold">การดำเนินการด่วน</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <a href="<?php echo url('modules/approval/pending-list.php'); ?>"
                        class="p-4 border-2 border-orange-300 rounded-lg hover:bg-orange-50 transition-all text-center group">
                        <div
                            class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:bg-orange-500 transition-colors">
                            <i class="fas fa-clipboard-list text-orange-600 group-hover:text-white text-xl"></i>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-orange-600">พิจารณาอนุมัติ</div>
                        <?php if ($dept_stats['pending_approval'] > 0): ?>
                            <div class="text-xs text-orange-600 mt-1"><?php echo $dept_stats['pending_approval']; ?> รายการ
                            </div>
                        <?php endif; ?>
                    </a>

                    <a href="<?php echo url('modules/reports/department.php'); ?>"
                        class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all text-center group">
                        <div
                            class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-500 transition-colors">
                            <i class="fas fa-chart-line text-blue-600 group-hover:text-white text-xl"></i>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-blue-600">รายงานหน่วยงาน</div>
                    </a>

                    <a href="<?php echo url('modules/approval/history.php'); ?>"
                        class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all text-center group">
                        <div
                            class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:bg-green-500 transition-colors">
                            <i class="fas fa-history text-green-600 group-hover:text-white text-xl"></i>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-green-600">ประวัติการพิจารณา</div>
                    </a>

                    <a href="<?php echo url('modules/users/list.php'); ?>"
                        class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all text-center group">
                        <div
                            class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:bg-purple-500 transition-colors">
                            <i class="fas fa-users-cog text-purple-600 group-hover:text-white text-xl"></i>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-purple-600">จัดการบุคลากร</div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Pending Approvals -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">รอพิจารณาอนุมัติ</h3>
                    <a href="<?php echo url('modules/approval/pending-list.php'); ?>"
                        class="text-sm text-blue-600 hover:text-blue-700">
                        ดูทั้งหมด →
                    </a>
                </div>
            </div>
            <div class="p-6">
                <?php if (empty($pending_evals)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-4xl text-gray-400"></i>
                        </div>
                        <p>ไม่มีรายการรอพิจารณา</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อบุคลากร
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        รอบการประเมิน</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">วันที่ส่ง
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">สถานะ</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">จัดการ
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pending_evals as $eval): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900"><?php echo e($eval['full_name_th']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo e($eval['email']); ?></div>
                                        </td>
                                        <td class="px-6 py-4"><?php echo e($eval['period_name'] ?? '-'); ?></td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm"><?php echo thai_date($eval['submitted_at']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo time_ago($eval['submitted_at']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span
                                                class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">รอพิจารณา</span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <a href="<?php echo url('modules/approval/review.php?id=' . $eval['evaluation_id']); ?>"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                                พิจารณา
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Staff Performance -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold">ผลการประเมินบุคลากร (Top 10)</h3>
            </div>
            <div class="p-6">
                <?php if (empty($staff_performance)): ?>
                    <div class="text-center py-8 text-gray-500">
                        ยังไม่มีข้อมูล
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($staff_performance as $index => $staff): ?>
                            <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <div
                                    class="w-8 h-8 <?php echo $index < 3 ? 'bg-gradient-to-br from-yellow-400 to-yellow-500' : 'bg-gray-300'; ?> rounded-full flex items-center justify-center font-bold text-white">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?php echo e($staff['full_name_th']); ?></div>
                                    <div class="text-sm text-gray-600"><?php echo e($staff['personnel_type'] ?? '-'); ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-blue-600">
                                        <?php echo $staff['avg_score'] ? number_format((float)$staff['avg_score'], 2) : '-'; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo number_format((int)$staff['approved_evals']); ?>/<?php echo number_format((int)$staff['total_evals']); ?>
                                        ครั้ง
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">

        <!-- Recent Approvals -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold">พิจารณาล่าสุด</h3>
            </div>
            <div class="p-6">
                <?php if (empty($approvals)): ?>
                    <div class="text-center py-8 text-gray-500 text-sm">
                        ยังไม่มีรายการที่พิจารณา
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($approvals as $approval): ?>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="font-medium text-sm text-gray-900"><?php echo e($approval['full_name_th']); ?>
                                    </div>
                                    <?php if ($approval['status'] === 'approved'): ?>
                                        <span
                                            class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">อนุมัติ</span>
                                    <?php else: ?>
                                        <span
                                            class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">ไม่อนุมัติ</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-600"><?php echo e($approval['period_name'] ?? '-'); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php echo time_ago($approval['reviewed_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">การแจ้งเตือน</h3>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-700">ดูทั้งหมด</a>
                </div>
            </div>
            <div class="p-6">
                <?php if (empty($recent_notifications)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-bell text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-sm">ไม่มีการแจ้งเตือน</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_notifications as $notif): ?>
                            <div
                                class="flex items-start gap-3 p-3 <?php echo !$notif['is_read'] ? 'bg-blue-50' : ''; ?> rounded-lg">
                                <div class="w-2 h-2 rounded-full bg-blue-500 mt-2 flex-shrink-0"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900"><?php echo e($notif['message']); ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo time_ago($notif['created_at']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow text-white p-6">
            <h4 class="font-semibold mb-4">สถิติรวม</h4>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-blue-100">แบบประเมินทั้งหมด</span>
                    <span class="font-bold"><?php echo number_format($dept_stats['total_evaluations']); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-blue-100">อนุมัติแล้ว</span>
                    <span class="font-bold"><?php echo number_format($dept_stats['approved']); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-blue-100">ปฏิเสธ</span>
                    <span class="font-bold"><?php echo number_format($dept_stats['rejected']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>