<?php

/**
 * /modules/configuration/system-backup.php
 * สำรองข้อมูลและกู้คืนระบบ (System Backup & Restore)
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requirePermission('system.backup');

$page_title = 'สำรองข้อมูลระบบ';
$db = getDB();

// ==================== Handle Actions ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_backup':
            try {
                $backup_name = 'backup_' . date('Y-m-d_His') . '.sql';
                $backup_path = APP_ROOT . '/backups/' . $backup_name;

                // Create backups directory if not exists
                if (!file_exists(APP_ROOT . '/backups/')) {
                    mkdir(APP_ROOT . '/backups/', 0755, true);
                }

                // Database backup command
                $command = sprintf(
                    'mysqldump --user=%s --password=%s --host=%s %s > %s',
                    DB_USER,
                    DB_PASS,
                    DB_HOST,
                    DB_NAME,
                    $backup_path
                );

                exec($command, $output, $return_var);

                if ($return_var === 0 && file_exists($backup_path)) {
                    // Save backup record
                    $stmt = $db->prepare("
                        INSERT INTO system_backups (
                            backup_name, backup_path, file_size, backup_type, created_by
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $backup_name,
                        $backup_path,
                        filesize($backup_path),
                        'manual',
                        $_SESSION['user']['user_id']
                    ]);

                    log_activity('create_backup', 'system_backups', $db->lastInsertId());
                    flash_success('สำรองข้อมูลสำเร็จ');
                } else {
                    flash_error('ไม่สามารถสำรองข้อมูลได้');
                }
            } catch (Exception $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('system-backup.php');
            break;

        case 'restore_backup':
            try {
                $backup_id = $_POST['backup_id'];

                // Get backup info
                $stmt = $db->prepare("SELECT * FROM system_backups WHERE backup_id = ?");
                $stmt->execute([$backup_id]);
                $backup = $stmt->fetch();

                if ($backup && file_exists($backup['backup_path'])) {
                    // Restore command
                    $command = sprintf(
                        'mysql --user=%s --password=%s --host=%s %s < %s',
                        DB_USER,
                        DB_PASS,
                        DB_HOST,
                        DB_NAME,
                        $backup['backup_path']
                    );

                    exec($command, $output, $return_var);

                    if ($return_var === 0) {
                        log_activity('restore_backup', 'system_backups', $backup_id);
                        flash_success('กู้คืนข้อมูลสำเร็จ');
                    } else {
                        flash_error('ไม่สามารถกู้คืนข้อมูลได้');
                    }
                } else {
                    flash_error('ไม่พบไฟล์สำรองข้อมูล');
                }
            } catch (Exception $e) {
                flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            }
            redirect('system-backup.php');
            break;
    }
}

// Delete backup
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM system_backups WHERE backup_id = ?");
        $stmt->execute([$_GET['id']]);
        $backup = $stmt->fetch();

        if ($backup) {
            // Delete file
            if (file_exists($backup['backup_path'])) {
                unlink($backup['backup_path']);
            }

            // Delete record
            $stmt = $db->prepare("DELETE FROM system_backups WHERE backup_id = ?");
            if ($stmt->execute([$_GET['id']])) {
                log_activity('delete_backup', 'system_backups', $_GET['id']);
                flash_success('ลบไฟล์สำรองข้อมูลสำเร็จ');
            }
        }
    } catch (Exception $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect('system-backup.php');
}

// Download backup
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM system_backups WHERE backup_id = ?");
        $stmt->execute([$_GET['id']]);
        $backup = $stmt->fetch();

        if ($backup && file_exists($backup['backup_path'])) {
            download_file($backup['backup_path'], $backup['backup_name']);
        } else {
            flash_error('ไม่พบไฟล์สำรองข้อมูล');
            redirect('system-backup.php');
        }
    } catch (Exception $e) {
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        redirect('system-backup.php');
    }
}

// ==================== Fetch Data ====================

// Get backups list
$backups = $db->query("
    SELECT sb.*, u.full_name_th as created_by_name
    FROM system_backups sb
    LEFT JOIN users u ON sb.created_by = u.user_id
    ORDER BY sb.created_at DESC
")->fetchAll();

// Get database size
$db_size = $db->query("
    SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.tables
    WHERE table_schema = '" . DB_NAME . "'
")->fetch()['size_mb'];

// Get upload directory size
$upload_size = 0;
if (file_exists(UPLOAD_PATH)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_PATH));
    foreach ($files as $file) {
        if ($file->isFile()) {
            $upload_size += $file->getSize();
        }
    }
    $upload_size = round($upload_size / 1024 / 1024, 2);
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">สำรองข้อมูลระบบ</h1>
            <p class="mt-2 text-sm text-gray-600">
                จัดการการสำรองข้อมูลและกู้คืนระบบ
            </p>
        </div>
        <button onclick="confirmBackup()" class="btn btn-primary">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            สำรองข้อมูลใหม่
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">ไฟล์สำรองข้อมูล</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format(count($backups)); ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">ขนาดฐานข้อมูล</p>
                <p class="text-3xl font-bold text-green-600"><?php echo $db_size; ?> MB</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">ขนาดไฟล์อัปโหลด</p>
                <p class="text-3xl font-bold text-purple-600"><?php echo $upload_size; ?> MB</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 mb-1">ล่าสุด</p>
                <p class="text-lg font-bold text-orange-600">
                    <?php echo !empty($backups) ? time_ago($backups[0]['created_at']) : '-'; ?>
                </p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Backup Warning -->
<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r mb-6">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd" />
        </svg>
        <div>
            <h4 class="text-yellow-800 font-medium">คำเตือนสำคัญ</h4>
            <p class="text-yellow-700 text-sm mt-1">
                ก่อนทำการกู้คืนข้อมูล กรุณาสำรองข้อมูลปัจจุบันก่อนเสมอ การกู้คืนข้อมูลจะเขียนทับข้อมูลที่มีอยู่ทั้งหมด
            </p>
        </div>
    </div>
</div>

<!-- Backups List -->
<div class="card">
    <div class="card-header">
        <h3 class="text-lg font-semibold">ไฟล์สำรองข้อมูล</h3>
    </div>
    <div class="card-body">
        <div class="overflow-x-auto">
            <table class="table">
                <thead class="table-header">
                    <tr>
                        <th class="table-th">ชื่อไฟล์</th>
                        <th class="table-th">ขนาดไฟล์</th>
                        <th class="table-th">ประเภท</th>
                        <th class="table-th">สำรองโดย</th>
                        <th class="table-th">วันที่สำรอง</th>
                        <th class="table-th text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="6" class="table-td text-center text-gray-500 py-8">
                                ยังไม่มีไฟล์สำรองข้อมูล
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($backups as $backup): ?>
                            <tr class="table-row">
                                <td class="table-td">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span class="font-mono text-sm"><?php echo e($backup['backup_name']); ?></span>
                                    </div>
                                </td>
                                <td class="table-td">
                                    <span class="font-semibold text-gray-700">
                                        <?php echo format_bytes($backup['file_size']); ?>
                                    </span>
                                </td>
                                <td class="table-td">
                                    <?php if ($backup['backup_type'] === 'auto'): ?>
                                        <span class="badge badge-primary">อัตโนมัติ</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">ด้วยตนเอง</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-td text-gray-600">
                                    <?php echo e($backup['created_by_name'] ?? 'System'); ?>
                                </td>
                                <td class="table-td">
                                    <div class="text-sm">
                                        <div class="text-gray-900"><?php echo thai_date($backup['created_at']); ?></div>
                                        <div class="text-gray-500"><?php echo thai_date($backup['created_at'], 'time'); ?></div>
                                    </div>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="?action=download&id=<?php echo $backup['backup_id']; ?>"
                                            class="btn-icon text-blue-600 hover:bg-blue-50" title="ดาวน์โหลด">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                        <button onclick='confirmRestore(<?php echo json_encode($backup); ?>)'
                                            class="btn-icon text-green-600 hover:bg-green-50" title="กู้คืน">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <a href="?action=delete&id=<?php echo $backup['backup_id']; ?>"
                                            class="btn-icon text-red-600 hover:bg-red-50" title="ลบ"
                                            onclick="return confirm('ต้องการลบไฟล์สำรองข้อมูลนี้ใช่หรือไม่?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div id="restoreModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="text-xl font-semibold">ยืนยันการกู้คืนข้อมูล</h3>
            <button onclick="closeModal('restoreModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" id="restoreForm">
            <input type="hidden" name="action" value="restore_backup">
            <input type="hidden" name="backup_id" id="restore_backup_id">
            <div class="modal-body space-y-4">
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r">
                    <div class="flex">
                        <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <h4 class="text-red-800 font-medium">คำเตือน!</h4>
                            <p class="text-red-700 text-sm mt-1">
                                การกู้คืนข้อมูลจะเขียนทับข้อมูลที่มีอยู่ทั้งหมด กรุณาแน่ใจก่อนดำเนินการ
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="font-medium text-gray-900">คุณกำลังจะกู้คืนข้อมูลจากไฟล์:</p>
                    <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded font-mono" id="restore_filename"></p>
                </div>

                <label class="flex items-center p-3 bg-yellow-50 rounded-lg">
                    <input type="checkbox" required class="form-checkbox">
                    <span class="ml-3 text-sm text-gray-700">
                        ฉันยืนยันว่าได้สำรองข้อมูลปัจจุบันแล้ว
                    </span>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('restoreModal')" class="btn btn-outline">ยกเลิก</button>
                <button type="submit" class="btn btn-danger">กู้คืนข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script>
    function confirmBackup() {
        if (confirm('ต้องการสำรองข้อมูลใช่หรือไม่?\n\nกระบวนการนี้อาจใช้เวลาสักครู่')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="create_backup">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    function confirmRestore(backup) {
        document.getElementById('restore_backup_id').value = backup.backup_id;
        document.getElementById('restore_filename').textContent = backup.backup_name;
        openModal('restoreModal');
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