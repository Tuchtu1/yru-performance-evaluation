<?php

/**
 * modules/portfolio/claim.php
 * อ้างอิงผลงานที่ผู้อื่นแชร์มาใช้กับตนเอง
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
authorize('portfolio.claim');

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$source_portfolio_id = $_GET['id'] ?? 0;

// ดึงข้อมูลผลงานต้นฉบับ
$stmt = $db->prepare("
    SELECT p.*, ea.aspect_name_th, u.full_name_th as owner_name
    FROM portfolios p
    LEFT JOIN evaluation_aspects ea ON p.aspect_id = ea.aspect_id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE p.portfolio_id = ? 
    AND p.user_id != ? 
    AND p.is_shared = 1
");
$stmt->execute([$source_portfolio_id, $user_id]);
$source_portfolio = $stmt->fetch();

if (!$source_portfolio) {
    flash_error('ไม่พบผลงานที่ต้องการอ้างอิง หรือผลงานนี้ไม่อนุญาตให้อ้างอิง');
    redirect('modules/portfolio/search.php');
}

// ตรวจสอบว่าเคยอ้างอิงแล้วหรือยัง
$stmt = $db->prepare("
    SELECT portfolio_id 
    FROM portfolios 
    WHERE user_id = ? AND title = ? AND description LIKE ?
    LIMIT 1
");
$stmt->execute([
    $user_id,
    $source_portfolio['title'],
    '%อ้างอิงจาก: ' . $source_portfolio['owner_name'] . '%'
]);
$existing = $stmt->fetch();

if ($existing) {
    flash_error('คุณได้อ้างอิงผลงานนี้ไปแล้ว');
    redirect('modules/portfolio/index.php');
}

// บันทึกการอ้างอิง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
    $custom_note = trim($_POST['custom_note'] ?? '');

    try {
        $db->beginTransaction();

        // สร้างผลงานใหม่โดยอ้างอิงจากต้นฉบับ
        $description = $source_portfolio['description'];
        if ($custom_note) {
            $description .= "\n\n" . $custom_note;
        }
        $description .= "\n\n[อ้างอิงจาก: " . $source_portfolio['owner_name'] . " - " . thai_date($source_portfolio['created_at']) . "]";

        $stmt = $db->prepare("
            INSERT INTO portfolios 
            (user_id, aspect_id, title, work_type, description, 
             max_usage_count, tags, is_shared, file_path, file_name, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $source_portfolio['aspect_id'],
            $source_portfolio['title'],
            $source_portfolio['work_type'],
            $description,
            1, // ให้ใช้ได้ครั้งเดียว
            $source_portfolio['tags'],
            $source_portfolio['file_path'],
            $source_portfolio['file_name'],
            $source_portfolio['file_size']
        ]);

        $new_portfolio_id = $db->lastInsertId();

        // Log activity
        log_activity('claim_portfolio', 'portfolios', $new_portfolio_id, [
            'source_portfolio_id' => $source_portfolio_id,
            'source_owner' => $source_portfolio['owner_name']
        ]);

        // สร้างการแจ้งเตือนให้เจ้าของต้นฉบับ
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, related_id, related_type)
            VALUES (?, 'มีผู้อ้างอิงผลงานของคุณ', ?, 'portfolio_claim', ?, 'portfolios')
        ");
        $stmt->execute([
            $source_portfolio['user_id'],
            $_SESSION['user']['full_name_th'] . ' ได้อ้างอิงผลงาน "' . $source_portfolio['title'] . '" ของคุณ',
            $new_portfolio_id
        ]);

        $db->commit();

        flash_success('อ้างอิงผลงานเรียบร้อยแล้ว สามารถใช้ในการประเมินของคุณได้');
        redirect('modules/portfolio/index.php');
    } catch (Exception $e) {
        $db->rollBack();
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        log_error('Claim Portfolio Error', ['error' => $e->getMessage()]);
    }
}

$page_title = 'อ้างอิงผลงาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">อ้างอิงผลงาน</h1>
            <p class="mt-1 text-sm text-gray-500">
                นำผลงานที่ผู้อื่นแชร์มาใช้ในการประเมินของคุณ
            </p>
        </div>

        <!-- Info -->
        <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r">
            <div class="flex">
                <i class="fas fa-info-circle text-blue-400 mr-3 mt-0.5"></i>
                <div>
                    <p class="text-sm font-medium text-blue-900">เกี่ยวกับการอ้างอิงผลงาน</p>
                    <p class="text-sm text-blue-700 mt-1">
                        การอ้างอิงผลงานจะสร้างสำเนาผลงานนี้มาใส่ในคลังผลงานของคุณ
                        โดยระบุแหล่งที่มาจากเจ้าของต้นฉบับ และสามารถเพิ่มหมายเหตุของคุณเองได้
                    </p>
                </div>
            </div>
        </div>

        <!-- Source Portfolio -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">ผลงานต้นฉบับ</h3>
                <div class="space-y-4">
                    <!-- Owner -->
                    <div>
                        <label class="text-sm text-gray-600">เจ้าของผลงาน</label>
                        <div class="flex items-center mt-1">
                            <div
                                class="w-10 h-10 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white font-medium mr-3">
                                <?php echo mb_substr($source_portfolio['owner_name'], 0, 2); ?>
                            </div>
                            <p class="font-medium text-gray-900"><?php echo e($source_portfolio['owner_name']); ?></p>
                        </div>
                    </div>

                    <!-- Aspect -->
                    <div>
                        <label class="text-sm text-gray-600">ด้าน</label>
                        <p class="mt-1">
                            <span
                                class="inline-block px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                                <?php echo e($source_portfolio['aspect_name_th']); ?>
                            </span>
                        </p>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="text-sm text-gray-600">ชื่อผลงาน</label>
                        <p class="mt-1 font-medium text-gray-900">
                            <?php echo e($source_portfolio['title']); ?>
                        </p>
                    </div>

                    <!-- Type -->
                    <?php if ($source_portfolio['work_type']): ?>
                        <div>
                            <label class="text-sm text-gray-600">ประเภท</label>
                            <p class="mt-1 text-gray-900">
                                <?php echo e($source_portfolio['work_type']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Description -->
                    <?php if ($source_portfolio['description']): ?>
                        <div>
                            <label class="text-sm text-gray-600">รายละเอียด</label>
                            <p class="mt-1 text-gray-900 whitespace-pre-line">
                                <?php echo e($source_portfolio['description']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- File -->
                    <?php if ($source_portfolio['file_name']): ?>
                        <div>
                            <label class="text-sm text-gray-600">ไฟล์แนบ</label>
                            <div class="mt-1 flex items-center text-sm text-gray-600 bg-gray-50 p-3 rounded">
                                <i class="fas fa-file-alt mr-2 text-gray-400"></i>
                                <?php echo e($source_portfolio['file_name']); ?>
                                (<?php echo format_bytes($source_portfolio['file_size']); ?>)
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tags -->
                    <?php if ($source_portfolio['tags']): ?>
                        <div>
                            <label class="text-sm text-gray-600">แท็ก</label>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <?php foreach (explode(',', $source_portfolio['tags']) as $tag): ?>
                                    <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                        #<?php echo e(trim($tag)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Claim Form -->
        <form method="POST">
            <?php echo csrf_field(); ?>

            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">หมายเหตุเพิ่มเติม (ถ้ามี)</h3>
                    <textarea name="custom_note"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        rows="4" placeholder="เพิ่มหมายเหตุหรือความคิดเห็นของคุณเกี่ยวกับผลงานนี้..."></textarea>
                    <p class="mt-2 text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        หมายเหตุนี้จะถูกเพิ่มเข้าไปในรายละเอียดผลงานที่อ้างอิง
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <a href="<?php echo url('modules/portfolio/search.php'); ?>" class="btn btn-outline">
                            <i class="fas fa-times mr-2"></i>
                            ยกเลิก
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-copy mr-2"></i>
                            ยืนยันการอ้างอิง
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>