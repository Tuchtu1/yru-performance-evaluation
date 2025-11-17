<?php

/**
 * modules/portfolio/add.php
 * เพิ่มผลงานใหม่เข้าคลังผลงาน
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';  // ✅ แก้เป็น includes
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();

if (!can('portfolio.create')) {
    flash_error('คุณไม่มีสิทธิ์เพิ่มผลงาน');
    redirect('modules/portfolio/index.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user']['user_id'];

try {
    // ✅ ดึงด้านการประเมิน (แก้จาก evaluation_dimensions)
    $stmt = $db->query("
        SELECT aspect_id, aspect_name_th, description
        FROM evaluation_aspects
        WHERE is_active = 1
        ORDER BY display_order
    ");
    $aspects = $stmt->fetchAll();

    // บันทึกข้อมูล
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
        $aspect_id = $_POST['aspect_id'] ?? '';  // ✅ แก้จาก dimension_id
        $title = trim($_POST['title'] ?? '');  // ✅ แก้จาก work_title
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
        if ($max_usage_count < 1) {
            $errors['max_usage_count'] = 'จำนวนครั้งต้องมากกว่า 0';
        }

        // Upload file if provided
        $file_path = null;
        $file_name = null;
        $file_size = null;

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = upload_file($_FILES['file'], 'portfolios/');

            if ($upload_result['success']) {
                $file_path = $upload_result['filepath'];
                $file_name = $upload_result['original_name'];
                $file_size = $upload_result['size'];
            } else {
                $errors['file'] = $upload_result['error'];
            }
        }

        if (empty($errors)) {
            // ✅ Insert ลงตาราง portfolios
            $stmt = $db->prepare("
                INSERT INTO portfolios 
                (user_id, aspect_id, title, work_type, description, work_date,
                 file_path, file_name, file_size, max_usage_count, tags, is_shared, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $user_id,
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
                $user_id
            ]);

            $portfolio_id = $db->lastInsertId();

            // Log activity
            if (function_exists('log_activity')) {
                log_activity('create_portfolio', 'portfolios', $portfolio_id, [
                    'title' => $title,
                    'aspect_id' => $aspect_id
                ]);
            }

            flash_success('เพิ่มผลงานเรียบร้อยแล้ว');
            redirect('modules/portfolio/index.php');
            exit;
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $_POST;
        }
    }
} catch (Exception $e) {
    error_log('Add Portfolio Error: ' . $e->getMessage());
    flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    $aspects = [];
}

$page_title = 'เพิ่มผลงานใหม่';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">เพิ่มผลงานใหม่</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        เพิ่มผลงานเข้าคลังเพื่อใช้ประกอบการประเมิน
                    </p>
                </div>
                <a href="<?php echo url('modules/portfolio/index.php'); ?>"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>ย้อนกลับ
                </a>
            </div>
        </div>

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
                                    <?php echo old('aspect_id') == $aspect['aspect_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($aspect['aspect_name_th']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (error('aspect_id')): ?>
                            <p class="text-red-600 text-sm mt-1"><?php echo error('aspect_id'); ?></p>
                        <?php endif; ?>
                        <p class="text-gray-500 text-sm mt-1">เลือกด้านที่ผลงานนี้เกี่ยวข้อง</p>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อผลงาน *</label>
                        <input type="text" name="title" value="<?php echo e(old('title')); ?>"
                            class="w-full border rounded px-3 py-2 <?php echo error('title') ? 'border-red-500' : ''; ?>"
                            placeholder="ระบุชื่อผลงาน" required>
                        <?php if (error('title')): ?>
                            <p class="text-red-600 text-sm mt-1"><?php echo error('title'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Work Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ประเภทผลงาน</label>
                        <input type="text" name="work_type" value="<?php echo e(old('work_type')); ?>"
                            class="w-full border rounded px-3 py-2"
                            placeholder="เช่น บทความวิจัย, โครงการบริการวิชาการ, กิจกรรมวัฒนธรรม">
                        <p class="text-gray-500 text-sm mt-1">ระบุประเภทของผลงาน</p>
                    </div>

                    <!-- Work Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">วันที่ทำผลงาน</label>
                        <input type="date" name="work_date" value="<?php echo e(old('work_date')); ?>"
                            class="w-full border rounded px-3 py-2">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">รายละเอียด</label>
                        <textarea name="description" class="w-full border rounded px-3 py-2" rows="4"
                            placeholder="รายละเอียดของผลงาน"><?php echo e(old('description')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- File Upload -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">ไฟล์เอกสาร</h3>
                </div>
                <div class="p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">แนบไฟล์ (ถ้ามี)</label>
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

            <!-- Settings -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">การตั้งค่า</h3>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Max Usage Count -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">จำนวนครั้งที่สามารถใช้ได้
                            *</label>
                        <input type="number" name="max_usage_count" value="<?php echo old('max_usage_count', 1); ?>"
                            class="w-full border rounded px-3 py-2 <?php echo error('max_usage_count') ? 'border-red-500' : ''; ?>"
                            min="1" max="10" required>
                        <?php if (error('max_usage_count')): ?>
                            <p class="text-red-600 text-sm mt-1"><?php echo error('max_usage_count'); ?></p>
                        <?php endif; ?>
                        <p class="text-gray-500 text-sm mt-1">กำหนดว่าผลงานนี้สามารถใช้ประกอบการประเมินได้กี่ครั้ง</p>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">แท็ก (Tags)</label>
                        <input type="text" name="tags" value="<?php echo e(old('tags')); ?>"
                            class="w-full border rounded px-3 py-2"
                            placeholder="เช่น วิจัย, นวัตกรรม, ชุมชน (คั่นด้วยเครื่องหมายจุลภาค)">
                        <p class="text-gray-500 text-sm mt-1">ใช้แท็กเพื่อช่วยในการค้นหาและจัดหมวดหมู่</p>
                    </div>

                    <!-- Is Shared -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_shared" value="1"
                                <?php echo old('is_shared') ? 'checked' : ''; ?> class="rounded text-blue-600">
                            <span class="ml-2 text-sm text-gray-700">
                                อนุญาตให้ผู้อื่นดูและใช้ผลงานนี้ได้
                            </span>
                        </label>
                        <p class="text-gray-500 text-sm mt-1 ml-6">เมื่อเปิดใช้งาน
                            ผู้อื่นจะสามารถค้นหาและอ้างอิงผลงานนี้ได้
                        </p>
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
                            <i class="fas fa-save mr-2"></i>บันทึกผลงาน
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
</script>

<?php
clearOldInput();
include APP_ROOT . '/includes/footer.php';
?>