<?php

/**
 * /modules/evaluation/view.php
 * ดูรายละเอียดแบบประเมิน
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requireAuth();

$page_title = 'รายละเอียดแบบประเมิน';
$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$evaluation_id = $_GET['id'] ?? 0;

// ==================== Fetch Evaluation Data ====================

$eval = $db->prepare("
    SELECT e.*, 
           ep.period_name, ep.year, ep.semester, ep.start_date, ep.end_date,
           u.full_name_th, u.email, u.position,
           pt.type_name_th as personnel_type_name,
           reviewer.full_name_th as reviewed_by_name,
           approver.full_name_th as approved_by_name
    FROM evaluations e
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    LEFT JOIN users u ON e.user_id = u.user_id
    LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
    LEFT JOIN users reviewer ON e.reviewed_by = reviewer.user_id
    LEFT JOIN users approver ON e.approved_by = approver.user_id
    WHERE e.evaluation_id = ?
");
$eval->execute([$evaluation_id]);
$evaluation = $eval->fetch();

if (!$evaluation) {
    flash_error('ไม่พบแบบประเมินที่ต้องการ');
    redirect('evaluation/list.php');
}

// Check permission
if ($evaluation['user_id'] != $user_id && !can('evaluation.view_all')) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงแบบประเมินนี้');
    redirect('evaluation/list.php');
}

// Get evaluation details
$details = $db->prepare("
    SELECT ed.*, ea.aspect_name_th, ea.weight_percentage,
           et.topic_name_th, et.max_score as topic_max_score
    FROM evaluation_details ed
    LEFT JOIN evaluation_aspects ea ON ed.aspect_id = ea.aspect_id
    LEFT JOIN evaluation_topics et ON ed.topic_id = et.topic_id
    WHERE ed.evaluation_id = ?
    ORDER BY ea.sort_order, et.sort_order
");
$details->execute([$evaluation_id]);
$evaluation_details = $details->fetchAll();

// Group by aspect
$grouped_details = [];
foreach ($evaluation_details as $detail) {
    $aspect_id = $detail['aspect_id'];
    if (!isset($grouped_details[$aspect_id])) {
        $grouped_details[$aspect_id] = [
            'aspect_name' => $detail['aspect_name_th'],
            'weight' => $detail['weight_percentage'],
            'items' => []
        ];
    }
    $grouped_details[$aspect_id]['items'][] = $detail;
}

// Get linked portfolios
$portfolios = $db->prepare("
    SELECT ep.*, p.title, p.description, p.work_type, p.work_date, p.file_path
    FROM evaluation_portfolios ep
    LEFT JOIN portfolios p ON ep.portfolio_id = p.portfolio_id
    WHERE ep.evaluation_id = ?
");
$portfolios->execute([$evaluation_id]);
$linked_portfolios = $portfolios->fetchAll();

// Get approval history
$history = $db->prepare("
    SELECT ah.*, u.full_name_th
    FROM approval_history ah
    LEFT JOIN users u ON ah.manager_user_id = u.user_id
    WHERE ah.evaluation_id = ?
    ORDER BY ah.created_at DESC
");
$history->execute([$evaluation_id]);
$approval_history = $history->fetchAll();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">รายละเอียดแบบประเมิน</h1>
            <p class="mt-2 text-sm text-gray-600">
                <?php echo e($evaluation['period_name']); ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="list.php" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                กลับ
            </a>
            <?php if ($evaluation['status'] == 'draft' && $evaluation['user_id'] == $user_id): ?>
                <a href="edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    แก้ไข
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                พิมพ์
            </button>
        </div>
    </div>
</div>

<!-- Status Card -->
<div
    class="card mb-6 <?php echo $evaluation['status'] == 'approved' ? 'bg-green-50' : ($evaluation['status'] == 'rejected' ? 'bg-red-50' : ''); ?>">
    <div class="card-body">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <?php
                $status_config = [
                    'draft' => ['label' => 'ร่าง', 'color' => 'gray', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                    'submitted' => ['label' => 'ส่งแล้ว', 'color' => 'blue', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'under_review' => ['label' => 'กำลังตรวจสอบ', 'color' => 'yellow', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'approved' => ['label' => 'อนุมัติ', 'color' => 'green', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'rejected' => ['label' => 'ไม่อนุมัติ', 'color' => 'red', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'returned' => ['label' => 'ส่งกลับแก้ไข', 'color' => 'orange', 'icon' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6']
                ];
                $config = $status_config[$evaluation['status']] ?? $status_config['draft'];
                ?>
                <div
                    class="w-16 h-16 bg-<?php echo $config['color']; ?>-100 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-<?php echo $config['color']; ?>-600" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="<?php echo $config['icon']; ?>" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php echo $config['label']; ?></h3>
                    <p class="text-sm text-gray-600">สถานะแบบประเมิน</p>
                </div>
            </div>
            <?php if ($evaluation['total_score'] > 0): ?>
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">
                        <?php echo number_format($evaluation['total_score'], 2); ?></div>
                    <div class="text-sm text-gray-600">คะแนนรวม</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Evaluation Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="text-lg font-semibold">ข้อมูลแบบประเมิน</h3>
            </div>
            <div class="card-body">
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">ชื่อ-นามสกุล</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo e($evaluation['full_name_th']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">ประเภทบุคลากร</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo e($evaluation['personnel_type_name']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">ตำแหน่ง</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo e($evaluation['position'] ?? '-'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">อีเมล</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo e($evaluation['email']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">วันที่สร้าง</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo thai_date($evaluation['created_at'], 'datetime'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">อัพเดทล่าสุด</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo thai_date($evaluation['updated_at'], 'datetime'); ?></dd>
                    </div>
                    <?php if ($evaluation['submitted_at']): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">วันที่ส่ง</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <?php echo thai_date($evaluation['submitted_at'], 'datetime'); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($evaluation['approved_at']): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">วันที่อนุมัติ</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <?php echo thai_date($evaluation['approved_at'], 'datetime'); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Evaluation Details -->
        <?php if (!empty($grouped_details)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">รายละเอียดการประเมิน</h3>
                </div>
                <div class="card-body space-y-6">
                    <?php foreach ($grouped_details as $aspect_id => $aspect): ?>
                        <div class="border-l-4 border-blue-500 pl-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-gray-900"><?php echo e($aspect['aspect_name']); ?></h4>
                                <span class="text-sm text-gray-600">น้ำหนัก
                                    <?php echo number_format($aspect['weight'], 1); ?>%</span>
                            </div>
                            <div class="space-y-3">
                                <?php foreach ($aspect['items'] as $item): ?>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="flex items-start justify-between mb-2">
                                            <h5 class="font-medium text-gray-900 flex-1">
                                                <?php echo e($item['topic_name_th'] ?? 'ไม่ระบุหัวข้อ'); ?></h5>
                                            <span class="text-lg font-bold text-blue-600 ml-4">
                                                <?php echo number_format($item['score'], 2); ?>
                                            </span>
                                        </div>
                                        <?php if ($item['self_assessment']): ?>
                                            <div class="mt-2 text-sm text-gray-700">
                                                <strong>การประเมินตนเอง:</strong>
                                                <p class="mt-1"><?php echo nl2br(e($item['self_assessment'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($item['evidence_description']): ?>
                                            <div class="mt-2 text-sm text-gray-700">
                                                <strong>หลักฐาน:</strong>
                                                <p class="mt-1"><?php echo nl2br(e($item['evidence_description'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-8 text-gray-500">
                    ยังไม่มีรายละเอียดการประเมิน
                </div>
            </div>
        <?php endif; ?>

        <!-- Linked Portfolios -->
        <?php if (!empty($linked_portfolios)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">ผลงานที่แนบ</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-3">
                        <?php foreach ($linked_portfolios as $portfolio): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo e($portfolio['title']); ?></div>
                                        <?php if ($portfolio['work_type']): ?>
                                            <div class="text-sm text-gray-500"><?php echo e($portfolio['work_type']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($portfolio['file_path']): ?>
                                    <a href="<?php echo url('uploads/' . $portfolio['file_path']); ?>" target="_blank"
                                        class="text-blue-600 hover:text-blue-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">

        <!-- Timeline -->
        <?php if (!empty($approval_history)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">ประวัติการดำเนินการ</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-4">
                        <?php foreach ($approval_history as $item):
                            $action_config = [
                                'submit' => ['label' => 'ส่งแบบประเมิน', 'color' => 'blue'],
                                'return' => ['label' => 'ส่งกลับแก้ไข', 'color' => 'orange'],
                                'approve' => ['label' => 'อนุมัติ', 'color' => 'green'],
                                'reject' => ['label' => 'ไม่อนุมัติ', 'color' => 'red']
                            ];
                            $config = $action_config[$item['action']] ?? ['label' => $item['action'], 'color' => 'gray'];
                        ?>
                            <div class="flex gap-3">
                                <div class="w-2 h-2 rounded-full bg-<?php echo $config['color']; ?>-500 mt-2 flex-shrink-0">
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?php echo $config['label']; ?></div>
                                    <div class="text-sm text-gray-600"><?php echo e($item['full_name_th']); ?></div>
                                    <?php if ($item['comment']): ?>
                                        <div class="text-sm text-gray-700 mt-1 p-2 bg-gray-50 rounded">
                                            <?php echo nl2br(e($item['comment'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo thai_date($item['created_at'], 'datetime'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <?php if ($evaluation['user_id'] == $user_id): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">การดำเนินการ</h3>
                </div>
                <div class="card-body space-y-2">
                    <?php if ($evaluation['status'] == 'draft'): ?>
                        <a href="edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-primary w-full">
                            แก้ไขแบบประเมิน
                        </a>
                        <a href="submit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-success w-full">
                            ส่งแบบประเมิน
                        </a>
                    <?php elseif ($evaluation['status'] == 'returned'): ?>
                        <a href="edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-primary w-full">
                            แก้ไขและส่งใหม่
                        </a>
                    <?php endif; ?>
                    <a href="status-tracking.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline w-full">
                        ติดตามสถานะ
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Period Info -->
        <div class="card bg-blue-50">
            <div class="card-body">
                <h4 class="font-semibold text-blue-900 mb-3">ข้อมูลรอบการประเมิน</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-blue-700">ปีการศึกษา:</span>
                        <span class="font-semibold text-blue-900"><?php echo $evaluation['year'] + 543; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-blue-700">ภาคเรียน:</span>
                        <span class="font-semibold text-blue-900"><?php echo $evaluation['semester']; ?></span>
                    </div>
                    <div class="text-blue-700">
                        <?php echo thai_date($evaluation['start_date']); ?>
                        <br>ถึง <?php echo thai_date($evaluation['end_date']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>