<?php

/**
 * /modules/configuration/evaluation-topics.php
 * จัดการหัวข้อการประเมิน (Evaluation Topics)
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requirePermission('config.evaluation_topics');

$page_title = 'จัดการหัวข้อการประเมิน';
$db = getDB();

// ==================== Handle Actions ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            try {
                $stmt = $db->prepare("
                    INSERT INTO evaluation_topics (
                        aspect_id, topic_code, topic_name_th, topic_name_en,
                        description, max_score, weight_percentage, sort_order, 
                        is_active, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $result = $stmt->execute([
                    $_POST['aspect_id'],
                    $_POST['topic_code'],
                    $_POST['topic_name_th'],
                    $_POST['topic_name_en'],
                    $_POST['description'],
                    $_POST['max_score'],
                    $_POST['weight_percentage'],
                    $_POST['sort_order'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_SESSION['user']['user_id']
                ]);

                if ($result) {
                    log_activity('create', 'evaluation_topics', $db->lastInsertId());
                    flash_success('เพิ่มหัวข้อการประเมินสำเร็จ');
                }
            } catch (PDOException $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('evaluation-topics.php');
            break;

        case 'edit':
            try {
                $stmt = $db->prepare("
                    UPDATE evaluation_topics 
                    SET aspect_id = ?, topic_code = ?, topic_name_th = ?, 
                        topic_name_en = ?, description = ?, max_score = ?, 
                        weight_percentage = ?, sort_order = ?, is_active = ?,
                        updated_by = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE topic_id = ?
                ");

                $result = $stmt->execute([
                    $_POST['aspect_id'],
                    $_POST['topic_code'],
                    $_POST['topic_name_th'],
                    $_POST['topic_name_en'],
                    $_POST['description'],
                    $_POST['max_score'],
                    $_POST['weight_percentage'],
                    $_POST['sort_order'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_SESSION['user']['user_id'],
                    $_POST['topic_id']
                ]);

                if ($result) {
                    log_activity('update', 'evaluation_topics', $_POST['topic_id']);
                    flash_success('แก้ไขหัวข้อการประเมินสำเร็จ');
                }
            } catch (PDOException $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('evaluation-topics.php');
            break;
    }
}

// Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM evaluation_topics WHERE topic_id = ?");
        if ($stmt->execute([$_GET['id']])) {
            log_activity('delete', 'evaluation_topics', $_GET['id']);
            flash_success('ลบหัวข้อการประเมินสำเร็จ');
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('evaluation-topics.php');
}

// Toggle Status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("UPDATE evaluation_topics SET is_active = NOT is_active WHERE topic_id = ?");
        if ($stmt->execute([$_GET['id']])) {
            log_activity('toggle_status', 'evaluation_topics', $_GET['id']);
            flash_success('เปลี่ยนสถานะสำเร็จ');
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('evaluation-topics.php');
}

// ==================== Fetch Data ====================

// Get aspects for filter and dropdowns
$aspects = $db->query("
    SELECT ea.*, pt.type_name_th as personnel_type_name
    FROM evaluation_aspects ea
    LEFT JOIN personnel_types pt ON ea.personnel_type_id = pt.personnel_type_id
    WHERE ea.is_active = 1
    ORDER BY pt.type_name_th, ea.sort_order
")->fetchAll();

$filter_aspect = $_GET['aspect'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT et.*, ea.aspect_name_th, ea.aspect_code,
        pt.type_name_th as personnel_type_name
        FROM evaluation_topics et
        LEFT JOIN evaluation_aspects ea ON et.aspect_id = ea.aspect_id
        LEFT JOIN personnel_types pt ON ea.personnel_type_id = pt.personnel_type_id
        WHERE 1=1";

$params = [];

if ($filter_aspect) {
    $sql .= " AND et.aspect_id = ?";
    $params[] = $filter_aspect;
}

if ($search) {
    $sql .= " AND (et.topic_code LIKE ? OR et.topic_name_th LIKE ? OR et.topic_name_en LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY ea.aspect_id, et.sort_order, et.topic_id";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$topics = $stmt->fetchAll();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">จัดการหัวข้อการประเมิน</h1>
            <p class="mt-2 text-sm text-gray-600">
                จัดการหัวข้อการประเมินภายใต้แต่ละด้านการประเมิน
            </p>
        </div>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            เพิ่มหัวข้อการประเมิน
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[250px]">
                <select name="aspect" class="form-select" onchange="this.form.submit()">
                    <option value="">ทุกด้านการประเมิน</option>
                    <?php
                    $current_type = '';
                    foreach ($aspects as $aspect):
                        if ($current_type !== $aspect['personnel_type_name']) {
                            if ($current_type !== '') echo '</optgroup>';
                            echo '<optgroup label="' . e($aspect['personnel_type_name']) . '">';
                            $current_type = $aspect['personnel_type_name'];
                        }
                    ?>
                        <option value="<?php echo $aspect['aspect_id']; ?>"
                            <?php echo $filter_aspect == $aspect['aspect_id'] ? 'selected' : ''; ?>>
                            <?php echo e($aspect['aspect_name_th']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($current_type !== '') echo '</optgroup>'; ?>
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
            <?php if ($filter_aspect || $search): ?>
                <a href="evaluation-topics.php" class="btn btn-outline">ล้าง</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Topics Table -->
<div class="card">
    <div class="card-body">
        <div class="overflow-x-auto">
            <table class="table">
                <thead class="table-header">
                    <tr>
                        <th class="table-th w-16">ลำดับ</th>
                        <th class="table-th">รหัส</th>
                        <th class="table-th">ชื่อหัวข้อการประเมิน</th>
                        <th class="table-th">ด้านการประเมิน</th>
                        <th class="table-th">ประเภทบุคลากร</th>
                        <th class="table-th text-right">คะแนนเต็ม</th>
                        <th class="table-th text-right">น้ำหนัก (%)</th>
                        <th class="table-th text-center">สถานะ</th>
                        <th class="table-th text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topics)): ?>
                        <tr>
                            <td colspan="9" class="table-td text-center text-gray-500 py-8">
                                ไม่พบข้อมูลหัวข้อการประเมิน
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topics as $topic): ?>
                            <tr class="table-row">
                                <td class="table-td text-center">
                                    <span class="text-gray-600"><?php echo $topic['sort_order']; ?></span>
                                </td>
                                <td class="table-td">
                                    <span class="font-mono font-semibold text-blue-600">
                                        <?php echo e($topic['topic_code']); ?>
                                    </span>
                                </td>
                                <td class="table-td">
                                    <div class="font-medium text-gray-900"><?php echo e($topic['topic_name_th']); ?></div>
                                    <?php if ($topic['topic_name_en']): ?>
                                        <div class="text-sm text-gray-500"><?php echo e($topic['topic_name_en']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($topic['description']): ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <?php echo e(str_limit($topic['description'], 60)); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="table-td">
                                    <span class="badge badge-secondary">
                                        <?php echo e($topic['aspect_name_th']); ?>
                                    </span>
                                </td>
                                <td class="table-td">
                                    <span class="text-sm text-gray-600"><?php echo e($topic['personnel_type_name']); ?></span>
                                </td>
                                <td class="table-td text-right">
                                    <span
                                        class="font-semibold text-gray-900"><?php echo number_format($topic['max_score']); ?></span>
                                </td>
                                <td class="table-td text-right">
                                    <span
                                        class="font-semibold text-purple-600"><?php echo number_format($topic['weight_percentage'], 1); ?>%</span>
                                </td>
                                <td class="table-td text-center">
                                    <?php if ($topic['is_active']): ?>
                                        <span class="badge badge-success">ใช้งาน</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">ปิดใช้งาน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick='editTopic(<?php echo json_encode($topic); ?>)'
                                            class="btn-icon text-blue-600 hover:bg-blue-50" title="แก้ไข">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <a href="?action=toggle&id=<?php echo $topic['topic_id']; ?>"
                                            class="btn-icon text-yellow-600 hover:bg-yellow-50"
                                            onclick="return confirm('ต้องการเปลี่ยนสถานะใช่หรือไม่?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $topic['topic_id']; ?>"
                                            class="btn-icon text-red-600 hover:bg-red-50"
                                            onclick="return confirm('ต้องการลบหัวข้อการประเมินนี้ใช่หรือไม่?')">
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
            <h3 class="text-xl font-semibold">เพิ่มหัวข้อการประเมิน</h3>
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
                    <label class="form-label">ด้านการประเมิน <span class="text-red-500">*</span></label>
                    <select name="aspect_id" required class="form-select">
                        <option value="">-- เลือกด้านการประเมิน --</option>
                        <?php
                        $current_type = '';
                        foreach ($aspects as $aspect):
                            if ($current_type !== $aspect['personnel_type_name']) {
                                if ($current_type !== '') echo '</optgroup>';
                                echo '<optgroup label="' . e($aspect['personnel_type_name']) . '">';
                                $current_type = $aspect['personnel_type_name'];
                            }
                        ?>
                            <option value="<?php echo $aspect['aspect_id']; ?>">
                                <?php echo e($aspect['aspect_name_th']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($current_type !== '') echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">รหัสหัวข้อ <span class="text-red-500">*</span></label>
                        <input type="text" name="topic_code" required maxlength="20" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ลำดับ <span class="text-red-500">*</span></label>
                        <input type="number" name="sort_order" required value="1" min="1" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
                        <input type="text" name="topic_name_th" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ</label>
                        <input type="text" name="topic_name_en" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">คะแนนเต็ม <span class="text-red-500">*</span></label>
                        <input type="number" name="max_score" required value="5" min="0" step="0.01" class="form-input">
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
            <h3 class="text-xl font-semibold">แก้ไขหัวข้อการประเมิน</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="topic_id" id="edit_topic_id">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">ด้านการประเมิน <span class="text-red-500">*</span></label>
                    <select name="aspect_id" id="edit_aspect_id" required class="form-select">
                        <option value="">-- เลือกด้านการประเมิน --</option>
                        <?php
                        $current_type = '';
                        foreach ($aspects as $aspect):
                            if ($current_type !== $aspect['personnel_type_name']) {
                                if ($current_type !== '') echo '</optgroup>';
                                echo '<optgroup label="' . e($aspect['personnel_type_name']) . '">';
                                $current_type = $aspect['personnel_type_name'];
                            }
                        ?>
                            <option value="<?php echo $aspect['aspect_id']; ?>">
                                <?php echo e($aspect['aspect_name_th']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($current_type !== '') echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">รหัสหัวข้อ <span class="text-red-500">*</span></label>
                        <input type="text" name="topic_code" id="edit_topic_code" required maxlength="20"
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
                        <input type="text" name="topic_name_th" id="edit_topic_name_th" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ</label>
                        <input type="text" name="topic_name_en" id="edit_topic_name_en" class="form-input">
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

    function editTopic(data) {
        document.getElementById('edit_topic_id').value = data.topic_id;
        document.getElementById('edit_aspect_id').value = data.aspect_id;
        document.getElementById('edit_topic_code').value = data.topic_code;
        document.getElementById('edit_topic_name_th').value = data.topic_name_th;
        document.getElementById('edit_topic_name_en').value = data.topic_name_en || '';
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