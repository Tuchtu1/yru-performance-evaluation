<?php

/**
 * modules/reports/scheduled-reports.php
 * จัดการรายงานที่ส่งอัตโนมัติตามกำหนดเวลา
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/helpers.php';

// ตรวจสอบการ login
requireAuth();

$db = getDB();
$current_user = $_SESSION['user'];

// Handle form submission
if (is_post()) {
    $action = input('action');

    if ($action === 'create' && verify_csrf(input('csrf_token'))) {
        try {
            $report_name = clean_string(input('report_name'));
            $report_type = input('report_type');
            $frequency = input('frequency');
            $day_of_week = input('day_of_week') ?: null;
            $day_of_month = input('day_of_month') ?: null;
            $time = input('time');
            $format = input('format');
            $recipients = input('recipients');
            $filters = json_encode([
                'department_id' => input('department_id'),
                'personnel_type_id' => input('personnel_type_id'),
                'year' => input('year'),
                'semester' => input('semester')
            ]);

            $stmt = $db->prepare("
                INSERT INTO scheduled_reports 
                (user_id, report_name, report_type, frequency, day_of_week, day_of_month, 
                 time, format, recipients, filters, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                $current_user['user_id'],
                $report_name,
                $report_type,
                $frequency,
                $day_of_week,
                $day_of_month,
                $time,
                $format,
                $recipients,
                $filters
            ]);

            flash_success('สร้างรายงานอัตโนมัติเรียบร้อยแล้ว');
            redirect('modules/reports/scheduled-reports.php');
        } catch (Exception $e) {
            flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    if ($action === 'toggle' && verify_csrf(input('csrf_token'))) {
        $schedule_id = input('schedule_id');

        try {
            // ตรวจสอบสิทธิ์
            $stmt = $db->prepare("SELECT user_id FROM scheduled_reports WHERE schedule_id = ?");
            $stmt->execute([$schedule_id]);
            $schedule = $stmt->fetch();

            if ($schedule && ($schedule['user_id'] == $current_user['user_id'] || isAdmin())) {
                $stmt = $db->prepare("
                    UPDATE scheduled_reports 
                    SET is_active = NOT is_active 
                    WHERE schedule_id = ?
                ");
                $stmt->execute([$schedule_id]);

                flash_success('อัปเดตสถานะเรียบร้อยแล้ว');
            } else {
                flash_error('ไม่มีสิทธิ์ดำเนินการ');
            }
        } catch (Exception $e) {
            flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        redirect('modules/reports/scheduled-reports.php');
    }

    if ($action === 'delete' && verify_csrf(input('csrf_token'))) {
        $schedule_id = input('schedule_id');

        try {
            // ตรวจสอบสิทธิ์
            $stmt = $db->prepare("SELECT user_id FROM scheduled_reports WHERE schedule_id = ?");
            $stmt->execute([$schedule_id]);
            $schedule = $stmt->fetch();

            if ($schedule && ($schedule['user_id'] == $current_user['user_id'] || isAdmin())) {
                $stmt = $db->prepare("DELETE FROM scheduled_reports WHERE schedule_id = ?");
                $stmt->execute([$schedule_id]);

                flash_success('ลบรายงานอัตโนมัติเรียบร้อยแล้ว');
            } else {
                flash_error('ไม่มีสิทธิ์ดำเนินการ');
            }
        } catch (Exception $e) {
            flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        redirect('modules/reports/scheduled-reports.php');
    }
}

try {
    // ดึงรายการรายงานอัตโนมัติ
    if (isAdmin()) {
        $stmt = $db->query("
            SELECT sr.*, u.full_name_th as full_name
            FROM scheduled_reports sr
            JOIN users u ON sr.user_id = u.user_id
            ORDER BY sr.created_at DESC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT sr.*, u.full_name_th as full_name
            FROM scheduled_reports sr
            JOIN users u ON sr.user_id = u.user_id
            WHERE sr.user_id = ?
            ORDER BY sr.created_at DESC
        ");
        $stmt->execute([$current_user['user_id']]);
    }
    $schedules = $stmt->fetchAll();

    // ดึงข้อมูลสำหรับ form
    $stmt = $db->query("SELECT DISTINCT year FROM evaluation_periods ORDER BY year DESC");
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $db->query("
        SELECT department_id, department_name_th as department_name 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY department_name_th
    ");
    $departments = $stmt->fetchAll();

    $stmt = $db->query("
        SELECT personnel_type_id, type_name_th as type_name 
        FROM personnel_types 
        WHERE is_active = 1 
        ORDER BY type_name_th
    ");
    $personnel_types = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Scheduled Reports Error: " . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    $schedules = [];
    $years = [];
    $departments = [];
    $personnel_types = [];
}

$page_title = 'รายงานอัตโนมัติ';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">รายงานอัตโนมัติ</h1>
                <p class="mt-1 text-gray-600">
                    ตั้งค่าการส่งรายงานอัตโนมัติตามกำหนดเวลา
                </p>
            </div>
            <button onclick="showCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                <i class="fas fa-plus mr-2"></i>สร้างรายงานอัตโนมัติ
            </button>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Schedule List -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <?php if (empty($schedules)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-alt text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">ยังไม่มีรายงานอัตโนมัติ</h3>
                    <p class="text-gray-600 mb-4">เริ่มต้นสร้างรายงานอัตโนมัติของคุณ</p>
                    <button onclick="showCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                        <i class="fas fa-plus mr-2"></i>สร้างรายงานอัตโนมัติ
                    </button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    ชื่อรายงาน
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    ประเภท
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    ความถี่
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    รูปแบบ
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                    ผู้รับ
                                </th>
                                <?php if (isAdmin()): ?>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        ผู้สร้าง
                                    </th>
                                <?php endif; ?>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    สถานะ
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                    จัดการ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($schedules as $schedule): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo e($schedule['report_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php
                                            $freq_text = [
                                                'daily' => 'ทุกวันเวลา ' . substr($schedule['time'], 0, 5) . ' น.',
                                                'weekly' => 'ทุก' . ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'][$schedule['day_of_week'] ?? 0] . ' เวลา ' . substr($schedule['time'], 0, 5) . ' น.',
                                                'monthly' => 'วันที่ ' . ($schedule['day_of_month'] ?? 1) . ' ของทุกเดือน เวลา ' . substr($schedule['time'], 0, 5) . ' น.'
                                            ];
                                            echo $freq_text[$schedule['frequency']];
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php
                                        $type_names = [
                                            'individual' => 'รายบุคคล',
                                            'department' => 'รายหน่วยงาน',
                                            'organization' => 'ภาพรวมองค์กร'
                                        ];
                                        echo $type_names[$schedule['report_type']];
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php
                                        $freq_names = [
                                            'daily' => 'รายวัน',
                                            'weekly' => 'รายสัปดาห์',
                                            'monthly' => 'รายเดือน'
                                        ];
                                        echo $freq_names[$schedule['frequency']];
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo strtoupper($schedule['format']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate" title="<?php echo e($schedule['recipients']); ?>">
                                            <?php
                                            $emails = explode(',', $schedule['recipients']);
                                            echo e(trim($emails[0]));
                                            if (count($emails) > 1) {
                                                echo ' (+' . (count($emails) - 1) . ')';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <?php if (isAdmin()): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo e($schedule['full_name']); ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <form method="POST" class="inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="schedule_id"
                                                value="<?php echo $schedule['schedule_id']; ?>">
                                            <button type="submit"
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 <?php echo $schedule['is_active'] ? 'bg-blue-600' : 'bg-gray-200'; ?>">
                                                <span
                                                    class="translate-x-0 pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?php echo $schedule['is_active'] ? 'translate-x-5' : 'translate-x-0'; ?>"></span>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <button onclick='viewSchedule(<?php echo json_encode($schedule); ?>)'
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" class="inline"
                                            onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบรายงานนี้?')">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="schedule_id"
                                                value="<?php echo $schedule['schedule_id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Schedule Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">สร้างรายงานอัตโนมัติ</h3>
            <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" id="createForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">

            <div class="space-y-4">
                <!-- Report Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ชื่อรายงาน</label>
                    <input type="text" name="report_name" required class="w-full border rounded px-3 py-2"
                        placeholder="เช่น รายงานประจำสัปดาห์">
                </div>

                <!-- Report Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ประเภทรายงาน</label>
                    <select name="report_type" required class="w-full border rounded px-3 py-2"
                        onchange="updateFilters(this.value)">
                        <option value="individual">รายงานรายบุคคล</option>
                        <option value="department">รายงานรายหน่วยงาน</option>
                        <option value="organization">รายงานภาพรวมองค์กร</option>
                    </select>
                </div>

                <!-- Frequency -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ความถี่</label>
                    <select name="frequency" required class="w-full border rounded px-3 py-2" id="frequency"
                        onchange="updateFrequencyOptions(this.value)">
                        <option value="daily">รายวัน</option>
                        <option value="weekly">รายสัปดาห์</option>
                        <option value="monthly">รายเดือน</option>
                    </select>
                </div>

                <!-- Day of Week (for weekly) -->
                <div id="dayOfWeekField" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">วัน</label>
                    <select name="day_of_week" class="w-full border rounded px-3 py-2">
                        <option value="0">วันอาทิตย์</option>
                        <option value="1">วันจันทร์</option>
                        <option value="2">วันอังคาร</option>
                        <option value="3">วันพุธ</option>
                        <option value="4">วันพฤหัสบดี</option>
                        <option value="5">วันศุกร์</option>
                        <option value="6">วันเสาร์</option>
                    </select>
                </div>

                <!-- Day of Month (for monthly) -->
                <div id="dayOfMonthField" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">วันที่</label>
                    <select name="day_of_month" class="w-full border rounded px-3 py-2">
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Time -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">เวลา</label>
                    <input type="time" name="time" required class="w-full border rounded px-3 py-2" value="08:00">
                </div>

                <!-- Format -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">รูปแบบไฟล์</label>
                    <select name="format" required class="w-full border rounded px-3 py-2">
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>

                <!-- Recipients -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">อีเมลผู้รับ</label>
                    <textarea name="recipients" required class="w-full border rounded px-3 py-2" rows="3"
                        placeholder="ใส่อีเมลผู้รับ คั่นด้วยเครื่องหมายจุลภาค (,)"></textarea>
                    <p class="mt-1 text-sm text-gray-500">ตัวอย่าง: user1@yru.ac.th, user2@yru.ac.th</p>
                </div>

                <!-- Filters -->
                <div id="filterSection">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">ตัวกรอง (ถ้ามี)</h4>

                    <div class="grid grid-cols-2 gap-4">
                        <div id="departmentFilter">
                            <label class="block text-sm text-gray-600 mb-1">หน่วยงาน</label>
                            <select name="department_id" class="w-full border rounded px-3 py-2">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo e($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="personnelTypeFilter">
                            <label class="block text-sm text-gray-600 mb-1">ประเภทบุคลากร</label>
                            <select name="personnel_type_id" class="w-full border rounded px-3 py-2">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($personnel_types as $pt): ?>
                                    <option value="<?php echo $pt['personnel_type_id']; ?>">
                                        <?php echo e($pt['type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600 mb-1">ปีการศึกษา</label>
                            <select name="year" class="w-full border rounded px-3 py-2">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>">
                                        <?php echo ($year + 543); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-600 mb-1">ภาคเรียน</label>
                            <select name="semester" class="w-full border rounded px-3 py-2">
                                <option value="">ทั้งหมด</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeCreateModal()"
                    class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">
                    ยกเลิก
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                    สร้างรายงาน
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Schedule Modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">รายละเอียดรายงานอัตโนมัติ</h3>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div id="viewContent" class="space-y-4">
            <!-- Content will be populated by JavaScript -->
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" onclick="closeViewModal()"
                class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">
                ปิด
            </button>
        </div>
    </div>
</div>

<script>
    function showCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
        document.getElementById('createForm').reset();
        updateFrequencyOptions('daily');
    }

    function updateFrequencyOptions(frequency) {
        const dayOfWeek = document.getElementById('dayOfWeekField');
        const dayOfMonth = document.getElementById('dayOfMonthField');

        dayOfWeek.classList.add('hidden');
        dayOfMonth.classList.add('hidden');

        if (frequency === 'weekly') {
            dayOfWeek.classList.remove('hidden');
        } else if (frequency === 'monthly') {
            dayOfMonth.classList.remove('hidden');
        }
    }

    function updateFilters(reportType) {
        const departmentFilter = document.getElementById('departmentFilter');
        const personnelTypeFilter = document.getElementById('personnelTypeFilter');

        if (reportType === 'individual') {
            departmentFilter.style.display = 'block';
            personnelTypeFilter.style.display = 'block';
        } else if (reportType === 'department') {
            departmentFilter.style.display = 'block';
            personnelTypeFilter.style.display = 'none';
        } else {
            departmentFilter.style.display = 'none';
            personnelTypeFilter.style.display = 'none';
        }
    }

    function viewSchedule(schedule) {
        const typeNames = {
            'individual': 'รายบุคคล',
            'department': 'รายหน่วยงาน',
            'organization': 'ภาพรวมองค์กร'
        };

        const freqNames = {
            'daily': 'รายวัน',
            'weekly': 'รายสัปดาห์',
            'monthly': 'รายเดือน'
        };

        const days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

        let scheduleText = '';
        const time = schedule.time.substring(0, 5);
        if (schedule.frequency === 'daily') {
            scheduleText = `ทุกวันเวลา ${time} น.`;
        } else if (schedule.frequency === 'weekly') {
            scheduleText = `ทุก${days[schedule.day_of_week || 0]} เวลา ${time} น.`;
        } else {
            scheduleText = `วันที่ ${schedule.day_of_month || 1} ของทุกเดือน เวลา ${time} น.`;
        }

        let content = `
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-sm font-medium text-gray-500">ชื่อรายงาน:</span>
                    <p class="mt-1 text-sm text-gray-900">${schedule.report_name}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">ประเภท:</span>
                    <p class="mt-1 text-sm text-gray-900">${typeNames[schedule.report_type]}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">ความถี่:</span>
                    <p class="mt-1 text-sm text-gray-900">${freqNames[schedule.frequency]}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">รูปแบบ:</span>
                    <p class="mt-1 text-sm text-gray-900">${schedule.format.toUpperCase()}</p>
                </div>
            </div>
            
            <div>
                <span class="text-sm font-medium text-gray-500">กำหนดการส่ง:</span>
                <p class="mt-1 text-sm text-gray-900">${scheduleText}</p>
            </div>
            
            <div>
                <span class="text-sm font-medium text-gray-500">ผู้รับ:</span>
                <p class="mt-1 text-sm text-gray-900">${schedule.recipients}</p>
            </div>
            
            <div>
                <span class="text-sm font-medium text-gray-500">สถานะ:</span>
                <p class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${schedule.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                        ${schedule.is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน'}
                    </span>
                </p>
            </div>
        </div>
    `;

        document.getElementById('viewContent').innerHTML = content;
        document.getElementById('viewModal').classList.remove('hidden');
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateFrequencyOptions('daily');
    });
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>