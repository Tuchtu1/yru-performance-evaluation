<?php

/**
 * modules/portfolio/index.php
 * หน้าแสดงคลังผลงานของบุคลากร (แก้ไขแล้ว)
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();

if (!can('portfolio.view_own')) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('dashboard/index.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user']['user_id'];

// Filters
$aspect_filter = $_GET['aspect'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

try {
    // Build query - ใช้ชื่อตาราง portfolios
    $where = ["p.user_id = ?"];
    $params = [$user_id];

    if ($aspect_filter) {
        $where[] = "p.aspect_id = ?";
        $params[] = $aspect_filter;
    }

    if ($search) {
        $where[] = "(p.title LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $where_clause = implode(' AND ', $where);

    // Count total
    $stmt = $db->prepare("SELECT COUNT(*) FROM portfolios p WHERE $where_clause");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Get portfolios - ใช้ชื่อตาราง portfolios ตามฐานข้อมูล
    $stmt = $db->prepare("
        SELECT p.*, 
               ea.aspect_name_th,
               ea.aspect_code,
               p.max_usage_count - p.current_usage_count as remaining_uses
        FROM portfolios p
        LEFT JOIN evaluation_aspects ea ON p.aspect_id = ea.aspect_id
        WHERE $where_clause
        ORDER BY p.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $portfolios = $stmt->fetchAll();

    // Get aspects for filter - ใช้ evaluation_aspects
    $stmt = $db->query("
        SELECT aspect_id, aspect_code, aspect_name_th 
        FROM evaluation_aspects 
        WHERE is_active = 1 
        ORDER BY display_order
    ");
    $aspects = $stmt->fetchAll();

    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_portfolios,
            SUM(CASE WHEN is_shared = 1 THEN 1 ELSE 0 END) as shared_portfolios,
            SUM(current_usage_count) as total_usage
        FROM portfolios
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    $pagination = paginate($total, $page, $per_page);
} catch (Exception $e) {
    error_log('Portfolio Index Error: ' . $e->getMessage());
    $portfolios = [];
    $aspects = [];
    $stats = ['total_portfolios' => 0, 'shared_portfolios' => 0, 'total_usage' => 0];
    $pagination = ['last_page' => 1];
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

$page_title = 'คลังผลงาน';
include APP_ROOT . '/includes/header.php';
?>

<!-- โหลด Alpine.js ที่ส่วนหัวก่อน -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">คลังผลงานของฉัน</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        จัดการและเก็บรักษาผลงานเพื่อใช้ประกอบการประเมิน
                    </p>
                </div>
                <?php if (can('portfolio.create')): ?>
                    <a href="<?php echo url('modules/portfolio/add.php'); ?>"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        เพิ่มผลงานใหม่
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">ผลงานทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">
                            <?php echo number_format($stats['total_portfolios']); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-briefcase text-2xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">ผลงานที่แชร์</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">
                            <?php echo number_format($stats['shared_portfolios']); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-share-alt text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">ครั้งที่ใช้งาน</p>
                        <p class="text-3xl font-bold text-purple-600 mt-1">
                            <?php echo number_format($stats['total_usage']); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-bar text-2xl text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-1">
                        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="ค้นหาผลงาน..."
                            class="w-full border rounded px-3 py-2">
                    </div>

                    <!-- Aspect Filter -->
                    <div>
                        <select name="aspect" class="w-full border rounded px-3 py-2">
                            <option value="">ด้านทั้งหมด</option>
                            <?php foreach ($aspects as $aspect): ?>
                                <option value="<?php echo $aspect['aspect_id']; ?>"
                                    <?php echo $aspect_filter == $aspect['aspect_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($aspect['aspect_name_th']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex-1">
                            <i class="fas fa-search mr-2"></i>ค้นหา
                        </button>
                        <?php if ($search || $aspect_filter): ?>
                            <a href="<?php echo url('modules/portfolio/index.php'); ?>"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Portfolio Grid -->
        <?php if (empty($portfolios)): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="p-12 text-center">
                    <i class="fas fa-briefcase text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">ยังไม่มีผลงาน</h3>
                    <p class="text-gray-600 mb-4">เริ่มต้นสร้างคลังผลงานของคุณเพื่อใช้ประกอบการประเมิน</p>
                    <?php if (can('portfolio.create')): ?>
                        <a href="<?php echo url('modules/portfolio/add.php'); ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded inline-block">
                            <i class="fas fa-plus mr-2"></i>เพิ่มผลงานแรก
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($portfolios as $portfolio): ?>
                    <div class="bg-white rounded-lg shadow hover:shadow-lg transition-all duration-200 overflow-hidden">
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex items-start justify-between mb-3">
                                <span class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                    <?php echo e($portfolio['aspect_name_th'] ?? 'ไม่ระบุด้าน'); ?>
                                </span>
                                <!-- ปุ่มดูรายละเอียด -->
                                <a href="<?php echo url('modules/portfolio/view.php?id=' . $portfolio['portfolio_id']); ?>"
                                    class="text-blue-600 hover:text-blue-800 p-1" title="ดูรายละเอียด">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- ปุ่มแก้ไข -->
                                <?php if (can('portfolio.edit_own') && $portfolio['user_id'] == $user_id): ?>
                                    <a href="<?php echo url('modules/portfolio/edit.php?id=' . $portfolio['portfolio_id']); ?>"
                                        class="text-green-600 hover:text-green-800 p-1" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Dropdown menu -->
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="text-gray-400 hover:text-gray-600 p-1"
                                        title="ตัวเลือกเพิ่มเติม">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div x-show="open" @click.away="open = false"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10"
                                        style="display: none;">

                                        <!-- จัดการการแชร์ -->
                                        <?php if (can('portfolio.share') && $portfolio['user_id'] == $user_id): ?>
                                            <a href="<?php echo url('modules/portfolio/share.php?id=' . $portfolio['portfolio_id']); ?>"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-share-alt mr-2"></i>จัดการการแชร์
                                            </a>
                                        <?php endif; ?>

                                        <!-- ดาวน์โหลดไฟล์ -->
                                        <?php if ($portfolio['file_name']): ?>
                                            <a href="<?php echo url('modules/portfolio/download.php?id=' . $portfolio['portfolio_id']); ?>"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-download mr-2"></i>ดาวน์โหลดไฟล์
                                            </a>
                                        <?php endif; ?>

                                        <hr class="my-1">

                                        <!-- ลบ -->
                                        <?php if (can('portfolio.delete_own') && $portfolio['user_id'] == $user_id): ?>
                                            <a href="<?php echo url('modules/portfolio/delete.php?id=' . $portfolio['portfolio_id']); ?>"
                                                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                                onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผลงานนี้?');">
                                                <i class="fas fa-trash mr-2"></i>ลบ
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Title -->
                            <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">
                                <a href="<?php echo url('modules/portfolio/view.php?id=' . $portfolio['portfolio_id']); ?>"
                                    class="hover:text-blue-600">
                                    <?php echo e($portfolio['title']); ?>
                                </a>
                            </h3>

                            <!-- Work Type -->
                            <?php if ($portfolio['work_type']): ?>
                                <p class="text-xs text-gray-500 mb-2">
                                    <i class="fas fa-tag mr-1"></i>
                                    ประเภท: <?php echo e($portfolio['work_type']); ?>
                                </p>
                            <?php endif; ?>

                            <!-- Description -->
                            <?php if ($portfolio['description']): ?>
                                <p class="text-sm text-gray-600 mb-3 line-clamp-2">
                                    <?php echo e($portfolio['description']); ?>
                                </p>
                            <?php endif; ?>

                            <!-- File Info -->
                            <?php if ($portfolio['file_name']):
                                $file_ext = strtolower(pathinfo($portfolio['file_name'], PATHINFO_EXTENSION));
                                $can_preview = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);
                            ?>
                                <div class="flex items-center text-xs text-gray-500 mb-3 bg-gray-50 p-2 rounded">
                                    <i class="fas fa-file mr-2 text-gray-400"></i>
                                    <span class="truncate flex-1">
                                        <?php echo e($portfolio['file_name']); ?>
                                    </span>
                                    <?php if ($portfolio['file_size']): ?>
                                        <span class="ml-2 text-gray-400">
                                            <?php echo format_bytes($portfolio['file_size']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($can_preview): ?>
                                    <div class="mb-3">
                                        <a href="<?php echo url('modules/portfolio/view-file.php?id=' . $portfolio['portfolio_id']); ?>"
                                            target="_blank" class="inline-flex items-center text-xs text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-external-link-alt mr-1"></i>
                                            เปิดดูไฟล์ในหน้าต่างใหม่
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Usage Info -->
                            <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                                <div class="flex items-center text-xs">
                                    <?php if ($portfolio['remaining_uses'] > 0): ?>
                                        <span class="text-green-600 font-medium">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            ใช้ได้อีก <?php echo $portfolio['remaining_uses']; ?> ครั้ง
                                        </span>
                                    <?php else: ?>
                                        <span class="text-red-600 font-medium">
                                            <i class="fas fa-times-circle mr-1"></i>
                                            ใช้ครบแล้ว
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if ($portfolio['is_shared']): ?>
                                        <span class="text-blue-600" title="แชร์ได้">
                                            <i class="fas fa-share-alt"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tags -->
                            <?php if ($portfolio['tags']): ?>
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <?php foreach (explode(',', $portfolio['tags']) as $tag): ?>
                                        <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                            #<?php echo e(trim($tag)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Footer with Quick Actions -->
                            <div class="mt-3 pt-3 border-t border-gray-200 flex items-center justify-between">
                                <!-- Left: Time -->
                                <div class="text-xs text-gray-400">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo time_ago($portfolio['created_at']); ?>
                                </div>

                                <!-- Right: Quick Action Buttons -->
                                <div class="flex items-center space-x-1">
                                    <!-- ปุ่มดู -->
                                    <a href="<?php echo url('modules/portfolio/view.php?id=' . $portfolio['portfolio_id']); ?>"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors"
                                        title="ดูรายละเอียด">
                                        <i class="fas fa-eye mr-1"></i>
                                        ดู
                                    </a>

                                    <!-- ปุ่มแก้ไข -->
                                    <?php if (can('portfolio.edit_own') && $portfolio['user_id'] == $user_id): ?>
                                        <a href="<?php echo url('modules/portfolio/edit.php?id=' . $portfolio['portfolio_id']); ?>"
                                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-600 hover:text-green-800 hover:bg-green-50 rounded transition-colors"
                                            title="แก้ไข">
                                            <i class="fas fa-edit mr-1"></i>
                                            แก้ไข
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['last_page'] > 1): ?>
                <div class="mt-6 flex justify-center">
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_except($_GET, ['page'])); ?>"
                                class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-50">
                                ← ก่อนหน้า
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($pagination['last_page'], $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_except($_GET, ['page'])); ?>"
                                class="px-4 py-2 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pagination['last_page']): ?>
                            <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_except($_GET, ['page'])); ?>"
                                class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-50">
                                ถัดไป →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>