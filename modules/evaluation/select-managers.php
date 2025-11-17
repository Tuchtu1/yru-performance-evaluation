<?php

/**
 * /modules/evaluation/select-managers.php
 * เลือกผู้บริหารที่จะตรวจสอบและอนุมัติแบบประเมิน
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
authorize('evaluation.submit');

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$evaluation_id = $_GET['id'] ?? 0;

// ดึงข้อมูลแบบประเมิน
$stmt = $db->prepare("
    SELECT e.*, ep.period_name, u.full_name, u.department_id
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.period_id
    JOIN users u ON e.user_id = u.user_id
    WHERE e.evaluation_id = ? AND e.user_id = ?
");
$stmt->execute([$evaluation_id, $user_id]);
$evaluation = $stmt->fetch();

if (!$evaluation || $evaluation['status'] !== 'draft') {
    flash_error('ไม่สามารถดำเนินการได้ในสถานะนี้');
    redirect('modules/evaluation/list.php');
}

// ดึงรายชื่อผู้บริหาร
$stmt = $db->prepare("
    SELECT u.user_id, u.full_name, u.position, d.department_name, r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE r.role_code IN ('MANAGER', 'ADMIN')
    AND u.status = 'active'
    AND u.user_id != ?
    ORDER BY 
        CASE WHEN u.department_id = ? THEN 0 ELSE 1 END,
        d.department_name,
        u.full_name
");
$stmt->execute([$user_id, $evaluation['department_id']]);
$managers = $stmt->fetchAll();

// ดึงผู้พิจารณาที่เลือกไว้แล้ว (ถ้ามี)
$stmt = $db->prepare("
    SELECT manager_user_id, review_order
    FROM evaluation_reviewers
    WHERE evaluation_id = ?
    ORDER BY review_order
");
$stmt->execute([$evaluation_id]);
$selected_managers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// บันทึกการเลือก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
    $manager_ids = $_POST['managers'] ?? [];

    if (empty($manager_ids)) {
        flash_error('กรุณาเลือกผู้พิจารณาอย่างน้อย 1 คน');
    } elseif (count($manager_ids) > 3) {
        flash_error('สามารถเลือกผู้พิจารณาได้ไม่เกิน 3 คน');
    } else {
        try {
            $db->beginTransaction();

            // ลบข้อมูลเก่า
            $stmt = $db->prepare("DELETE FROM evaluation_reviewers WHERE evaluation_id = ?");
            $stmt->execute([$evaluation_id]);

            // เพิ่มข้อมูลใหม่
            $stmt = $db->prepare("
                INSERT INTO evaluation_reviewers 
                (evaluation_id, manager_user_id, review_order, status)
                VALUES (?, ?, ?, 'pending')
            ");

            foreach ($manager_ids as $order => $manager_id) {
                $stmt->execute([$evaluation_id, $manager_id, $order + 1]);
            }

            // Log activity
            log_activity('select_reviewers', 'evaluation_reviewers', $evaluation_id, [
                'managers' => $manager_ids
            ]);

            $db->commit();

            flash_success('เลือกผู้พิจารณาเรียบร้อยแล้ว');

            // ถ้ามาจากหน้าส่งแบบประเมิน ให้กลับไปที่หน้านั้น
            if (isset($_POST['from_submit'])) {
                redirect('modules/evaluation/submit.php?id=' . $evaluation_id);
            } else {
                redirect('modules/evaluation/view.php?id=' . $evaluation_id);
            }
        } catch (Exception $e) {
            $db->rollBack();
            flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            log_error('Select Managers Error', ['error' => $e->getMessage(), 'evaluation_id' => $evaluation_id]);
        }
    }
}

$page_title = 'เลือกผู้พิจารณา';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">เลือกผู้พิจารณาแบบประเมิน</h1>
                <p class="mt-1 text-sm text-gray-500">
                    เลือกผู้บริหารที่จะตรวจสอบและอนุมัติแบบประเมินของคุณ (สูงสุด 3 คน)
                </p>
            </div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd" />
            </svg>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-blue-900">คำแนะนำ</h3>
                <ul class="mt-2 text-sm text-blue-700 list-disc list-inside space-y-1">
                    <li>เลือกผู้พิจารณาตามลำดับความสำคัญ (ผู้พิจารณาคนแรกจะพิจารณาก่อน)</li>
                    <li>ควรเลือกผู้บริหารที่อยู่ในหน่วยงานเดียวกันหรือเกี่ยวข้องกับงานของคุณ</li>
                    <li>สามารถเปลี่ยนแปลงผู้พิจารณาได้ก่อนส่งแบบประเมิน</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Selection Form -->
    <form method="POST" id="selectManagerForm">
        <?php echo csrf_field(); ?>
        <?php if (isset($_GET['from_submit'])): ?>
            <input type="hidden" name="from_submit" value="1">
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="text-lg font-semibold">รายชื่อผู้บริหาร</h3>
            </div>
            <div class="card-body">
                <div class="space-y-2" id="managerList">
                    <?php foreach ($managers as $manager): ?>
                        <label
                            class="flex items-start p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" name="managers[]" value="<?php echo $manager['user_id']; ?>"
                                class="manager-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 mt-1"
                                <?php echo in_array($manager['user_id'], array_keys($selected_managers)) ? 'checked' : ''; ?>>
                            <div class="ml-3 flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            <?php echo e($manager['full_name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?php echo e($manager['position']); ?>
                                            <?php if ($manager['department_name']): ?>
                                                · <?php echo e($manager['department_name']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="badge badge-primary">
                                        <?php echo $manager['role_name']; ?>
                                    </span>
                                </div>
                                <?php if ($manager['department_id'] == $evaluation['department_id']): ?>
                                    <span class="inline-block mt-2 text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                        หน่วยงานเดียวกัน
                                    </span>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Selected Count -->
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">จำนวนที่เลือก:</span>
                        <span id="selectedCount" class="font-semibold text-gray-900">0 / 3 คน</span>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <div class="flex items-center justify-between">
                    <a href="<?php echo url('modules/evaluation/view.php?id=' . $evaluation_id); ?>"
                        class="btn btn-outline">
                        ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        บันทึกการเลือก
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Selected Order Preview -->
    <div class="mt-6 card" id="orderPreview" style="display: none;">
        <div class="card-header">
            <h3 class="text-lg font-semibold">ลำดับการพิจารณา</h3>
        </div>
        <div class="card-body">
            <div class="space-y-2" id="orderList">
                <!-- Generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
    const maxManagers = 3;
    const checkboxes = document.querySelectorAll('.manager-checkbox');
    const selectedCountEl = document.getElementById('selectedCount');
    const submitBtn = document.getElementById('submitBtn');
    const orderPreview = document.getElementById('orderPreview');
    const orderList = document.getElementById('orderList');

    function updateSelection() {
        const checked = Array.from(checkboxes).filter(cb => cb.checked);
        const count = checked.length;

        // Update count
        selectedCountEl.textContent = `${count} / ${maxManagers} คน`;
        selectedCountEl.classList.toggle('text-red-600', count > maxManagers);
        selectedCountEl.classList.toggle('text-gray-900', count <= maxManagers);

        // Disable/enable checkboxes
        if (count >= maxManagers) {
            checkboxes.forEach(cb => {
                if (!cb.checked) cb.disabled = true;
            });
        } else {
            checkboxes.forEach(cb => cb.disabled = false);
        }

        // Show/hide order preview
        if (count > 0) {
            orderPreview.style.display = 'block';
            updateOrderPreview(checked);
        } else {
            orderPreview.style.display = 'none';
        }

        // Enable/disable submit button
        submitBtn.disabled = count === 0 || count > maxManagers;
    }

    function updateOrderPreview(checkedBoxes) {
        orderList.innerHTML = '';

        checkedBoxes.forEach((checkbox, index) => {
            const label = checkbox.closest('label');
            const name = label.querySelector('.font-medium').textContent.trim();
            const position = label.querySelector('.text-sm').textContent.trim();

            const orderItem = document.createElement('div');
            orderItem.className = 'flex items-center p-3 bg-gray-50 rounded-lg';
            orderItem.innerHTML = `
            <div class="flex items-center justify-center w-8 h-8 bg-blue-600 text-white rounded-full font-semibold text-sm mr-3">
                ${index + 1}
            </div>
            <div class="flex-1">
                <p class="font-medium text-gray-900">${name}</p>
                <p class="text-sm text-gray-600">${position}</p>
            </div>
        `;
            orderList.appendChild(orderItem);
        });
    }

    // Initialize
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelection);
    });

    updateSelection();

    // Confirm before submit
    document.getElementById('selectManagerForm').addEventListener('submit', function(e) {
        const count = Array.from(checkboxes).filter(cb => cb.checked).length;
        if (count === 0) {
            e.preventDefault();
            alert('กรุณาเลือกผู้พิจารณาอย่างน้อย 1 คน');
        } else if (count > maxManagers) {
            e.preventDefault();
            alert(`สามารถเลือกผู้พิจารณาได้ไม่เกิน ${maxManagers} คน`);
        }
    });
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>