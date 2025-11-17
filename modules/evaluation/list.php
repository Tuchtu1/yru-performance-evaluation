<?php

/**
 * modules/evaluation/list.php
 * รายการแบบประเมินของฉัน
 */

require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/helpers.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
if (!can('evaluation.view_own')) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('modules/dashboard/index.php');
    exit;
}

$page_title = 'แบบประเมินของฉัน';
$db = getDB();
$user_id = $_SESSION['user']['user_id'];

// ==================== Filters ====================
$status_filter = $_GET['status'] ?? '';
$period_filter = $_GET['period'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Get evaluation periods
    $stmt = $db->query("
        SELECT * FROM evaluation_periods 
        ORDER BY year DESC, semester DESC
    ");
    $periods = $stmt->fetchAll();

    // Build query
    $sql = "SELECT e.*, ep.period_name, ep.year, ep.semester, ep.end_date,
            u.full_name_th, pt.type_name_th as personnel_type_name
            FROM evaluations e
            LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
            LEFT JOIN users u ON e.user_id = u.user_id
            LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
            WHERE e.user_id = ?";

    $params = [$user_id];

    if ($status_filter) {
        $sql .= " AND e.status = ?";
        $params[] = $status_filter;
    }

    if ($period_filter) {
        $sql .= " AND e.period_id = ?";
        $params[] = $period_filter;
    }

    if ($search) {
        $sql .= " AND (ep.period_name LIKE ? OR pt.type_name_th LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $sql .= " ORDER BY e.updated_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $evaluations = $stmt->fetchAll();

    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM evaluations
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $statistics = $stmt->fetch();

    // Ensure numeric values
    $statistics['total'] = (int)($statistics['total'] ?? 0);
    $statistics['draft'] = (int)($statistics['draft'] ?? 0);
    $statistics['submitted'] = (int)($statistics['submitted'] ?? 0);
    $statistics['under_review'] = (int)($statistics['under_review'] ?? 0);
    $statistics['approved'] = (int)($statistics['approved'] ?? 0);
    $statistics['rejected'] = (int)($statistics['rejected'] ?? 0);
} catch (Exception $e) {
    error_log("Evaluation List Error: " . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $periods = [];
    $evaluations = [];
    $statistics = [
        'total' => 0,
        'draft' => 0,
        'submitted' => 0,
        'under_review' => 0,
        'approved' => 0,
        'rejected' => 0
    ];
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">แบบประเมินของฉัน</h1>
            <p class="mt-2 text-sm text-gray-600">
                จัดการและติดตามแบบประเมินผลการปฏิบัติงานของคุณ
            </p>
        </div>
        <a href="<?php echo url('modules/evaluation/create.php'); ?>"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
            <i class="fas fa-plus mr-2"></i>สร้างแบบประเมินใหม่
        </a>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-gray-900"><?php echo number_format($statistics['total']); ?></div>
        <div class="text-sm text-gray-600">ทั้งหมด</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-gray-600"><?php echo number_format($statistics['draft']); ?></div>
        <div class="text-sm text-gray-600">ร่าง</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-blue-600"><?php echo number_format($statistics['submitted']); ?></div>
        <div class="text-sm text-gray-600">ส่งแล้ว</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-yellow-600"><?php echo number_format($statistics['under_review']); ?></div>
        <div class="text-sm text-gray-600">กำลังตรวจ</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-green-600"><?php echo number_format($statistics['approved']); ?></div>
        <div class="text-sm text-gray-600">อนุมัติ</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-2xl font-bold text-red-600"><?php echo number_format($statistics['rejected']); ?></div>
        <div class="text-sm text-gray-600">ไม่อนุมัติ</div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <select name="period" class="w-full border rounded px-3 py-2" onchange="this.form.submit()">
                    <option value="">ทุกรอบการประเมิน</option>
                    <?php foreach ($periods as $period): ?>
                        <option value="<?php echo $period['period_id']; ?>"
                            <?php echo $period_filter == $period['period_id'] ? 'selected' : ''; ?>>
                            <?php echo e($period['period_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <select name="status" class="w-full border rounded px-3 py-2" onchange="this.form.submit()">
                    <option value="">ทุกสถานะ</option>
                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>ร่าง</option>
                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>ส่งแล้ว
                    </option>
                    <option value="under_review" <?php echo $status_filter == 'under_review' ? 'selected' : ''; ?>>
                        กำลังตรวจสอบ</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>อนุมัติ
                    </option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>ไม่อนุมัติ
                    </option>
                    <option value="returned" <?php echo $status_filter == 'returned' ? 'selected' : ''; ?>>ส่งกลับแก้ไข
                    </option>
                </select>
            </div>
            <div class="flex-1 min-w-[250px]">
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="ค้นหา..."
                    class="w-full border rounded px-3 py-2">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                <i class="fas fa-search mr-2"></i>ค้นหา
            </button>
            <?php if ($status_filter || $period_filter || $search): ?>
                <a href="list.php"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">ล้าง</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Evaluations List -->
<div class="bg-white rounded-lg shadow">
    <div class="p-6">
        <?php if (empty($evaluations)): ?>
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-alt text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">ไม่พบแบบประเมิน</h3>
                <p class="text-gray-600 mb-4">คุณยังไม่มีแบบประเมินในระบบ</p>
                <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                    สร้างแบบประเมินเลย
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($evaluations as $eval):
                    $status_config = [
                        'draft' => ['label' => 'ร่าง', 'class' => 'bg-gray-100 text-gray-800'],
                        'submitted' => ['label' => 'ส่งแล้ว', 'class' => 'bg-blue-100 text-blue-800'],
                        'under_review' => ['label' => 'กำลังตรวจสอบ', 'class' => 'bg-yellow-100 text-yellow-800'],
                        'approved' => ['label' => 'อนุมัติ', 'class' => 'bg-green-100 text-green-800'],
                        'rejected' => ['label' => 'ไม่อนุมัติ', 'class' => 'bg-red-100 text-red-800'],
                        'returned' => ['label' => 'ส่งกลับแก้ไข', 'class' => 'bg-orange-100 text-orange-800']
                    ];

                    $config = $status_config[$eval['status']] ?? $status_config['draft'];
                    $days_left = days_between(date('Y-m-d'), $eval['end_date']);
                ?>
                    <div
                        class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="font-semibold text-gray-900"><?php echo e($eval['period_name'] ?? '-'); ?></h3>
                                <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $config['class']; ?>">
                                    <?php echo $config['label']; ?>
                                </span>
                                <?php if ($eval['status'] == 'draft' && $days_left >= 0): ?>
                                    <span class="text-xs text-gray-500">
                                        เหลือเวลา <?php echo $days_left; ?> วัน
                                    </span>
                                <?php elseif ($eval['status'] == 'draft' && $days_left < 0): ?>
                                    <span class="text-xs text-red-600 font-semibold">หมดเขตแล้ว</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span><?php echo e($eval['personnel_type_name'] ?? '-'); ?></span>
                                <span>•</span>
                                <span>อัพเดท: <?php echo thai_date($eval['updated_at'], 'short'); ?></span>
                                <?php if (isset($eval['total_score']) && $eval['total_score'] > 0): ?>
                                    <span>•</span>
                                    <span class="font-semibold text-blue-600">
                                        คะแนน: <?php echo number_format((float)$eval['total_score'], 2); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <?php if ($eval['status'] == 'draft'): ?>
                                <a href="edit.php?id=<?php echo $eval['evaluation_id']; ?>"
                                    class="bg-white border border-gray-300 text-gray-700 px-3 py-1 rounded text-sm hover:bg-gray-50"
                                    title="แก้ไข">
                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                </a>
                            <?php endif; ?>

                            <a href="view.php?id=<?php echo $eval['evaluation_id']; ?>"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                <i class="fas fa-eye mr-1"></i>ดูรายละเอียด
                            </a>

                            <?php if ($eval['status'] == 'draft'): ?>
                                <button onclick="confirmDelete(<?php echo $eval['evaluation_id']; ?>)"
                                    class="text-red-600 hover:bg-red-50 p-2 rounded" title="ลบ">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form id="deleteForm" method="POST" action="delete.php" style="display: none;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="evaluation_id" id="delete_evaluation_id">
</form>

<script>
    function confirmDelete(id) {
        if (confirm('ต้องการลบแบบประเมินนี้ใช่หรือไม่?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้')) {
            document.getElementById('delete_evaluation_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>