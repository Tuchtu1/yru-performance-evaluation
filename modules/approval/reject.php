<?php

/**
 * reject.php - ไม่อนุมัติแบบประเมิน
 * สามารถใช้ไฟล์นี้เป็น return.php (ส่งกลับแก้ไข) ได้โดยเปลี่ยน action
 */

session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// กำหนด action (reject หรือ return)
$script_name = basename($_SERVER['SCRIPT_NAME']);
$action_type = $script_name === 'return.php' ? 'return' : 'reject';

// ตรวจสอบสิทธิ์
if (!can('approval.reject')) {
    echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์']);
    exit;
}

// ตรวจสอบ method
if (!is_post()) {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// ตรวจสอบ CSRF
if (!verify_csrf(input('csrf_token'))) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$current_user = $_SESSION['user'];
$evaluation_id = (int)input('evaluation_id', 0);
$comment = clean_string(input('comment', ''));

if ($evaluation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid evaluation ID']);
    exit;
}

// ต้องระบุเหตุผล
if (empty($comment)) {
    $message = $action_type === 'return'
        ? 'กรุณาระบุเหตุผลในการส่งกลับแก้ไข'
        : 'กรุณาระบุเหตุผลในการไม่อนุมัติ';
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // ดึงข้อมูลแบบประเมิน
    $stmt = $db->prepare("
        SELECT e.*, u.full_name_th, u.email 
        FROM evaluations e
        INNER JOIN users u ON e.user_id = u.user_id
        WHERE e.evaluation_id = ?
    ");
    $stmt->execute([$evaluation_id]);
    $evaluation = $stmt->fetch();

    if (!$evaluation) {
        throw new Exception('ไม่พบแบบประเมิน');
    }

    // ตรวจสอบสถานะ
    if (!in_array($evaluation['status'], ['submitted', 'under_review'])) {
        throw new Exception('ไม่สามารถดำเนินการกับแบบประเมินในสถานะนี้ได้');
    }

    // กำหนดสถานะใหม่
    $new_status = $action_type === 'return' ? 'returned' : 'rejected';
    $action_name = $action_type === 'return' ? 'ส่งกลับแก้ไข' : 'ไม่อนุมัติ';

    // ตรวจสอบว่าเป็นผู้บริหารที่ถูกเลือกหรือไม่ (สำหรับ manager)
    if ($current_user['role'] === 'manager') {
        $check_manager = $db->prepare("
            SELECT em_id 
            FROM evaluation_managers 
            WHERE evaluation_id = ? AND manager_user_id = ?
        ");
        $check_manager->execute([$evaluation_id, $current_user['user_id']]);

        if (!$check_manager->fetch()) {
            throw new Exception('คุณไม่ได้รับมอบหมายให้พิจารณาแบบประเมินนี้');
        }

        // อัพเดทสถานะผู้บริหาร
        $update_manager = $db->prepare("
            UPDATE evaluation_managers 
            SET status = ?, 
                review_comment = ?, 
                reviewed_at = NOW()
            WHERE evaluation_id = ? AND manager_user_id = ?
        ");
        $update_manager->execute([$new_status, $comment, $evaluation_id, $current_user['user_id']]);
    }

    // อัพเดทสถานะแบบประเมิน
    $update_eval = $db->prepare("
        UPDATE evaluations 
        SET status = ?, reviewed_at = NOW()
        WHERE evaluation_id = ?
    ");
    $update_eval->execute([$new_status, $evaluation_id]);

    // บันทึกประวัติ
    $history = $db->prepare("
        INSERT INTO approval_history 
        (evaluation_id, manager_user_id, action, comment, previous_status, new_status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $history->execute([
        $evaluation_id,
        $current_user['user_id'],
        $action_type,
        $comment,
        $evaluation['status'],
        $new_status
    ]);

    // Log activity
    log_activity($action_type . '_evaluation', 'evaluations', $evaluation_id, [
        'evaluator' => $evaluation['full_name_th'],
        'reason' => $comment
    ]);

    // สร้างการแจ้งเตือน
    $notif_title = $action_type === 'return'
        ? 'แบบประเมินถูกส่งกลับแก้ไข'
        : 'แบบประเมินไม่ได้รับการอนุมัติ';

    $notif_message = $current_user['full_name_th'] . ' ' . $action_name . ' แบบประเมินของคุณ: ' . $comment;

    $notif = $db->prepare("
        INSERT INTO notifications 
        (user_id, title, message, type, related_id, related_type)
        VALUES (?, ?, ?, 'evaluation', ?, ?)
    ");
    $notif->execute([
        $evaluation['user_id'],
        $notif_title,
        $notif_message,
        $evaluation_id,
        $new_status
    ]);

    // TODO: ส่งอีเมล
    // $email_template = $action_type === 'return' ? 'evaluation_returned' : 'evaluation_rejected';
    // send_email_template($evaluation['email'], $notif_title, $email_template, [
    //     'name' => $evaluation['full_name_th'],
    //     'manager_name' => $current_user['full_name_th'],
    //     'comment' => $comment
    // ]);

    $db->commit();

    $success_message = $action_type === 'return'
        ? 'ส่งกลับแก้ไขสำเร็จ'
        : 'บันทึกการไม่อนุมัติสำเร็จ';

    echo json_encode([
        'success' => true,
        'message' => $success_message
    ]);
} catch (Exception $e) {
    $db->rollBack();
    log_error('Reject/Return evaluation failed', [
        'evaluation_id' => $evaluation_id,
        'action' => $action_type,
        'error' => $e->getMessage()
    ]);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

<?php
// สำหรับ return.php ให้คัดลอกไฟล์นี้และเปลี่ยนชื่อเป็น return.php
// หรือใช้ symlink: ln -s reject.php return.php
?>