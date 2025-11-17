<?php

/**
 * /modules/configuration/personnel-type.php
 * จัดการประเภทบุคลากร
 * Personnel Type Management
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

// ตรวจสอบสิทธิ์
requirePermission('config.personnel_types');

$page_title = 'จัดการประเภทบุคลากร';
$db = getDB();

// ==================== Handle Actions ====================

// เพิ่มประเภทบุคลากร
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $stmt = $db->prepare("
            INSERT INTO personnel_types (type_code, type_name_th, type_name_en, description, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $_POST['type_code'],
            $_POST['type_name_th'],
            $_POST['type_name_en'],
            $_POST['description'],
            isset($_POST['is_active']) ? 1 : 0,
            $_SESSION['user']['user_id']
        ]);

        if ($result) {
            log_activity('create', 'personnel_types', $db->lastInsertId());
            flash_success('เพิ่มประเภทบุคลากรสำเร็จ');
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect($_SERVER['PHP_SELF']);
}

// แก้ไขประเภทบุคลากร
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $db->prepare("
            UPDATE personnel_types 
            SET type_code = ?, type_name_th = ?, type_name_en = ?, 
                description = ?, is_active = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
            WHERE personnel_type_id = ?
        ");

        $result = $stmt->execute([
            $_POST['type_code'],
            $_POST['type_name_th'],
            $_POST['type_name_en'],
            $_POST['description'],
            isset($_POST['is_active']) ? 1 : 0,
            $_SESSION['user']['user_id'],
            $_POST['personnel_type_id']
        ]);

        if ($result) {
            log_activity('update', 'personnel_types', $_POST['personnel_type_id']);
            flash_success('แก้ไขประเภทบุคลากรสำเร็จ');
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('personnel-type.php');
}

// ลบประเภทบุคลากร
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        // ตรวจสอบว่ามีการใช้งานอยู่หรือไม่
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE personnel_type_id = ?");
        $stmt->execute([$_GET['id']]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            flash_error('ไม่สามารถลบได้ เนื่องจากมีผู้ใช้งานที่เชื่อมโยงอยู่');
        } else {
            $stmt = $db->prepare("DELETE FROM personnel_types WHERE personnel_type_id = ?");
            if ($stmt->execute([$_GET['id']])) {
                log_activity('delete', 'personnel_types', $_GET['id']);
                flash_success('ลบประเภทบุคลากรสำเร็จ');
            }
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('personnel-type.php');
}

// เปลี่ยนสถานะ
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("UPDATE personnel_types SET is_active = NOT is_active WHERE personnel_type_id = ?");
        if ($stmt->execute([$_GET['id']])) {
            log_activity('toggle_status', 'personnel_types', $_GET['id']);
            flash_success('เปลี่ยนสถานะสำเร็จ');
        }
    } catch (PDOException $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('personnel-type.php');
}

// ==================== Fetch Data ====================

$search = $_GET['search'] ?? '';
$sql = "SELECT pt.*, 
        COUNT(DISTINCT u.user_id) as user_count,
        COUNT(DISTINCT ea.aspect_id) as aspect_count
        FROM personnel_types pt
        LEFT JOIN users u ON pt.personnel_type_id = u.personnel_type_id
        LEFT JOIN evaluation_aspects ea ON pt.personnel_type_id = ea.personnel_type_id
        WHERE 1=1";

if ($search) {
    $sql .= " AND (pt.type_code LIKE :search OR pt.type_name_th LIKE :search OR pt.type_name_en LIKE :search)";
}

$sql .= " GROUP BY pt.personnel_type_id ORDER BY pt.created_at DESC";

$stmt = $db->prepare($sql);
if ($search) {
    $stmt->bindValue(':search', "%$search%");
}
$stmt->execute();
$personnel_types = $stmt->fetchAll();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">จัดการประเภทบุคลากร</h1>
            <p class="mt-2 text-sm text-gray-600">
                จัดการประเภทบุคลากรในระบบ เช่น สายวิชาการ สายสนับสนุน เป็นต้น
            </p>
        </div>
        <button onclick="openModal('addModal')" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            เพิ่มประเภทบุคลากร
        </button>
    </div>
</div>

<!-- Search Bar -->
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="flex gap-4">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo e($search); ?>"
                    placeholder="ค้นหาด้วยรหัส ชื่อภาษาไทย หรือภาษาอังกฤษ..." class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                ค้นหา
            </button>
            <?php if ($search): ?>
                <a href="personnel-type.php" class="btn btn-outline">ล้างการค้นหา</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Personnel Types Table -->
<div class="card">
    <div class="card-body">
        <div class="overflow-x-auto">
            <table class="table">
                <thead class="table-header">
                    <tr>
                        <th class="table-th">รหัส</th>
                        <th class="table-th">ชื่อภาษาไทย</th>
                        <th class="table-th">ชื่อภาษาอังกฤษ</th>
                        <th class="table-th">จำนวนบุคลากร</th>
                        <th class="table-th">จำนวนด้านประเมิน</th>
                        <th class="table-th">สถานะ</th>
                        <th class="table-th text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($personnel_types)): ?>
                        <tr>
                            <td colspan="7" class="table-td text-center text-gray-500 py-8">
                                ไม่พบข้อมูลประเภทบุคลากร
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($personnel_types as $type): ?>
                            <tr class="table-row">
                                <td class="table-td">
                                    <span
                                        class="font-mono font-semibold text-blue-600"><?php echo e($type['type_code']); ?></span>
                                </td>
                                <td class="table-td">
                                    <div class="font-medium text-gray-900"><?php echo e($type['type_name_th']); ?></div>
                                    <?php if ($type['description']): ?>
                                        <div class="text-sm text-gray-500"><?php echo e(str_limit($type['description'], 50)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="table-td text-gray-600"><?php echo e($type['type_name_en'] ?? '-'); ?></td>
                                <td class="table-td">
                                    <span class="badge badge-primary"><?php echo number_format($type['user_count']); ?>
                                        คน</span>
                                </td>
                                <td class="table-td">
                                    <span class="badge badge-secondary"><?php echo number_format($type['aspect_count']); ?>
                                        ด้าน</span>
                                </td>
                                <td class="table-td">
                                    <?php if ($type['is_active']): ?>
                                        <span class="badge badge-success">ใช้งาน</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">ปิดใช้งาน</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick='editPersonnelType(<?php echo json_encode($type); ?>)'
                                            class="btn-icon text-blue-600 hover:bg-blue-50" title="แก้ไข">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <a href="?action=toggle&id=<?php echo $type['personnel_type_id']; ?>"
                                            class="btn-icon text-yellow-600 hover:bg-yellow-50"
                                            title="<?php echo $type['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน'; ?>"
                                            onclick="return confirm('ต้องการเปลี่ยนสถานะใช่หรือไม่?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $type['personnel_type_id']; ?>"
                                            class="btn-icon text-red-600 hover:bg-red-50" title="ลบ"
                                            onclick="return confirm('ต้องการลบประเภทบุคลากรนี้ใช่หรือไม่?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้')">
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
    <div class="modal-content max-w-2xl">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">เพิ่มประเภทบุคลากร</h3>
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
                    <label class="form-label">รหัสประเภทบุคลากร <span class="text-red-500">*</span></label>
                    <input type="text" name="type_code" required maxlength="20" class="form-input"
                        placeholder="เช่น ACADEMIC, SUPPORT">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
                        <input type="text" name="type_name_th" required class="form-input"
                            placeholder="เช่น สายวิชาการ">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ</label>
                        <input type="text" name="type_name_en" class="form-input" placeholder="e.g. Academic Staff">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">คำอธิบาย</label>
                    <textarea name="description" rows="3" class="form-textarea"
                        placeholder="รายละเอียดเพิ่มเติม..."></textarea>
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
    <div class="modal-content max-w-2xl">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">แก้ไขประเภทบุคลากร</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="personnel_type_id" id="edit_personnel_type_id">
            <div class="modal-body space-y-4">
                <div class="form-group">
                    <label class="form-label">รหัสประเภทบุคลากร <span class="text-red-500">*</span></label>
                    <input type="text" name="type_code" id="edit_type_code" required maxlength="20" class="form-input">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
                        <input type="text" name="type_name_th" id="edit_type_name_th" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ</label>
                        <input type="text" name="type_name_en" id="edit_type_name_en" class="form-input">
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
        const modal = document.getElementById(modalId);
        modal.classList.add('show');

        // Focus input แรก
        setTimeout(() => {
            const firstInput = modal.querySelector('input:not([type="hidden"])');
            if (firstInput) firstInput.focus();
        }, 100);
    }
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="animate-spin">⏳</span> กำลังบันทึก...';
            }
        });
    });

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function editPersonnelType(data) {
        document.getElementById('edit_personnel_type_id').value = data.personnel_type_id;
        document.getElementById('edit_type_code').value = data.type_code;
        document.getElementById('edit_type_name_th').value = data.type_name_th;
        document.getElementById('edit_type_name_en').value = data.type_name_en || '';
        document.getElementById('edit_description').value = data.description || '';
        document.getElementById('edit_is_active').checked = data.is_active == 1;
        openModal('editModal');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>