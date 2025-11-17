<?php

/**
 * modules/portfolio/share.php
 * จัดการการแชร์ผลงานให้ผู้อื่นเห็นและใช้งานได้
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
authorize('portfolio.share');

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$portfolio_id = $_GET['id'] ?? 0;

// ดึงข้อมูลผลงาน
$stmt = $db->prepare("
    SELECT p.*, ea.aspect_name_th
    FROM portfolios p
    LEFT JOIN evaluation_aspects ea ON p.aspect_id = ea.aspect_id
    WHERE p.portfolio_id = ? AND p.user_id = ?
");
$stmt->execute([$portfolio_id, $user_id]);
$portfolio = $stmt->fetch();

if (!$portfolio) {
    flash_error('ไม่พบผลงานที่ต้องการจัดการ');
    redirect('modules/portfolio/index.php');
}

// ดึงสถิติการใช้งาน
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT ep.evaluation_id) as evaluation_count,
        COUNT(DISTINCT u.user_id) as user_count
    FROM evaluation_portfolios ep
    JOIN evaluations e ON ep.evaluation_id = e.evaluation_id
    JOIN users u ON e.user_id = u.user_id
    WHERE ep.portfolio_id = ? AND u.user_id != ?
");
$stmt->execute([$portfolio_id, $user_id]);
$usage_stats = $stmt->fetch();

// ดึงรายชื่อผู้ใช้งาน
$stmt = $db->prepare("
    SELECT DISTINCT u.user_id, u.full_name_th, u.position, d.department_name_th,
           COUNT(ep.link_id) as usage_count,
           MAX(ep.created_at) as last_used
    FROM evaluation_portfolios ep
    JOIN evaluations e ON ep.evaluation_id = e.evaluation_id
    JOIN users u ON e.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE ep.portfolio_id = ? AND u.user_id != ?
    GROUP BY u.user_id, u.full_name_th, u.position, d.department_name_th
    ORDER BY last_used DESC
");
$stmt->execute([$portfolio_id, $user_id]);
$users = $stmt->fetchAll();

// สลับสถานะการแชร์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
    $is_shared = isset($_POST['is_shared']) ? 1 : 0;

    try {
        $stmt = $db->prepare("
            UPDATE portfolios 
            SET is_shared = ?, updated_at = NOW()
            WHERE portfolio_id = ? AND user_id = ?
        ");
        $stmt->execute([$is_shared, $portfolio_id, $user_id]);

        // Log activity
        log_activity($is_shared ? 'share_portfolio' : 'unshare_portfolio', 'portfolios', $portfolio_id);

        flash_success($is_shared ? 'เปิดการแชร์ผลงานเรียบร้อยแล้ว' : 'ปิดการแชร์ผลงานเรียบร้อยแล้ว');
        redirect('modules/portfolio/share.php?id=' . $portfolio_id);
    } catch (Exception $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        log_error('Share Portfolio Error', ['error' => $e->getMessage()]);
    }
}

$page_title = 'จัดการการแชร์';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">จัดการการแชร์ผลงาน</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        ตั้งค่าการแชร์และดูสถิติการใช้งาน
                    </p>
                </div>
                <a href="<?php echo url('modules/portfolio/index.php'); ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left mr-2"></i>
                    ย้อนกลับ
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Portfolio Info -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">ข้อมูลผลงาน</h3>
                        <div class="space-y-3">
                            <div>
                                <span
                                    class="inline-block px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">
                                    <?php echo e($portfolio['aspect_name_th']); ?>
                                </span>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-900">
                                <?php echo e($portfolio['title']); ?>
                            </h4>
                            <?php if ($portfolio['work_type']): ?>
                                <p class="text-sm text-gray-600">
                                    ประเภท: <?php echo e($portfolio['work_type']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($portfolio['description']): ?>
                                <p class="text-gray-700 whitespace-pre-line">
                                    <?php echo e($portfolio['description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Share Settings -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">การตั้งค่าการแชร์</h3>
                        <form method="POST">
                            <?php echo csrf_field(); ?>

                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="is_shared" value="1"
                                        <?php echo $portfolio['is_shared'] ? 'checked' : ''; ?>
                                        class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        id="shareToggle">
                                </div>
                                <div class="ml-3 flex-1">
                                    <label for="shareToggle" class="font-medium text-gray-900 cursor-pointer">
                                        เปิดการแชร์ผลงานนี้
                                    </label>
                                    <p class="text-sm text-gray-600 mt-1">
                                        เมื่อเปิดการแชร์ ผู้อื่นจะสามารถค้นหา ดู และอ้างอิงผลงานนี้ได้
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-gray-200">
                                <button type="submit" class="btn btn-primary w-full">
                                    <i class="fas fa-save mr-2"></i>
                                    บันทึกการตั้งค่า
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Usage List -->
                <?php if (!empty($users)): ?>
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">รายชื่อผู้ใช้งาน</h3>
                            <div class="space-y-3">
                                <?php foreach ($users as $user): ?>
                                    <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                                        <div
                                            class="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-400 rounded-full flex items-center justify-center text-white font-medium mr-3 flex-shrink-0">
                                            <?php echo mb_substr($user['full_name_th'], 0, 2); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-gray-900">
                                                <?php echo e($user['full_name_th']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo e($user['position']); ?>
                                                <?php if ($user['department_name_th']): ?>
                                                    · <?php echo e($user['department_name_th']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <div class="mt-1 flex items-center text-xs text-gray-500">
                                                <span>ใช้งาน <?php echo $user['usage_count']; ?> ครั้ง</span>
                                                <span class="mx-2">•</span>
                                                <span>ล่าสุด <?php echo time_ago($user['last_used']); ?></span>
                                            </div>
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
                <!-- Stats -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">สถิติการใช้งาน</h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-600">สถานะ</span>
                                    <?php if ($portfolio['is_shared']): ?>
                                        <span
                                            class="inline-block px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                            กำลังแชร์
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="inline-block px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                            ไม่แชร์
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600 mb-1">จำนวนผู้ใช้</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php echo number_format($usage_stats['user_count']); ?>
                                </p>
                                <p class="text-xs text-gray-500">คน</p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600 mb-1">จำนวนครั้งที่ถูกอ้างอิง</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    <?php echo number_format($usage_stats['evaluation_count']); ?>
                                </p>
                                <p class="text-xs text-gray-500">ครั้ง</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Share Info -->
                <div class="bg-blue-50 rounded-lg border border-blue-200">
                    <div class="p-6">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 mr-3 mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-medium text-blue-900 mb-1">เกี่ยวกับการแชร์</h4>
                                <ul class="text-xs text-blue-700 space-y-1">
                                    <li>• ผู้อื่นสามารถค้นหาและดูผลงานได้</li>
                                    <li>• สามารถอ้างอิงไปใช้ในการประเมินได้</li>
                                    <li>• ไฟล์ต้นฉบับจะไม่ถูกเปลี่ยนแปลง</li>
                                    <li>• คุณสามารถปิดการแชร์ได้ตลอดเวลา</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">การดำเนินการ</h3>
                        <div class="space-y-2">
                            <a href="<?php echo url('modules/portfolio/edit.php?id=' . $portfolio_id); ?>"
                                class="btn btn-outline w-full">
                                <i class="fas fa-edit mr-2"></i>
                                แก้ไขผลงาน
                            </a>
                            <a href="<?php echo url('modules/portfolio/view.php?id=' . $portfolio_id); ?>"
                                class="btn btn-outline w-full">
                                <i class="fas fa-eye mr-2"></i>
                                ดูรายละเอียด
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>