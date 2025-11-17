<?php

/**
 * modules/evaluation/submit.php
 * ส่งแบบประเมินเพื่อให้ผู้บริหารพิจารณา
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';  // ✅ แก้เป็น includes
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();

// ตรวจสอบสิทธิ์
if (!can('evaluation.submit')) {
    flash_error('คุณไม่มีสิทธิ์ส่งแบบประเมิน');
    redirect('modules/evaluation/list.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$evaluation_id = $_GET['id'] ?? 0;

try {
    // ดึงข้อมูลแบบประเมิน
    $stmt = $db->prepare("
        SELECT e.*, 
               ep.period_name, 
               ep.year, 
               ep.semester,
               pt.type_name_th as type_name
        FROM evaluations e
        JOIN evaluation_periods ep ON e.period_id = ep.period_id
        LEFT JOIN personnel_types pt ON e.personnel_type_id = pt.personnel_type_id
        WHERE e.evaluation_id = ? AND e.user_id = ?
    ");
    $stmt->execute([$evaluation_id, $user_id]);
    $evaluation = $stmt->fetch();

    if (!$evaluation) {
        flash_error('ไม่พบแบบประเมินที่ต้องการ');
        redirect('modules/evaluation/list.php');
        exit;
    }

    if (!in_array($evaluation['status'], ['draft', 'returned'])) {
        flash_error('ไม่สามารถส่งแบบประเมินในสถานะนี้ได้');
        redirect('modules/evaluation/view.php?id=' . $evaluation_id);
        exit;
    }

    // ตรวจสอบว่ามีข้อมูลครบถ้วนหรือไม่
    $stmt = $db->prepare("
        SELECT COUNT(*) as detail_count, 
               COALESCE(SUM(score), 0) as total_score
        FROM evaluation_details
        WHERE evaluation_id = ?
    ");
    $stmt->execute([$evaluation_id]);
    $stats = $stmt->fetch();

    // ดึงรายชื่อผู้บริหารที่สามารถพิจารณาได้
    $stmt = $db->query("
        SELECT user_id, full_name_th, position, department_id, role
        FROM users
        WHERE role IN ('admin', 'manager') 
        AND is_active = 1
        ORDER BY 
            CASE role 
                WHEN 'admin' THEN 1 
                WHEN 'manager' THEN 2 
            END,
            full_name_th
    ");
    $available_managers = $stmt->fetchAll();

    // บันทึกการส่งแบบประเมิน
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {

        // ตรวจสอบข้อมูล
        if ($stats['detail_count'] == 0) {
            flash_error('กรุณากรอกข้อมูลในแบบประเมินก่อนส่ง');
            redirect('modules/evaluation/edit.php?id=' . $evaluation_id);
            exit;
        }

        $db->beginTransaction();

        // อัปเดตสถานะเป็น submitted
        $stmt = $db->prepare("
            UPDATE evaluations 
            SET status = 'submitted',
                submitted_at = NOW(),
                total_score = ?,
                updated_at = NOW()
            WHERE evaluation_id = ?
        ");
        $stmt->execute([$stats['total_score'], $evaluation_id]);

        // สร้างประวัติการส่ง
        $stmt = $db->prepare("
            INSERT INTO approval_history 
            (evaluation_id, manager_user_id, action, previous_status, new_status, created_at)
            VALUES (?, ?, 'submit', 'draft', 'submitted', NOW())
        ");
        $stmt->execute([$evaluation_id, $user_id]);

        // สร้างการแจ้งเตือนให้ผู้บริหารทุกคน
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, related_id, related_type, created_at)
            VALUES (?, ?, ?, 'evaluation', ?, 'evaluation', NOW())
        ");

        foreach ($available_managers as $manager) {
            $stmt->execute([
                $manager['user_id'],
                'มีแบบประเมินรอการพิจารณา',
                'แบบประเมินของ ' . $_SESSION['user']['full_name_th'] . ' รอการพิจารณา',
                $evaluation_id
            ]);
        }

        // Log activity
        if (function_exists('log_activity')) {
            log_activity('submit_evaluation', 'evaluations', $evaluation_id, [
                'total_score' => $stats['total_score'],
                'detail_count' => $stats['detail_count']
            ]);
        }

        $db->commit();

        flash_success('ส่งแบบประเมินเรียบร้อยแล้ว รอการพิจารณาจากผู้บริหาร');
        redirect('modules/evaluation/view.php?id=' . $evaluation_id);
        exit;
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Submit Evaluation Error: ' . $e->getMessage());
    flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    redirect('modules/evaluation/list.php');
    exit;
}

$page_title = 'ส่งแบบประเมิน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">ยืนยันการส่งแบบประเมิน</h1>
            <p class="mt-1 text-sm text-gray-500">
                กรุณาตรวจสอบข้อมูลก่อนส่งแบบประเมินเพื่อให้ผู้บริหารพิจารณา
            </p>
        </div>

        <!-- Evaluation Info -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">ข้อมูลแบบประเมิน</h3>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-gray-600">รอบการประเมิน</dt>
                        <dd class="mt-1 font-medium text-gray-900">
                            <?php echo e($evaluation['period_name']); ?>
                            (<?php echo ($evaluation['year'] + 543); ?>
                            <?php if ($evaluation['semester']): ?>/<?php echo $evaluation['semester']; ?><?php endif; ?>
                            )
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-600">ประเภทบุคลากร</dt>
                        <dd class="mt-1 font-medium text-gray-900"><?php echo e($evaluation['type_name']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-600">จำนวนรายการประเมิน</dt>
                        <dd class="mt-1 font-medium text-gray-900"><?php echo $stats['detail_count']; ?> รายการ</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-600">คะแนนรวม</dt>
                        <dd class="mt-1 text-2xl font-bold text-blue-600">
                            <?php echo number_format($stats['total_score'], 2); ?> คะแนน
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Checklist -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">รายการตรวจสอบ</h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Check: Has Details -->
                <div class="flex items-start">
                    <?php if ($stats['detail_count'] > 0): ?>
                        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-medium text-gray-900">กรอกข้อมูลในแบบประเมินแล้ว</p>
                            <p class="text-sm text-gray-600">มีข้อมูล <?php echo $stats['detail_count']; ?> รายการ</p>
                        </div>
                    <?php else: ?>
                        <svg class="w-5 h-5 text-red-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-medium text-red-900">ยังไม่ได้กรอกข้อมูลในแบบประเมิน</p>
                            <a href="<?php echo url('modules/evaluation/edit.php?id=' . $evaluation_id); ?>"
                                class="text-sm text-blue-600 hover:text-blue-700">
                                กรอกข้อมูลตอนนี้ →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Check: Ready to submit -->
                <div class="flex items-start">
                    <?php if ($stats['detail_count'] > 0): ?>
                        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-medium text-gray-900">พร้อมส่งแบบประเมิน</p>
                            <p class="text-sm text-gray-600">แบบประเมินผ่านการตรวจสอบเรียบร้อย</p>
                        </div>
                    <?php else: ?>
                        <svg class="w-5 h-5 text-yellow-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-medium text-yellow-900">ยังไม่พร้อมส่ง</p>
                            <p class="text-sm text-gray-600">กรุณาดำเนินการให้ครบถ้วน</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Warning -->
        <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r">
            <div class="flex">
                <svg class="w-5 h-5 text-yellow-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                </svg>
                <div class="flex-1">
                    <h4 class="text-sm font-medium text-yellow-900 mb-2">ข้อควรระวัง</h4>
                    <ul class="text-sm text-yellow-800 list-disc list-inside space-y-1">
                        <li>เมื่อส่งแบบประเมินแล้ว จะไม่สามารถแก้ไขได้จนกว่าจะได้รับการส่งกลับ</li>
                        <li>ผู้บริหารจะได้รับการแจ้งเตือนทันที</li>
                        <li>สามารถติดตามสถานะการพิจารณาได้ที่หน้าแบบประเมินของฉัน</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($stats['detail_count'] > 0): ?>
            <form method="POST" id="submitForm">
                <?php echo csrf_field(); ?>
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <a href="<?php echo url('modules/evaluation/view.php?id=' . $evaluation_id); ?>"
                                class="bg-white border border-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-50">
                                ยกเลิก
                            </a>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">
                                <i class="fas fa-paper-plane mr-2"></i>
                                ยืนยันการส่งแบบประเมิน
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <div class="text-center py-6">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-gray-600 mb-4">กรุณากรอกข้อมูลในแบบประเมินก่อนส่ง</p>
                        <a href="<?php echo url('modules/evaluation/edit.php?id=' . $evaluation_id); ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded inline-block">
                            กรอกข้อมูลตอนนี้
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Confirm before submit
    const submitForm = document.getElementById('submitForm');
    if (submitForm) {
        submitForm.addEventListener('submit', function(e) {
            if (!confirm(
                    'คุณแน่ใจหรือไม่ว่าต้องการส่งแบบประเมินนี้?\n\n' +
                    'เมื่อส่งแล้วจะไม่สามารถแก้ไขได้จนกว่าจะได้รับการส่งกลับ'
                )) {
                e.preventDefault();
            }
        });
    }
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>