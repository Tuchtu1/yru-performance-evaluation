<?php

/**
 * modules/reports/comparison.php
 * รายงานเปรียบเทียบผลการประเมิน
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/helpers.php';

// ตรวจสอบการ login
requireAuth();

// ตรวจสอบสิทธิ์
if (!isAdmin() && !isManager()) {
    flash_error('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('modules/dashboard/index.php');
    exit;
}

$db = getDB();
$current_user = $_SESSION['user'];

// รับค่า filter
$type = $_GET['type'] ?? 'year'; // year, semester, department, personnel_type
$compare_values = $_GET['compare'] ?? [];
$aspect_id = $_GET['aspect_id'] ?? '';

try {
    // ดึงรายการปีการศึกษา
    $stmt = $db->query("
        SELECT DISTINCT year 
        FROM evaluation_periods 
        ORDER BY year DESC
    ");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ดึงรายการด้านการประเมิน
    $stmt = $db->query("
        SELECT * FROM evaluation_aspects 
        WHERE is_active = 1 
        ORDER BY display_order
    ");
    $aspects = $stmt->fetchAll();

    // ดึงรายการหน่วยงาน
    if (isAdmin()) {
        $stmt = $db->query("
            SELECT department_id, department_name_th as department_name
            FROM departments 
            WHERE is_active = 1 
            ORDER BY department_name_th
        ");
    } else {
        $stmt = $db->prepare("
            SELECT department_id, department_name_th as department_name
            FROM departments 
            WHERE department_id = ? AND is_active = 1
        ");
        $stmt->execute([$current_user['department_id']]);
    }
    $departments = $stmt->fetchAll();

    // ดึงรายการประเภทบุคลากร
    $stmt = $db->query("
        SELECT personnel_type_id, type_name_th as type_name
        FROM personnel_types 
        WHERE is_active = 1 
        ORDER BY type_name_th
    ");
    $personnel_types = $stmt->fetchAll();

    // ดึงข้อมูลเปรียบเทียบ
    $comparison_data = [];

    if (!empty($compare_values) && count($compare_values) >= 2) {
        switch ($type) {
            case 'year':
                foreach ($compare_values as $year) {
                    $stmt = $db->prepare("
                        SELECT 
                            ep.year,
                            AVG(e.total_score) as avg_score,
                            COUNT(DISTINCT e.evaluation_id) as total_evaluations,
                            COUNT(DISTINCT CASE WHEN e.status = 'approved' THEN e.evaluation_id END) as approved_count,
                            MAX(e.total_score) as max_score,
                            MIN(e.total_score) as min_score
                        FROM evaluations e
                        JOIN evaluation_periods ep ON e.period_id = ep.period_id
                        WHERE ep.year = ? AND e.status = 'approved'
                        GROUP BY ep.year
                    ");
                    $stmt->execute([$year]);
                    $data = $stmt->fetch();

                    if ($data) {
                        $comparison_data[$year] = $data;
                    }
                }
                break;

            case 'semester':
                foreach ($compare_values as $value) {
                    list($year, $semester) = explode('-', $value);
                    $stmt = $db->prepare("
                        SELECT 
                            CONCAT(ep.year, '/', ep.semester) as period,
                            AVG(e.total_score) as avg_score,
                            COUNT(DISTINCT e.evaluation_id) as total_evaluations,
                            COUNT(DISTINCT CASE WHEN e.status = 'approved' THEN e.evaluation_id END) as approved_count,
                            MAX(e.total_score) as max_score,
                            MIN(e.total_score) as min_score
                        FROM evaluations e
                        JOIN evaluation_periods ep ON e.period_id = ep.period_id
                        WHERE ep.year = ? AND ep.semester = ? AND e.status = 'approved'
                        GROUP BY ep.year, ep.semester
                    ");
                    $stmt->execute([$year, $semester]);
                    $data = $stmt->fetch();

                    if ($data) {
                        $comparison_data[$value] = $data;
                    }
                }
                break;

            case 'department':
                foreach ($compare_values as $dept_id) {
                    $stmt = $db->prepare("
                        SELECT 
                            d.department_name_th as department_name,
                            AVG(e.total_score) as avg_score,
                            COUNT(DISTINCT e.evaluation_id) as total_evaluations,
                            COUNT(DISTINCT CASE WHEN e.status = 'approved' THEN e.evaluation_id END) as approved_count,
                            MAX(e.total_score) as max_score,
                            MIN(e.total_score) as min_score
                        FROM evaluations e
                        JOIN users u ON e.user_id = u.user_id
                        JOIN departments d ON u.department_id = d.department_id
                        WHERE d.department_id = ? AND e.status = 'approved'
                        GROUP BY d.department_id, d.department_name_th
                    ");
                    $stmt->execute([$dept_id]);
                    $data = $stmt->fetch();

                    if ($data) {
                        $comparison_data[$dept_id] = $data;
                    }
                }
                break;

            case 'personnel_type':
                foreach ($compare_values as $type_id) {
                    $stmt = $db->prepare("
                        SELECT 
                            pt.type_name_th as type_name,
                            AVG(e.total_score) as avg_score,
                            COUNT(DISTINCT e.evaluation_id) as total_evaluations,
                            COUNT(DISTINCT CASE WHEN e.status = 'approved' THEN e.evaluation_id END) as approved_count,
                            MAX(e.total_score) as max_score,
                            MIN(e.total_score) as min_score
                        FROM evaluations e
                        JOIN users u ON e.user_id = u.user_id
                        JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
                        WHERE pt.personnel_type_id = ? AND e.status = 'approved'
                        GROUP BY pt.personnel_type_id, pt.type_name_th
                    ");
                    $stmt->execute([$type_id]);
                    $data = $stmt->fetch();

                    if ($data) {
                        $comparison_data[$type_id] = $data;
                    }
                }
                break;
        }
    }

    // ดึงข้อมูลเปรียบเทียบตามด้านการประเมิน (ถ้าเลือก)
    $aspect_comparison = [];
    if (!empty($compare_values) && $aspect_id) {
        foreach ($compare_values as $value) {
            $where_conditions = [];
            $params = [$aspect_id];

            switch ($type) {
                case 'year':
                    $where_conditions[] = "ep.year = ?";
                    $params[] = $value;
                    break;
                case 'semester':
                    list($year, $semester) = explode('-', $value);
                    $where_conditions[] = "ep.year = ? AND ep.semester = ?";
                    $params[] = $year;
                    $params[] = $semester;
                    break;
                case 'department':
                    $where_conditions[] = "u.department_id = ?";
                    $params[] = $value;
                    break;
                case 'personnel_type':
                    $where_conditions[] = "u.personnel_type_id = ?";
                    $params[] = $value;
                    break;
            }

            $where_clause = implode(' AND ', $where_conditions);

            $stmt = $db->prepare("
                SELECT 
                    et.topic_name_th as topic_name,
                    AVG(es.score) as avg_score,
                    COUNT(es.score_id) as count
                FROM evaluation_scores es
                JOIN evaluation_topics et ON es.topic_id = et.topic_id
                JOIN evaluations e ON es.evaluation_id = e.evaluation_id
                JOIN evaluation_periods ep ON e.period_id = ep.period_id
                JOIN users u ON e.user_id = u.user_id
                WHERE et.aspect_id = ? AND $where_clause AND e.status = 'approved'
                GROUP BY et.topic_id, et.topic_name_th
                ORDER BY et.display_order
            ");
            $stmt->execute($params);
            $aspect_comparison[$value] = $stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    error_log("Comparison Report Error: " . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $comparison_data = [];
    $aspect_comparison = [];
}

$page_title = 'รายงานเปรียบเทียบผลการประเมิน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">รายงานเปรียบเทียบผลการประเมิน</h1>
        <p class="mt-1 text-gray-600">
            เปรียบเทียบผลการประเมินตามเงื่อนไขต่างๆ
        </p>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="bg-white rounded-lg shadow mb-6 no-print">
        <div class="border-b px-6 py-4">
            <h2 class="text-lg font-semibold">เลือกเงื่อนไขเปรียบเทียบ</h2>
        </div>
        <div class="p-6">
            <form method="GET" id="comparisonForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- ประเภทการเปรียบเทียบ -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ประเภทการเปรียบเทียบ
                        </label>
                        <select name="type" id="compareType" class="w-full border rounded px-3 py-2" required>
                            <option value="year" <?php echo $type === 'year' ? 'selected' : ''; ?>>
                                เปรียบเทียบรายปีการศึกษา
                            </option>
                            <option value="semester" <?php echo $type === 'semester' ? 'selected' : ''; ?>>
                                เปรียบเทียบรายภาคเรียน
                            </option>
                            <option value="department" <?php echo $type === 'department' ? 'selected' : ''; ?>>
                                เปรียบเทียบรายหน่วยงาน
                            </option>
                            <option value="personnel_type" <?php echo $type === 'personnel_type' ? 'selected' : ''; ?>>
                                เปรียบเทียบรายประเภทบุคลากร
                            </option>
                        </select>
                    </div>

                    <!-- ด้านการประเมิน -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ด้านการประเมิน (เลือกเพื่อดูรายละเอียด)
                        </label>
                        <select name="aspect_id" class="w-full border rounded px-3 py-2">
                            <option value="">ไม่ระบุ</option>
                            <?php foreach ($aspects as $asp): ?>
                                <option value="<?php echo $asp['aspect_id']; ?>"
                                    <?php echo $aspect_id == $asp['aspect_id'] ? 'selected' : ''; ?>>
                                    <?php echo e($asp['aspect_name_th']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Selection Area -->
                <div id="selectionArea">
                    <!-- จะถูกเติมด้วย JavaScript -->
                </div>

                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" onclick="window.print()"
                        class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">
                        <i class="fas fa-print mr-2"></i>พิมพ์รายงาน
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                        <i class="fas fa-chart-bar mr-2"></i>เปรียบเทียบ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($comparison_data)): ?>
        <!-- Comparison Results -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold">ผลการเปรียบเทียบ</h2>
            </div>
            <div class="p-6">
                <!-- Summary Table -->
                <div class="overflow-x-auto mb-6">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    <?php
                                    $header_label = [
                                        'year' => 'ปีการศึกษา',
                                        'semester' => 'ภาคเรียน',
                                        'department' => 'หน่วยงาน',
                                        'personnel_type' => 'ประเภทบุคลากร'
                                    ];
                                    echo $header_label[$type];
                                    ?>
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    คะแนนเฉลี่ย
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    คะแนนสูงสุด
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    คะแนนต่ำสุด
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    จำนวนประเมิน
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    อนุมัติแล้ว
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($comparison_data as $key => $data): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php
                                        if ($type === 'year') {
                                            echo ($data['year'] + 543);
                                        } elseif ($type === 'semester') {
                                            list($y, $s) = explode('-', $key);
                                            echo ($y + 543) . '/' . $s;
                                        } else {
                                            echo e($data[array_key_first($data)]);
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="text-lg font-semibold text-blue-600">
                                            <?php echo number_format($data['avg_score'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                        <span class="font-medium text-green-600">
                                            <?php echo number_format($data['max_score'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                        <span class="font-medium text-orange-600">
                                            <?php echo number_format($data['min_score'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                        <?php echo number_format($data['total_evaluations']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                        <?php echo number_format($data['approved_count']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Chart -->
                <div>
                    <canvas id="comparisonChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <?php if (!empty($aspect_comparison)): ?>
            <!-- Aspect Comparison -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h2 class="text-lg font-semibold">เปรียบเทียบตามด้านการประเมิน</h2>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        หัวข้อการประเมิน
                                    </th>
                                    <?php foreach ($compare_values as $value): ?>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                            <?php
                                            if ($type === 'year') {
                                                echo ($value + 543);
                                            } elseif ($type === 'semester') {
                                                list($y, $s) = explode('-', $value);
                                                echo ($y + 543) . '/' . $s;
                                            } else {
                                                $stmt = $db->prepare("SELECT " .
                                                    ($type === 'department' ? 'department_name_th FROM departments WHERE department_id' : 'type_name_th FROM personnel_types WHERE personnel_type_id') .
                                                    " = ?");
                                                $stmt->execute([$value]);
                                                echo e($stmt->fetchColumn());
                                            }
                                            ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                // รวม topics จากทุก comparison
                                $all_topics = [];
                                foreach ($aspect_comparison as $topics) {
                                    foreach ($topics as $topic) {
                                        $all_topics[$topic['topic_name']] = $topic['topic_name'];
                                    }
                                }

                                foreach ($all_topics as $topic_name):
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo e($topic_name); ?>
                                        </td>
                                        <?php foreach ($compare_values as $value): ?>
                                            <td class="px-6 py-4 text-center text-sm">
                                                <?php
                                                $score = null;
                                                if (isset($aspect_comparison[$value])) {
                                                    foreach ($aspect_comparison[$value] as $topic) {
                                                        if ($topic['topic_name'] === $topic_name) {
                                                            $score = $topic['avg_score'];
                                                            break;
                                                        }
                                                    }
                                                }
                                                if ($score !== null): ?>
                                                    <span class="font-semibold text-blue-600">
                                                        <?php echo number_format($score, 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
    // Dynamic form based on comparison type
    const compareType = document.getElementById('compareType');
    const selectionArea = document.getElementById('selectionArea');

    const data = {
        years: <?php echo json_encode($years); ?>,
        departments: <?php echo json_encode($departments); ?>,
        personnel_types: <?php echo json_encode($personnel_types); ?>
    };

    const selectedValues = <?php echo json_encode($compare_values); ?>;

    function updateSelectionArea() {
        const type = compareType.value;
        let html =
            '<div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-2">เลือกอย่างน้อย 2 รายการเพื่อเปรียบเทียบ</label><div class="grid grid-cols-2 md:grid-cols-4 gap-3">';

        switch (type) {
            case 'year':
                data.years.forEach(year => {
                    const checked = selectedValues.includes(String(year)) ? 'checked' : '';
                    html += `
                    <label class="flex items-center space-x-2 p-3 border rounded hover:bg-gray-50 cursor-pointer ${checked ? 'bg-blue-50 border-blue-500' : ''}">
                        <input type="checkbox" name="compare[]" value="${year}" ${checked} class="rounded">
                        <span>${parseInt(year) + 543}</span>
                    </label>
                `;
                });
                break;

            case 'semester':
                data.years.forEach(year => {
                    [1, 2, 3].forEach(sem => {
                        const val = `${year}-${sem}`;
                        const checked = selectedValues.includes(val) ? 'checked' : '';
                        html += `
                        <label class="flex items-center space-x-2 p-3 border rounded hover:bg-gray-50 cursor-pointer ${checked ? 'bg-blue-50 border-blue-500' : ''}">
                            <input type="checkbox" name="compare[]" value="${val}" ${checked} class="rounded">
                            <span>${parseInt(year) + 543}/${sem}</span>
                        </label>
                    `;
                    });
                });
                break;

            case 'department':
                data.departments.forEach(dept => {
                    const checked = selectedValues.includes(String(dept.department_id)) ? 'checked' : '';
                    html += `
                    <label class="flex items-center space-x-2 p-3 border rounded hover:bg-gray-50 cursor-pointer ${checked ? 'bg-blue-50 border-blue-500' : ''}">
                        <input type="checkbox" name="compare[]" value="${dept.department_id}" ${checked} class="rounded">
                        <span class="text-sm">${dept.department_name}</span>
                    </label>
                `;
                });
                break;

            case 'personnel_type':
                data.personnel_types.forEach(ptype => {
                    const checked = selectedValues.includes(String(ptype.personnel_type_id)) ? 'checked' : '';
                    html += `
                    <label class="flex items-center space-x-2 p-3 border rounded hover:bg-gray-50 cursor-pointer ${checked ? 'bg-blue-50 border-blue-500' : ''}">
                        <input type="checkbox" name="compare[]" value="${ptype.personnel_type_id}" ${checked} class="rounded">
                        <span>${ptype.type_name}</span>
                    </label>
                `;
                });
                break;
        }

        html += '</div></div>';
        selectionArea.innerHTML = html;
    }

    compareType.addEventListener('change', updateSelectionArea);
    updateSelectionArea();

    // Chart
    <?php if (!empty($comparison_data)): ?>
        const ctx = document.getElementById('comparisonChart');
        const chartData = <?php echo json_encode($comparison_data); ?>;

        const labels = Object.keys(chartData).map(key => {
            <?php if ($type === 'year'): ?>
                return String(parseInt(chartData[key].year) + 543);
            <?php elseif ($type === 'semester'): ?>
                const parts = key.split('-');
                return `${parseInt(parts[0]) + 543}/${parts[1]}`;
            <?php else: ?>
                return chartData[key][Object.keys(chartData[key])[0]];
            <?php endif; ?>
        });

        const avgScores = Object.values(chartData).map(d => parseFloat(d.avg_score) || 0);
        const maxScores = Object.values(chartData).map(d => parseFloat(d.max_score) || 0);
        const minScores = Object.values(chartData).map(d => parseFloat(d.min_score) || 0);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                        label: 'คะแนนเฉลี่ย',
                        data: avgScores,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1
                    },
                    {
                        label: 'คะแนนสูงสุด',
                        data: maxScores,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 1
                    },
                    {
                        label: 'คะแนนต่ำสุด',
                        data: minScores,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: 'rgb(239, 68, 68)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'กราฟเปรียบเทียบคะแนน',
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    <?php endif; ?>
</script>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>