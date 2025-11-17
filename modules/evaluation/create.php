<?php

/**
 * /modules/evaluation/create.php
 * สร้างแบบประเมินใหม่
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';
require_once '../../includes/functions.php';

requirePermission('evaluation.create');

$page_title = 'สร้างแบบประเมินใหม่';
$db = getDB();
$user_id = $_SESSION['user']['user_id'];

// ==================== Handle Form Submission ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $period_id = $_POST['period_id'];

        // ตรวจสอบว่ามีแบบประเมินในรอบนี้แล้วหรือยัง
        $check = $db->prepare("
            SELECT COUNT(*) as count FROM evaluations 
            WHERE user_id = ? AND period_id = ?
        ");
        $check->execute([$user_id, $period_id]);

        if ($check->fetch()['count'] > 0) {
            flash_error('คุณมีแบบประเมินในรอบนี้อยู่แล้ว');
            redirect('evaluation/list.php');
        }

        // Get user's personnel_type_id
        $user = $db->prepare("SELECT personnel_type_id FROM users WHERE user_id = ?");
        $user->execute([$user_id]);
        $personnel_type_id = $user->fetch()['personnel_type_id'];

        // สร้างแบบประเมินใหม่
        $stmt = $db->prepare("
            INSERT INTO evaluations (
                period_id, user_id, personnel_type_id, status, created_at
            ) VALUES (?, ?, ?, 'draft', NOW())
        ");

        $stmt->execute([$period_id, $user_id, $personnel_type_id]);
        $evaluation_id = $db->lastInsertId();

        // บันทึก activity log
        log_activity('create', 'evaluations', $evaluation_id);

        $db->commit();

        flash_success('สร้างแบบประเมินสำเร็จ');
        redirect('edit.php?id=' . $evaluation_id);
    } catch (PDOException $e) {
        $db->rollBack();
        flash_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// ==================== Fetch Data ====================

// Get user info
$user_info = $db->prepare("
    SELECT u.*, pt.type_name_th as personnel_type_name
    FROM users u
    LEFT JOIN personnel_types pt ON u.personnel_type_id = pt.personnel_type_id
    WHERE u.user_id = ?
");
$user_info->execute([$user_id]);
$user = $user_info->fetch();

// Get active or recent periods
$periods = $db->query("
    SELECT ep.*,
           CASE 
               WHEN CURDATE() BETWEEN ep.start_date AND ep.end_date THEN 1
               ELSE 0
           END as is_current,
           DATEDIFF(ep.end_date, CURDATE()) as days_left
    FROM evaluation_periods ep
    WHERE ep.status = 'active' 
       OR (ep.end_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
    ORDER BY is_current DESC, ep.year DESC, ep.semester DESC
")->fetchAll();

// Check existing evaluations for each period
foreach ($periods as &$period) {
    $check = $db->prepare("
        SELECT evaluation_id, status 
        FROM evaluations 
        WHERE user_id = ? AND period_id = ?
    ");
    $check->execute([$user_id, $period['period_id']]);
    $period['existing'] = $check->fetch();
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">สร้างแบบประเมินใหม่</h1>
            <p class="mt-2 text-sm text-gray-600">
                เลือกรอบการประเมินที่ต้องการสร้างแบบประเมิน
            </p>
        </div>
        <a href="list.php" class="btn btn-outline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            กลับ
        </a>
    </div>
</div>

<!-- User Info Card -->
<div class="card mb-6">
    <div class="card-body">
        <div class="flex items-center gap-4">
            <div
                class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                <?php echo mb_substr($user['full_name_th'], 0, 2); ?>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900"><?php echo e($user['full_name_th']); ?></h3>
                <p class="text-sm text-gray-600">
                    <?php echo e($user['personnel_type_name']); ?>
                    <?php if ($user['position']): ?>
                        • <?php echo e($user['position']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Available Periods -->
<?php if (empty($periods)): ?>
    <div class="card">
        <div class="card-body text-center py-12">
            <svg class="w-20 h-20 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">ไม่มีรอบการประเมินที่เปิดใช้งาน</h3>
            <p class="text-gray-600">กรุณาติดต่อผู้ดูแลระบบ</p>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($periods as $period):
            $is_active = $period['is_current'];
            $is_expired = $period['days_left'] < 0;
            $has_existing = $period['existing'] !== false;
        ?>
            <div class="card hover:shadow-lg transition-shadow <?php echo $is_active ? 'ring-2 ring-blue-500' : ''; ?>">
                <div class="card-body">
                    <!-- Header -->
                    <div class="mb-4">
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="text-lg font-semibold text-gray-900 flex-1">
                                <?php echo e($period['period_name']); ?>
                            </h3>
                            <?php if ($is_active): ?>
                                <span class="badge badge-success ml-2">เปิดใช้งาน</span>
                            <?php elseif ($is_expired): ?>
                                <span class="badge badge-gray ml-2">หมดเขต</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-600">
                            ปีการศึกษา <?php echo $period['year'] + 543; ?> / <?php echo $period['semester']; ?>
                        </p>
                    </div>

                    <!-- Dates -->
                    <div class="space-y-2 mb-4 text-sm">
                        <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <?php echo thai_date($period['start_date']); ?> - <?php echo thai_date($period['end_date']); ?>
                        </div>
                        <?php if (!$is_expired && $period['days_left'] >= 0): ?>
                            <div
                                class="flex items-center <?php echo $period['days_left'] <= 7 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                เหลือเวลาอีก <?php echo $period['days_left']; ?> วัน
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($period['description']): ?>
                        <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo e($period['description']); ?></p>
                    <?php endif; ?>

                    <!-- Action Button -->
                    <div class="pt-4 border-t">
                        <?php if ($has_existing): ?>
                            <?php
                            $status_config = [
                                'draft' => ['label' => 'ร่าง', 'class' => 'badge-gray'],
                                'submitted' => ['label' => 'ส่งแล้ว', 'class' => 'badge-primary'],
                                'under_review' => ['label' => 'กำลังตรวจสอบ', 'class' => 'badge-warning'],
                                'approved' => ['label' => 'อนุมัติ', 'class' => 'badge-success'],
                                'rejected' => ['label' => 'ไม่อนุมัติ', 'class' => 'badge-danger'],
                                'returned' => ['label' => 'ส่งกลับ', 'class' => 'bg-orange-100 text-orange-800']
                            ];
                            $status = $period['existing']['status'];
                            $config = $status_config[$status];
                            ?>
                            <div class="text-center">
                                <span class="badge <?php echo $config['class']; ?> mb-2">
                                    <?php echo $config['label']; ?>
                                </span>
                                <a href="view.php?id=<?php echo $period['existing']['evaluation_id']; ?>"
                                    class="btn btn-outline btn-sm w-full">
                                    ดูแบบประเมิน
                                </a>
                            </div>
                        <?php elseif ($is_expired): ?>
                            <button disabled class="btn btn-outline btn-sm w-full opacity-50 cursor-not-allowed">
                                หมดเขตแล้ว
                            </button>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="period_id" value="<?php echo $period['period_id']; ?>">
                                <button type="submit" class="btn btn-primary w-full">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    สร้างแบบประเมิน
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Info Box -->
<div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clip-rule="evenodd" />
        </svg>
        <div>
            <h4 class="text-blue-800 font-medium">หมายเหตุ</h4>
            <p class="text-blue-700 text-sm mt-1">
                • คุณสามารถสร้างแบบประเมินได้เพียง 1 ครั้งต่อ 1 รอบการประเมิน<br>
                • แบบประเมินจะถูกบันทึกเป็น "ร่าง" และสามารถแก้ไขได้จนกว่าจะส่ง<br>
                • กรุณาส่งแบบประเมินก่อนวันที่หมดเขต
            </p>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>