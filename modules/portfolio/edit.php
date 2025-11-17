<?php

/**
 * modules/portfolio/edit.php
 * แก้ไขผลงานในคลัง
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';  // ✅ แก้
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();

if (!can('portfolio.edit_own')) {
    flash_error('คุณไม่มีสิทธิ์แก้ไขผลงาน');
    redirect('modules/portfolio/index.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$portfolio_id = $_GET['id'] ?? 0;

try {
    // ✅ ดึงข้อมูลผลงาน
    $stmt = $db->prepare("
        SELECT p.*, ea.aspect_name_th
        FROM portfolios p
        LEFT JOIN evaluation_aspects ea ON p.aspect_id = ea.aspect_id
        WHERE p.portfolio_id = ? AND p.user_id = ?
    ");
    $stmt->execute([$portfolio_id, $user_id]);
    $portfolio = $stmt->fetch();

    if (!$portfolio) {
        flash_error('ไม่พบผลงานที่ต้องการแก้ไข');
        redirect('modules/portfolio/index.php');
        exit;
    }

    // ✅ ตรวจสอบว่ามีการใช้งานแล้วหรือไม่
    $stmt = $db->prepare("
        SELECT COUNT(*) as usage_count
        FROM evaluation_portfolios
        WHERE portfolio_id = ?
    ");
    $stmt->execute([$portfolio_id]);
    $usage = $stmt->fetch();

    // ✅ ดึงด้านการประเมิน
    $stmt = $db->query("
        SELECT aspect_id, aspect_name_th, description
        FROM evaluation_aspects
        WHERE is_active = 1
        ORDER BY display_order
    ");
    $aspects = $stmt->fetchAll();

    // บันทึกการแก้ไข
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
        $aspect_id = $_POST['aspect_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $work_type = trim($_POST['work_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $work_date = $_POST['work_date'] ?? null;
        $max_usage_count = intval($_POST['max_usage_count'] ?? 1);
        $tags = trim($_POST['tags'] ?? '');
        $is_shared = isset($_POST['is_shared']) ? 1 : 0;

        $errors = [];

        // Validation
        if (empty($aspect_id)) {
            $errors['aspect_id'] = 'กรุณาเลือกด้านการประเมิน';
        }
        if (empty($title)) {
            $errors['title'] = 'กรุณาระบุชื่อผลงาน';
        }
        if ($max_usage_count < $portfolio['current_usage_count']) {
            $errors['max_usage_count'] = 'จำนวนครั้งต้องไม่น้อยกว่าจำนวนที่ใช้ไปแล้ว (' . $portfolio['current_usage_count'] . ' ครั้ง)';
        }

        // Upload new file if provided
        $file_path = $portfolio['file_path'];
        $file_name = $portfolio['file_name'];
        $file_size = $portfolio['file_size'];

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = upload_file($_FILES['file'], 'portfolios/');

            if ($upload_result['success']) {
                // Delete old file
                if ($portfolio['file_path']) {
                    delete_file($portfolio['file_path']);
                }

                $file_path = $upload_result['filepath'];
                $file_name = $upload_result['original_name'];
                $file_size = $upload_result['size'];
            } else {
                $errors['file'] = $upload_result['error'];
            }
        }

        // Delete file if requested
        if (isset($_POST['delete_file']) && $portfolio['file_path']) {
            delete_file($portfolio['file_path']);
            $file_path = null;
            $file_name = null;
            $file_size = null;
        }

        if (empty($errors)) {
            // ✅ Update
            $stmt = $db->prepare("
                UPDATE portfolios 
                SET aspect_id = ?,
                    title = ?,
                    work_type = ?,
                    description = ?,
                    work_date = ?,
                    file_path = ?,
                    file_name = ?,
                    file_size = ?,
                    max_usage_count = ?,
                    tags = ?,
                    is_shared = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE portfolio_id = ? AND user_id = ?
            ");

            $stmt->execute([
                $aspect_id,
                $title,
                $work_type,
                $description,
                $work_date,
                $file_path,
                $file_name,
                $file_size,
                $max_usage_count,
                $tags,
                $is_shared,
                $user_id,
                $portfolio_id,
                $user_id
            ]);

            // Log activity
            if (function_exists('log_activity')) {
                log_activity('update_portfolio', 'portfolios', $portfolio_id, [
                    'title' => $title
                ]);
            }

            flash_success('แก้ไขผลงานเรียบร้อยแล้ว');
            redirect('modules/portfolio/index.php');
            exit;
        } else {
            $_SESSION['errors'] = $errors;
        }
    }
} catch (Exception $e) {
    error_log('Edit Portfolio Error: ' . $e->getMessage());
    flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    redirect('modules/portfolio/index.php');
    exit;
}

$page_title = 'แก้ไขผลงาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">แก้ไขผลงาน</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        แก้ไขข้อมูลผลงาน: <?php echo e($portfolio['title']); ?>
                    </p>
                </div>
                <a href="<?php echo url('modules/portfolio/index.php'); ?>"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>ย้อนกลับ
                </a>
            </div>
        </div>

        <!-- Usage Warning -->
        <?php if ($usage['usage_count'] > 0): ?>
            <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-yellow-400 text-xl mr-3"></i>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">
                            ผลงานนี้ถูกใช้งานแล้ว <?php echo $usage['usage_count']; ?> ครั้ง
                        </p>
                        <p class="text-sm text-yellow-700 mt-1">
                            การแก้ไขอาจส่งผลต่อแบบประเมินที่อ้างอิงผลงานนี้
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <?php echo csrf_field(); ?>

            <!-- Basic Information -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">ข้อมูลพื้นฐาน</h3>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Aspect -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ด้านการประเมิน *</label>
                        <select name="aspect_id"
                            class="w-full border rounded px-3 py-2 <?php echo error('aspect_id') ? 'border-red-500' : ''; ?>"
                            required>
                            <option value="">-- เลือกด้าน --</option>
                            <?php foreach ($aspects as $aspect): ?>
                                <option value="<?php echo $aspect['aspect_id']; ?>"
                                    <?php echo $portfolio['aspect_id'] == $aspect['aspect_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($aspect['aspect_name_th']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (error('aspect_id')): ?>
                            <p class="text-red-600 text-sm mt-1"><?php echo error('aspect_id'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อผลงาน *</label>
                        <input type="text" name="title" value="<?php echo e($portfolio['title']); ?>"
                            class="w-full border rounded px-3 py-2 <?php echo error('title') ? 'border-red-500' : ''; ?>"
                            required>
                        <?php if (error('title')): ?>
                            <p class="text-red-600 text-sm mt-1"><?php echo error('title'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Work Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ประเภทผลงาน</label>
                        <input type="text" name="work_type" value="<?php echo e($portfolio['work_type']); ?>"
                            class="w-full border rounded px-3 py-2">
                    </div>

                    <!-- Work Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">วันที่ทำผลงาน</label>
                        <input type="date" name="work_date" value="<?php echo e($portfolio['work_date']); ?>"
                            class="w-full border rounded px-3 py-2">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">รายละเอียด</label>
                        <textarea name="description" class="w-full border rounded px-3 py-2"
                            rows="4"><?php echo e($portfolio['description']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- File Management -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">ไฟล์เอกสาร</h3>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Current File -->
                    <?php if ($portfolio['file_name']): ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center flex-1">
                                    <i class="fas fa-file text-3xl text-gray-400 mr-3"></i>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900"><?php echo e($portfolio['file_name']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo format_bytes($portfolio['file_size']); ?>
                                        </p>
                                    </div>
                                </div>
                                <label class="flex items-center">
                                    <input type="checkbox" name="delete_file" value="1" class="rounded text-red-600">
                                    <span class="ml-2 text-sm text-red-600">ลบไฟล์</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Upload New File -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <?php echo $portfolio['file_name'] ? 'แทนที่ด้วยไฟล์ใหม่' : 'แนบไฟล์'; ?>
                        </label>
                        <input type="file" name="file" class="w-full border rounded px-3 py-2"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <?php if (error('file')): ?>
                            <p class="text-red-600 text-sm mt-1"><?php echo error('file'); ?></p>
                        <?php endif; ?>
                        <p class="text-gray-500 text-sm mt-1">
                            รองรับไฟล์: PDF, Word, Excel, รูปภาพ | ขนาดไม่เกิน
                            <?php echo format_bytes(MAX_FILE_SIZE); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">การตั้งค่า</h3>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Usage Count -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">จำนวนครั้งที่สามารถใช้ได้
                            *</label>
                        <input type="number" name="max_usage_count" value="<?php echo $portfolio['max_usage_count']; ?>"
                            class="w-full border rounded px-3 py-2 <?php echo error('max_usage_count') ? 'border-red-500' : ''; ?>"
                            min="<?php echo $portfolio['current_usage_count']; ?>" max="10" required>
                        <?php if (error('max_usage_count')): ?>
                            <p class="text-red-600 text-sm mt-1"><?php echo error('max_usage_count'); ?></p>
                        <?php endif; ?>
                        <p class="text-gray-500 text-sm mt-1">
                            ใช้งานไปแล้ว <?php echo $portfolio['current_usage_count']; ?> ครั้ง
                            (เหลืออีก <?php echo $portfolio['max_usage_count'] - $portfolio['current_usage_count']; ?>
                            ครั้ง)
                        </p>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">แท็ก (Tags)</label>
                        <input type="text" name="tags" value="<?php echo e($portfolio['tags']); ?>"
                            class="w-full border rounded px-3 py-2" placeholder="คั่นด้วยเครื่องหมายจุลภาค">
                    </div>

                    <!-- Is Shared -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_shared" value="1"
                                <?php echo $portfolio['is_shared'] ? 'checked' : ''; ?> class="rounded text-blue-600">
                            <span class="ml-2 text-sm text-gray-700">
                                อนุญาตให้ผู้อื่นดูและใช้ผลงานนี้ได้
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <a href="<?php echo url('modules/portfolio/index.php'); ?>"
                            class="bg-white border border-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-50">
                            ยกเลิก
                        </a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                            <i class="fas fa-save mr-2"></i>บันทึกการแก้ไข
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // File size validation
    document.querySelector('input[type="file"]')?.addEventListener('change', function(e) {
        const maxSize = <?php echo MAX_FILE_SIZE; ?>;
        const file = e.target.files[0];

        if (file && file.size > maxSize) {
            alert('ไฟล์มีขนาดใหญ่เกินไป (สูงสุด <?php echo format_bytes(MAX_FILE_SIZE); ?>)');
            e.target.value = '';
        }
    });

    // Delete file confirmation
    document.querySelector('input[name="delete_file"]')?.addEventListener('change', function(e) {
        if (e.target.checked) {
            if (!confirm('คุณแน่ใจหรือไม่ว่าต้องการลบไฟล์นี้?')) {
                e.target.checked = false;
            }
        }
    });
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>