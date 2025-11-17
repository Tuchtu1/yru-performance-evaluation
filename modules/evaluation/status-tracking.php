<?php

/**
 * modules/evaluation/status-tracking.php
 * ติดตามสถานะการพิจารณาแบบประเมิน
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';  // ✅ แก้เป็น includes
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login
requireAuth();

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$evaluation_id = $_GET['id'] ?? 0;

try {
    // ดึงข้อมูลแบบประเมิน
    $stmt = $db->prepare("
        SELECT e.*, 
               ep.period_name, 
               ep.year, 
               ep.semester,
               pt.type_name_th as type_name,
               u.full_name_th as user_name
        FROM evaluations e
        JOIN evaluation_periods ep ON e.period_id = ep.period_id
        LEFT JOIN personnel_types pt ON e.personnel_type_id = pt.personnel_type_id
        JOIN users u ON e.user_id = u.user_id
        WHERE e.evaluation_id = ?
    ");
    $stmt->execute([$evaluation_id]);
    $evaluation = $stmt->fetch();

    if (!$evaluation) {
        flash_error('ไม่พบแบบประเมินที่ต้องการ');
        redirect('modules/evaluation/list.php');
        exit;
    }

    // ตรวจสอบสิทธิ์ในการดู
    if (!isAdmin() && !isManager() && $evaluation['user_id'] != $user_id) {
        flash_error('คุณไม่มีสิทธิ์ดูข้อมูลนี้');
        redirect('modules/evaluation/list.php');
        exit;
    }

    // ดึงประวัติการดำเนินการจาก approval_history
    $stmt = $db->prepare("
        SELECT ah.*, 
               u.full_name_th as actor_name,
               u.position
        FROM approval_history ah
        LEFT JOIN users u ON ah.manager_user_id = u.user_id
        WHERE ah.evaluation_id = ?
        ORDER BY ah.created_at DESC
    ");
    $stmt->execute([$evaluation_id]);
    $activities = $stmt->fetchAll();

    // ดึง activity logs (ถ้ามี)
    $stmt = $db->prepare("
        SELECT al.*, 
               u.full_name_th as actor_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.table_name = 'evaluations' 
        AND al.record_id = ?
        ORDER BY al.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$evaluation_id]);
    $system_logs = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Status Tracking Error: ' . $e->getMessage());
    flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    redirect('modules/evaluation/list.php');
    exit;
}

$page_title = 'ติดตามสถานะแบบประเมิน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">ติดตามสถานะการพิจารณา</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        แบบประเมิน: <?php echo e($evaluation['period_name']); ?>
                        (<?php echo ($evaluation['year'] + 543); ?>
                        <?php if ($evaluation['semester']): ?>/<?php echo $evaluation['semester']; ?><?php endif; ?>)
                    </p>
                </div>
                <a href="<?php echo url('modules/evaluation/view.php?id=' . $evaluation_id); ?>"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>ย้อนกลับ
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Timeline (Main Content) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Current Status Card -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">สถานะปัจจุบัน</h3>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <?php
                                $statusIcons = [
                                    'draft' => '<div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center"><i class="fas fa-file-alt text-2xl text-gray-400"></i></div>',
                                    'submitted' => '<div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center"><i class="fas fa-paper-plane text-2xl text-blue-500"></i></div>',
                                    'under_review' => '<div class="w-16 h-16 rounded-full bg-yellow-100 flex items-center justify-center"><i class="fas fa-eye text-2xl text-yellow-500"></i></div>',
                                    'approved' => '<div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center"><i class="fas fa-check-circle text-2xl text-green-500"></i></div>',
                                    'rejected' => '<div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center"><i class="fas fa-times-circle text-2xl text-red-500"></i></div>',
                                    'returned' => '<div class="w-16 h-16 rounded-full bg-orange-100 flex items-center justify-center"><i class="fas fa-undo text-2xl text-orange-500"></i></div>'
                                ];
                                echo $statusIcons[$evaluation['status']] ?? $statusIcons['draft'];
                                ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-semibold text-gray-900">
                                    <?php echo $status_names[$evaluation['status']]; ?>
                                </h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php
                                    switch ($evaluation['status']) {
                                        case 'draft':
                                            echo 'กำลังจัดทำแบบประเมิน ยังไม่ได้ส่ง';
                                            break;
                                        case 'submitted':
                                            echo 'ส่งแบบประเมินเรียบร้อยแล้ว รอการพิจารณา';
                                            break;
                                        case 'under_review':
                                            echo 'อยู่ระหว่างการพิจารณาโดยผู้บริหาร';
                                            break;
                                        case 'approved':
                                            echo 'ผ่านการอนุมัติเรียบร้อยแล้ว';
                                            break;
                                        case 'rejected':
                                            echo 'ไม่ผ่านการพิจารณา';
                                            break;
                                        case 'returned':
                                            echo 'ส่งกลับเพื่อแก้ไข';
                                            break;
                                    }
                                    ?>
                                </p>
                                <?php if ($evaluation['submitted_at']): ?>
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="far fa-clock mr-1"></i>
                                        วันที่ส่ง: <?php echo thai_date($evaluation['submitted_at'], 'datetime'); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($evaluation['approved_at']): ?>
                                    <p class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-check mr-1"></i>
                                        อนุมัติเมื่อ: <?php echo thai_date($evaluation['approved_at'], 'datetime'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity History -->
                <?php if (!empty($activities)): ?>
                    <div class="bg-white rounded-lg shadow">
                        <div class="border-b px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">ประวัติการดำเนินการ</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($activities as $index => $activity): ?>
                                    <div class="relative">
                                        <?php if ($index < count($activities) - 1): ?>
                                            <div class="absolute left-5 top-12 h-full w-0.5 bg-gray-200"></div>
                                        <?php endif; ?>

                                        <div class="flex items-start space-x-4">
                                            <!-- Icon -->
                                            <div class="flex-shrink-0 relative z-10">
                                                <?php
                                                $actionIcons = [
                                                    'submit' => '<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center"><i class="fas fa-paper-plane text-blue-600"></i></div>',
                                                    'approve' => '<div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center"><i class="fas fa-check text-green-600"></i></div>',
                                                    'reject' => '<div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center"><i class="fas fa-times text-red-600"></i></div>',
                                                    'return' => '<div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center"><i class="fas fa-undo text-orange-600"></i></div>'
                                                ];
                                                echo $actionIcons[$activity['action']] ?? '<div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center"><i class="fas fa-circle text-gray-600"></i></div>';
                                                ?>
                                            </div>

                                            <!-- Content -->
                                            <div class="flex-1 bg-gray-50 rounded-lg p-4">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div>
                                                        <p class="font-medium text-gray-900">
                                                            <?php
                                                            $actionTexts = [
                                                                'submit' => 'ส่งแบบประเมิน',
                                                                'approve' => 'อนุมัติแบบประเมิน',
                                                                'reject' => 'ไม่อนุมัติแบบประเมิน',
                                                                'return' => 'ส่งกลับแบบประเมินเพื่อแก้ไข'
                                                            ];
                                                            echo $actionTexts[$activity['action']] ?? $activity['action'];
                                                            ?>
                                                        </p>
                                                        <p class="text-sm text-gray-600">
                                                            โดย <?php echo e($activity['actor_name'] ?? 'ผู้ใช้'); ?>
                                                            <?php if ($activity['position']): ?>
                                                                (<?php echo e($activity['position']); ?>)
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <span
                                                        class="px-2 py-1 text-xs rounded-full <?php echo $status_colors[$activity['new_status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                        <?php echo $status_names[$activity['new_status']] ?? $activity['new_status']; ?>
                                                    </span>
                                                </div>

                                                <p class="text-xs text-gray-500 mb-2">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?php echo thai_date($activity['created_at'], 'datetime'); ?>
                                                </p>

                                                <?php if ($activity['comment']): ?>
                                                    <div class="mt-3 bg-white rounded p-3 border border-gray-200">
                                                        <p class="text-xs font-medium text-gray-700 mb-1">ความคิดเห็น:</p>
                                                        <p class="text-sm text-gray-600">
                                                            <?php echo nl2br(e($activity['comment'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Logs (Optional) -->
                <?php if (!empty($system_logs) && isAdmin()): ?>
                    <div class="bg-white rounded-lg shadow">
                        <div class="border-b px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-history mr-2 text-gray-500"></i>
                                System Logs
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                <?php foreach ($system_logs as $log): ?>
                                    <div class="flex items-start space-x-3 text-sm p-2 hover:bg-gray-50 rounded">
                                        <i class="fas fa-circle text-xs text-gray-400 mt-1.5"></i>
                                        <div class="flex-1">
                                            <span class="text-gray-900 font-medium">
                                                <?php echo e($log['actor_name'] ?? 'System'); ?>
                                            </span>
                                            <span class="text-gray-600">
                                                <?php echo e($log['action']); ?>
                                            </span>
                                            <span class="text-xs text-gray-500 ml-2">
                                                <?php echo thai_date($log['created_at'], 'datetime'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Summary Card -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">สรุปข้อมูล</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">ผู้ประเมิน</p>
                            <p class="font-medium text-gray-900"><?php echo e($evaluation['user_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 mb-1">ประเภทบุคลากร</p>
                            <p class="font-medium text-gray-900"><?php echo e($evaluation['type_name'] ?? '-'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 mb-1">รอบการประเมิน</p>
                            <p class="font-medium text-gray-900">
                                <?php echo e($evaluation['period_name']); ?>
                            </p>
                        </div>
                        <?php if ($evaluation['total_score'] > 0): ?>
                            <div class="pt-3 border-t">
                                <p class="text-xs text-gray-600 mb-1">คะแนนรวม</p>
                                <p class="text-2xl font-bold text-blue-600">
                                    <?php echo number_format($evaluation['total_score'], 2); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Details -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">รายละเอียดสถานะ</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <?php if ($evaluation['submitted_at']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-paper-plane text-blue-500 mr-3 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">ส่งแบบประเมิน</p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo thai_date($evaluation['submitted_at'], 'datetime'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($evaluation['reviewed_at']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-eye text-yellow-500 mr-3 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">ตรวจสอบแล้ว</p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo thai_date($evaluation['reviewed_at'], 'datetime'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($evaluation['approved_at']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">อนุมัติแล้ว</p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo thai_date($evaluation['approved_at'], 'datetime'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($evaluation['rejected_at']): ?>
                            <div class="flex items-start">
                                <i class="fas fa-times-circle text-red-500 mr-3 mt-1"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">ไม่อนุมัติ</p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo thai_date($evaluation['rejected_at'], 'datetime'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($evaluation['status'] === 'draft'): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt text-4xl text-gray-300 mb-2"></i>
                                <p class="text-sm text-gray-500">ยังไม่ได้ส่งแบบประเมิน</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 space-y-2">
                        <a href="<?php echo url('modules/evaluation/view.php?id=' . $evaluation_id); ?>"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded inline-flex items-center justify-center">
                            <i class="fas fa-eye mr-2"></i>
                            ดูแบบประเมิน
                        </a>

                        <?php if ($evaluation['user_id'] == $user_id && in_array($evaluation['status'], ['draft', 'returned'])): ?>
                            <a href="<?php echo url('modules/evaluation/edit.php?id=' . $evaluation_id); ?>"
                                class="w-full bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50 inline-flex items-center justify-center">
                                <i class="fas fa-edit mr-2"></i>
                                แก้ไขแบบประเมิน
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo url('modules/evaluation/list.php'); ?>"
                            class="w-full bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50 inline-flex items-center justify-center">
                            <i class="fas fa-list mr-2"></i>
                            รายการแบบประเมิน
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>