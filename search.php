<?php

/**
 * /modules/portfolio/search.php
 * ค้นหาผลงานในระบบ (รวมผลงานที่ผู้อื่นแชร์)
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login
requireAuth();

$db = getDB();
$user_id = $_SESSION['user']['user_id'];

// Filters
$dimension_filter = $_GET['dimension'] ?? '';
$search = $_GET['search'] ?? '';
$owner_filter = $_GET['owner'] ?? 'all'; // all, own, shared

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Build query
$where = ["wp.is_active = 1"];
$params = [];

// Owner filter
if ($owner_filter === 'own') {
    $where[] = "wp.user_id = ?";
    $params[] = $user_id;
} elseif ($owner_filter === 'shared') {
    $where[] = "wp.user_id != ? AND wp.is_shared = 1";
    $params[] = $user_id;
} else {
    $where[] = "(wp.user_id = ? OR (wp.user_id != ? AND wp.is_shared = 1))";
    $params[] = $user_id;
    $params[] = $user_id;
}

if ($dimension_filter) {
    $where[] = "wp.dimension_id = ?";
    $params[] = $dimension_filter;
}

if ($search) {
    $where[] = "(wp.work_title LIKE ? OR wp.description LIKE ? OR wp.tags LIKE ? OR u.full_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where);

// Count total
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM work_portfolios wp
    JOIN users u ON wp.user_id = u.user_id
    WHERE $where_clause
");
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get portfolios
$stmt = $db->prepare("
    SELECT wp.*, 
           ed.dimension_name,
           u.full_name as owner_name,
           u.user_id as owner_id,
           wp.max_usage_count - wp.current_usage_count as remaining_uses,
           CASE WHEN wp.user_id = ? THEN 1 ELSE 0 END as is_own
    FROM work_portfolios wp
    JOIN evaluation_dimensions ed ON wp.dimension_id = ed.dimension_id
    JOIN users u ON wp.user_id = u.user_id
    WHERE $where_clause
    ORDER BY wp.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute(array_merge([$user_id], $params));
$portfolios = $stmt->fetchAll();

// Get dimensions
$stmt = $db->query("
    SELECT dimension_id, dimension_name 
    FROM evaluation_dimensions 
    WHERE is_active = 1 
    ORDER BY display_order
");
$dimensions = $stmt->fetchAll();

$pagination = paginate($total, $page, $per_page);

$page_title = 'ค้นหาผลงาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">ค้นหาผลงาน</h1>
        <p class="mt-1 text-sm text-gray-500">
            ค้นหาผลงานของคุณและผลงานที่ผู้อื่นแชร์ในระบบ
        </p>
    </div>

    <!-- Search & Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <form method="GET" class="space-y-4">
                <!-- Search Box -->
                <div>
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo e($search); ?>"
                            placeholder="ค้นหาจากชื่อผลงาน, รายละเอียด, แท็ก หรือชื่อผู้สร้าง..."
                            class="form-input pl-10">
                        <svg class="absolute left-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Filters Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Dimension Filter -->
                    <select name="dimension" class="form-select">
                        <option value="">มิติทั้งหมด</option>
                        <?php foreach ($dimensions as $dim): ?>
                            <option value="<?php echo $dim['dimension_id']; ?>"
                                <?php echo $dimension_filter == $dim['dimension_id'] ? 'selected' : ''; ?>>
                                <?php echo e($dim['dimension_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Owner Filter -->
                    <select name="owner" class="form-select">
                        <option value="all" <?php echo $owner_filter === 'all' ? 'selected' : ''; ?>>
                            ผลงานทั้งหมด
                        </option>
                        <option value="own" <?php echo $owner_filter === 'own' ? 'selected' : ''; ?>>
                            ผลงานของฉัน
                        </option>
                        <option value="shared" <?php echo $owner_filter === 'shared' ? 'selected' : ''; ?>>
                            ผลงานที่ผู้อื่นแชร์
                        </option>
                    </select>

                    <!-- Search Button -->
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        ค้นหา
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="mb-4">
        <p class="text-sm text-gray-600">
            พบ <span class="font-semibold text-gray-900"><?php echo number_format($total); ?></span> ผลงาน
            <?php if ($search): ?>
                จากการค้นหา "<span class="font-semibold"><?php echo e($search); ?></span>"
            <?php endif; ?>
        </p>
    </div>

    <!-- Results Grid -->
    <?php if (empty($portfolios)): ?>
        <div class="card">
            <div class="card-body text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">ไม่พบผลงาน</h3>
                <p class="text-gray-600">ลองเปลี่ยนคำค้นหาหรือปรับตัวกรองใหม่</p>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($portfolios as $portfolio): ?>
                <div class="portfolio-item <?php echo !$portfolio['is_own'] ? 'border-blue-200' : ''; ?>">
                    <!-- Owner Badge -->
                    <?php if (!$portfolio['is_own']): ?>
                        <div class="mb-2">
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <?php echo e($portfolio['owner_name']); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-start justify-between mb-3">
                        <span class="badge badge-primary text-xs">
                            <?php echo e($portfolio['dimension_name']); ?>
                        </span>
                        <?php if ($portfolio['is_own']): ?>
                            <a href="<?php echo url('modules/portfolio/edit.php?id=' . $portfolio['portfolio_id']); ?>"
                                class="action-button">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo url('modules/portfolio/claim.php?id=' . $portfolio['portfolio_id']); ?>"
                                class="action-button text-blue-600 hover:bg-blue-50" title="อ้างอิงผลงานนี้">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>

                    <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">
                        <?php echo e($portfolio['work_title']); ?>
                    </h3>

                    <?php if ($portfolio['work_type']): ?>
                        <p class="text-xs text-gray-500 mb-2">
                            ประเภท: <?php echo e($portfolio['work_type']); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($portfolio['description']): ?>
                        <p class="text-sm text-gray-600 mb-3 line-clamp-3">
                            <?php echo e($portfolio['description']); ?>
                        </p>
                    <?php endif; ?>

                    <!-- File Info -->
                    <?php if ($portfolio['file_name']): ?>
                        <div class="flex items-center text-xs text-gray-500 mb-3">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            มีไฟล์แนบ (<?php echo format_bytes($portfolio['file_size']); ?>)
                        </div>
                    <?php endif; ?>

                    <!-- Usage & Tags -->
                    <div class="pt-3 border-t border-gray-200 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <?php if ($portfolio['remaining_uses'] > 0): ?>
                                <span class="text-green-600 font-medium">
                                    ใช้ได้อีก <?php echo $portfolio['remaining_uses']; ?> ครั้ง
                                </span>
                            <?php else: ?>
                                <span class="text-red-600 font-medium">
                                    ใช้ครบแล้ว
                                </span>
                            <?php endif; ?>
                            <span class="text-gray-400">
                                <?php echo time_ago($portfolio['created_at']); ?>
                            </span>
                        </div>

                        <?php if ($portfolio['tags']): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach (array_slice(explode(',', $portfolio['tags']), 0, 3) as $tag): ?>
                                    <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                        #<?php echo e(trim($tag)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['last_page'] > 1): ?>
            <div class="mt-6 flex justify-center">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_except($_GET, ['page'])); ?>"
                            class="pagination-btn">← ก่อนหน้า</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($pagination['last_page'], $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_except($_GET, ['page'])); ?>"
                            class="pagination-btn <?php echo $i === $page ? 'pagination-active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $pagination['last_page']): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_except($_GET, ['page'])); ?>"
                            class="pagination-btn">ถัดไป →</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>