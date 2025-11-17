<?php

/**
 * /modules/configuration/evaluation-period.php
 * จัดการรอบการประเมิน (Evaluation Period)
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requirePermission('config.evaluation_periods');

$page_title = 'จัดการรอบการประเมิน';
$db = getDB();

// ==================== Handle Actions ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO evaluation_periods (
                        period_name, year, semester, start_date, end_date,
                        description, is_active, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $is_active = isset($_POST['is_active']) ? 1 : 0;

                $result = $stmt->execute([
                    $_POST['period_name'],
                    $_POST['year'],
                    $_POST['semester'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['description'],
                    $is_active,
                    $_SESSION['user']['user_id']
                ]);

                if ($result) {
                    $period_id = $db->lastInsertId();

                    // ถ้าเปิดใช้งาน ให้ปิดรอบอื่นทั้งหมด
                    if ($is_active) {
                        $stmt = $db->prepare("UPDATE evaluation_periods SET is_active = 0 WHERE period_id != ?");
                        $stmt->execute([$period_id]);
                    }

                    log_activity('create', 'evaluation_periods', $period_id);
                    $db->commit();
                    flash_success('เพิ่มรอบการประเมินสำเร็จ');
                }
            } catch (PDOException $e) {
                $db->rollBack();
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('evaluation-period.php');
            break;

        case 'edit':
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    UPDATE evaluation_periods 
                    SET period_name = ?, year = ?, semester = ?, 
                        start_date = ?, end_date = ?, description = ?, 
                        is_active = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE period_id = ?
                ");

                $is_active = isset($_POST['is_active']) ? 1 : 0;

                $result = $stmt->execute([
                    $_POST['period_name'],
                    $_POST['year'],
                    $_POST['semester'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['description'],
                    $is_active,
                    $_SESSION['user']['user_id'],
                    $_POST['period_id']
                ]);

                if ($result) {
                    // ถ้าเปิดใช้งาน ให้ปิดรอบอื่นทั้งหมด
                    if ($is_active) {
                        $stmt = $db->prepare("UPDATE evaluation_periods SET is_active = 0 WHERE period_id != ?");
                        $stmt->execute([$_POST['period_id']]);
                    }

                    log_activity('update', 'evaluation_periods', $_POST['period_id']);
                    $db->commit();
                    flash_success('แก้ไขรอบการประเมินสำเร็จ');
                }
            } catch (PDOException $e) {
                $db->rollBack();
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('evaluation-period.php');
            break;
    }
}

// Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        // ตรวจสอบว่ามีการใช้งานอยู่หรือไม่
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM evaluations WHERE period_id = ?");
        $stmt->execute([$_GET['id']]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            flash_error('ไม่สามารถลบได้ เนื่องจากมีแบบประเมินที่เชื่อมโยงอยู่');
        } else {
            $stmt = $db->prepare("DELETE FROM evaluation_periods WHERE period_id = ?");
            if ($stmt->execute([$_GET['id']])) {
                log_activity('delete', 'evaluation_periods', $_GET['id']);
                flash_success('ลบรอบการประเมินสำเร็จ');
            }
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('evaluation-period.php');
}

// Activate Period
if (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['id'])) {
    try {
        $db->beginTransaction();

        // ปิดรอบอื่นทั้งหมด
        $stmt = $db->prepare("UPDATE evaluation_periods SET is_active = 0");
        $stmt->execute();

        // เปิดรอบที่เลือก
        $stmt = $db->prepare("UPDATE evaluation_periods SET is_active = 1 WHERE period_id = ?");
        if ($stmt->execute([$_GET['id']])) {
            log_activity('activate', 'evaluation_periods', $_GET['id']);
            $db->commit();
            flash_success('เปิดใช้งานรอบการประเมินสำเร็จ');
        }
    } catch (PDOException $e) {
        $db->rollBack();
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('evaluation-period.php');
}

// ==================== Fetch Data ====================

$year_filter = $_GET['year'] ?? '';
$semester_filter = $_GET['semester'] ?? '';

$sql = "SELECT ep.*,
        COUNT(DISTINCT e.evaluation_id) as evaluation_count,
        COUNT(DISTINCT CASE WHEN e.status = 'approved' THEN e.evaluation_id END) as approved_count
        FROM evaluation_periods ep
        LEFT JOIN evaluations e ON ep.period_id = e.period_id
        WHERE 1=1";

$params = [];

if ($year_filter) {
    $sql .= " AND ep.year = ?";
    $params[] = $year_filter;
}

if ($semester_filter) {
    $sql .= " AND ep.semester = ?";
    $params[] = $semester_filter;
}

$sql .= " GROUP BY ep.period_id ORDER BY ep.year DESC, ep.semester DESC, ep.period_id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$periods = $stmt->fetchAll();

// Get available years for filter
$years = $db->query("SELECT DISTINCT year FROM evaluation_periods ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">จัดการรอบการประเมิน</h1>
            <p class="mt-2 text-sm text-gray-600">
                จัดการรอบการประเมินผลการปฏิบัติงาน แบ่งตามปีและภาคเรียน
            </p>
        </div>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            เพิ่มรอบการประเมิน
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="w-48">
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <option value="">ทุกปีการศึกษา</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                            <?php echo $year + 543; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-48">
                <select name="semester" class="form-select" onchange="this.form.submit()">
                    <option value="">ทุกภาคเรียน</option>
                    <option value="1" <?php echo $semester_filter == '1' ? 'selected' : ''; ?>>ภาคเรียนที่ 1</option>
                    <option value="2" <?php echo $semester_filter == '2' ? 'selected' : ''; ?>>ภาคเรียนที่ 2</option>
                    <option value="3" <?php echo $semester_filter == '3' ? 'selected' : ''; ?>>ภาคฤดูร้อน</option>
                </select>
            </div>
            <?php if ($year_filter || $semester_filter): ?>
                <a href="evaluation-period.php" class="btn btn-outline">ล้างตัวกรอง</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Periods Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($periods)): ?>
        <div class="col-span-full">
            <div class="card">
                <div class="card-body text-center py-12 text-gray-500">
                    ไม่พบข้อมูลรอบการประเมิน
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($periods as $period): ?>
            <div
                class="card hover:shadow-lg transition-shadow <?php echo $period['is_active'] ? 'ring-2 ring-blue-500' : ''; ?>">
                <div class="card-body">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo e($period['period_name']); ?></h3>
                            <p class="text-sm text-gray-600 mt-1">
                                ปีการศึกษา <?php echo $period['year'] + 543; ?> / <?php echo $period['semester']; ?>
                            </p>
                        </div>
                        <?php if ($period['is_active']): ?>
                            <span class="badge badge-success">เปิดใช้งาน</span>
                        <?php else: ?>
                            <span class="badge badge-gray">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </div>

                    <!-- Dates -->
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>เริ่ม: <?php echo thai_date($period['start_date']); ?></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>สิ้นสุด: <?php echo thai_date($period['end_date']); ?></span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-3 mb-4 pt-4 border-t">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">
                                <?php echo number_format($period['evaluation_count']); ?></div>
                            <div class="text-xs text-gray-500">แบบประเมิน</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">
                                <?php echo number_format($period['approved_count']); ?></div>
                            <div class="text-xs text-gray-500">อนุมัติแล้ว</div>
                        </div>
                    </div>

                    <?php if ($period['description']): ?>
                        <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo e($period['description']); ?></p>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="flex gap-2 pt-4 border-t">
                        <?php if (!$period['is_active']): ?>
                            <a href="?action=activate&id=<?php echo $period['period_id']; ?>" class="btn btn-primary btn-sm flex-1"
                                onclick="return confirm('ต้องการเปิดใช้งานรอบนี้ใช่หรือไม่?')">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                เปิดใช้งาน
                            </a>
                        <?php endif; ?>
                        <button onclick='editPeriod(<?php echo json_encode($period); ?>)' class="btn btn-outline btn-sm flex-1">
                            แก้ไข
                        </button>
                        <?php if ($period['evaluation_count'] == 0): ?>
                            <a href="?action=delete&id=<?php echo $period['period_id']; ?>"
                                class="btn btn-outline btn-sm text-red-600 hover:bg-red-50"
                                onclick="return confirm('ต้องการลบรอบการประเมินนี้ใช่หรือไม่?')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content max-w-2xl">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">เพิ่มรอบการประเมิน</h3>
            <button onclick="closeModal('addModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">ชื่อรอบการประเมิน <span class="text-red-500">*</span></label>
                    <input type="text" name="period_name" required class="form-input"
                        placeholder="เช่น การประเมินครั้งที่ 1 ปีการศึกษา 2567">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ปีการศึกษา (ค.ศ.) <span class="text-red-500">*</span></label>
                        <input type="number" name="year" required value="<?php echo date('Y'); ?>" min="2020" max="2100"
                            class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ภาคเรียน <span class="text-red-500">*</span></label>
                        <select name="semester" required class="form-select">
                            <option value="1">ภาคเรียนที่ 1</option>
                            <option value="2">ภาคเรียนที่ 2</option>
                            <option value="3">ภาคฤดูร้อน</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">วันที่เริ่มต้น <span class="text-red-500">*</span></label>
                        <input type="date" name="start_date" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่สิ้นสุด <span class="text-red-500">*</span></label>
                        <input type="date" name="end_date" required class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">คำอธิบาย</label>
                    <textarea name="description" rows="3" class="form-textarea"
                        placeholder="รายละเอียดเพิ่มเติม..."></textarea>
                </div>

                <div class="form-group">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" class="form-checkbox">
                        <span class="ml-2 text-sm text-gray-700">เปิดใช้งานทันที (จะปิดรอบอื่นอัตโนมัติ)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addModal')" class="btn btn-outline">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content max-w-2xl">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">แก้ไขรอบการประเมิน</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="period_id" id="edit_period_id">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">ชื่อรอบการประเมิน <span class="text-red-500">*</span></label>
                    <input type="text" name="period_name" id="edit_period_name" required class="form-input">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ปีการศึกษา (ค.ศ.) <span class="text-red-500">*</span></label>
                        <input type="number" name="year" id="edit_year" required min="2020" max="2100"
                            class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ภาคเรียน <span class="text-red-500">*</span></label>
                        <select name="semester" id="edit_semester" required class="form-select">
                            <option value="1">ภาคเรียนที่ 1</option>
                            <option value="2">ภาคเรียนที่ 2</option>
                            <option value="3">ภาคฤดูร้อน</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">วันที่เริ่มต้น <span class="text-red-500">*</span></label>
                        <input type="date" name="start_date" id="edit_start_date" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">วันที่สิ้นสุด <span class="text-red-500">*</span></label>
                        <input type="date" name="end_date" id="edit_end_date" required class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">คำอธิบาย</label>
                    <textarea name="description" id="edit_description" rows="3" class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" id="edit_is_active" class="form-checkbox">
                        <span class="ml-2 text-sm text-gray-700">เปิดใช้งาน</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('editModal')" class="btn btn-outline">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function editPeriod(data) {
        document.getElementById('edit_period_id').value = data.period_id;
        document.getElementById('edit_period_name').value = data.period_name;
        document.getElementById('edit_year').value = data.year;
        document.getElementById('edit_semester').value = data.semester;
        document.getElementById('edit_start_date').value = data.start_date;
        document.getElementById('edit_end_date').value = data.end_date;
        document.getElementById('edit_description').value = data.description || '';
        document.getElementById('edit_is_active').checked = data.is_active == 1;
        openModal('editModal');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>