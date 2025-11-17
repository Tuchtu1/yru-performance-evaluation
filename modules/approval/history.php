<?php

/**
 * /modules/approval/history.php
 * หน้าประวัติการพิจารณาแบบประเมิน
 * สำหรับผู้บริหาร (Manager) และผู้ดูแลระบบ (Admin)
 */

session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

requirePermission('approval.history');

$current_user = $_SESSION['user'];
$page_title = 'ประวัติการพิจารณา';
$db = getDB();

// Filters
$action_filter = input('action', 'all');
$date_from = input('date_from', '');
$date_to = input('date_to', '');
$search = input('search', '');
$page = (int)input('page', 1);

// สร้าง WHERE clause
$where_conditions = [];
$params = [];

// ถ้าเป็น manager ให้ดูเฉพาะที่ตัวเองพิจารณา
if ($current_user['role'] === 'manager') {
    $where_conditions[] = "ah.manager_user_id = ?";
    $params[] = $current_user['user_id'];
}

// Filter by action
if ($action_filter && $action_filter !== 'all') {
    $where_conditions[] = "ah.action = ?";
    $params[] = $action_filter;
}

// Filter by date range
if ($date_from) {
    $where_conditions[] = "DATE(ah.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(ah.created_at) <= ?";
    $params[] = $date_to;
}

