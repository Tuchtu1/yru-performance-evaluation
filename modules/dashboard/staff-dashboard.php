<?php

/**
 * modules/dashboard/staff-dashboard.php
 * Dashboard สำหรับบุคลากร (Staff)
 */

require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/helpers.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
if (!isStaff() && !isAdmin()) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('modules/dashboard/index.php');
    exit;
}

$page_title = 'หน้าหลัก';
$db = getDB();
$user_id = $_SESSION['user']['user_id'];

try {
    // Get active period
    $stmt = $db->query("SELECT * FROM evaluation_periods WHERE is_active = 1 LIMIT 1");
    $active_period = $stmt->fetch();

    // Get evaluation statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            AVG(CASE WHEN status = 'approved' THEN total_score END) as avg_score
        FROM evaluations 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    // Ensure numeric values
    $stats['total'] = (int)($stats['total'] ?? 0);
    $stats['draft'] = (int)($stats['draft'] ?? 0);
    $stats['submitted'] = (int)($stats['submitted'] ?? 0);
    $stats['under_review'] = (int)($stats['under_review'] ?? 0);
    $stats['approved'] = (int)($stats['approved'] ?? 0);
    $stats['rejected'] = (int)($stats['rejected'] ?? 0);
    $stats['avg_score'] = $stats['avg_score'] ? (float)$stats['avg_score'] : null;

    // Get recent evaluations
    $stmt = $db->prepare("
        SELECT e.*, ep.period_name, pt.type_name_th as personnel_type
        FROM evaluations e
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        LEFT JOIN personnel_types pt ON e.personnel_type_id = pt.personnel_type_id
        WHERE e.user_id = ?
        ORDER BY e.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll();

    // Get portfolio statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared
        FROM portfolios 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $portfolio = $stmt->fetch();
    $portfolio['total'] = (int)($portfolio['total'] ?? 0);
    $portfolio['shared'] = (int)($portfolio['shared'] ?? 0);

    // Get recent notifications
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_notifications = $stmt->fetchAll();

    // Get deadlines
    $deadlines = [];
    $deadline_days = null;
    if ($active_period) {
        $deadline_days = days_between(date('Y-m-d'), $active_period['end_date']);
        if ($deadline_days >= 0) {
            $deadlines[] = [
                'title' => 'ส่งแบบประเมิน ' . $active_period['period_name'],
                'date' => $active_period['end_date'],
                'days_left' => $deadline_days
            ];
        }
    }
} catch (Exception $e) {
    error_log("Staff Dashboard Error: " . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $stats = [
        'total' => 0,
        'draft' => 0,
        'submitted' => 0,
        'under_review' => 0,
        'approved' => 0,
        'rejected' => 0,
        'avg_score' => null
    ];
    $evaluations = [];
    $portfolio = ['total' => 0, 'shared' => 0];
    $recent_notifications = [];
    $deadlines = [];
    $deadline_days = null;
}

include '../../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 mb-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold mb-2">
                สวัสดี, <?php echo e($_SESSION['user']['full_name_th']); ?> 👋
            </h1>
            <p class="text-blue-100">
                <?php if ($active_period): ?>
                    รอบการประเมินปัจจุบัน: <?php echo e($active_period['period_name']); ?>
                <?php else: ?>
                    ยังไม่มีรอบการประเมินที่เปิดใช้งาน
                <?php endif; ?>
            </p>
        </div>
        <?php if ($active_period && $deadline_days !== null && $deadline_days >= 0): ?>
            <div class="text-right">
                <div class="text-3xl font-bold"><?php echo $deadline_days; ?></div>
                <div class="text-sm text-blue-100">วันที่เหลือ</div>
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
    <!-- Total Evaluations -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-blue-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($stats['total']); ?></div>
        <div class="text-sm text-gray-600">แบบประเมินทั้งหมด</div>
    </div>

    <!-- Draft -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-edit text-yellow-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($stats['draft']); ?></div>
        <div class="text-sm text-gray-600">ร่าง</div>
    </div>

    <!-- Approved -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($stats['approved']); ?></div>
        <div class="text-sm text-gray-600">อนุมัติแล้ว</div>
    </div>

    <!-- Portfolio -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-briefcase text-purple-600 text-xl"></i>
            </div>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-1"><?php echo number_format($portfolio['total']); ?></div>
        <div class="text-sm text-gray-600">ผลงานในคลัง</div>
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="<?php echo url('modules/evaluation/create.php'); ?>"
                        class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all text-center group">
                        <div
                            class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-500 transition-colors">
                            <i class="fas fa-plus text-blue-600 group-hover:text-white text-xl"></i>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-blue-600">สร้างแบบประเมิน</div>
                    </a>

                    <a href="<?php echo url('modules/portfolio/add.php'); ?>"
                        class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-500 hover:bg-purple-50 transition-all text-center group">
                        <div
                            class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:bg-purple-500 transition-colors">
                            <i class="fas fa-folder-plus text-purple-600 group-hover:text-white text-xl"></i>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-purple-600">เพิ่มผลงาน</div>
                    </a>

                    <a href="<?php echo url('modules/reports/individual.php'); ?>"
                        class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-green-500 hover:bg-green-50 transition-all text-center group">
                        <div
                            class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3 group-hover:bg-green-500 transition-colors">
                            <i class="fas fa-chart-bar text-green-600 group-hover:text-white text-xl"></i>
                        </div>
                        <div class="font-semibold text-gray-900 group-hover:text-green-600">ดูรายงาน</div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Evaluations -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">แบบประเมินล่าสุด</h3>
                    <a href="<?php echo url('modules/evaluation/list.php'); ?>"
                        class="text-sm text-blue-600 hover:text-blue-700">
                        ดูทั้งหมด →
                    </a>
                </div>
            </div>
            <div class="p-6">
                <?php if (empty($evaluations)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-file-alt text-4xl text-gray-400"></i>
                        </div>
                        <p>ยังไม่มีแบบประเมิน</p>
                        <a href="<?php echo url('modules/evaluation/create.php'); ?>"
                            class="text-blue-600 hover:underline mt-2 inline-block">
                            สร้างแบบประเมินเลย
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($evaluations as $eval): ?>
                            <div
                                class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?php echo e($eval['period_name'] ?? '-'); ?></div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <?php echo e($eval['personnel_type'] ?? '-'); ?>
                                        <span class="mx-2">•</span>
                                        <?php echo thai_date($eval['updated_at'], 'short'); ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <?php
                                    $status_classes = [
                                        'draft' => 'bg-gray-100 text-gray-800',
                                        'submitted' => 'bg-blue-100 text-blue-800',
                                        'under_review' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'returned' => 'bg-orange-100 text-orange-800'
                                    ];
                                    $status_names = [
                                        'draft' => 'ร่าง',
                                        'submitted' => 'ส่งแล้ว',
                                        'under_review' => 'กำลังตรวจสอบ',
                                        'approved' => 'อนุมัติ',
                                        'rejected' => 'ไม่อนุมัติ',
                                        'returned' => 'ส่งกลับแก้ไข'
                                    ];
                                    ?>
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-medium <?php echo $status_classes[$eval['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $status_names[$eval['status']] ?? $eval['status']; ?>
                                    </span>
                                    <a href="<?php echo url('modules/evaluation/view.php?id=' . $eval['evaluation_id']); ?>"
                                        class="text-blue-600 hover:text-blue-700">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($stats['approved'] > 0 && $stats['avg_score'] !== null): ?>
            <!-- Performance Chart -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold">ผลการประเมิน</h3>
                </div>
                <div class="p-6">
                    <div class="text-center py-8">
                        <div class="text-5xl font-bold text-blue-600 mb-2">
                            <?php echo number_format($stats['avg_score'], 2); ?>
                        </div>
                        <div class="text-gray-600">คะแนนเฉลี่ยที่ได้รับการอนุมัติ</div>
                        <div class="mt-4">
                            <div class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-full text-sm">
                                <i class="fas fa-check-circle mr-2"></i>
                                จาก <?php echo number_format($stats['approved']); ?> ครั้งที่อนุมัติ
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column -->
    <div class="space-y-6">

        <!-- Deadlines -->
        <?php if (!empty($deadlines)): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold">กำหนดการ</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <?php foreach ($deadlines as $deadline): ?>
                            <div
                                class="p-4 <?php echo $deadline['days_left'] <= 7 ? 'bg-red-50 border-l-4 border-red-500' : 'bg-blue-50 border-l-4 border-blue-500'; ?> rounded-r">
                                <div class="font-medium text-gray-900 mb-1"><?php echo e($deadline['title']); ?></div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600"><?php echo thai_date($deadline['date']); ?></span>
                                    <span
                                        class="font-semibold <?php echo $deadline['days_left'] <= 7 ? 'text-red-600' : 'text-blue-600'; ?>">
                                        <?php echo $deadline['days_left']; ?> วัน
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
                                class="flex items-start gap-3 p-3 <?php echo !$notif['is_read'] ? 'bg-blue-50' : ''; ?> rounded-lg hover:bg-gray-50 transition-colors">
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

        <!-- Help & Support -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow text-white p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-question-circle text-2xl"></i>
                </div>
                <div>
                    <h4 class="font-semibold">ต้องการความช่วยเหลือ?</h4>
                    <p class="text-sm text-purple-100">ติดต่อเราได้ตลอดเวลา</p>
                </div>
            </div>
            <a href="#"
                class="block w-full text-center bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded transition-colors">
                ติดต่อฝ่ายสนับสนุน
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>