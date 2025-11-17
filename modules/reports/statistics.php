<?php

/**
 * modules/reports/statistics.php
 * สถิติและกราฟรายงานแบบละเอียด
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('reports.statistics');

$db = getDB();
$current_user = $_SESSION['user'];

// รับค่า filter
$view = $_GET['view'] ?? 'overview'; // overview, trends, distribution, comparison
$year_filter = $_GET['year'] ?? '';
$semester_filter = $_GET['semester'] ?? '';
$department_filter = $_GET['department_id'] ?? '';
$personnel_type_filter = $_GET['personnel_type_id'] ?? '';

// ดึงข้อมูลสำหรับ filters
$stmt = $db->query("SELECT DISTINCT academic_year FROM evaluation_periods ORDER BY academic_year DESC");
$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM personnel_types WHERE is_active = 1 ORDER BY type_name");
$personnel_types = $stmt->fetchAll();

// สร้าง WHERE clause
$where = ["1=1"];
$params = [];

if ($year_filter) {
    $where[] = "ep.academic_year = ?";
    $params[] = $year_filter;
}
if ($semester_filter) {
    $where[] = "ep.semester = ?";
    $params[] = $semester_filter;
}
if ($department_filter) {
    $where[] = "u.department_id = ?";
    $params[] = $department_filter;
}
if ($personnel_type_filter) {
    $where[] = "u.personnel_type_id = ?";
    $params[] = $personnel_type_filter;
}

$where_clause = implode(' AND ', $where);

// ============ ดึงข้อมูลสถิติ ============

// 1. สถิติภาพรวม
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT u.user_id) as total_users,
        COUNT(DISTINCT e.evaluation_id) as total_evaluations,
        AVG(e.total_score) as avg_score,
        MAX(e.total_score) as max_score,
        MIN(e.total_score) as min_score,
        STDDEV(e.total_score) as std_score,
        COUNT(DISTINCT CASE WHEN e.status = 'approved' THEN e.evaluation_id END) as approved_count,
        COUNT(DISTINCT CASE WHEN e.status = 'submitted' THEN e.evaluation_id END) as pending_count,
        COUNT(DISTINCT CASE WHEN e.status = 'draft' THEN e.evaluation_id END) as draft_count
    FROM users u
    LEFT JOIN evaluations e ON u.user_id = e.user_id
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    WHERE $where_clause
");
$stmt->execute($params);
$overview_stats = $stmt->fetch();

// 2. แนวโน้มคะแนนตามเวลา
$stmt = $db->prepare("
    SELECT 
        ep.academic_year,
        ep.semester,
        AVG(e.total_score) as avg_score,
        COUNT(e.evaluation_id) as count
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.period_id
    JOIN users u ON e.user_id = u.user_id
    WHERE $where_clause
    GROUP BY ep.academic_year, ep.semester
    ORDER BY ep.academic_year, ep.semester
");
$stmt->execute($params);
$trends = $stmt->fetchAll();

// 3. กระจายคะแนน (Distribution)
$stmt = $db->prepare("
    SELECT 
        CASE 
            WHEN e.total_score >= 90 THEN '90-100'
            WHEN e.total_score >= 80 THEN '80-89'
            WHEN e.total_score >= 70 THEN '70-79'
            WHEN e.total_score >= 60 THEN '60-69'
            ELSE 'ต่ำกว่า 60'
        END as score_range,
        COUNT(*) as count
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.period_id
    JOIN users u ON e.user_id = u.user_id
    WHERE $where_clause AND e.total_score IS NOT NULL
    GROUP BY score_range
    ORDER BY score_range DESC
");
$stmt->execute($params);
$distribution = $stmt->fetchAll();

// 4. คะแนนเฉลี่ยแยกตามหน่วยงาน
$stmt = $db->prepare("
    SELECT 
        d.department_name,
        COUNT(DISTINCT u.user_id) as user_count,
        COUNT(e.evaluation_id) as eval_count,
        AVG(e.total_score) as avg_score
    FROM departments d
    LEFT JOIN users u ON d.department_id = u.department_id
    LEFT JOIN evaluations e ON u.user_id = e.user_id
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    WHERE d.is_active = 1 AND $where_clause
    GROUP BY d.department_id, d.department_name
    ORDER BY avg_score DESC
");
$stmt->execute($params);
$dept_stats = $stmt->fetchAll();

// 5. คะแนนเฉลี่ยแยกตามประเภทบุคลากร
$stmt = $db->prepare("
    SELECT 
        pt.type_name,
        COUNT(DISTINCT u.user_id) as user_count,
        COUNT(e.evaluation_id) as eval_count,
        AVG(e.total_score) as avg_score
    FROM personnel_types pt
    LEFT JOIN users u ON pt.personnel_type_id = u.personnel_type_id
    LEFT JOIN evaluations e ON u.user_id = e.user_id
    LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
    WHERE pt.is_active = 1 AND $where_clause
    GROUP BY pt.personnel_type_id, pt.type_name
    ORDER BY avg_score DESC
");
$stmt->execute($params);
$type_stats = $stmt->fetchAll();

// 6. คะแนนเฉลี่ยแยกตามมิติ
$stmt = $db->prepare("
    SELECT 
        ed.dimension_id,
        dim.dimension_name,
        AVG(et.weight_score) as weight,
        AVG(ed.score) as avg_score,
        COUNT(ed.detail_id) as count
    FROM evaluation_details ed
    JOIN evaluation_topics et ON ed.topic_id = et.topic_id
    JOIN evaluation_dimensions dim ON et.dimension_id = dim.dimension_id
    JOIN evaluations e ON ed.evaluation_id = e.evaluation_id
    JOIN evaluation_periods ep ON e.period_id = ep.period_id
    JOIN users u ON e.user_id = u.user_id
    WHERE $where_clause
    GROUP BY ed.dimension_id, dim.dimension_name
    ORDER BY dim.display_order
");
$stmt->execute($params);
$dimension_stats = $stmt->fetchAll();

// 7. Top Performers
$stmt = $db->prepare("
    SELECT 
        u.full_name,
        d.department_name,
        pt.type_name,
        AVG(e.total_score) as avg_score,
        COUNT(e.evaluation_id) as eval_count
    FROM users u
    JOIN evaluations e ON u.user_id = e.user_id
    JOIN evaluation_periods ep ON e.period_id = ep.period_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
    WHERE $where_clause AND e.status = 'approved'
    GROUP BY u.user_id
    HAVING eval_count >= 3
    ORDER BY avg_score DESC
    LIMIT 10
");
$stmt->execute($params);
$top_performers = $stmt->fetchAll();

// 8. สถิติการส่งแบบประเมินตามเวลา
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(e.submitted_date, '%Y-%m') as month,
        COUNT(*) as submission_count
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.period_id
    JOIN users u ON e.user_id = u.user_id
    WHERE e.submitted_date IS NOT NULL AND $where_clause
    GROUP BY month
    ORDER BY month
    LIMIT 12
");
$stmt->execute($params);
$submission_trends = $stmt->fetchAll();

$page_title = 'สถิติรายงาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">สถิติและวิเคราะห์รายงาน</h1>
                <p class="mt-1 text-sm text-gray-500">
                    วิเคราะห์ข้อมูลการประเมินผลอย่างละเอียด
                </p>
            </div>
            <button onclick="window.print()" class="btn-secondary no-print">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                พิมพ์รายงาน
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-6 no-print">
        <div class="card-body">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <select name="year" class="form-select">
                    <option value="">ปีการศึกษาทั้งหมด</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                            <?php echo ($year + 543); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="semester" class="form-select">
                    <option value="">ภาคเรียนทั้งหมด</option>
                    <option value="1" <?php echo $semester_filter == '1' ? 'selected' : ''; ?>>ภาคเรียนที่ 1</option>
                    <option value="2" <?php echo $semester_filter == '2' ? 'selected' : ''; ?>>ภาคเรียนที่ 2</option>
                    <option value="3" <?php echo $semester_filter == '3' ? 'selected' : ''; ?>>ภาคเรียนที่ 3</option>
                </select>

                <select name="department_id" class="form-select">
                    <option value="">หน่วยงานทั้งหมด</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>"
                            <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                            <?php echo e($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="personnel_type_id" class="form-select">
                    <option value="">ประเภทบุคลากรทั้งหมด</option>
                    <?php foreach ($personnel_types as $pt): ?>
                        <option value="<?php echo $pt['personnel_type_id']; ?>"
                            <?php echo $personnel_type_filter == $pt['personnel_type_id'] ? 'selected' : ''; ?>>
                            <?php echo e($pt['type_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-primary md:col-span-4">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    กรองข้อมูล
                </button>
            </form>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">จำนวนบุคลากร</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($overview_stats['total_users']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">จำนวนการประเมิน</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($overview_stats['total_evaluations']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">คะแนนเฉลี่ย</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($overview_stats['avg_score'], 2); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-lg p-3">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">อนุมัติแล้ว</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($overview_stats['approved_count']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trends Chart -->
    <?php if (!empty($trends)): ?>
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="text-lg font-semibold">แนวโน้มคะแนนเฉลี่ย</h2>
            </div>
            <div class="card-body">
                <canvas id="trendsChart" height="80"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <!-- Distribution Chart -->
    <?php if (!empty($distribution)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="card">
                <div class="card-header">
                    <h2 class="text-lg font-semibold">การกระจายของคะแนน</h2>
                </div>
                <div class="card-body">
                    <canvas id="distributionChart" height="200"></canvas>
                </div>
            </div>

            <!-- Dimension Stats -->
            <?php if (!empty($dimension_stats)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-lg font-semibold">คะแนนเฉลี่ยตามมิติ</h2>
                    </div>
                    <div class="card-body">
                        <canvas id="dimensionChart" height="200"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Department Stats -->
    <?php if (!empty($dept_stats)): ?>
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="text-lg font-semibold">คะแนนเฉลี่ยแยกตามหน่วยงาน</h2>
            </div>
            <div class="card-body">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    หน่วยงาน
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    จำนวนบุคลากร
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    จำนวนการประเมิน
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    คะแนนเฉลี่ย
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    ระดับ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($dept_stats as $dept): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo e($dept['department_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                        <?php echo number_format($dept['user_count']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                        <?php echo number_format($dept['eval_count']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-lg font-semibold text-blue-600">
                                            <?php echo number_format($dept['avg_score'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php
                                        $score = $dept['avg_score'];
                                        if ($score >= 90) {
                                            echo '<span class="badge badge-success">ดีเยี่ยม</span>';
                                        } elseif ($score >= 80) {
                                            echo '<span class="badge badge-info">ดีมาก</span>';
                                        } elseif ($score >= 70) {
                                            echo '<span class="badge badge-warning">ดี</span>';
                                        } elseif ($score >= 60) {
                                            echo '<span class="badge badge-secondary">พอใช้</span>';
                                        } else {
                                            echo '<span class="badge badge-danger">ปรับปรุง</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Personnel Type Stats -->
    <?php if (!empty($type_stats)): ?>
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="text-lg font-semibold">คะแนนเฉลี่ยแยกตามประเภทบุคลากร</h2>
            </div>
            <div class="card-body">
                <canvas id="personnelTypeChart" height="100"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top Performers -->
    <?php if (!empty($top_performers)): ?>
        <div class="card mb-6">
            <div class="card-header">
                <h2 class="text-lg font-semibold">
                    <svg class="w-5 h-5 inline-block text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    บุคลากรที่มีผลการประเมินดีเด่น (Top 10)
                </h2>
            </div>
            <div class="card-body">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase w-16">
                                    อันดับ
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    ชื่อ-นามสกุล
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    หน่วยงาน
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    ประเภท
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    จำนวนครั้ง
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    คะแนนเฉลี่ย
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($top_performers as $index => $performer): ?>
                                <tr class="<?php echo $index < 3 ? 'bg-yellow-50' : ''; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php if ($index == 0): ?>
                                            <span class="text-2xl">🥇</span>
                                        <?php elseif ($index == 1): ?>
                                            <span class="text-2xl">🥈</span>
                                        <?php elseif ($index == 2): ?>
                                            <span class="text-2xl">🥉</span>
                                        <?php else: ?>
                                            <span class="text-sm font-medium text-gray-900"><?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo e($performer['full_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($performer['department_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($performer['type_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                        <?php echo number_format($performer['eval_count']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-lg font-bold text-green-600">
                                            <?php echo number_format($performer['avg_score'], 2); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
    // Trends Chart
    <?php if (!empty($trends)): ?>
        const trendsCtx = document.getElementById('trendsChart');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: [<?php
                            echo implode(',', array_map(function ($t) {
                                return "'" . ($t['academic_year'] + 543) . "/" . $t['semester'] . "'";
                            }, $trends));
                            ?>],
                datasets: [{
                    label: 'คะแนนเฉลี่ย',
                    data: [<?php echo implode(',', array_column($trends, 'avg_score')); ?>],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 60,
                        max: 100
                    }
                }
            }
        });
    <?php endif; ?>

    // Distribution Chart
    <?php if (!empty($distribution)): ?>
        const distCtx = document.getElementById('distributionChart');
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php echo implode(',', array_map(function ($d) {
                        return "'" . $d['score_range'] . "'";
                    }, $distribution)); ?>
                ],
                datasets: [{
                    data: [<?php echo implode(',', array_column($distribution, 'count')); ?>],
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    <?php endif; ?>

    // Dimension Chart
    <?php if (!empty($dimension_stats)): ?>
        const dimCtx = document.getElementById('dimensionChart');
        new Chart(dimCtx, {
            type: 'radar',
            data: {
                labels: [
                    <?php echo implode(',', array_map(function ($d) {
                        return "'" . $d['dimension_name'] . "'";
                    }, $dimension_stats)); ?>
                ],
                datasets: [{
                    label: 'คะแนนเฉลี่ย',
                    data: [<?php echo implode(',', array_column($dimension_stats, 'avg_score')); ?>],
                    backgroundColor: 'rgba(139, 92, 246, 0.2)',
                    borderColor: 'rgb(139, 92, 246)',
                    pointBackgroundColor: 'rgb(139, 92, 246)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgb(139, 92, 246)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    <?php endif; ?>

    // Personnel Type Chart
    <?php if (!empty($type_stats)): ?>
        const ptCtx = document.getElementById('personnelTypeChart');
        new Chart(ptCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php echo implode(',', array_map(function ($t) {
                        return "'" . $t['type_name'] . "'";
                    }, $type_stats)); ?>
                ],
                datasets: [{
                    label: 'คะแนนเฉลี่ย',
                    data: [<?php echo implode(',', array_column($type_stats, 'avg_score')); ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 60,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    <?php endif; ?>
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>