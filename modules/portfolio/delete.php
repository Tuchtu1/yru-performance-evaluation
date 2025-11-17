<?php

/**
 * modules/portfolio/delete.php
 * ลบผลงานออกจากคลัง
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
authorize('portfolio.delete_own');

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$portfolio_id = $_GET['id'] ?? 0;

// ดึงข้อมูลผลงาน
$stmt = $db->prepare("
    SELECT p.*, 
           ea.aspect_name_th,
           (SELECT COUNT(*) FROM evaluation_portfolios WHERE portfolio_id = p.portfolio_id) as usage_count
    FROM portfolios p
    LEFT JOIN evaluation_aspects ea ON p.aspect_id = ea.aspect_id
    WHERE p.portfolio_id = ? AND p.user_id = ?
");
$stmt->execute([$portfolio_id, $user_id]);
$portfolio = $stmt->fetch();

if (!$portfolio) {
    flash_error('ไม่พบผลงานที่ต้องการลบ');
    redirect('modules/portfolio/index.php');
}

// ตรวจสอบว่ามีการใช้งานหรือไม่
if ($portfolio['usage_count'] > 0) {
    flash_error('ไม่สามารถลบผลงานที่ถูกใช้งานแล้วได้');
    redirect('modules/portfolio/index.php');
}

// ลบผลงาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
    try {
        $db->beginTransaction();

        // ลบไฟล์
        if ($portfolio['file_path']) {
            delete_file($portfolio['file_path']);
        }

        // ลบข้อมูลในฐานข้อมูล
        $stmt = $db->prepare("DELETE FROM portfolios WHERE portfolio_id = ? AND user_id = ?");
        $stmt->execute([$portfolio_id, $user_id]);

        // Log activity
        log_activity('delete_portfolio', 'portfolios', $portfolio_id, [
            'title' => $portfolio['title']
        ]);

        $db->commit();

        flash_success('ลบผลงานเรียบร้อยแล้ว');
        redirect('modules/portfolio/index.php');
    } catch (Exception $e) {
        $db->rollBack();
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        log_error('Delete Portfolio Error', ['error' => $e->getMessage(), 'portfolio_id' => $portfolio_id]);
        redirect('modules/portfolio/index.php');
    }
}

$page_title = 'ลบผลงาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">ยืนยันการลบผลงาน</h1>
        </div>

        <!-- Warning Card -->
        <div class="bg-white rounded-lg shadow border-red-200 border-2">
            <div class="p-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-medium text-red-900 mb-2">
                            คุณแน่ใจหรือไม่ที่จะลบผลงานนี้?
                        </h3>
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <p class="font-medium text-gray-900 mb-2">
                                <?php echo e($portfolio['title']); ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                ด้าน: <?php echo e($portfolio['aspect_name_th'] ?? 'ไม่ระบุ'); ?>
                            </p>
                            <?php if ($portfolio['file_name']): ?>
                                <p class="text-sm text-gray-600">
                                    ไฟล์: <?php echo e($portfolio['file_name']); ?>
                                    (<?php echo format_bytes($portfolio['file_size']); ?>)
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="bg-red-50 rounded-lg p-4">
                            <p class="text-sm text-red-800 mb-2 font-medium">คำเตือน:</p>
                            <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
                                <li>การลบผลงานนี้ไม่สามารถยกเลิกได้</li>
                                <li>ไฟล์เอกสารที่แนบมาจะถูกลบออกจากระบบ</li>
                                <li>ข้อมูลทั้งหมดจะสูญหายถาวร</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Form -->
        <form method="POST" class="mt-6">
            <?php echo csrf_field(); ?>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <a href="<?php echo url('modules/portfolio/index.php'); ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left mr-2"></i>
                        ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash mr-2"></i>
                        ยืนยันการลบ
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>