// Search
if ($search) {
    $where_conditions[] = "(u.full_name_th LIKE ? OR evaluator.full_name_th LIKE ? OR ep.period_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// นับจำนวนทั้งหมด
$count_sql = "
    SELECT COUNT(*) as total
    FROM approval_history ah
    LEFT JOIN users u ON ah.manager_user_id = u.user_id
    LEFT JOIN evaluations e ON ah.evaluation_id = e.evaluation_id
    LEFT JOIN users evaluator ON e.user_id = evaluator.user_id
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    $where_sql
";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];

// Pagination
$pagination = paginate($total, $page);

// ดึงข้อมูล
$sql = "
    SELECT 
        ah.*,
        u.full_name_th as manager_name,
        u.position as manager_position,
        e.evaluation_id,
        e.total_score,
        evaluator.full_name_th as evaluator_name,
        evaluator.personnel_type,
        evaluator.position as evaluator_position,
        d.department_name_th,
        ep.period_name,
        ep.year
    FROM approval_history ah
    LEFT JOIN users u ON ah.manager_user_id = u.user_id
    LEFT JOIN evaluations e ON ah.evaluation_id = e.evaluation_id
    LEFT JOIN users evaluator ON e.user_id = evaluator.user_id
    LEFT JOIN departments d ON evaluator.department_id = d.department_id
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    $where_sql
    ORDER BY ah.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$histories = $stmt->fetchAll();

// สถิติ
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN ah.action = 'approve' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN ah.action = 'reject' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN ah.action = 'return' THEN 1 ELSE 0 END) as returned
    FROM approval_history ah
    " . ($current_user['role'] === 'manager' ? "WHERE ah.manager_user_id = ?" : "WHERE DATE(ah.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

$stats_stmt = $db->prepare($stats_sql);
if ($current_user['role'] === 'manager') {
    $stats_stmt->execute([$current_user['user_id']]);
} else {
    $stats_stmt->execute();
}
$stats = $stats_stmt->fetch();

$action_names = [
    'submit' => 'ส่งแบบประเมิน',
    'approve' => 'อนุมัติ',
    'reject' => 'ไม่อนุมัติ',
    'return' => 'ส่งกลับแก้ไข'
];

$action_colors = [
    'submit' => 'bg-blue-100 text-blue-800',
    'approve' => 'bg-green-100 text-green-800',
    'reject' => 'bg-red-100 text-red-800',
    'return' => 'bg-orange-100 text-orange-800'
];

include APP_ROOT . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900"><?php echo $page_title; ?></h1>
    <p class="text-gray-500 mt-1">ประวัติการพิจารณาและอนุมัติแบบประเมินทั้งหมด</p>
</div>

<!-- สถิติ -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">ทั้งหมด</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
            </div>
            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">อนุมัติ</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $stats['approved']; ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">ไม่อนุมัติ</p>
                <p class="text-3xl font-bold text-red-600"><?php echo $stats['rejected']; ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">ส่งกลับแก้ไข</p>
                <p class="text-3xl font-bold text-orange-600"><?php echo $stats['returned']; ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="form-label">การดำเนินการ</label>
            <select name="action" class="form-select" onchange="this.form.submit()">
                <option value="all" <?php echo $action_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                <option value="approve" <?php echo $action_filter === 'approve' ? 'selected' : ''; ?>>อนุมัติ</option>
                <option value="reject" <?php echo $action_filter === 'reject' ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                <option value="return" <?php echo $action_filter === 'return' ? 'selected' : ''; ?>>ส่งกลับแก้ไข
                </option>
            </select>
        </div>

        <div>
            <label class="form-label">วันที่เริ่มต้น</label>
            <input type="date" name="date_from" value="<?php echo e($date_from); ?>" class="form-input">
        </div>

        <div>
            <label class="form-label">วันที่สิ้นสุด</label>
            <input type="date" name="date_to" value="<?php echo e($date_to); ?>" class="form-input">
        </div>

        <div class="md:col-span-2">
            <label class="form-label">ค้นหา</label>
            <div class="flex space-x-2">
                <input type="text" name="search" value="<?php echo e($search); ?>" class="form-input flex-1"
                    placeholder="ชื่อผู้ประเมิน, ผู้พิจารณา...">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
                <?php if ($search || $action_filter !== 'all' || $date_from || $date_to): ?>
                    <a href="history.php" class="btn btn-outline">รีเซ็ต</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- รายการประวัติ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($histories)): ?>
        <div class="p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-gray-500 text-lg">ไม่มีประวัติการพิจารณา</p>
        </div>
    <?php else: ?>
        <!-- Timeline View -->
        <div class="p-6">
            <div class="space-y-6">
                <?php foreach ($histories as $history): ?>
                    <div class="flex items-start space-x-4 pb-6 border-b border-gray-100 last:border-0 last:pb-0">
                        <div class="flex-shrink-0 mt-1">
                            <div
                                class="w-12 h-12 rounded-full flex items-center justify-center
                                        <?php echo str_replace('text-', 'bg-', $action_colors[$history['action']]) ?? 'bg-gray-100'; ?>">
                                <?php if ($history['action'] === 'approve'): ?>
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                <?php elseif ($history['action'] === 'reject'): ?>
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                <?php elseif ($history['action'] === 'return'): ?>
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900">
                                        <?php echo e($history['manager_name']); ?>
                                        <span
                                            class="badge ml-2 <?php echo $action_colors[$history['action']] ?? 'badge-gray'; ?>">
                                            <?php echo $action_names[$history['action']] ?? $history['action']; ?>
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <?php echo e($history['manager_position']); ?>
                                    </p>
                                </div>
                                <div class="text-right ml-4">
                                    <p class="text-sm text-gray-500">
                                        <?php echo thai_date($history['created_at'], 'datetime'); ?>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        <?php echo time_ago($history['created_at']); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- ข้อมูลผู้ประเมิน -->
                            <div class="bg-gray-50 rounded-lg p-4 mb-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">ผู้ประเมิน</p>
                                        <p class="font-medium text-gray-900 mt-1">
                                            <?php echo e($history['evaluator_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?php echo e($history['evaluator_position']); ?> -
                                            <?php echo e($history['department_name_th']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            <?php echo e($history['period_name']); ?> (<?php echo e($history['year']); ?>)
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-500">คะแนน</p>
                                        <p class="text-2xl font-bold text-blue-600">
                                            <?php echo number_format_thai($history['total_score']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Comment -->
                            <?php if ($history['comment']): ?>
                                <div class="bg-white border border-gray-200 rounded-lg p-4">
                                    <p class="text-sm font-medium text-gray-700 mb-2">ความเห็น:</p>
                                    <p class="text-sm text-gray-900">
                                        <?php echo nl2br(e($history['comment'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Quick actions -->
                            <div class="flex items-center space-x-3 mt-3">
                                <a href="review.php?id=<?php echo $history['evaluation_id']; ?>"
                                    class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                    ดูรายละเอียด →
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['last_page'] > 1): ?>
            <div class="px-6 py-4 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        แสดง <?php echo $pagination['from']; ?> ถึง <?php echo $pagination['to']; ?>
                        จากทั้งหมด <?php echo $pagination['total']; ?> รายการ
                    </p>

                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>"
                                class="pagination-btn">← ก่อนหน้า</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($pagination['last_page'], $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>"
                                class="pagination-btn <?php echo $i === $page ? 'pagination-active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pagination['last_page']): ?>
                            <a href="?page=<?php echo $page + 1; ?>&action=<?php echo $action_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>"
                                class="pagination-btn">ถัดไป →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>