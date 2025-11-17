<?php

/**
 * modules/reports/individual.php
 * รายงานผลการประเมินรายบุคคล
 */

require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../includes/helpers.php';

// ตรวจสอบการ login
if (!isset($_SESSION['user'])) {
    redirect('modules/auth/login.php');
}

$user_id = $_SESSION['user']['user_id'];
$db = getDB();

try {
    // ดึงข้อมูลผู้ใช้ (ไม่ JOIN กับตาราง roles)
    $stmt = $db->prepare("
        SELECT u.*, 
               pt.type_name_th as personnel_type_name,
               d.department_name_th
        FROM users u
        LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('ไม่พบข้อมูลผู้ใช้');
    }

    // ดึงรายการงวดการประเมิน
    $stmt = $db->prepare("
        SELECT * FROM evaluation_periods
        WHERE is_active = 1
        ORDER BY year DESC, semester DESC
    ");
    $stmt->execute();
    $periods = $stmt->fetchAll();

    // ถ้ามีการเลือกงวด
    $selected_period = $_GET['period_id'] ?? null;
    $evaluations = [];
    $period_info = null;

    if ($selected_period) {
        // ดึงข้อมูลงวด
        $stmt = $db->prepare("SELECT * FROM evaluation_periods WHERE period_id = ?");
        $stmt->execute([$selected_period]);
        $period_info = $stmt->fetch();

        // ดึงข้อมูลการประเมิน
        $stmt = $db->prepare("
            SELECT e.*,
                   ep.period_name, ep.year, ep.semester,
                   pt.type_name_th as personnel_type_name
            FROM evaluations e
            INNER JOIN evaluation_periods ep ON e.period_id = ep.period_id
            LEFT JOIN personnel_types pt ON e.personnel_type_id = pt.personnel_type_id
            WHERE e.user_id = ? AND e.period_id = ?
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$user_id, $selected_period]);
        $evaluations = $stmt->fetchAll();

        // ดึงรายละเอียดคะแนนแต่ละด้าน
        foreach ($evaluations as &$evaluation) {
            $stmt = $db->prepare("
                SELECT es.*,
                       ea.aspect_name_th, ea.weight_percentage,
                       et.topic_name_th
                FROM evaluation_scores es
                INNER JOIN evaluation_aspects ea ON es.aspect_id = ea.aspect_id
                LEFT JOIN evaluation_topics et ON es.topic_id = et.topic_id
                WHERE es.evaluation_id = ?
                ORDER BY ea.display_order, et.display_order
            ");
            $stmt->execute([$evaluation['evaluation_id']]);
            $evaluation['scores'] = $stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    error_log("Individual Report Error: " . $e->getMessage());
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

$page_title = "รายงานผลการประเมินรายบุคคล";
include '../../includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
            <?php echo $page_title; ?>
        </h1>
        <p class="text-gray-600">
            ดูรายงานผลการประเมินของคุณ
        </p>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- ข้อมูลผู้ใช้ -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">ข้อมูลบุคลากร</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-gray-600">ชื่อ-นามสกุล:</label>
                <p class="font-medium"><?php echo e($user['full_name_th']); ?></p>
            </div>
            <div>
                <label class="text-gray-600">อีเมล:</label>
                <p class="font-medium"><?php echo e($user['email']); ?></p>
            </div>
            <div>
                <label class="text-gray-600">ประเภทบุคลากร:</label>
                <p class="font-medium"><?php echo e($user['personnel_type_name'] ?? '-'); ?></p>
            </div>
            <div>
                <label class="text-gray-600">หน่วยงาน:</label>
                <p class="font-medium"><?php echo e($user['department_name_th'] ?? '-'); ?></p>
            </div>
        </div>
    </div>

    <!-- เลือกงวดการประเมิน -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">เลือกงวดการประเมิน</h2>
        <form method="get" class="flex gap-4">
            <select name="period_id" class="flex-1 border rounded px-3 py-2" required>
                <option value="">-- เลือกงวด --</option>
                <?php foreach ($periods as $period): ?>
                    <option value="<?php echo $period['period_id']; ?>"
                        <?php echo ($selected_period == $period['period_id']) ? 'selected' : ''; ?>>
                        <?php echo e($period['period_name']); ?>
                        (ปี <?php echo $period['year']; ?>
                        <?php if ($period['semester']): ?>
                            ภาคเรียนที่ <?php echo $period['semester']; ?>
                            <?php endif; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                <i class="fas fa-search mr-2"></i>ดูรายงาน
            </button>
        </form>
    </div>

    <?php if ($selected_period && $period_info): ?>
        <!-- ข้อมูลงวดการประเมิน -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold mb-2"><?php echo e($period_info['period_name']); ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-600">ระยะเวลา:</span>
                    <span class="font-medium">
                        <?php echo thai_date($period_info['start_date']); ?> -
                        <?php echo thai_date($period_info['end_date']); ?>
                    </span>
                </div>
                <div>
                    <span class="text-gray-600">กำหนดส่ง:</span>
                    <span
                        class="font-medium"><?php echo thai_date($period_info['submission_deadline'], 'datetime'); ?></span>
                </div>
                <div>
                    <span class="text-gray-600">สถานะงวด:</span>
                    <span class="font-medium">
                        <?php
                        $status_badges = [
                            'draft' => 'bg-gray-100 text-gray-800',
                            'active' => 'bg-green-100 text-green-800',
                            'closed' => 'bg-red-100 text-red-800'
                        ];
                        $status_texts = [
                            'draft' => 'ร่าง',
                            'active' => 'เปิดใช้งาน',
                            'closed' => 'ปิด'
                        ];
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs <?php echo $status_badges[$period_info['status']]; ?>">
                            <?php echo $status_texts[$period_info['status']]; ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- รายการการประเมิน -->
        <?php if (empty($evaluations)): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded">
                <i class="fas fa-info-circle mr-2"></i>
                ยังไม่มีข้อมูลการประเมินในงวดนี้
            </div>
        <?php else: ?>
            <?php foreach ($evaluations as $evaluation): ?>
                <div class="bg-white rounded-lg shadow mb-6">
                    <!-- Header -->
                    <div class="border-b px-6 py-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold">
                                    แบบประเมิน #<?php echo $evaluation['evaluation_id']; ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    สร้างเมื่อ: <?php echo thai_date($evaluation['created_at'], 'datetime'); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <?php
                                $status_badges = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'submitted' => 'bg-blue-100 text-blue-800',
                                    'under_review' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'returned' => 'bg-orange-100 text-orange-800'
                                ];
                                $status_texts = [
                                    'draft' => 'ร่าง',
                                    'submitted' => 'ส่งแล้ว',
                                    'under_review' => 'กำลังตรวจสอบ',
                                    'approved' => 'อนุมัติ',
                                    'rejected' => 'ไม่อนุมัติ',
                                    'returned' => 'ส่งกลับแก้ไข'
                                ];
                                ?>
                                <span
                                    class="px-4 py-2 rounded-full text-sm font-medium <?php echo $status_badges[$evaluation['status']]; ?>">
                                    <?php echo $status_texts[$evaluation['status']]; ?>
                                </span>
                                <div class="mt-2">
                                    <span class="text-2xl font-bold text-blue-600">
                                        <?php echo number_format($evaluation['total_score'], 2); ?>
                                    </span>
                                    <span class="text-gray-600">คะแนน</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- รายละเอียดคะแนน -->
                    <div class="p-6">
                        <?php if (!empty($evaluation['scores'])): ?>
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2">ด้านการประเมิน</th>
                                        <th class="text-center py-2 w-32">น้ำหนัก (%)</th>
                                        <th class="text-center py-2 w-32">คะแนน</th>
                                        <th class="text-center py-2 w-32">คะแนนถ่วงน้ำหนัก</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_aspect = null;
                                    foreach ($evaluation['scores'] as $score):
                                        if ($current_aspect != $score['aspect_id']):
                                            $current_aspect = $score['aspect_id'];
                                    ?>
                                            <tr class="border-b bg-gray-50">
                                                <td class="py-3 font-semibold">
                                                    <?php echo e($score['aspect_name_th']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php echo number_format($score['weight_percentage'], 2); ?>%
                                                </td>
                                                <td class="text-center font-medium">
                                                    <?php echo number_format($score['score'], 2); ?>
                                                </td>
                                                <td class="text-center font-medium text-blue-600">
                                                    <?php echo number_format($score['weighted_score'], 2); ?>
                                                </td>
                                            </tr>
                                        <?php
                                        endif;
                                        if ($score['topic_name_th']):
                                        ?>
                                            <tr class="border-b">
                                                <td class="py-2 pl-8 text-gray-700">
                                                    → <?php echo e($score['topic_name_th']); ?>
                                                </td>
                                                <td colspan="3" class="text-center text-sm text-gray-600">
                                                    <?php if ($score['notes']): ?>
                                                        <?php echo e($score['notes']); ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-blue-50 font-bold">
                                        <td class="py-3" colspan="3">รวมคะแนนทั้งหมด</td>
                                        <td class="text-center text-blue-600 text-lg">
                                            <?php echo number_format($evaluation['total_score'], 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php else: ?>
                            <p class="text-gray-600 text-center py-4">ยังไม่มีข้อมูลคะแนน</p>
                        <?php endif; ?>
                    </div>

                    <!-- Footer Actions -->
                    <div class="border-t px-6 py-4 bg-gray-50">
                        <div class="flex justify-end gap-2">
                            <a href="view.php?id=<?php echo $evaluation['evaluation_id']; ?>"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                <i class="fas fa-eye mr-2"></i>ดูรายละเอียด
                            </a>
                            <button onclick="printReport(<?php echo $evaluation['evaluation_id']; ?>)"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                                <i class="fas fa-print mr-2"></i>พิมพ์รายงาน
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function printReport(evaluationId) {
        window.open('print.php?id=' + evaluationId, '_blank');
    }
</script>

<?php include '../../includes/footer.php'; ?>