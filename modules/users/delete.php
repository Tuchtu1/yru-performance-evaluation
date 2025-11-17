<?php

/**
 * modules/users/delete.php
 * ลบผู้ใช้งาน
 */

require_once '../../config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

// ตรวจสอบการ login และสิทธิ์
requireAuth();
requirePermission('users.delete');
$current_user = $_SESSION['user'];
$db = getDB();
$user_id = $_GET['id'] ?? 0;

// ดึงข้อมูลผู้ใช้
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    flash_error('ไม่พบข้อมูลผู้ใช้');
    redirect('modules/users/list.php');
}

// ห้ามลบตัวเอง
if ($user_id == $current_user['user_id']) {
    flash_error('ไม่สามารถลบบัญชีของตัวเองได้');
    redirect('modules/users/list.php');
}

// Handle form submission
if (is_post() && verify_csrf(input('csrf_token'))) {
    try {
        $action = input('action');

        if ($action === 'delete') {
            $db->beginTransaction();

            // ตรวจสอบว่ามีข้อมูลที่เกี่ยวข้องหรือไม่
            $stmt = $db->prepare("SELECT COUNT(*) FROM evaluations WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $eval_count = $stmt->fetchColumn();

            if ($eval_count > 0) {
                // ถ้ามีข้อมูลแบบประเมิน ให้เปลี่ยนเป็น inactive แทน
                $stmt = $db->prepare("
                    UPDATE users SET 
                        is_active = 0,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);

                flash_warning("ไม่สามารถลบผู้ใช้ที่มีข้อมูลการประเมินได้ ระบบได้เปลี่ยนสถานะเป็น 'ไม่ใช้งาน' แทน");
            } else {
                // ลบได้เลย
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);

                flash_success('ลบผู้ใช้งานเรียบร้อยแล้ว');
            }

            // Log activity
            log_activity('delete', 'users', $user_id, json_encode([
                'username' => $user['username'],
                'email' => $user['email']
            ]));

            $db->commit();
            redirect('modules/users/list.php');
        }
    } catch (Exception $e) {
        $db->rollBack();
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        log_error('Delete User Error', ['error' => $e->getMessage(), 'user_id' => $user_id]);
    }
}

$page_title = 'ลบผู้ใช้งาน';
include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center space-x-3">
            <a href="<?php echo url('modules/users/list.php'); ?>" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">ลบผู้ใช้งาน</h1>
                <p class="mt-1 text-sm text-gray-500">
                    ยืนยันการลบผู้ใช้งานออกจากระบบ
                </p>
            </div>
        </div>
    </div>

    <!-- Warning Card -->
    <div class="card border-l-4 border-red-500">
        <div class="card-body">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-lg font-medium text-red-800">คำเตือน!</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p class="mb-2">คุณกำลังจะลบผู้ใช้งานต่อไปนี้:</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mt-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <span class="font-medium">ชื่อผู้ใช้:</span>
                                    <span class="ml-2"><?php echo e($user['username']); ?></span>
                                </div>
                                <div>
                                    <span class="font-medium">ชื่อ-นามสกุล:</span>
                                    <span class="ml-2"><?php echo e($user['full_name_th']); ?></span>
                                </div>
                                <div>
                                    <span class="font-medium">อีเมล:</span>
                                    <span class="ml-2"><?php echo e($user['email']); ?></span>
                                </div>
                                <div>
                                    <span class="font-medium">สถานะ:</span>
                                    <span class="ml-2">
                                        <?php echo $user['is_active'] ? 'ใช้งานอยู่' : 'ไม่ใช้งาน'; ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium">บทบาท:</span>
                                    <span class="ml-2">
                                        <?php echo e($role_names[$user['role']] ?? $user['role']); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium">ล็อกอินล่าสุด:</span>
                                    <span class="ml-2">
                                        <?php echo $user['last_login'] ? time_ago($user['last_login']) : 'ไม่เคย'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mt-6">
        <div class="flex">
            <svg class="w-5 h-5 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd" />
            </svg>
            <div class="text-sm text-yellow-700">
                <p class="font-medium mb-1">หมายเหตุ:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>หากผู้ใช้มีข้อมูลการประเมินแล้ว ระบบจะไม่ลบข้อมูล แต่จะเปลี่ยนสถานะเป็น "ไม่ใช้งาน" แทน</li>
                    <li>การลบผู้ใช้จะไม่สามารถกู้คืนได้</li>
                    <li>ข้อมูล Login ประวัติการใช้งาน และกิจกรรมจะถูกเก็บไว้ในระบบ</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Confirmation Form -->
    <form method="POST" class="mt-6">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="delete">

        <div class="flex justify-end space-x-3">
            <a href="<?php echo url('modules/users/list.php'); ?>" class="btn-secondary">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                ยกเลิก
            </a>
            <button type="submit" class="btn-danger">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                ยืนยันการลบ
            </button>
        </div>
    </form>
</div>

<style>
    .btn-danger {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border: 1px solid transparent;
        border-radius: 0.375rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        font-size: 0.875rem;
        font-weight: 500;
        color: #ffffff;
        background-color: #dc2626;
    }

    .btn-danger:hover {
        background-color: #b91c1c;
    }

    .btn-danger:focus {
        outline: none;
        box-shadow: 0 0 0 2px #fff, 0 0 0 4px #ef4444;
    }
</style>

<?php include APP_ROOT . '/includes/footer.php'; ?>