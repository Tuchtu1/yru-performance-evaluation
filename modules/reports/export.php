<?php

/**
 * modules/reports/export.php
 * ส่งออกรายงานในรูปแบบต่างๆ (PDF, Excel, CSV)
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('reports.export');

$db = getDB();
$current_user = $_SESSION['user'];

// รับค่าพารามิเตอร์
$type = $_GET['type'] ?? 'individual'; // individual, department, organization
$format = $_GET['format'] ?? 'pdf'; // pdf, excel, csv
$user_id = $_GET['user_id'] ?? null;
$department_id = $_GET['department_id'] ?? null;
$year = $_GET['year'] ?? null;
$semester = $_GET['semester'] ?? null;

// ตรวจสอบสิทธิ์การเข้าถึงข้อมูล
if ($type === 'individual' && $user_id) {
    if (!isAdmin() && !isManager() && $current_user['user_id'] != $user_id) {
        die('ไม่มีสิทธิ์เข้าถึงข้อมูล');
    }
}

if ($type === 'department' && $department_id) {
    if (!isAdmin() && $current_user['department_id'] != $department_id) {
        die('ไม่มีสิทธิ์เข้าถึงข้อมูล');
    }
}

if ($type === 'organization' && !isAdmin()) {
    die('เฉพาะผู้ดูแลระบบเท่านั้น');
}

// ============ ฟังก์ชันสำหรับดึงข้อมูล ============

function getIndividualData($db, $user_id, $year = null, $semester = null)
{
    $where = ["e.user_id = ?"];
    $params = [$user_id];

    if ($year) {
        $where[] = "ep.academic_year = ?";
        $params[] = $year;
    }
    if ($semester) {
        $where[] = "ep.semester = ?";
        $params[] = $semester;
    }

    $where_clause = implode(' AND ', $where);

    // ข้อมูลผู้ใช้
    $stmt = $db->prepare("
        SELECT u.*, d.department_name, pt.type_name, r.role_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // รายการแบบประเมิน
    $stmt = $db->prepare("
        SELECT 
            e.*,
            ep.period_name,
            ep.academic_year,
            ep.semester,
            ef.form_name
        FROM evaluations e
        JOIN evaluation_periods ep ON e.period_id = ep.period_id
        JOIN evaluation_forms ef ON e.form_id = ef.form_id
        WHERE $where_clause
        ORDER BY ep.academic_year DESC, ep.semester DESC
    ");
    $stmt->execute($params);
    $evaluations = $stmt->fetchAll();

    // คำนวณสถิติ
    $stmt = $db->prepare("
        SELECT 
            COUNT(e.evaluation_id) as total_count,
            AVG(e.total_score) as avg_score,
            MAX(e.total_score) as max_score,
            MIN(e.total_score) as min_score,
            COUNT(CASE WHEN e.status = 'approved' THEN 1 END) as approved_count
        FROM evaluations e
        JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();

    return [
        'user' => $user,
        'evaluations' => $evaluations,
        'stats' => $stats
    ];
}

function getDepartmentData($db, $department_id, $year = null, $semester = null)
{
    $where = ["u.department_id = ?"];
    $params = [$department_id];

    if ($year) {
        $where[] = "ep.academic_year = ?";
        $params[] = $year;
    }
    if ($semester) {
        $where[] = "ep.semester = ?";
        $params[] = $semester;
    }

    $where_clause = implode(' AND ', $where);

    // ข้อมูลหน่วยงาน
    $stmt = $db->prepare("SELECT * FROM departments WHERE department_id = ?");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();

    // รายการบุคลากรและคะแนน
    $stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.full_name,
            pt.type_name,
            COUNT(DISTINCT e.evaluation_id) as eval_count,
            AVG(e.total_score) as avg_score,
            MAX(e.total_score) as max_score
        FROM users u
        LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE $where_clause
        GROUP BY u.user_id
        ORDER BY u.full_name
    ");
    $stmt->execute($params);
    $staff_list = $stmt->fetchAll();

    // สถิติภาพรวม
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT u.user_id) as total_staff,
            COUNT(e.evaluation_id) as total_evaluations,
            AVG(e.total_score) as dept_avg_score,
            MAX(e.total_score) as dept_max_score,
            MIN(e.total_score) as dept_min_score
        FROM users u
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();

    return [
        'department' => $department,
        'staff_list' => $staff_list,
        'stats' => $stats
    ];
}

function getOrganizationData($db, $year = null, $semester = null)
{
    $where = ["1=1"];
    $params = [];

    if ($year) {
        $where[] = "ep.academic_year = ?";
        $params[] = $year;
    }
    if ($semester) {
        $where[] = "ep.semester = ?";
        $params[] = $semester;
    }

    $where_clause = implode(' AND ', $where);

    // สถิติภาพรวมทั้งองค์กร
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT u.user_id) as total_staff,
            COUNT(DISTINCT d.department_id) as total_departments,
            COUNT(e.evaluation_id) as total_evaluations,
            AVG(e.total_score) as org_avg_score,
            MAX(e.total_score) as org_max_score,
            MIN(e.total_score) as org_min_score,
            COUNT(CASE WHEN e.status = 'approved' THEN 1 END) as approved_count
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();

    // สถิติแยกตามหน่วยงาน
    $stmt = $db->prepare("
        SELECT 
            d.department_name,
            COUNT(DISTINCT u.user_id) as staff_count,
            COUNT(e.evaluation_id) as eval_count,
            AVG(e.total_score) as avg_score
        FROM departments d
        LEFT JOIN users u ON d.department_id = u.department_id
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE d.is_active = 1 AND $where_clause
        GROUP BY d.department_id
        ORDER BY avg_score DESC
    ");
    $stmt->execute($params);
    $departments = $stmt->fetchAll();

    // สถิติแยกตามประเภทบุคลากร
    $stmt = $db->prepare("
        SELECT 
            pt.type_name,
            COUNT(DISTINCT u.user_id) as staff_count,
            COUNT(e.evaluation_id) as eval_count,
            AVG(e.total_score) as avg_score
        FROM personnel_types pt
        LEFT JOIN users u ON pt.personnel_type_id = u.personnel_type_id
        LEFT JOIN evaluations e ON u.user_id = e.user_id
        LEFT JOIN evaluation_periods ep ON e.period_id = ep.period_id
        WHERE pt.is_active = 1 AND $where_clause
        GROUP BY pt.personnel_type_id
        ORDER BY avg_score DESC
    ");
    $stmt->execute($params);
    $personnel_types = $stmt->fetchAll();

    return [
        'stats' => $stats,
        'departments' => $departments,
        'personnel_types' => $personnel_types
    ];
}

// ============ ดึงข้อมูลตามประเภท ============

$data = [];
switch ($type) {
    case 'individual':
        if (!$user_id) die('กรุณาระบุผู้ใช้');
        $data = getIndividualData($db, $user_id, $year, $semester);
        break;
    case 'department':
        if (!$department_id) die('กรุณาระบุหน่วยงาน');
        $data = getDepartmentData($db, $department_id, $year, $semester);
        break;
    case 'organization':
        $data = getOrganizationData($db, $year, $semester);
        break;
}

// ============ ส่งออกตามรูปแบบ ============

switch ($format) {
    case 'csv':
        exportCSV($type, $data, $year, $semester);
        break;
    case 'excel':
        exportExcel($type, $data, $year, $semester);
        break;
    case 'pdf':
    default:
        exportPDF($type, $data, $year, $semester);
        break;
}

// ============ ฟังก์ชันส่งออก CSV ============

function exportCSV($type, $data, $year, $semester)
{
    $filename = "report_{$type}_" . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    switch ($type) {
        case 'individual':
            // Header
            fputcsv($output, ['รายงานผลการประเมินรายบุคคล']);
            fputcsv($output, ['ชื่อ-นามสกุล', $data['user']['full_name']]);
            fputcsv($output, ['หน่วยงาน', $data['user']['department_name']]);
            fputcsv($output, ['ประเภทบุคลากร', $data['user']['type_name']]);
            fputcsv($output, []);

            // Stats
            fputcsv($output, ['สถิติภาพรวม']);
            fputcsv($output, ['จำนวนครั้งที่ประเมิน', $data['stats']['total_count']]);
            fputcsv($output, ['คะแนนเฉลี่ย', number_format($data['stats']['avg_score'], 2)]);
            fputcsv($output, ['คะแนนสูงสุด', number_format($data['stats']['max_score'], 2)]);
            fputcsv($output, ['คะแนนต่ำสุด', number_format($data['stats']['min_score'], 2)]);
            fputcsv($output, []);

            // Evaluations
            fputcsv($output, ['รายการประเมิน']);
            fputcsv($output, ['ปีการศึกษา', 'ภาคเรียน', 'ชื่อรอบประเมิน', 'คะแนน', 'สถานะ', 'วันที่ส่ง']);

            foreach ($data['evaluations'] as $eval) {
                fputcsv($output, [
                    $eval['academic_year'] + 543,
                    $eval['semester'],
                    $eval['period_name'],
                    number_format($eval['total_score'], 2),
                    $GLOBALS['status_names'][$eval['status']],
                    thai_date($eval['submitted_date'], 'short')
                ]);
            }
            break;

        case 'department':
            // Header
            fputcsv($output, ['รายงานผลการประเมินรายหน่วยงาน']);
            fputcsv($output, ['หน่วยงาน', $data['department']['department_name']]);
            fputcsv($output, []);

            // Stats
            fputcsv($output, ['สถิติภาพรวม']);
            fputcsv($output, ['จำนวนบุคลากร', $data['stats']['total_staff']]);
            fputcsv($output, ['จำนวนการประเมิน', $data['stats']['total_evaluations']]);
            fputcsv($output, ['คะแนนเฉลี่ย', number_format($data['stats']['dept_avg_score'], 2)]);
            fputcsv($output, []);

            // Staff list
            fputcsv($output, ['รายชื่อบุคลากร']);
            fputcsv($output, ['ชื่อ-นามสกุล', 'ประเภท', 'จำนวนครั้ง', 'คะแนนเฉลี่ย', 'คะแนนสูงสุด']);

            foreach ($data['staff_list'] as $staff) {
                fputcsv($output, [
                    $staff['full_name'],
                    $staff['type_name'],
                    $staff['eval_count'],
                    number_format($staff['avg_score'], 2),
                    number_format($staff['max_score'], 2)
                ]);
            }
            break;

        case 'organization':
            // Header
            fputcsv($output, ['รายงานภาพรวมองค์กร']);
            fputcsv($output, []);

            // Stats
            fputcsv($output, ['สถิติภาพรวม']);
            fputcsv($output, ['จำนวนบุคลากร', $data['stats']['total_staff']]);
            fputcsv($output, ['จำนวนหน่วยงาน', $data['stats']['total_departments']]);
            fputcsv($output, ['จำนวนการประเมิน', $data['stats']['total_evaluations']]);
            fputcsv($output, ['คะแนนเฉลี่ย', number_format($data['stats']['org_avg_score'], 2)]);
            fputcsv($output, []);

            // Departments
            fputcsv($output, ['สถิติแยกตามหน่วยงาน']);
            fputcsv($output, ['หน่วยงาน', 'จำนวนบุคลากร', 'จำนวนการประเมิน', 'คะแนนเฉลี่ย']);

            foreach ($data['departments'] as $dept) {
                fputcsv($output, [
                    $dept['department_name'],
                    $dept['staff_count'],
                    $dept['eval_count'],
                    number_format($dept['avg_score'], 2)
                ]);
            }

            fputcsv($output, []);

            // Personnel types
            fputcsv($output, ['สถิติแยกตามประเภทบุคลากร']);
            fputcsv($output, ['ประเภทบุคลากร', 'จำนวนบุคลากร', 'จำนวนการประเมิน', 'คะแนนเฉลี่ย']);

            foreach ($data['personnel_types'] as $pt) {
                fputcsv($output, [
                    $pt['type_name'],
                    $pt['staff_count'],
                    $pt['eval_count'],
                    number_format($pt['avg_score'], 2)
                ]);
            }
            break;
    }

    fclose($output);
    exit;
}

// ============ ฟังก์ชันส่งออก Excel ============

function exportExcel($type, $data, $year, $semester)
{
    // สำหรับ Excel จริงๆ ควรใช้ PHPSpreadsheet
    // แต่ในที่นี้จะใช้วิธี HTML Table + .xls extension แทน

    $filename = "report_{$type}_" . date('Y-m-d_His') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head>';
    echo '<body>';

    switch ($type) {
        case 'individual':
            echo '<h2>รายงานผลการประเมินรายบุคคล</h2>';
            echo '<table border="1">';
            echo '<tr><td><b>ชื่อ-นามสกุล</b></td><td>' . $data['user']['full_name'] . '</td></tr>';
            echo '<tr><td><b>หน่วยงาน</b></td><td>' . $data['user']['department_name'] . '</td></tr>';
            echo '<tr><td><b>ประเภทบุคลากร</b></td><td>' . $data['user']['type_name'] . '</td></tr>';
            echo '</table><br>';

            echo '<h3>สถิติภาพรวม</h3>';
            echo '<table border="1">';
            echo '<tr><td>จำนวนครั้งที่ประเมิน</td><td>' . $data['stats']['total_count'] . '</td></tr>';
            echo '<tr><td>คะแนนเฉลี่ย</td><td>' . number_format($data['stats']['avg_score'], 2) . '</td></tr>';
            echo '<tr><td>คะแนนสูงสุด</td><td>' . number_format($data['stats']['max_score'], 2) . '</td></tr>';
            echo '<tr><td>คะแนนต่ำสุด</td><td>' . number_format($data['stats']['min_score'], 2) . '</td></tr>';
            echo '</table><br>';

            echo '<h3>รายการประเมิน</h3>';
            echo '<table border="1">';
            echo '<tr><th>ปีการศึกษา</th><th>ภาคเรียน</th><th>ชื่อรอบประเมิน</th><th>คะแนน</th><th>สถานะ</th><th>วันที่ส่ง</th></tr>';
            foreach ($data['evaluations'] as $eval) {
                echo '<tr>';
                echo '<td>' . ($eval['academic_year'] + 543) . '</td>';
                echo '<td>' . $eval['semester'] . '</td>';
                echo '<td>' . $eval['period_name'] . '</td>';
                echo '<td>' . number_format($eval['total_score'], 2) . '</td>';
                echo '<td>' . $GLOBALS['status_names'][$eval['status']] . '</td>';
                echo '<td>' . thai_date($eval['submitted_date'], 'short') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            break;

        case 'department':
            echo '<h2>รายงานผลการประเมินรายหน่วยงาน</h2>';
            echo '<table border="1">';
            echo '<tr><td><b>หน่วยงาน</b></td><td>' . $data['department']['department_name'] . '</td></tr>';
            echo '</table><br>';

            echo '<h3>สถิติภาพรวม</h3>';
            echo '<table border="1">';
            echo '<tr><td>จำนวนบุคลากร</td><td>' . $data['stats']['total_staff'] . '</td></tr>';
            echo '<tr><td>จำนวนการประเมิน</td><td>' . $data['stats']['total_evaluations'] . '</td></tr>';
            echo '<tr><td>คะแนนเฉลี่ย</td><td>' . number_format($data['stats']['dept_avg_score'], 2) . '</td></tr>';
            echo '</table><br>';

            echo '<h3>รายชื่อบุคลากร</h3>';
            echo '<table border="1">';
            echo '<tr><th>ชื่อ-นามสกุล</th><th>ประเภท</th><th>จำนวนครั้ง</th><th>คะแนนเฉลี่ย</th><th>คะแนนสูงสุด</th></tr>';
            foreach ($data['staff_list'] as $staff) {
                echo '<tr>';
                echo '<td>' . $staff['full_name'] . '</td>';
                echo '<td>' . $staff['type_name'] . '</td>';
                echo '<td>' . $staff['eval_count'] . '</td>';
                echo '<td>' . number_format($staff['avg_score'], 2) . '</td>';
                echo '<td>' . number_format($staff['max_score'], 2) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            break;

        case 'organization':
            echo '<h2>รายงานภาพรวมองค์กร</h2>';

            echo '<h3>สถิติภาพรวม</h3>';
            echo '<table border="1">';
            echo '<tr><td>จำนวนบุคลากร</td><td>' . $data['stats']['total_staff'] . '</td></tr>';
            echo '<tr><td>จำนวนหน่วยงาน</td><td>' . $data['stats']['total_departments'] . '</td></tr>';
            echo '<tr><td>จำนวนการประเมิน</td><td>' . $data['stats']['total_evaluations'] . '</td></tr>';
            echo '<tr><td>คะแนนเฉลี่ย</td><td>' . number_format($data['stats']['org_avg_score'], 2) . '</td></tr>';
            echo '</table><br>';

            echo '<h3>สถิติแยกตามหน่วยงาน</h3>';
            echo '<table border="1">';
            echo '<tr><th>หน่วยงาน</th><th>จำนวนบุคลากร</th><th>จำนวนการประเมิน</th><th>คะแนนเฉลี่ย</th></tr>';
            foreach ($data['departments'] as $dept) {
                echo '<tr>';
                echo '<td>' . $dept['department_name'] . '</td>';
                echo '<td>' . $dept['staff_count'] . '</td>';
                echo '<td>' . $dept['eval_count'] . '</td>';
                echo '<td>' . number_format($dept['avg_score'], 2) . '</td>';
                echo '</tr>';
            }
            echo '</table><br>';

            echo '<h3>สถิติแยกตามประเภทบุคลากร</h3>';
            echo '<table border="1">';
            echo '<tr><th>ประเภทบุคลากร</th><th>จำนวนบุคลากร</th><th>จำนวนการประเมิน</th><th>คะแนนเฉลี่ย</th></tr>';
            foreach ($data['personnel_types'] as $pt) {
                echo '<tr>';
                echo '<td>' . $pt['type_name'] . '</td>';
                echo '<td>' . $pt['staff_count'] . '</td>';
                echo '<td>' . $pt['eval_count'] . '</td>';
                echo '<td>' . number_format($pt['avg_score'], 2) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            break;
    }

    echo '</body></html>';
    exit;
}

// ============ ฟังก์ชันส่งออก PDF ============

function exportPDF($type, $data, $year, $semester)
{
    // สำหรับ PDF จริงๆ ควรใช้ TCPDF หรือ mPDF
    // แต่ในที่นี้จะใช้วิธี HTML + CSS พิมพ์เป็น PDF แทน

?>
    <!DOCTYPE html>
    <html lang="th">

    <head>
        <meta charset="UTF-8">
        <title>รายงาน</title>
        <style>
            @page {
                size: A4;
                margin: 2cm;
            }

            body {
                font-family: 'Sarabun', sans-serif;
                font-size: 14pt;
                line-height: 1.6;
            }

            h1 {
                text-align: center;
                font-size: 18pt;
                margin-bottom: 20px;
            }

            h2 {
                font-size: 16pt;
                margin-top: 20px;
                margin-bottom: 10px;
                border-bottom: 2px solid #333;
                padding-bottom: 5px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }

            th {
                background-color: #f0f0f0;
                font-weight: bold;
            }

            .info-table td {
                border: none;
            }

            .info-table td:first-child {
                font-weight: bold;
                width: 200px;
            }

            .text-center {
                text-align: center;
            }

            .text-right {
                text-align: right;
            }

            @media print {
                .no-print {
                    display: none;
                }
            }
        </style>
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    </head>

    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()"
                style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 5px; cursor: pointer;">
                พิมพ์รายงาน
            </button>
            <button onclick="window.close()"
                style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                ปิด
            </button>
        </div>

        <?php
        switch ($type) {
            case 'individual':
        ?>
                <h1>รายงานผลการประเมินรายบุคคล</h1>

                <table class="info-table">
                    <tr>
                        <td>ชื่อ-นามสกุล</td>
                        <td><?php echo $data['user']['full_name']; ?></td>
                    </tr>
                    <tr>
                        <td>หน่วยงาน</td>
                        <td><?php echo $data['user']['department_name']; ?></td>
                    </tr>
                    <tr>
                        <td>ประเภทบุคลากร</td>
                        <td><?php echo $data['user']['type_name']; ?></td>
                    </tr>
                </table>

                <h2>สถิติภาพรวม</h2>
                <table>
                    <tr>
                        <th>รายการ</th>
                        <th class="text-center">ค่า</th>
                    </tr>
                    <tr>
                        <td>จำนวนครั้งที่ประเมิน</td>
                        <td class="text-center"><?php echo $data['stats']['total_count']; ?></td>
                    </tr>
                    <tr>
                        <td>คะแนนเฉลี่ย</td>
                        <td class="text-center"><?php echo number_format($data['stats']['avg_score'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>คะแนนสูงสุด</td>
                        <td class="text-center"><?php echo number_format($data['stats']['max_score'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>คะแนนต่ำสุด</td>
                        <td class="text-center"><?php echo number_format($data['stats']['min_score'], 2); ?></td>
                    </tr>
                </table>

                <h2>รายการประเมิน</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ปีการศึกษา</th>
                            <th>ภาคเรียน</th>
                            <th>ชื่อรอบประเมิน</th>
                            <th class="text-center">คะแนน</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-center">วันที่ส่ง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['evaluations'] as $eval): ?>
                            <tr>
                                <td class="text-center"><?php echo ($eval['academic_year'] + 543); ?></td>
                                <td class="text-center"><?php echo $eval['semester']; ?></td>
                                <td><?php echo $eval['period_name']; ?></td>
                                <td class="text-center"><?php echo number_format($eval['total_score'], 2); ?></td>
                                <td class="text-center"><?php echo $GLOBALS['status_names'][$eval['status']]; ?></td>
                                <td class="text-center"><?php echo thai_date($eval['submitted_date'], 'short'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php
                break;

            case 'department':
            ?>
                <h1>รายงานผลการประเมินรายหน่วยงาน</h1>

                <table class="info-table">
                    <tr>
                        <td>หน่วยงาน</td>
                        <td><?php echo $data['department']['department_name']; ?></td>
                    </tr>
                </table>

                <h2>สถิติภาพรวม</h2>
                <table>
                    <tr>
                        <th>รายการ</th>
                        <th class="text-center">ค่า</th>
                    </tr>
                    <tr>
                        <td>จำนวนบุคลากร</td>
                        <td class="text-center"><?php echo $data['stats']['total_staff']; ?></td>
                    </tr>
                    <tr>
                        <td>จำนวนการประเมิน</td>
                        <td class="text-center"><?php echo $data['stats']['total_evaluations']; ?></td>
                    </tr>
                    <tr>
                        <td>คะแนนเฉลี่ย</td>
                        <td class="text-center"><?php echo number_format($data['stats']['dept_avg_score'], 2); ?></td>
                    </tr>
                </table>

                <h2>รายชื่อบุคลากร</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ประเภท</th>
                            <th class="text-center">จำนวนครั้ง</th>
                            <th class="text-center">คะแนนเฉลี่ย</th>
                            <th class="text-center">คะแนนสูงสุด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['staff_list'] as $staff): ?>
                            <tr>
                                <td><?php echo $staff['full_name']; ?></td>
                                <td><?php echo $staff['type_name']; ?></td>
                                <td class="text-center"><?php echo $staff['eval_count']; ?></td>
                                <td class="text-center"><?php echo number_format($staff['avg_score'], 2); ?></td>
                                <td class="text-center"><?php echo number_format($staff['max_score'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php
                break;

            case 'organization':
            ?>
                <h1>รายงานภาพรวมองค์กร</h1>

                <h2>สถิติภาพรวม</h2>
                <table>
                    <tr>
                        <th>รายการ</th>
                        <th class="text-center">ค่า</th>
                    </tr>
                    <tr>
                        <td>จำนวนบุคลากร</td>
                        <td class="text-center"><?php echo $data['stats']['total_staff']; ?></td>
                    </tr>
                    <tr>
                        <td>จำนวนหน่วยงาน</td>
                        <td class="text-center"><?php echo $data['stats']['total_departments']; ?></td>
                    </tr>
                    <tr>
                        <td>จำนวนการประเมิน</td>
                        <td class="text-center"><?php echo $data['stats']['total_evaluations']; ?></td>
                    </tr>
                    <tr>
                        <td>คะแนนเฉลี่ย</td>
                        <td class="text-center"><?php echo number_format($data['stats']['org_avg_score'], 2); ?></td>
                    </tr>
                </table>

                <h2>สถิติแยกตามหน่วยงาน</h2>
                <table>
                    <thead>
                        <tr>
                            <th>หน่วยงาน</th>
                            <th class="text-center">จำนวนบุคลากร</th>
                            <th class="text-center">จำนวนการประเมิน</th>
                            <th class="text-center">คะแนนเฉลี่ย</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['departments'] as $dept): ?>
                            <tr>
                                <td><?php echo $dept['department_name']; ?></td>
                                <td class="text-center"><?php echo $dept['staff_count']; ?></td>
                                <td class="text-center"><?php echo $dept['eval_count']; ?></td>
                                <td class="text-center"><?php echo number_format($dept['avg_score'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="page-break-before: always;"></div>

                <h2>สถิติแยกตามประเภทบุคลากร</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ประเภทบุคลากร</th>
                            <th class="text-center">จำนวนบุคลากร</th>
                            <th class="text-center">จำนวนการประเมิน</th>
                            <th class="text-center">คะแนนเฉลี่ย</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['personnel_types'] as $pt): ?>
                            <tr>
                                <td><?php echo $pt['type_name']; ?></td>
                                <td class="text-center"><?php echo $pt['staff_count']; ?></td>
                                <td class="text-center"><?php echo $pt['eval_count']; ?></td>
                                <td class="text-center"><?php echo number_format($pt['avg_score'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
        <?php
                break;
        }
        ?>

        <div style="margin-top: 40px; text-align: right; font-size: 12pt; color: #666;">
            <p>พิมพ์เมื่อ: <?php echo thai_date(date('Y-m-d H:i:s'), 'datetime'); ?></p>
        </div>
    </body>

    </html>
<?php
    exit;
}
