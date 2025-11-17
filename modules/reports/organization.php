<?php

/**
 * modules/reports/organization.php
 * เปรียบเทียบผลการประเมินระหว่างบุคคล/หน่วยงาน/ช่วงเวลา
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
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

// Comparison Type
$comparison_type = $_GET['type'] ?? 'users'; // users, departments, periods

// Selected Items
$selected_items = isset($_GET['items']) ? (array)$_GET['items'] : [];

try {
    // ดึงข้อมูลสำหรับเลือก
    $users = [];
    $departments = [];
    $periods = [];

    if (isAdmin()) {
        // Admin ดูได้ทุกคน
        $stmt = $db->query("
            SELECT u.user_id, u.full_name_th as full_name, d.department_name_th as department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.department_id
            WHERE u.is_active = 1
            ORDER BY u.full_name_th
        ");
        $users = $stmt->fetchAll();

        $stmt = $db->query("
            SELECT department_id, department_name_th as department_name
            FROM departments
            WHERE is_active = 1
            ORDER BY department_name_th
        ");
        $departments = $stmt->fetchAll();
    } else {
        // Manager ดูเฉพาะหน่วยงานของตัวเอง
        $dept_id = $_SESSION['user']['department_id'];

        $stmt = $db->prepare("
            SELECT u.user_id, u.full_name_th as full_name, d.department_name_th as department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.department_id
            WHERE u.department_id = ? AND u.is_active = 1
            ORDER BY u.full_name_th
        ");
        $stmt->execute([$dept_id]);
        $users = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT department_id, department_name_th as department_name
            FROM departments
            WHERE department_id = ? AND is_active = 1
        ");
        $stmt->execute([$dept_id]);
        $departments = $stmt->fetchAll();
    }

    // ดึงรอบการประเมิน
    $stmt = $db->query("
        SELECT period_id, period_name, year, semester
        FROM evaluation_periods
        ORDER BY year DESC, semester DESC
    ");
    $periods = $stmt->fetchAll();

    // ดึงข้อมูลสำหรับเปรียบเทียบ
    $comparison_data = [];
    $aspect_comparison = [];

    if (!empty($selected_items) && count($selected_items) >= 2) {
        if ($comparison_type === 'users') {
            // เปรียบเทียบระหว่างบุคคล
            $placeholders = implode(',', array_fill(0, count($selected_items), '?'));

            $stmt = $db->prepare("
                SELECT u.user_id, u.full_name_th as full_name, d.department_name_th as department_name,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count,
                       AVG(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as avg_score,
                       MAX(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as max_score,
                       MIN(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as min_score
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.department_id
                LEFT JOIN evaluations e ON u.user_id = e.user_id
                WHERE u.user_id IN ($placeholders)
                GROUP BY u.user_id, u.full_name_th, d.department_name_th
            ");
            $stmt->execute($selected_items);
            $comparison_data = $stmt->fetchAll();

            // คะแนนตามด้านการประเมินของแต่ละคน
            $stmt = $db->prepare("
                SELECT u.user_id, u.full_name_th as full_name, ea.aspect_name_th as aspect_name,
                       AVG(es.score) as avg_score
                FROM users u
                JOIN evaluations e ON u.user_id = e.user_id
                JOIN evaluation_scores es ON e.evaluation_id = es.evaluation_id
                JOIN evaluation_aspects ea ON es.aspect_id = ea.aspect_id
                WHERE u.user_id IN ($placeholders) AND e.status = 'approved'
                GROUP BY u.user_id, u.full_name_th, ea.aspect_id, ea.aspect_name_th
                ORDER BY u.user_id, ea.display_order
            ");
            $stmt->execute($selected_items);

            foreach ($stmt->fetchAll() as $row) {
                $aspect_comparison[$row['user_id']][$row['aspect_name']] = $row['avg_score'];
            }
        } elseif ($comparison_type === 'departments') {
            // เปรียบเทียบระหว่างหน่วยงาน
            $placeholders = implode(',', array_fill(0, count($selected_items), '?'));

            $stmt = $db->prepare("
                SELECT d.department_id, d.department_name_th as department_name,
                       COUNT(DISTINCT u.user_id) as staff_count,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count,
                       AVG(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as avg_score
                FROM departments d
                LEFT JOIN users u ON d.department_id = u.department_id
                LEFT JOIN evaluations e ON u.user_id = e.user_id
                WHERE d.department_id IN ($placeholders)
                GROUP BY d.department_id, d.department_name_th
            ");
            $stmt->execute($selected_items);
            $comparison_data = $stmt->fetchAll();

            // คะแนนตามด้านการประเมินของแต่ละหน่วยงาน
            $stmt = $db->prepare("
                SELECT d.department_id, d.department_name_th as department_name, ea.aspect_name_th as aspect_name,
                       AVG(es.score) as avg_score
                FROM departments d
                JOIN users u ON d.department_id = u.department_id
                JOIN evaluations e ON u.user_id = e.user_id
                JOIN evaluation_scores es ON e.evaluation_id = es.evaluation_id
                JOIN evaluation_aspects ea ON es.aspect_id = ea.aspect_id
                WHERE d.department_id IN ($placeholders) AND e.status = 'approved'
                GROUP BY d.department_id, d.department_name_th, ea.aspect_id, ea.aspect_name_th
                ORDER BY d.department_id, ea.display_order
            ");
            $stmt->execute($selected_items);

            foreach ($stmt->fetchAll() as $row) {
                $aspect_comparison[$row['department_id']][$row['aspect_name']] = $row['avg_score'];
            }
        } elseif ($comparison_type === 'periods') {
            // เปรียบเทียบระหว่างช่วงเวลา
            $placeholders = implode(',', array_fill(0, count($selected_items), '?'));

            $stmt = $db->prepare("
                SELECT ep.period_id, ep.period_name, ep.year, ep.semester,
                       COUNT(DISTINCT e.evaluation_id) as evaluation_count,
                       AVG(CASE WHEN e.status = 'approved' THEN e.total_score ELSE NULL END) as avg_score
                FROM evaluation_periods ep
                LEFT JOIN evaluations e ON ep.period_id = e.period_id
                WHERE ep.period_id IN ($placeholders)
                GROUP BY ep.period_id, ep.period_name, ep.year, ep.semester
                ORDER BY ep.year DESC, ep.semester DESC
            ");
            $stmt->execute($selected_items);
            $comparison_data = $stmt->fetchAll();

            // คะแนนตามด้านการประเมินของแต่ละช่วงเวลา
            $stmt = $db->prepare("
                SELECT ep.period_id, ep.period_name, ea.aspect_name_th as aspect_name,
                       AVG(es.score) as avg_score
                FROM evaluation_periods ep
                JOIN evaluations e ON ep.period_id = e.period_id
                JOIN evaluation_scores es ON e.evaluation_id = es.evaluation_id
                JOIN evaluation_aspects ea ON es.aspect_id = ea.aspect_id
                WHERE ep.period_id IN ($placeholders) AND e.status = 'approved'
                GROUP BY ep.period_id, ep.period_name, ea.aspect_id, ea.aspect_name_th
                ORDER BY ep.period_id, ea.display_order
            ");
            $stmt->execute($selected_items);

            foreach ($stmt->fetchAll() as $row) {
                $aspect_comparison[$row['period_id']][$row['aspect_name']] = $row['avg_score'];
            }
        }
    }

    // ดึงด้านการประเมินทั้งหมด
    $stmt = $db->query("
        SELECT aspect_name_th as aspect_name
        FROM evaluation_aspects 
        WHERE is_active = 1 
        ORDER BY display_order
    ");
    $all_aspects = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Comparison Report Error: " . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $comparison_data = [];
    $aspect_comparison = [];
}

$page_title = 'เปรียบเทียบผลการประเมิน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">เปรียบเทียบผลการประเมิน</h1>
                <p class="mt-1 text-gray-600">
                    เปรียบเทียบผลการประเมินระหว่างบุคคล หน่วยงาน หรือช่วงเวลา
                </p>
            </div>
            <?php if (!empty($comparison_data)): ?>
                <div class="flex items-center space-x-2">
                    <button onclick="window.print()"
                        class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50 no-print">
                        <i class="fas fa-print mr-2"></i>พิมพ์
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Selection Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-6 no-print">
        <form method="GET">
            <!-- Comparison Type -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">ประเภทการเปรียบเทียบ</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label
                        class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:border-blue-300 transition-colors
                        <?php echo $comparison_type === 'users' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                        <input type="radio" name="type" value="users"
                            <?php echo $comparison_type === 'users' ? 'checked' : ''; ?> onchange="this.form.submit()"
                            class="mt-1 mr-3">
                        <div>
                            <p class="font-medium text-gray-900 mb-1">เปรียบเทียบบุคคล</p>
                            <p class="text-sm text-gray-600">เปรียบเทียบผลการประเมินระหว่างบุคลากร</p>
                        </div>
                    </label>

                    <label
                        class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:border-blue-300 transition-colors
                        <?php echo $comparison_type === 'departments' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                        <input type="radio" name="type" value="departments"
                            <?php echo $comparison_type === 'departments' ? 'checked' : ''; ?>
                            onchange="this.form.submit()" class="mt-1 mr-3">
                        <div>
                            <p class="font-medium text-gray-900 mb-1">เปรียบเทียบหน่วยงาน</p>
                            <p class="text-sm text-gray-600">เปรียบเทียบผลการประเมินระหว่างหน่วยงาน</p>
                        </div>
                    </label>

                    <label
                        class="flex items-start p-4 border-2 rounded-lg cursor-pointer hover:border-blue-300 transition-colors
                        <?php echo $comparison_type === 'periods' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                        <input type="radio" name="type" value="periods"
                            <?php echo $comparison_type === 'periods' ? 'checked' : ''; ?> onchange="this.form.submit()"
                            class="mt-1 mr-3">
                        <div>
                            <p class="font-medium text-gray-900 mb-1">เปรียบเทียบช่วงเวลา</p>
                            <p class="text-sm text-gray-600">เปรียบเทียบผลการประเมินในช่วงเวลาต่างๆ</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Item Selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    เลือกรายการเพื่อเปรียบเทียบ (2-5 รายการ)
                </label>

                <?php if ($comparison_type === 'users'): ?>
                    <select name="items[]" multiple size="10" class="w-full border rounded px-3 py-2" required>
                        <?php if (empty($users)): ?>
                            <option disabled>ไม่พบข้อมูลบุคลากร</option>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>"
                                    <?php echo in_array($user['user_id'], $selected_items) ? 'selected' : ''; ?>>
                                    <?php echo e($user['full_name']); ?>
                                    <?php if ($user['department_name']): ?>
                                        - <?php echo e($user['department_name']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                <?php elseif ($comparison_type === 'departments'): ?>
                    <select name="items[]" multiple size="10" class="w-full border rounded px-3 py-2" required>
                        <?php if (empty($departments)): ?>
                            <option disabled>ไม่พบข้อมูลหน่วยงาน</option>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"
                                    <?php echo in_array($dept['department_id'], $selected_items) ? 'selected' : ''; ?>>
                                    <?php echo e($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                <?php elseif ($comparison_type === 'periods'): ?>
                    <select name="items[]" multiple size="10" class="w-full border rounded px-3 py-2" required>
                        <?php if (empty($periods)): ?>
                            <option disabled>ไม่พบข้อมูลรอบการประเมิน</option>
                        <?php else: ?>
                            <?php foreach ($periods as $period): ?>
                                <option value="<?php echo $period['period_id']; ?>"
                                    <?php echo in_array($period['period_id'], $selected_items) ? 'selected' : ''; ?>>
                                    <?php echo e($period['period_name']); ?>
                                    (<?php echo $period['year'] + 543; ?>
                                    <?php if ($period['semester']): ?>/<?php echo $period['semester']; ?><?php endif; ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                <?php endif; ?>

                <p class="text-sm text-gray-500 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    กด Ctrl (หรือ Cmd บน Mac) ค้างไว้เพื่อเลือกหลายรายการ
                </p>
            </div>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                <i class="fas fa-chart-bar mr-2"></i>แสดงผลเปรียบเทียบ
            </button>
        </form>
    </div>

    <!-- Comparison Results -->
    <?php if (!empty($comparison_data)): ?>

        <!-- Summary Table -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold">สรุปผลเปรียบเทียบ</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">รายการ</th>
                            <?php if ($comparison_type === 'users'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">หน่วยงาน</th>
                            <?php elseif ($comparison_type === 'departments'): ?>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">บุคลากร</th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">จำนวนประเมิน</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">คะแนนเฉลี่ย</th>
                            <?php if ($comparison_type === 'users'): ?>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">สูงสุด</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">ต่ำสุด</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($comparison_data as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">
                                        <?php
                                        if ($comparison_type === 'users') {
                                            echo e($item['full_name']);
                                        } elseif ($comparison_type === 'departments') {
                                            echo e($item['department_name']);
                                        } else {
                                            echo e($item['period_name']);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <?php if ($comparison_type === 'users'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600">
                                            <?php echo e($item['department_name'] ?? '-'); ?>
                                        </span>
                                    </td>
                                <?php elseif ($comparison_type === 'departments'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <?php echo number_format($item['staff_count']); ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php echo number_format($item['evaluation_count']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($item['avg_score']): ?>
                                        <span class="text-lg font-bold text-blue-600">
                                            <?php echo number_format($item['avg_score'], 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($comparison_type === 'users'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="font-medium text-green-600">
                                            <?php echo $item['max_score'] ? number_format($item['max_score'], 2) : '-'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <span class="font-medium text-orange-600">
                                            <?php echo $item['min_score'] ? number_format($item['min_score'], 2) : '-'; ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Aspect Comparison -->
        <?php if (!empty($aspect_comparison) && !empty($all_aspects)): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-lg font-semibold">เปรียบเทียบคะแนนตามด้านการประเมิน</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ด้านการประเมิน</th>
                                <?php foreach ($comparison_data as $item): ?>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                        <?php
                                        if ($comparison_type === 'users') {
                                            echo e(mb_substr($item['full_name'], 0, 15));
                                        } elseif ($comparison_type === 'departments') {
                                            echo e(mb_substr($item['department_name'], 0, 15));
                                        } else {
                                            echo e(mb_substr($item['period_name'], 0, 15));
                                        }
                                        ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($all_aspects as $aspect): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        <?php echo e($aspect); ?>
                                    </td>
                                    <?php foreach ($comparison_data as $item): ?>
                                        <?php
                                        $item_id = $comparison_type === 'users' ? $item['user_id']
                                            : ($comparison_type === 'departments' ? $item['department_id'] : $item['period_id']);
                                        $score = $aspect_comparison[$item_id][$aspect] ?? null;
                                        ?>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($score): ?>
                                                <div>
                                                    <span class="font-bold text-blue-600">
                                                        <?php echo number_format($score, 2); ?>
                                                    </span>
                                                    <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-blue-600 h-2 rounded-full"
                                                            style="width: <?php echo min(($score / 100) * 100, 100); ?>%"></div>
                                                    </div>
                                                </div>
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
        <?php endif; ?>

    <?php else: ?>

        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-chart-bar text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-medium text-gray-900 mb-2">
                เลือกรายการเพื่อเปรียบเทียบ
            </h3>
            <p class="text-gray-600">
                กรุณาเลือกอย่างน้อย 2 รายการเพื่อแสดงผลการเปรียบเทียบ
            </p>
        </div>

    <?php endif; ?>
</div>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>