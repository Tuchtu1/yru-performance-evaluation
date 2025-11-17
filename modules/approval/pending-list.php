<?php

/**
 * modules/approval/pending-list.php
 * หน้ารายการแบบประเมินรอพิจารณา
 * สำหรับผู้บริหาร (Manager) และผู้ดูแลระบบ (Admin)
 */

session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบสิทธิ์ - เฉพาะ Manager และ Admin
requirePermission('approval.view_pending');

$current_user = $_SESSION['user'];
$page_title = 'รายการรออนุมัติ';
$db = getDB();

// Filters
$status_filter = input('status', 'submitted');
$search = input('search', '');
$page = (int)input('page', 1);

// สร้าง WHERE clause
$where_conditions = [];
$params = [];

// ถ้าเป็น manager ให้ดูเฉพาะที่ถูกมอบหมายให้
if ($current_user['role'] === 'manager') {
    $where_conditions[] = "em.manager_user_id = ?";
    $params[] = $current_user['user_id'];
}

// Filter by status
if ($status_filter && $status_filter !== 'all') {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
} else {
    $where_conditions[] = "e.status IN ('submitted', 'under_review')";
}

// Search
if ($search) {
    $where_conditions[] = "(u.full_name_th LIKE ? OR ep.period_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// นับจำนวนทั้งหมด
$count_sql = "
    SELECT COUNT(DISTINCT e.evaluation_id) as total
    FROM evaluations e
    LEFT JOIN evaluation_managers em ON e.evaluation_id = em.evaluation_id
    LEFT JOIN users u ON e.user_id = u.user_id
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
        e.*,
        u.full_name_th,
        u.personnel_type,
        u.position,
        d.department_name_th,
        ep.period_name,
        ep.year,
        COUNT(DISTINCT em.em_id) as total_managers,
        COUNT(DISTINCT CASE WHEN em.status = 'approved' THEN em.em_id END) as approved_count
    FROM evaluations e
    LEFT JOIN evaluation_managers em ON e.evaluation_id = em.evaluation_id
    LEFT JOIN users u ON e.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    $where_sql
    GROUP BY e.evaluation_id
    ORDER BY e.submitted_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$evaluations = $stmt->fetchAll();

// สถิติ
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN e.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN e.status = 'under_review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_today
    FROM evaluations e
    " . ($current_user['role'] === 'manager'
    ? "LEFT JOIN evaluation_managers em ON e.evaluation_id = em.evaluation_id WHERE em.manager_user_id = ?"
    : "WHERE DATE(e.submitted_at) = CURDATE()");

$stats_stmt = $db->prepare($stats_sql);
if ($current_user['role'] === 'manager') {
    $stats_stmt->execute([$current_user['user_id']]);
} else {
    $stats_stmt->execute();
}
$stats = $stats_stmt->fetch();

include APP_ROOT . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900"><?php echo $page_title; ?></h1>
    <p class="text-gray-500 mt-1">พิจารณาและอนุมัติแบบประเมินผลการปฏิบัติงาน</p>
</div>

<!-- สถิติ -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">รอพิจารณา</p>
                <p class="text-3xl font-bold text-blue-600"><?php echo $stats['submitted']; ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">กำลังตรวจสอบ</p>
                <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['under_review']; ?></p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">อนุมัติวันนี้</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $stats['approved_today']; ?></p>
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
                <p class="text-sm font-medium text-gray-500 mb-1">ทั้งหมด</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
            </div>
            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="form-label">สถานะ</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>ส่งแล้ว
                </option>
                <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>
                    กำลังตรวจสอบ</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="form-label">ค้นหา</label>
            <div class="flex space-x-2">
                <input type="text" name="search" value="<?php echo e($search); ?>" class="form-input flex-1"
                    placeholder="ชื่อผู้ประเมิน, รอบการประเมิน...">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
                <?php if ($search || $status_filter !== 'submitted'): ?>
                    <a href="pending-list.php" class="btn btn-outline">รีเซ็ต</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- รายการ -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($evaluations)): ?>
        <div class="p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-gray-500 text-lg">ไม่มีแบบประเมินรออนุมัติ</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="table">
                <thead class="table-header">
                    <tr>
                        <th class="table-th">ผู้ประเมิน</th>
                        <th class="table-th">ตำแหน่ง/หน่วยงาน</th>
                        <th class="table-th">รอบการประเมิน</th>
                        <th class="table-th">คะแนน</th>
                        <th class="table-th">วันที่ส่ง</th>
                        <th class="table-th">สถานะ</th>
                        <th class="table-th text-center">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                        <tr class="table-row">
                            <td class="table-td">
                                <div class="flex items-center space-x-3">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-400 rounded-full flex items-center justify-center text-white font-medium">
                                        <?php echo mb_substr($eval['full_name_th'], 0, 2); ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo e($eval['full_name_th']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo e($personnel_names[$eval['personnel_type']] ?? ''); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="table-td">
                                <p class="text-sm font-medium"><?php echo e($eval['position']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo e($eval['department_name_th']); ?></p>
                            </td>
                            <td class="table-td">
                                <p class="font-medium"><?php echo e($eval['period_name']); ?></p>
                                <p class="text-sm text-gray-500">ปี <?php echo e($eval['year']); ?></p>
                            </td>
                            <td class="table-td">
                                <span class="text-lg font-bold text-blue-600">
                                    <?php echo number_format_thai($eval['total_score']); ?>
                                </span>
                            </td>
                            <td class="table-td">
                                <p class="text-sm"><?php echo thai_date($eval['submitted_at'], 'short'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo time_ago($eval['submitted_at']); ?></p>
                            </td>
                            <td class="table-td">
                                <span class="badge <?php echo $status_colors[$eval['status']]; ?>">
                                    <?php echo $status_names[$eval['status']]; ?>
                                </span>
                                <?php if ($eval['total_managers'] > 0): ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        อนุมัติ <?php echo $eval['approved_count']; ?>/<?php echo $eval['total_managers']; ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="table-td">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="review.php?id=<?php echo $eval['evaluation_id']; ?>" class="btn btn-sm btn-primary"
                                        title="ตรวจสอบและพิจารณา">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span class="ml-1">ตรวจสอบ</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="pagination-btn">← ก่อนหน้า</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($pagination['last_page'], $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="pagination-btn <?php echo $i === $page ? 'pagination-active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pagination['last_page']): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="pagination-btn">ถัดไป →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>