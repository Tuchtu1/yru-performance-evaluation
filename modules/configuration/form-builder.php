<?php

/**
 * /modules/configuration/form-builder.php
 * สร้างและจัดการฟอร์มประเมิน (Form Builder)
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requirePermission('config.view');

$page_title = 'สร้างฟอร์มประเมิน';
$db = getDB();

// ==================== Fetch Data ====================

// Get personnel types
$personnel_types = $db->query("SELECT * FROM personnel_types WHERE is_active = 1 ORDER BY type_name_th")->fetchAll();

// Get selected personnel type
$selected_type = $_GET['type'] ?? '';
$aspects = [];
$topics = [];

if ($selected_type) {
    // Get aspects for selected type
    $stmt = $db->prepare("
        SELECT * FROM evaluation_aspects 
        WHERE personnel_type_id = ? AND is_active = 1 
        ORDER BY sort_order
    ");
    $stmt->execute([$selected_type]);
    $aspects = $stmt->fetchAll();

    if (!empty($aspects)) {
        $aspect_ids = array_column($aspects, 'aspect_id');
        $placeholders = implode(',', array_fill(0, count($aspect_ids), '?'));

        $stmt = $db->prepare("
            SELECT * FROM evaluation_topics 
            WHERE aspect_id IN ($placeholders) AND is_active = 1 
            ORDER BY aspect_id, sort_order
        ");
        $stmt->execute($aspect_ids);
        $topics = $stmt->fetchAll();

        // Group topics by aspect
        $grouped_topics = [];
        foreach ($topics as $topic) {
            $grouped_topics[$topic['aspect_id']][] = $topic;
        }
    }
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">สร้างฟอร์มประเมิน</h1>
            <p class="mt-2 text-sm text-gray-600">
                ออกแบบและจัดการโครงสร้างฟอร์มการประเมินสำหรับแต่ละประเภทบุคลากร
            </p>
        </div>
        <?php if ($selected_type): ?>
            <div class="flex gap-2">
                <button onclick="previewForm()" class="btn btn-outline">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    ดูตัวอย่าง
                </button>
                <button onclick="exportForm()" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    ส่งออกฟอร์ม
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Personnel Type Selection -->
<div class="card mb-6">
    <div class="card-body">
        <label class="form-label mb-3">เลือกประเภทบุคลากร</label>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($personnel_types as $type): ?>
                <a href="?type=<?php echo $type['personnel_type_id']; ?>"
                    class="card-hover p-4 rounded-lg border-2 transition-all <?php echo $selected_type == $type['personnel_type_id'] ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300'; ?>">
                    <div class="flex items-center">
                        <div
                            class="w-12 h-12 rounded-lg <?php echo $selected_type == $type['personnel_type_id'] ? 'bg-blue-500' : 'bg-gray-200'; ?> flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 <?php echo $selected_type == $type['personnel_type_id'] ? 'text-white' : 'text-gray-500'; ?>"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3
                                class="font-semibold <?php echo $selected_type == $type['personnel_type_id'] ? 'text-blue-700' : 'text-gray-900'; ?>">
                                <?php echo e($type['type_name_th']); ?>
                            </h3>
                            <p class="text-sm text-gray-500"><?php echo e($type['type_code']); ?></p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($selected_type && !empty($aspects)): ?>

    <!-- Form Structure -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold">โครงสร้างฟอร์มประเมิน</h3>
        </div>
        <div class="card-body">

            <!-- Summary Stats -->
            <div class="grid grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo count($aspects); ?></div>
                    <div class="text-sm text-gray-600">ด้านการประเมิน</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo count($topics); ?></div>
                    <div class="text-sm text-gray-600">หัวข้อย่อย</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        <?php echo number_format(array_sum(array_column($aspects, 'max_score'))); ?>
                    </div>
                    <div class="text-sm text-gray-600">คะแนนเต็มรวม</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">
                        <?php echo number_format(array_sum(array_column($aspects, 'weight_percentage')), 1); ?>%
                    </div>
                    <div class="text-sm text-gray-600">น้ำหนักรวม</div>
                </div>
            </div>

            <!-- Form Structure -->
            <div class="space-y-4">
                <?php foreach ($aspects as $aspect): ?>
                    <div class="border-2 border-gray-200 rounded-lg overflow-hidden">
                        <!-- Aspect Header -->
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <span class="px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm font-semibold">
                                        <?php echo $aspect['aspect_code']; ?>
                                    </span>
                                    <h4 class="text-lg font-semibold"><?php echo e($aspect['aspect_name_th']); ?></h4>
                                </div>
                                <div class="flex items-center space-x-4 text-sm">
                                    <div>
                                        <span class="opacity-75">คะแนนเต็ม:</span>
                                        <span class="font-bold"><?php echo number_format($aspect['max_score']); ?></span>
                                    </div>
                                    <div>
                                        <span class="opacity-75">น้ำหนัก:</span>
                                        <span
                                            class="font-bold"><?php echo number_format($aspect['weight_percentage'], 1); ?>%</span>
                                    </div>
                                </div>
                            </div>
                            <?php if ($aspect['description']): ?>
                                <p class="mt-2 text-sm opacity-90"><?php echo e($aspect['description']); ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Topics -->
                        <?php if (isset($grouped_topics[$aspect['aspect_id']])): ?>
                            <div class="bg-white p-4">
                                <table class="w-full">
                                    <thead class="border-b-2 border-gray-200">
                                        <tr>
                                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700 w-12">ลำดับ</th>
                                            <th class="text-left py-2 px-3 text-sm font-semibold text-gray-700">หัวข้อการประเมิน
                                            </th>
                                            <th class="text-center py-2 px-3 text-sm font-semibold text-gray-700 w-32">คะแนนเต็ม
                                            </th>
                                            <th class="text-center py-2 px-3 text-sm font-semibold text-gray-700 w-32">น้ำหนัก (%)
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grouped_topics[$aspect['aspect_id']] as $topic): ?>
                                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                                <td class="py-3 px-3 text-center text-gray-600"><?php echo $topic['sort_order']; ?></td>
                                                <td class="py-3 px-3">
                                                    <div class="font-medium text-gray-900"><?php echo e($topic['topic_name_th']); ?>
                                                    </div>
                                                    <?php if ($topic['description']): ?>
                                                        <div class="text-sm text-gray-500 mt-1"><?php echo e($topic['description']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3 px-3 text-center font-semibold text-gray-700">
                                                    <?php echo number_format($topic['max_score']); ?>
                                                </td>
                                                <td class="py-3 px-3 text-center font-semibold text-purple-600">
                                                    <?php echo number_format($topic['weight_percentage'], 1); ?>%
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 border-t border-yellow-200 p-4 text-center text-yellow-700">
                                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                ยังไม่มีหัวข้อย่อยในด้านนี้
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div class="bg-gray-50 px-4 py-3 flex gap-2 border-t">
                            <a href="evaluation-aspects.php?type=<?php echo $selected_type; ?>" class="btn btn-sm btn-outline">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                แก้ไขด้าน
                            </a>
                            <a href="evaluation-topics.php?aspect=<?php echo $aspect['aspect_id']; ?>"
                                class="btn btn-sm btn-outline">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                จัดการหัวข้อ
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Warnings -->
            <?php
            $total_weight = array_sum(array_column($aspects, 'weight_percentage'));
            if ($total_weight != 100):
            ?>
                <div class="mt-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <h4 class="text-red-800 font-medium">ข้อผิดพลาด: น้ำหนักคะแนนไม่ถูกต้อง</h4>
                            <p class="text-red-700 text-sm mt-1">
                                น้ำหนักรวมของทุกด้านควรเท่ากับ 100% แต่ปัจจุบันรวมเป็น
                                <?php echo number_format($total_weight, 1); ?>%
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

<?php elseif ($selected_type): ?>

    <!-- Empty State -->
    <div class="card">
        <div class="card-body text-center py-12">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">ยังไม่มีโครงสร้างฟอร์ม</h3>
            <p class="text-gray-600 mb-4">
                กรุณาเพิ่มด้านการประเมินและหัวข้อย่อยก่อนใช้งานฟอร์ม
            </p>
            <div class="flex gap-3 justify-center">
                <a href="evaluation-aspects.php" class="btn btn-primary">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    เพิ่มด้านการประเมิน
                </a>
                <a href="evaluation-topics.php" class="btn btn-outline">
                    เพิ่มหัวข้อการประเมิน
                </a>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Preview Modal -->
<div id="previewModal" class="modal">
    <div class="modal-content max-w-4xl">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">ตัวอย่างฟอร์มประเมิน</h3>
            <button onclick="closeModal('previewModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="modal-body" id="previewContent">
            <!-- Preview content will be loaded here -->
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('previewModal')" class="btn btn-outline">ปิด</button>
            <button onclick="printForm()" class="btn btn-primary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                พิมพ์ฟอร์ม
            </button>
        </div>
    </div>
</div>

<script>
    function previewForm() {
        // Simple preview - you can enhance this with more detailed HTML
        const content = `
        <div class="space-y-4">
            <h4 class="text-lg font-semibold border-b pb-2">แบบประเมินผลการปฏิบัติงาน</h4>
            <?php foreach ($aspects as $aspect): ?>
            <div class="border rounded-lg p-4">
                <h5 class="font-semibold text-blue-600"><?php echo e($aspect['aspect_name_th']); ?></h5>
                <?php if (isset($grouped_topics[$aspect['aspect_id']])): ?>
                <div class="mt-3 space-y-2">
                    <?php foreach ($grouped_topics[$aspect['aspect_id']] as $topic): ?>
                    <div class="flex items-center justify-between py-2 border-b">
                        <span><?php echo e($topic['topic_name_th']); ?></span>
                        <div class="flex gap-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="inline-flex items-center">
                                <input type="radio" class="form-radio" name="topic_<?php echo $topic['topic_id']; ?>">
                                <span class="ml-1"><?php echo $i; ?></span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    `;
        document.getElementById('previewContent').innerHTML = content;
        openModal('previewModal');
    }

    function exportForm() {
        if (confirm('ต้องการส่งออกโครงสร้างฟอร์มใช่หรือไม่?')) {
            window.location.href = 'export-form.php?type=<?php echo $selected_type; ?>';
        }
    }

    function printForm() {
        window.print();
    }

    function openModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>