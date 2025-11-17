<?php

/**
 * /modules/configuration/evaluation-aspects.php
 * จัดการด้านการประเมิน (Evaluation Aspects)
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requirePermission('config.evaluation_aspects');

$page_title = 'จัดการด้านการประเมิน';
$db = getDB();

// ==================== Handle Actions ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            try {
                $stmt = $db->prepare("
                    INSERT INTO evaluation_aspects (
                        personnel_type_id, aspect_code, aspect_name_th, aspect_name_en,
                        description, max_score, weight_percentage, sort_order, is_active, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $result = $stmt->execute([
                    $_POST['personnel_type_id'],
                    $_POST['aspect_code'],
                    $_POST['aspect_name_th'],
                    $_POST['aspect_name_en'],
                    $_POST['description'],
                    $_POST['max_score'],
                    $_POST['weight_percentage'],
                    $_POST['sort_order'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_SESSION['user']['user_id']
                ]);

                if ($result) {
                    log_activity('create', 'evaluation_aspects', $db->lastInsertId());
                    flash_success('เพิ่มด้านการประเมินสำเร็จ');
                }
            } catch (PDOException $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('evaluation-aspects.php');
            break;

        case 'edit':
            try {
                $stmt = $db->prepare("
                    UPDATE evaluation_aspects 
                    SET personnel_type_id = ?, aspect_code = ?, aspect_name_th = ?, 
                        aspect_name_en = ?, description = ?, max_score = ?, 
                        weight_percentage = ?, sort_order = ?, is_active = ?,
                        updated_by = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE aspect_id = ?
                ");

                $result = $stmt->execute([
                    $_POST['personnel_type_id'],
                    $_POST['aspect_code'],
                    $_POST['aspect_name_th'],
                    $_POST['aspect_name_en'],
                    $_POST['description'],
                    $_POST['max_score'],
                    $_POST['weight_percentage'],
                    $_POST['sort_order'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_SESSION['user']['user_id'],
                    $_POST['aspect_id']
                ]);

                if ($result) {
                    log_activity('update', 'evaluation_aspects', $_POST['aspect_id']);
                    flash_success('แก้ไขด้านการประเมินสำเร็จ');
                }
            } catch (PDOException $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('evaluation-aspects.php');
            break;
    }
}

// Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM evaluation_topics WHERE aspect_id = ?");
        $stmt->execute([$_GET['id']]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            flash_error('ไม่สามารถลบได้ เนื่องจากมีหัวข้อการประเมินที่เชื่อมโยงอยู่');
        } else {
            $stmt = $db->prepare("DELETE FROM evaluation_aspects WHERE aspect_id = ?");
            if ($stmt->execute([$_GET['id']])) {
                log_activity('delete', 'evaluation_aspects', $_GET['id']);
                flash_success('ลบด้านการประเมินสำเร็จ');
            }
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('evaluation-aspects.php');
}

// Toggle Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("UPDATE evaluation_aspects SET is_active = NOT is_active WHERE aspect_id = ?");
        if ($stmt->execute([$_GET['id']])) {
            log_activity('toggle_status', 'evaluation_aspects', $_GET['id']);
            flash_success('เปลี่ยนสถานะสำเร็จ');
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('evaluation-aspects.php');
}

// ==================== Fetch Data ====================

// Get personnel types for filter
$personnel_types = $db->query("SELECT * FROM personnel_types WHERE is_active = 1 ORDER BY type_name_th")->fetchAll();

$filter_type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT ea.*, pt.type_name_th as personnel_type_name,
        COUNT(DISTINCT et.topic_id) as topic_count
        FROM evaluation_aspects ea
        LEFT JOIN personnel_types pt ON ea.personnel_type_id = pt.personnel_type_id
        LEFT JOIN evaluation_topics et ON ea.aspect_id = et.aspect_id
        WHERE 1=1";

$params = [];

if ($filter_type) {
    $sql .= " AND ea.personnel_type_id = ?";
    $params[] = $filter_type;
}

if ($search) {
    $sql .= " AND (ea.aspect_code LIKE ? OR ea.aspect_name_th LIKE ? OR ea.aspect_name_en LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " GROUP BY ea.aspect_id ORDER BY ea.personnel_type_id, ea.sort_order, ea.aspect_id";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$aspects = $stmt->fetchAll();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">จัดการด้านการประเมิน</h1>
            <p class="mt-2 text-sm text-gray-600">
                จัดการด้านการประเมินสำหรับแต่ละประเภทบุคลากร
            </p>
        </div>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            เพิ่มด้านการประเมิน
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">ทุกประเภทบุคลากร</option>
                    <?php foreach ($personnel_types as $type): ?>
                        <option value="<?php echo $type['personnel_type_id']; ?>"
                            <?php echo $filter_type == $type['personnel_type_id'] ? 'selected' : ''; ?>>
                            <?php echo e($type['type_name_th']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[300px]">
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="ค้นหา..."
                    class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                ค้นหา
            </button>
            <?php if ($filter_type || $search): ?>
                <a href="evaluation-aspects.php" class="btn btn-outline">ล้าง</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Aspects Table -->
<div class="card">
    <div class="card-body">
        <div class="overflow-x-auto">
            <table class="table">
                <thead class="table-header">
                    <tr>
                        <th class="table-th">ลำดับ</th>
                        <th class="table-th">รหัส</th>
                        <th class="table-th">ชื่อด้านการประเมิน</th>
                        <th class="table-th">ประเภทบุคลากร</th>
                        <th class="table-th text-right">คะแนนเต็ม</th>
                        <th class="table-th text-right">น้ำหนัก (%)</th>
                        <th class="table-th text-center">จำนวนหัวข้อ</th>
                        <th class="table-th text-center">สถานะ</th>
                        <th class="table-th text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($aspects)): ?>
                        <tr>
                            <td colspan="9" class="table-td text-center text-gray-500 py-8">
                                ไม่พบข้อมูลด้านการประเมิน
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($aspects as $aspect): ?>
                            <tr class="table-row">
                                <td class="table-td text-center">
                                    <span class="text-gray-600"><?php echo $aspect['sort_order']; ?></span>
                                </td>
                                <td class="table-td">
                                    <span class="font-mono font-semibold text-blue-600">
                                        <?php echo e($aspect['aspect_code']); ?>
                                    </span>
                                </td>
                                <td class="table-td">
                                    <div class="font-medium text-gray-900"><?php echo e($aspect['aspect_name_th']); ?></div>
                                    <?php if ($aspect['aspect_name_en']): ?>
                                        <div class="text-sm text-gray-500"><?php echo e($aspect['aspect_name_en']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="table-td">
                                    <span class="badge badge-secondary"><?php echo e($aspect['personnel_type_name']); ?></span>
                                </td>
                                <td class="table-td text-right">
                                    <span
                                        class="font-semibold text-gray-900"><?php echo number_format($aspect['max_score']); ?></span>
                                </td>
                                <td class="table-td text-right">
                                    <span
                                        class="font-semibold text-purple-600"><?php echo number_format($aspect['weight_percentage'], 1); ?>%</span>
                                </td>
                                <td class="table-td text-center">
                                    <span
                                        class="badge badge-primary"><?php echo number_format($aspect['topic_count']); ?></span>
                                </td>
                                <td class="table-td text-center">
                                    <?php if ($aspect['is_active']): ?>
                                        <span class="badge badge-success">ใช้งาน</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">ปิดใช้งาน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick='editAspect(<?php echo json_encode($aspect); ?>)'
                                            class="btn-icon text-blue-600 hover:bg-blue-50" title="แก้ไข">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <a href="?action=toggle&id=<?php echo $aspect['aspect_id']; ?>"
                                            class="btn-icon text-yellow-600 hover:bg-yellow-50"
                                            onclick="return confirm('ต้องการเปลี่ยนสถานะใช่หรือไม่?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $aspect['aspect_id']; ?>"
                                            class="btn-icon text-red-600 hover:bg-red-50"
                                            onclick="return confirm('ต้องการลบด้านการประเมินนี้ใช่หรือไม่?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content max-w-3xl">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">เพิ่มด้านการประเมิน</h3>
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
                    <label class="form-label">ประเภทบุคลากร <span class="text-red-500">*</span></label>
                    <select name="personnel_type_id" required class="form-select">
                        <option value="">-- เลือกประเภทบุคลากร --</option>
                        <?php foreach ($personnel_types as $type): ?>
                            <option value="<?php echo $type['personnel_type_id']; ?>">
                                <?php echo e($type['type_name_th']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">รหัสด้านการประเมิน <span class="text-red-500">*</span></label>
                        <input type="text" name="aspect_code" required maxlength="20" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ลำดับ <span class="text-red-500">*</span></label>
                        <input type="number" name="sort_order" required value="1" min="1" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
                        <input type="text" name="aspect_name_th" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ</label>
                        <input type="text" name="aspect_name_en" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">คะแนนเต็ม <span class="text-red-500">*</span></label>
                        <input type="number" name="max_score" required value="100" min="0" step="0.01"
                            class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">น้ำหนัก (%) <span class="text-red-500">*</span></label>
                        <input type="number" name="weight_percentage" required value="0" min="0" max="100" step="0.01"
                            class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">คำอธิบาย</label>
                    <textarea name="description" rows="3" class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" checked class="form-checkbox">
                        <span class="ml-2 text-sm text-gray-700">เปิดใช้งาน</span>
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
    <div class="modal-content max-w-3xl">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">แก้ไขด้านการประเมิน</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="aspect_id" id="edit_aspect_id">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">ประเภทบุคลากร <span class="text-red-500">*</span></label>
                    <select name="personnel_type_id" id="edit_personnel_type_id" required class="form-select">
                        <option value="">-- เลือกประเภทบุคลากร --</option>
                        <?php foreach ($personnel_types as $type): ?>
                            <option value="<?php echo $type['personnel_type_id']; ?>">
                                <?php echo e($type['type_name_th']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">รหัสด้านการประเมิน <span class="text-red-500">*</span></label>
                        <input type="text" name="aspect_code" id="edit_aspect_code" required maxlength="20"
                            class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ลำดับ <span class="text-red-500">*</span></label>
                        <input type="number" name="sort_order" id="edit_sort_order" required min="1" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
                        <input type="text" name="aspect_name_th" id="edit_aspect_name_th" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ</label>
                        <input type="text" name="aspect_name_en" id="edit_aspect_name_en" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">คะแนนเต็ม <span class="text-red-500">*</span></label>
                        <input type="number" name="max_score" id="edit_max_score" required min="0" step="0.01"
                            class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">น้ำหนัก (%) <span class="text-red-500">*</span></label>
                        <input type="number" name="weight_percentage" id="edit_weight_percentage" required min="0"
                            max="100" step="0.01" class="form-input">
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

    function editAspect(data) {
        document.getElementById('edit_aspect_id').value = data.aspect_id;
        document.getElementById('edit_personnel_type_id').value = data.personnel_type_id;
        document.getElementById('edit_aspect_code').value = data.aspect_code;
        document.getElementById('edit_aspect_name_th').value = data.aspect_name_th;
        document.getElementById('edit_aspect_name_en').value = data.aspect_name_en || '';
        document.getElementById('edit_description').value = data.description || '';
        document.getElementById('edit_max_score').value = data.max_score;
        document.getElementById('edit_weight_percentage').value = data.weight_percentage;
        document.getElementById('edit_sort_order').value = data.sort_order;
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