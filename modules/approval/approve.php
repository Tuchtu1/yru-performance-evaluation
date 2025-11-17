<?php

/**
 * /modules/approval/approve.php
 * Action: อนุมัติแบบประเมิน
 */

session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';
require_once APP_ROOT . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบสิทธิ์
if (!can('approval.approve')) {
    echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์อนุมัติ']);
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
        throw new Exception('ไม่สามารถอนุมัติแบบประเมินในสถานะนี้ได้');
    }

    // ตรวจสอบว่าเป็นผู้บริหารที่ถูกเลือกหรือไม่ (สำหรับ manager)
    if ($current_user['role'] === 'manager') {
        $check_manager = $db->prepare("
            SELECT em_id, status 
            FROM evaluation_managers 
            WHERE evaluation_id = ? AND manager_user_id = ?
        ");
        $check_manager->execute([$evaluation_id, $current_user['user_id']]);
        $manager_record = $check_manager->fetch();

        if (!$manager_record) {
            throw new Exception('คุณไม่ได้รับมอบหมายให้พิจารณาแบบประเมินนี้');
        }

        if ($manager_record['status'] === 'approved') {
            throw new Exception('คุณได้อนุมัติแบบประเมินนี้แล้ว');
        }

        // อัพเดทสถานะผู้บริหาร
        $update_manager = $db->prepare("
            UPDATE evaluation_managers 
            SET status = 'approved', 
                review_comment = ?, 
                reviewed_at = NOW()
            WHERE evaluation_id = ? AND manager_user_id = ?
        ");
        $update_manager->execute([$comment, $evaluation_id, $current_user['user_id']]);

        // ตรวจสอบว่าผู้บริหารทุกคนอนุมัติแล้วหรือยัง
        $check_all = $db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            FROM evaluation_managers 
            WHERE evaluation_id = ?
        ");
        $check_all->execute([$evaluation_id]);
        $approval_status = $check_all->fetch();

        // ถ้าทุกคนอนุมัติแล้ว ให้เปลี่ยนสถานะเป็น approved
        if ($approval_status['total'] == $approval_status['approved']) {
            $update_eval = $db->prepare("
                UPDATE evaluations 
                SET status = 'approved', approved_at = NOW()
                WHERE evaluation_id = ?
            ");
            $update_eval->execute([$evaluation_id]);
            $final_status = 'approved';
        } else {
            // ถ้ายังไม่ครบ ให้เป็น under_review
            $update_eval = $db->prepare("
                UPDATE evaluations 
                SET status = 'under_review'
                WHERE evaluation_id = ?
            ");
            $update_eval->execute([$evaluation_id]);
            $final_status = 'under_review';
        }
    } else {
        // Admin อนุมัติได้ทันที
        $update_eval = $db->prepare("
            UPDATE evaluations 
            SET status = 'approved', approved_at = NOW()
            WHERE evaluation_id = ?
        ");
        $update_eval->execute([$evaluation_id]);
        $final_status = 'approved';
    }

    // บันทึกประวัติ
    $history = $db->prepare("
        INSERT INTO approval_history 
        (evaluation_id, manager_user_id, action, comment, previous_status, new_status)
        VALUES (?, ?, 'approve', ?, ?, ?)
    ");
    $history->execute([
        $evaluation_id,
        $current_user['user_id'],
        $comment,
        $evaluation['status'],
        $final_status
    ]);

    // Log activity
    log_activity('approve_evaluation', 'evaluations', $evaluation_id, [
        'evaluator' => $evaluation['full_name_th'],
        'final_status' => $final_status
    ]);

    // TODO: ส่งการแจ้งเตือนไปยังผู้ส่งแบบประเมิน
    if ($final_status === 'approved') {
        // สร้างการแจ้งเตือน
        $notif = $db->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type, related_id, related_type)
            VALUES (?, ?, ?, 'evaluation', ?, 'approved')
        ");
        $notif->execute([
            $evaluation['user_id'],
            'แบบประเมินได้รับการอนุมัติ',
            $current_user['full_name_th'] . ' ได้อนุมัติแบบประเมินของคุณแล้ว',
            $evaluation_id
        ]);

        // TODO: ส่งอีเมล
        // send_email_template($evaluation['email'], 'แบบประเมินได้รับการอนุมัติ', 'evaluation_approved', [
        //     'name' => $evaluation['full_name_th'],
        //     'manager_name' => $current_user['full_name_th']
        // ]);
    }

    $db->commit();

    $success_message = $final_status === 'approved'
        ? 'อนุมัติแบบประเมินสำเร็จ'
        : 'บันทึกการอนุมัติสำเร็จ รอผู้บริหารท่านอื่นพิจารณา';

    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'final_status' => $final_status
    ]);
} catch (Exception $e) {
    $db->rollBack();
    log_error('Approve evaluation failed', [
        'evaluation_id' => $evaluation_id,
        'error' => $e->getMessage()
    ]);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
