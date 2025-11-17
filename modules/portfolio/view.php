<?php

/**
 * modules/portfolio/view.php
 * ดูรายละเอียดผลงาน
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$portfolio_id = $_GET['id'] ?? 0;

// ดึงข้อมูลผลงาน
$stmt = $db->prepare("
    SELECT p.*, 
           ea.aspect_name_th,
           ea.aspect_code,
           u.full_name_th as owner_name,
           u.email as owner_email,
           u.position as owner_position,
           d.department_name_th as owner_department,
           p.max_usage_count - p.current_usage_count as remaining_uses
    FROM portfolios p
    LEFT JOIN evaluation_aspects ea ON p.aspect_id = ea.aspect_id
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE p.portfolio_id = ?
");
$stmt->execute([$portfolio_id]);
$portfolio = $stmt->fetch();

if (!$portfolio) {
    flash_error('ไม่พบผลงานที่ต้องการดู');
    redirect('modules/portfolio/index.php');
    exit;
}

// ตรวจสอบสิทธิ์การเข้าถึง
$can_view = false;

if ($portfolio['user_id'] == $user_id) {
    $can_view = true;
} elseif ($portfolio['is_shared'] == 1) {
    $can_view = true;
} elseif (can('portfolio.view_all')) {
    $can_view = true;
}

if (!$can_view) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงผลงานนี้');
    redirect('modules/portfolio/index.php');
    exit;
}

// ดึงประวัติการใช้งาน
$stmt = $db->prepare("
    SELECT ep.link_id, 
           e.evaluation_id,
           e.created_at as eval_created_at,
           u.full_name_th,
           u.position,
           d.department_name_th,
           evp.period_name,
           evp.year
    FROM evaluation_portfolios ep
    JOIN evaluations e ON ep.evaluation_id = e.evaluation_id
    JOIN users u ON e.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    JOIN evaluation_periods evp ON e.period_id = evp.period_id
    WHERE ep.portfolio_id = ?
    ORDER BY ep.created_at DESC
    LIMIT 10
");
$stmt->execute([$portfolio_id]);
$usage_history = $stmt->fetchAll();

// ตรวจสอบประเภทไฟล์
$file_ext = '';
$is_image = false;
$is_pdf = false;
$can_preview = false;

if ($portfolio['file_name']) {
    $file_ext = strtolower(pathinfo($portfolio['file_name'], PATHINFO_EXTENSION));
    $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    $is_pdf = ($file_ext === 'pdf');
    $can_preview = $is_image || $is_pdf;
}

$page_title = 'ดูรายละเอียดผลงาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">รายละเอียดผลงาน</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        ข้อมูลและรายละเอียดของผลงาน
                    </p>
                </div>
                <a href="<?php echo url('modules/portfolio/index.php'); ?>"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    ย้อนกลับ
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Portfolio Info -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <!-- Badges -->
                        <div class="flex items-center gap-2 mb-4 flex-wrap">
                            <span
                                class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                <i class="fas fa-folder mr-1"></i>
                                <?php echo e($portfolio['aspect_name_th']); ?>
                            </span>
                            <?php if ($portfolio['is_shared']): ?>
                                <span
                                    class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-share-alt mr-1"></i>แชร์แล้ว
                                </span>
                            <?php endif; ?>
                            <?php if ($portfolio['user_id'] != $user_id): ?>
                                <span
                                    class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                                    <i class="fas fa-user mr-1"></i>ผลงานของผู้อื่น
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Title -->
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">
                            <?php echo e($portfolio['title']); ?>
                        </h2>

                        <!-- Meta Info -->
                        <div class="grid grid-cols-2 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                            <?php if ($portfolio['work_type']): ?>
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <i class="fas fa-tag mr-1"></i>ประเภท
                                    </p>
                                    <p class="font-medium text-gray-900">
                                        <?php echo e($portfolio['work_type']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($portfolio['work_date']): ?>
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <i class="far fa-calendar mr-1"></i>วันที่
                                    </p>
                                    <p class="font-medium text-gray-900">
                                        <?php echo thai_date($portfolio['work_date']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div>
                                <p class="text-sm text-gray-600 mb-1">
                                    <i class="far fa-clock mr-1"></i>สร้างเมื่อ
                                </p>
                                <p class="font-medium text-gray-900">
                                    <?php echo thai_date($portfolio['created_at'], 'datetime'); ?>
                                </p>
                            </div>

                            <?php if ($portfolio['updated_at'] != $portfolio['created_at']): ?>
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <i class="fas fa-edit mr-1"></i>แก้ไขล่าสุด
                                    </p>
                                    <p class="font-medium text-gray-900">
                                        <?php echo thai_date($portfolio['updated_at'], 'datetime'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <?php if ($portfolio['description']): ?>
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-align-left mr-2 text-gray-400"></i>
                                    รายละเอียด
                                </h3>
                                <div class="prose max-w-none text-gray-700 whitespace-pre-line bg-gray-50 p-4 rounded-lg">
                                    <?php echo e($portfolio['description']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- File Section -->
                        <?php if ($portfolio['file_name']): ?>
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-paperclip mr-2 text-gray-400"></i>
                                    ไฟล์แนบ
                                </h3>
                                <div
                                    class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-100">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center flex-1">
                                            <div
                                                class="w-14 h-14 rounded-lg flex items-center justify-center mr-4 <?php echo $is_pdf ? 'bg-red-100' : ($is_image ? 'bg-blue-100' : 'bg-gray-100'); ?>">
                                                <?php if ($is_image): ?>
                                                    <i class="fas fa-image text-2xl text-blue-600"></i>
                                                <?php elseif ($is_pdf): ?>
                                                    <i class="fas fa-file-pdf text-2xl text-red-600"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file-alt text-2xl text-gray-600"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 truncate">
                                                    <?php echo e($portfolio['file_name']); ?>
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo format_bytes($portfolio['file_size']); ?>
                                                    <span class="mx-2">•</span>
                                                    <span class="uppercase"><?php echo $file_ext; ?></span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-4">
                                            <?php if ($can_preview): ?>
                                                <button
                                                    onclick="previewFile(<?php echo $portfolio_id; ?>, '<?php echo $file_ext; ?>')"
                                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg transition-all">
                                                    <i class="fas fa-eye mr-2"></i>ดูไฟล์
                                                </button>
                                            <?php endif; ?>
                                            <a href="<?php echo url('modules/portfolio/download.php?id=' . $portfolio_id); ?>"
                                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all shadow-sm hover:shadow">
                                                <i class="fas fa-download mr-2"></i>ดาวน์โหลด
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Preview Container -->
                                    <div id="file-preview-<?php echo $portfolio_id; ?>" class="hidden mt-4">
                                        <div class="bg-white rounded-lg p-4 shadow-inner border-2 border-blue-200">
                                            <div class="flex items-center justify-between mb-3">
                                                <h4 class="font-semibold text-gray-900 flex items-center">
                                                    <i class="fas fa-eye mr-2 text-blue-600"></i>
                                                    ตัวอย่างไฟล์
                                                </h4>
                                                <button onclick="closePreview(<?php echo $portfolio_id; ?>)"
                                                    class="text-gray-400 hover:text-gray-600 transition-colors p-1 hover:bg-gray-100 rounded">
                                                    <i class="fas fa-times text-xl"></i>
                                                </button>
                                            </div>
                                            <div id="preview-content-<?php echo $portfolio_id; ?>" class="text-center">
                                                <!-- Content will be loaded here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Tags -->
                        <?php if ($portfolio['tags']): ?>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-tags mr-2 text-gray-400"></i>
                                    แท็ก
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach (explode(',', $portfolio['tags']) as $tag): ?>
                                        <span
                                            class="inline-flex items-center px-3 py-1 text-sm bg-gradient-to-r from-gray-100 to-gray-50 text-gray-700 rounded-full border border-gray-200 hover:border-gray-300 transition-colors">
                                            <i class="fas fa-hashtag mr-1 text-xs"></i>
                                            <?php echo e(trim($tag)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Usage History -->
                <?php if (!empty($usage_history)): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-history mr-2 text-gray-400"></i>
                                ประวัติการใช้งาน
                            </h3>
                            <div class="space-y-3">
                                <?php foreach ($usage_history as $history): ?>
                                    <div
                                        class="flex items-start p-4 bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg border border-gray-100 hover:shadow-sm transition-shadow">
                                        <div
                                            class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-4 flex-shrink-0 shadow">
                                            <?php echo mb_substr($history['full_name_th'], 0, 2); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900">
                                                <?php echo e($history['full_name_th']); ?>
                                            </p>
                                            <?php if ($history['position']): ?>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo e($history['position']); ?>
                                                    <?php if ($history['department_name_th']): ?>
                                                        <span class="text-gray-400">•</span>
                                                        <?php echo e($history['department_name_th']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <i class="far fa-calendar-alt mr-1"></i>
                                                <?php echo e($history['period_name']); ?>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo time_ago($history['eval_created_at']); ?>
                                            </p>
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
                <!-- Owner Info -->
                <?php if ($portfolio['user_id'] != $user_id): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user-circle mr-2 text-gray-400"></i>
                            เจ้าของผลงาน
                        </h3>
                        <div class="flex items-start">
                            <div
                                class="w-14 h-14 bg-gradient-to-br from-purple-400 to-pink-500 rounded-full flex items-center justify-center text-white font-bold mr-3 flex-shrink-0 shadow">
                                <?php echo mb_substr($portfolio['owner_name'], 0, 2); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900">
                                    <?php echo e($portfolio['owner_name']); ?>
                                </p>
                                <?php if ($portfolio['owner_position']): ?>
                                    <p class="text-sm text-gray-600">
                                        <?php echo e($portfolio['owner_position']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($portfolio['owner_department']): ?>
                                    <p class="text-sm text-gray-600">
                                        <?php echo e($portfolio['owner_department']); ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="far fa-envelope mr-1"></i>
                                    <?php echo e($portfolio['owner_email']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div
                    class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-sm border border-blue-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-chart-bar mr-2 text-blue-600"></i>
                        สถิติการใช้งาน
                    </h3>
                    <div class="space-y-4">
                        <div class="bg-white rounded-lg p-4 border border-blue-100">
                            <p class="text-sm text-gray-600 mb-1">จำนวนครั้งที่ใช้</p>
                            <p class="text-3xl font-bold text-gray-900">
                                <?php echo number_format($portfolio['current_usage_count']); ?>
                                <span class="text-lg text-gray-500 font-normal">
                                    / <?php echo $portfolio['max_usage_count']; ?>
                                </span>
                            </p>
                        </div>

                        <div class="bg-white rounded-lg p-4 border border-blue-100">
                            <p class="text-sm text-gray-600 mb-1">คงเหลือ</p>
                            <p
                                class="text-3xl font-bold <?php echo $portfolio['remaining_uses'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo number_format($portfolio['remaining_uses']); ?>
                                <span class="text-lg font-normal">ครั้ง</span>
                            </p>
                        </div>

                        <?php if ($portfolio['is_shared']): ?>
                            <div class="pt-4 border-t border-blue-200">
                                <div class="flex items-center text-sm text-blue-700 bg-blue-50 p-3 rounded-lg">
                                    <i class="fas fa-share-alt mr-2"></i>
                                    <span class="font-medium">ผลงานนี้เปิดแชร์แล้ว</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <?php if ($portfolio['user_id'] == $user_id): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-cog mr-2 text-gray-400"></i>
                            จัดการผลงาน
                        </h3>
                        <div class="space-y-2">
                            <?php if (can('portfolio.edit_own')): ?>
                                <a href="<?php echo url('modules/portfolio/edit.php?id=' . $portfolio_id); ?>"
                                    class="flex items-center justify-center w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm hover:shadow">
                                    <i class="fas fa-edit mr-2"></i>แก้ไขผลงาน
                                </a>
                            <?php endif; ?>

                            <?php if (can('portfolio.share')): ?>
                                <a href="<?php echo url('modules/portfolio/share.php?id=' . $portfolio_id); ?>"
                                    class="flex items-center justify-center w-full px-4 py-2 bg-white border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-share-alt mr-2"></i>จัดการการแชร์
                                </a>
                            <?php endif; ?>

                            <?php if (can('portfolio.delete_own')): ?>
                                <a href="<?php echo url('modules/portfolio/delete.php?id=' . $portfolio_id); ?>"
                                    class="flex items-center justify-center w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors shadow-sm hover:shadow"
                                    onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผลงานนี้?');">
                                    <i class="fas fa-trash mr-2"></i>ลบผลงาน
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($portfolio['is_shared'] && can('portfolio.claim')): ?>
                    <div
                        class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg shadow-sm border border-purple-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-copy mr-2 text-purple-600"></i>
                            การดำเนินการ
                        </h3>
                        <a href="<?php echo url('modules/portfolio/claim.php?id=' . $portfolio_id); ?>"
                            class="flex items-center justify-center w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors shadow-sm hover:shadow">
                            <i class="fas fa-copy mr-2"></i>อ้างอิงผลงานนี้
                        </a>
                        <p class="text-xs text-gray-600 mt-3 text-center">
                            คัดลอกผลงานนี้ไปยังคลังของคุณ
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript สำหรับดูไฟล์ -->
<script>
    function previewFile(portfolioId, fileExt) {
        const previewDiv = document.getElementById('file-preview-' + portfolioId);
        const contentDiv = document.getElementById('preview-content-' + portfolioId);

        // แสดง preview container
        previewDiv.classList.remove('hidden');

        // แสดง loading
        contentDiv.innerHTML =
            '<div class="py-12"><i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i><p class="text-gray-600 mt-3 font-medium">กำลังโหลดไฟล์...</p></div>';

        // สร้าง URL สำหรับดูไฟล์
        const viewUrl = '<?php echo url('modules/portfolio/view-file.php?id='); ?>' + portfolioId;

        // ตรวจสอบประเภทไฟล์
        if (fileExt === 'pdf') {
            // แสดง PDF
            contentDiv.innerHTML = '<iframe src="' + viewUrl +
                '" class="w-full rounded-lg border-2 border-gray-200 shadow-inner" style="height: 600px;"></iframe>';
        } else {
            // แสดงรูปภาพ
            const img = new Image();
            img.onload = function() {
                contentDiv.innerHTML = '<img src="' + viewUrl +
                    '" alt="Preview" class="max-w-full h-auto rounded-lg border-2 border-gray-200 mx-auto shadow-lg" style="max-height: 600px;">';
            };
            img.onerror = function() {
                contentDiv.innerHTML =
                    '<div class="py-12 text-red-600"><i class="fas fa-exclamation-triangle text-4xl"></i><p class="mt-3 font-medium text-lg">ไม่สามารถแสดงไฟล์ได้</p><p class="text-sm text-gray-600 mt-2">กรุณาลองดาวน์โหลดไฟล์แทน</p></div>';
            };
            img.src = viewUrl;
        }

        // Scroll ไปที่ preview
        setTimeout(() => {
            previewDiv.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }, 100);
    }

    function closePreview(portfolioId) {
        const previewDiv = document.getElementById('file-preview-' + portfolioId);
        previewDiv.classList.add('hidden');
    }
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>