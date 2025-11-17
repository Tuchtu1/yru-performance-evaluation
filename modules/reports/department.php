<?php

/**
 * modules/reports/department.php
 * รายงานผลการประเมินรายหน่วยงาน
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';  // แก้ path ให้ถูกต้อง
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login
requireAuth();

// ตรวจสอบสิทธิ์ (ให้ manager และ admin ดูได้)
if (!isAdmin() && !isManager()) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('modules/dashboard/index.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user']['user_id'];

// ถ้าเป็น admin ดูได้ทุกหน่วยงาน, manager ดูได้เฉพาะหน่วยงานของตัวเอง
$user_department_id = $_SESSION['user']['department_id'];
$department_filter = $_GET['department'] ?? ($user_department_id ?: '');
$year_filter = $_GET['year'] ?? date('Y');
$semester_filter = $_GET['semester'] ?? '';

try {
    // ดึงรายการหน่วยงาน
    if (isAdmin()) {
        $stmt = $db->query("
            SELECT department_id, department_name_th
            FROM departments 
            WHERE is_active = 1 
            ORDER BY department_name_th
        ");
    } else {
        $stmt = $db->prepare("
            SELECT department_id, department_name_th
            FROM departments 
            WHERE department_id = ? AND is_active = 1
        ");
        $stmt->execute([$user_department_id]);
    }
    $departments = $stmt->fetchAll();

    // ถ้าไม่มีหน่วยงาน ให้สร้างข้อความแจ้งเตือนแต่ไม่ redirect
    if (empty($departments)) {
        $no_department = true;
        $departments = [];
        $department_filter = null;
    } else {
        $no_department = false;
        // ถ้าไม่ได้เลือก ให้ใช้หน่วยงานแรก
        if (!$department_filter && !empty($departments)) {
            $department_filter = $departments[0]['department_id'];
        }
    }

    // ดึงข้อมูลหน่วยงานที่เลือก
    $current_department = null;
    if ($department_filter) {
        $stmt = $db->prepare("SELECT * FROM departments WHERE department_id = ?");
        $stmt->execute([$department_filter]);
        $current_department = $stmt->fetch();
    }

    // ถ้าไม่มีหน่วยงาน ให้ข้ามการ query ข้อมูล
    if (!$department_filter || $no_department) {
        $stats = [
            'total_staff' => 0,
            'total_evaluations' => 0,
            'approved_count' => 0,
            'pending_count' => 0,
            'avg_score' => null,
            'max_score' => null,
            'min_score' => null
        ];
        $staff_list = [];
        $aspect_scores = [];
        $years = [];
    } else {

        // สถิติภาพรวมหน่วยงาน
        $where = ["u.department_id = ?"];
        $params = [$department_filter];

        if ($year_filter) {
            $where[] = "ep.year = ?";
            $params[] = $year_filter;
        }

        if ($semester_filter) {
            $where[] = "ep.semester = ?";
            $params[] = $semester_filter;
        }

        $where_clause = implode(' AND ', $where);

        // แก้ไข SQL query ให้ตรงกับโครงสร้างฐานข้อมูล
        $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT u.user_id) as total_staff,
            COUNT(DISTINCT e.evaluation_id) as total_evaluations,
            SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN e.status = 'submitted' THEN 1 ELSE 0 END) as pending_count,
            AVG(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as avg_score,
            MAX(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as max_score,
            MIN(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as min_score
        FROM users u
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE $where_clause
    ");
        $stmt->execute($params);
        $stats = $stmt->fetch();

        // รายการบุคลากรและผลการประเมิน
        $stmt = $db->prepare("
        SELECT u.user_id, 
               u.full_name_th as full_name, 
               u.position, 
               pt.type_name_th as type_name,
               COUNT(DISTINCT e.evaluation_id) as evaluation_count,
               SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
               AVG(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as avg_score,
               MAX(e.submitted_at) as last_evaluation
        FROM users u
        LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE $where_clause
        GROUP BY u.user_id, u.full_name_th, u.position, pt.type_name_th
        ORDER BY u.full_name_th
    ");
        $stmt->execute($params);
        $staff_list = $stmt->fetchAll();

        // คะแนนเฉลี่ยตามด้านการประเมิน (aspects)
        $stmt = $db->prepare("
        SELECT ea.aspect_name_th as aspect_name,
               AVG(es.score) as avg_score,
               COUNT(DISTINCT e.user_id) as staff_count
        FROM users u
        JOIN evaluations e ON u.user_id = e.user_id
        JOIN evaluation_periods ep ON e.period_id = ep.period_id
        JOIN evaluation_scores es ON e.evaluation_id = es.evaluation_id
        JOIN evaluation_aspects ea ON es.aspect_id = ea.aspect_id
        WHERE $where_clause AND e.status = 'approved'
        GROUP BY ea.aspect_id, ea.aspect_name_th
        ORDER BY ea.display_order
    ");
        $stmt->execute($params);
        $aspect_scores = $stmt->fetchAll();

        // ดึงรายการปี
        $stmt = $db->query("
        SELECT DISTINCT year 
        FROM evaluation_periods 
        ORDER BY year DESC
    ");
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } // ปิด if (!$department_filter || $no_department)

} catch (Exception $e) {
    error_log("Department Report Error: " . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $no_department = true;
    $stats = ['total_staff' => 0, 'total_evaluations' => 0, 'approved_count' => 0, 'avg_score' => null, 'max_score' => null];
    $staff_list = [];
    $aspect_scores = [];
    $years = [];
}

$page_title = 'รายงานรายหน่วยงาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">รายงานผลการประเมินรายหน่วยงาน</h1>
                <p class="mt-1 text-gray-600">
                    สรุปผลการประเมินและเปรียบเทียบบุคลากรในหน่วยงาน
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <a href="<?php echo url('modules/reports/export.php?type=department&department_id=' . $department_filter); ?>"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50"
                    target="_blank">
                    <i class="fas fa-download mr-2"></i>ส่งออก
                </a>
                <button onclick="window.print()"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50 no-print">
                    <i class="fas fa-print mr-2"></i>พิมพ์
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($no_department): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-info-circle mr-2"></i>
            ยังไม่มีข้อมูลหน่วยงาน กรุณาเพิ่มข้อมูลหน่วยงานก่อนใช้งานรายงานนี้
        </div>
    <?php else: ?>

        <!-- Department Info -->
        <?php if ($current_department): ?>
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex items-center">
                    <div
                        class="w-16 h-16 bg-gradient-to-br from-green-500 to-teal-600 rounded-lg flex items-center justify-center text-white text-2xl font-bold mr-4">
                        <?php echo mb_substr($current_department['department_name_th'], 0, 2); ?>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">
                            <?php echo e($current_department['department_name_th']); ?>
                        </h2>
                        <?php if ($current_department['department_code']): ?>
                            <p class="text-sm text-gray-600">รหัส: <?php echo e($current_department['department_code']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">หน่วยงาน</label>
                    <select name="department" class="w-full border rounded px-3 py-2" onchange="this.form.submit()">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>"
                                <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo e($dept['department_name_th']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ปี</label>
                    <select name="year" class="w-full border rounded px-3 py-2">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                                <?php echo ($year + 543); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ภาคเรียน</label>
                    <select name="semester" class="w-full border rounded px-3 py-2">
                        <option value="">ทั้งหมด</option>
                        <option value="1" <?php echo $semester_filter == '1' ? 'selected' : ''; ?>>ภาคเรียนที่ 1</option>
                        <option value="2" <?php echo $semester_filter == '2' ? 'selected' : ''; ?>>ภาคเรียนที่ 2</option>
                        <option value="3" <?php echo $semester_filter == '3' ? 'selected' : ''; ?>>ภาคฤดูร้อน</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                        <i class="fas fa-filter mr-2"></i>กรองข้อมูล
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600">บุคลากรทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">
                            <?php echo number_format($stats['total_staff']); ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">คน</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-gray-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600">จำนวนการประเมิน</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1">
                            <?php echo number_format($stats['total_evaluations']); ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">ครั้ง</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600">อนุมัติแล้ว</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">
                            <?php echo number_format($stats['approved_count']); ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php
                            $approval_rate = $stats['total_evaluations'] > 0
                                ? ($stats['approved_count'] / $stats['total_evaluations']) * 100
                                : 0;
                            echo number_format($approval_rate, 1) . '%';
                            ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600">คะแนนเฉลี่ย</p>
                        <p class="text-3xl font-bold text-purple-600 mt-1">
                            <?php echo $stats['avg_score'] ? number_format($stats['avg_score'], 2) : '-'; ?>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            สูงสุด: <?php echo $stats['max_score'] ? number_format($stats['max_score'], 2) : '-'; ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aspect Scores -->
        <?php if (!empty($aspect_scores)): ?>
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold">คะแนนเฉลี่ยตามด้านการประเมิน</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($aspect_scores as $aspect): ?>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo e($aspect['aspect_name']); ?>
                                    </span>
                                    <span class="text-lg font-bold text-blue-600">
                                        <?php echo number_format($aspect['avg_score'], 2); ?>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                    <div class="bg-blue-600 h-2 rounded-full"
                                        style="width: <?php echo min(($aspect['avg_score'] / 100) * 100, 100); ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-500">
                                    มีบุคลากรประเมิน <?php echo $aspect['staff_count']; ?> คน
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Staff List -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold">รายชื่อบุคลากรและผลการประเมิน</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ชื่อ-นามสกุล</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ตำแหน่ง</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ประเภท</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                จำนวนประเมิน</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                อนุมัติ</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                คะแนนเฉลี่ย</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ประเมินล่าสุด</th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider no-print">
                                รายงาน</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($staff_list)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                    ไม่พบข้อมูลบุคลากร
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_list as $staff): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">
                                            <?php echo e($staff['full_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600">
                                            <?php echo e($staff['position'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-xs text-gray-600">
                                            <?php echo e($staff['type_name'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php echo number_format($staff['evaluation_count']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="font-medium text-green-600">
                                            <?php echo number_format($staff['approved_count']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php if ($staff['avg_score']): ?>
                                            <span class="font-bold text-blue-600">
                                                <?php echo number_format($staff['avg_score'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                        <?php echo $staff['last_evaluation'] ? thai_date($staff['last_evaluation'], 'short') : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center no-print">
                                        <a href="<?php echo url('modules/reports/individual.php?user_id=' . $staff['user_id']); ?>"
                                            class="text-blue-600 hover:text-blue-700">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; // ปิด if ($no_department) 
    ?>
</div>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>