<?php

/**
 * /modules/approval/review.php
 * หน้าตรวจสอบแบบประเมินรายละเอียด
 * สำหรับผู้บริหารพิจารณาแบบประเมิน
 */

session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

requirePermission('approval.review');

$current_user = $_SESSION['user'];
$evaluation_id = (int)input('id', 0);

if ($evaluation_id <= 0) {
    flash_error('ไม่พบแบบประเมินที่ต้องการ');
    redirect('modules/approval/pending-list.php');
}

$db = getDB();

// ดึงข้อมูลแบบประเมิน
$stmt = $db->prepare("
    SELECT 
        e.*,
        u.full_name_th,
        u.full_name_en,
        u.personnel_type,
        u.position,
        u.email,
        d.department_name_th,
        d.department_name_en,
        ep.period_name,
        ep.year,
        ep.semester
    FROM evaluations e
    INNER JOIN users u ON e.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    WHERE e.evaluation_id = ?
");
$stmt->execute([$evaluation_id]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    flash_error('ไม่พบแบบประเมิน');
    redirect('modules/approval/pending-list.php');
}

// ตรวจสอบสิทธิ์ - ต้องเป็นผู้บริหารที่ถูกเลือก หรือเป็น admin
if ($current_user['role'] === 'manager') {
    $check_manager = $db->prepare("
        SELECT em_id FROM evaluation_managers 
        WHERE evaluation_id = ? AND manager_user_id = ?
    ");
    $check_manager->execute([$evaluation_id, $current_user['user_id']]);

    if (!$check_manager->fetch() && $current_user['role'] !== 'admin') {
        flash_error('คุณไม่มีสิทธิ์ตรวจสอบแบบประเมินนี้');
        redirect('modules/approval/pending-list.php');
    }
}

// ดึงรายละเอียดแต่ละด้าน
$details_stmt = $db->prepare("
    SELECT 
        ed.*,
        ea.aspect_name_th,
        ea.aspect_code,
        ea.weight_percentage as aspect_weight,
        et.topic_name_th,
        et.max_score,
        et.weight_percentage as topic_weight
    FROM evaluation_details ed
    INNER JOIN evaluation_aspects ea ON ed.aspect_id = ea.aspect_id
    LEFT JOIN evaluation_topics et ON ed.topic_id = et.topic_id
    WHERE ed.evaluation_id = ?
    ORDER BY ea.display_order, et.display_order
");
$details_stmt->execute([$evaluation_id]);
$details = $details_stmt->fetchAll();

// จัดกลุ่มตามด้าน
$grouped_details = [];
foreach ($details as $detail) {
    $aspect_id = $detail['aspect_id'];
    if (!isset($grouped_details[$aspect_id])) {
        $grouped_details[$aspect_id] = [
            'aspect_name' => $detail['aspect_name_th'],
            'aspect_code' => $detail['aspect_code'],
            'aspect_weight' => $detail['aspect_weight'],
            'topics' => []
        ];
    }
    $grouped_details[$aspect_id]['topics'][] = $detail;
}

// ดึงผลงานที่แนบ
$portfolio_stmt = $db->prepare("
    SELECT 
        ep.*,
        wp.title,
        wp.description,
        wp.file_name,
        wp.file_path,
        wp.file_size,
        ea.aspect_name_th
    FROM evaluation_portfolios ep
    INNER JOIN work_portfolio wp ON ep.portfolio_id = wp.portfolio_id
    LEFT JOIN evaluation_aspects ea ON wp.aspect_id = ea.aspect_id
    WHERE ep.evaluation_id = ?
    ORDER BY ea.display_order
");
$portfolio_stmt->execute([$evaluation_id]);
$portfolios = $portfolio_stmt->fetchAll();

// ดึงผู้บริหารที่เลือก
$managers_stmt = $db->prepare("
    SELECT 
        em.*,
        u.full_name_th,
        u.position,
        u.email
    FROM evaluation_managers em
    INNER JOIN users u ON em.manager_user_id = u.user_id
    WHERE em.evaluation_id = ?
    ORDER BY em.selection_order
");
$managers_stmt->execute([$evaluation_id]);
$managers = $managers_stmt->fetchAll();

// ดึงประวัติการพิจารณา
$history_stmt = $db->prepare("
    SELECT 
        ah.*,
        u.full_name_th
    FROM approval_history ah
    LEFT JOIN users u ON ah.manager_user_id = u.user_id
    WHERE ah.evaluation_id = ?
    ORDER BY ah.created_at DESC
");
$history_stmt->execute([$evaluation_id]);
$history = $history_stmt->fetchAll();

// สถานะของผู้บริหารคนนี้
$my_status = null;
if ($current_user['role'] === 'manager') {
    $my_status_stmt = $db->prepare("
        SELECT status, review_comment 
        FROM evaluation_managers 
        WHERE evaluation_id = ? AND manager_user_id = ?
    ");
    $my_status_stmt->execute([$evaluation_id, $current_user['user_id']]);
    $my_status = $my_status_stmt->fetch();
}

$page_title = 'ตรวจสอบแบบประเมิน';
include APP_ROOT . '/includes/header.php';
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <nav class="breadcrumb mb-2">
                <a href="<?php echo url('modules/dashboard/index.php'); ?>" class="breadcrumb-item">หน้าหลัก</a>
                <span class="mx-2">/</span>
                <a href="pending-list.php" class="breadcrumb-item">รายการรออนุมัติ</a>
                <span class="mx-2">/</span>
                <span class="breadcrumb-active">ตรวจสอบ</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">ตรวจสอบแบบประเมิน</h1>
        </div>
        <div class="flex space-x-2">
            <button onclick="window.print()" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                พิมพ์
            </button>
            <a href="pending-list.php" class="btn btn-outline">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                กลับ
            </a>
        </div>
    </div>
</div>

<!-- ข้อมูลผู้ประเมิน -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex items-start justify-between mb-6">
        <div class="flex items-center space-x-4">
            <div
                class="w-16 h-16 bg-gradient-to-br from-blue-400 to-purple-400 rounded-full flex items-center justify-center text-white text-xl font-bold">
                <?php echo mb_substr($evaluation['full_name_th'], 0, 2); ?>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900"><?php echo e($evaluation['full_name_th']); ?></h2>
                <p class="text-gray-600"><?php echo e($evaluation['position']); ?></p>
                <p class="text-sm text-gray-500"><?php echo e($evaluation['department_name_th']); ?></p>
            </div>
        </div>
        <div class="text-right">
            <span class="badge badge-lg <?php echo $status_colors[$evaluation['status']]; ?>">
                <?php echo $status_names[$evaluation['status']]; ?>
            </span>
            <p class="text-sm text-gray-500 mt-2">
                ส่งเมื่อ: <?php echo thai_date($evaluation['submitted_at'], 'datetime'); ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-gray-100">
        <div>
            <p class="text-sm font-medium text-gray-500 mb-1">รอบการประเมิน</p>
            <p class="font-semibold text-gray-900"><?php echo e($evaluation['period_name']); ?></p>
            <p class="text-sm text-gray-600">ปีการศึกษา <?php echo e($evaluation['year']); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 mb-1">ประเภทบุคลากร</p>
            <p class="font-semibold text-gray-900"><?php echo $personnel_names[$evaluation['personnel_type']]; ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 mb-1">คะแนนรวม</p>
            <p class="text-3xl font-bold text-blue-600"><?php echo number_format_thai($evaluation['total_score']); ?>
            </p>
        </div>
    </div>
</div>

<!-- รายละเอียดการประเมินแต่ละด้าน -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900">รายละเอียดการประเมิน</h3>
    </div>
    <div class="p-6">
        <?php foreach ($grouped_details as $aspect_id => $aspect_data): ?>
            <div class="mb-8 last:mb-0">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-900">
                        <?php echo e($aspect_data['aspect_name']); ?>
                    </h4>
                    <span class="badge badge-primary">
                        น้ำหนัก <?php echo $aspect_data['aspect_weight']; ?>%
                    </span>
                </div>

                <div class="space-y-4">
                    <?php foreach ($aspect_data['topics'] as $topic): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900"><?php echo e($topic['topic_name_th']); ?></p>
                                    <?php if ($topic['self_assessment']): ?>
                                        <p class="text-sm text-gray-600 mt-2">
                                            <span class="font-medium">ประเมินตนเอง:</span>
                                            <?php echo nl2br(e($topic['self_assessment'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($topic['evidence_description']): ?>
                                        <p class="text-sm text-gray-600 mt-2">
                                            <span class="font-medium">หลักฐาน:</span>
                                            <?php echo nl2br(e($topic['evidence_description'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-4 text-right">
                                    <p class="text-2xl font-bold text-blue-600">
                                        <?php echo number_format_thai($topic['score']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        / <?php echo $topic['max_score']; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ผลงานที่แนบ -->
<?php if (!empty($portfolios)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">ผลงานที่แนบ</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($portfolios as $portfolio): ?>
                    <div class="portfolio-item">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate"><?php echo e($portfolio['title']); ?></p>
                                <p class="text-sm text-gray-600 mt-1"><?php echo e($portfolio['aspect_name_th']); ?></p>
                                <?php if ($portfolio['file_name']): ?>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="text-xs text-gray-500"><?php echo e($portfolio['file_name']); ?></span>
                                        <span class="text-xs text-gray-400">•</span>
                                        <span
                                            class="text-xs text-gray-500"><?php echo format_bytes($portfolio['file_size']); ?></span>
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

<!-- ผู้บริหารที่ได้รับมอบหมาย -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900">ผู้บริหารที่ได้รับมอบหมาย</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($managers as $manager): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center space-x-3 mb-3">
                        <div
                            class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 font-medium">
                            <?php echo mb_substr($manager['full_name_th'], 0, 2); ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900"><?php echo e($manager['full_name_th']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo e($manager['position']); ?></p>
                        </div>
                    </div>
                    <span class="badge <?php echo $status_colors[$manager['status']] ?? 'badge-gray'; ?>">
                        <?php
                        $manager_status_names = [
                            'pending' => 'รอพิจารณา',
                            'reviewing' => 'กำลังตรวจสอบ',
                            'approved' => 'อนุมัติ',
                            'rejected' => 'ไม่อนุมัติ'
                        ];
                        echo $manager_status_names[$manager['status']] ?? $manager['status'];
                        ?>
                    </span>
                    <?php if ($manager['reviewed_at']): ?>
                        <p class="text-xs text-gray-500 mt-2">
                            <?php echo thai_date($manager['reviewed_at'], 'datetime'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ประวัติการพิจารณา -->
<?php if (!empty($history)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">ประวัติการพิจารณา</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <?php foreach ($history as $item): ?>
                    <div class="flex items-start space-x-4 pb-4 border-b border-gray-100 last:border-0 last:pb-0">
                        <div class="flex-shrink-0 w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-900"><?php echo e($item['full_name_th']); ?></p>
                            <p class="text-sm text-gray-600 mt-1">
                                <?php
                                $action_names = [
                                    'submit' => 'ส่งแบบประเมิน',
                                    'return' => 'ส่งกลับแก้ไข',
                                    'approve' => 'อนุมัติ',
                                    'reject' => 'ไม่อนุมัติ'
                                ];
                                echo $action_names[$item['action']] ?? $item['action'];
                                ?>
                            </p>
                            <?php if ($item['comment']): ?>
                                <p class="text-sm text-gray-700 mt-2 bg-gray-50 p-3 rounded">
                                    <?php echo nl2br(e($item['comment'])); ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-2">
                                <?php echo thai_date($item['created_at'], 'datetime'); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ฟอร์มพิจารณา -->
<?php if (in_array($evaluation['status'], ['submitted', 'under_review']) && (!$my_status || $my_status['status'] === 'pending')): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">พิจารณาแบบประเมิน</h3>
        </div>
        <div class="p-6">
            <form id="reviewForm" method="POST" data-validate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">

                <div class="form-group">
                    <label class="form-label">ความเห็น / ข้อเสนอแนะ</label>
                    <textarea name="comment" class="form-textarea" rows="4"
                        placeholder="กรอกความเห็นหรือข้อเสนอแนะ (ถ้ามี)"></textarea>
                </div>

                <div class="flex items-center justify-end space-x-3">
                    <button type="button" onclick="submitAction('return')" class="btn btn-warning">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                        ส่งกลับแก้ไข
                    </button>
                    <button type="button" onclick="submitAction('reject')" class="btn btn-danger">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        ไม่อนุมัติ
                    </button>
                    <button type="button" onclick="submitAction('approve')" class="btn btn-success">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        อนุมัติ
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function submitAction(action) {
        const form = document.getElementById('reviewForm');
        const comment = form.querySelector('[name="comment"]').value;

        let confirmMessage = '';
        let endpoint = '';

        switch (action) {
            case 'approve':
                confirmMessage = 'คุณแน่ใจหรือไม่ที่จะอนุมัติแบบประเมินนี้?';
                endpoint = 'approve.php';
                break;
            case 'reject':
                confirmMessage = 'คุณแน่ใจหรือไม่ที่จะไม่อนุมัติแบบประเมินนี้?';
                endpoint = 'reject.php';
                break;
            case 'return':
                if (!comment) {
                    showError('กรุณาระบุเหตุผลในการส่งกลับแก้ไข');
                    return;
                }
                confirmMessage = 'คุณแน่ใจหรือไม่ที่จะส่งกลับให้แก้ไข?';
                endpoint = 'return.php';
                break;
        }

        if (confirm(confirmMessage)) {
            showLoading();

            const formData = new FormData(form);
            formData.append('action', action);

            fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showSuccess(data.message);
                        setTimeout(() => {
                            window.location.href = 'pending-list.php';
                        }, 1500);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    showError('เกิดข้อผิดพลาด: ' + error.message);
                });
        }
    }
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>