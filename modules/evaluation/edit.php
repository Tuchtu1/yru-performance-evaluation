<?php

/**
 * modules/evaluation/edit.php
 * แก้ไขแบบประเมินผลการปฏิบัติงาน
 * FIXED: แก้ไข SQL JOIN สำหรับ portfolios ให้ถูกต้อง
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบการ login
requireAuth();

// ตรวจสอบสิทธิ์
if (!can('evaluation.edit_own')) {
    flash_error('คุณไม่มีสิทธิ์แก้ไขแบบประเมิน');
    redirect('modules/evaluation/list.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user']['user_id'];
$evaluation_id = $_GET['id'] ?? 0;

try {
    // ดึงข้อมูลแบบประเมิน
    $stmt = $db->prepare("
        SELECT e.*, ep.period_name, ep.year, ep.semester,
               pt.type_name_th as type_name
        FROM evaluations e
        JOIN evaluation_periods ep ON e.period_id = ep.period_id
        LEFT JOIN personnel_types pt ON e.personnel_type_id = pt.personnel_type_id
        WHERE e.evaluation_id = ? AND e.user_id = ?
    ");
    $stmt->execute([$evaluation_id, $user_id]);
    $evaluation = $stmt->fetch();

    // ตรวจสอบว่ามีแบบประเมินและแก้ไขได้หรือไม่
    if (!$evaluation) {
        flash_error('ไม่พบแบบประเมินที่ต้องการแก้ไข');
        redirect('modules/evaluation/list.php');
        exit;
    }

    if (!in_array($evaluation['status'], ['draft', 'returned'])) {
        flash_error('ไม่สามารถแก้ไขแบบประเมินในสถานะนี้ได้');
        redirect('modules/evaluation/view.php?id=' . $evaluation_id);
        exit;
    }

    // ดึงด้านการประเมินและหัวข้อ
    $stmt = $db->query("
        SELECT ea.*,
               GROUP_CONCAT(
                   CONCAT(et.topic_id, ':', et.topic_name_th, ':', et.max_score)
                   ORDER BY et.display_order
                   SEPARATOR '||'
               ) as topics
        FROM evaluation_aspects ea
        LEFT JOIN evaluation_topics et ON ea.aspect_id = et.aspect_id AND et.is_active = 1
        WHERE ea.is_active = 1
        GROUP BY ea.aspect_id
        ORDER BY ea.display_order
    ");
    $aspects = $stmt->fetchAll();

    // ✅ แก้ไข: ดึงข้อมูลที่บันทึกไว้แล้ว (ผ่าน evaluation_details และ evaluation_portfolios)
    $stmt = $db->prepare("
        SELECT
            ed.detail_id,
            ed.aspect_id,
            ed.topic_id,
            ed.score,
            ed.self_assessment,
            ed.evidence_description,
            ed.notes,

            -- ข้อมูล portfolio (ผ่าน evaluation_portfolios)
            ep.link_id,
            p.portfolio_id,
            p.title as portfolio_title,
            p.file_name,
            p.file_path

        FROM evaluation_details ed

        -- JOIN กับ evaluation_portfolios
        LEFT JOIN evaluation_portfolios ep ON ed.detail_id = ep.detail_id

        -- JOIN กับ portfolios
        LEFT JOIN portfolios p ON ep.portfolio_id = p.portfolio_id

        WHERE ed.evaluation_id = ?
        ORDER BY ed.aspect_id, ed.topic_id
    ");
    $stmt->execute([$evaluation_id]);
    $saved_details = [];
    foreach ($stmt->fetchAll() as $detail) {
        $key = $detail['aspect_id'] . '_' . $detail['topic_id'];
        $saved_details[$key] = $detail;
    }

    // ✅ เพิ่ม: ดึงข้อมูล evaluation_scores (ถ้ามี)
    $stmt = $db->prepare("
        SELECT
            es.score_id,
            es.aspect_id,
            es.topic_id,
            es.score,
            es.weighted_score,
            es.evidence,
            es.notes
        FROM evaluation_scores es
        WHERE es.evaluation_id = ?
    ");
    $stmt->execute([$evaluation_id]);
    $saved_scores = [];
    foreach ($stmt->fetchAll() as $score) {
        $key = $score['aspect_id'] . '_' . $score['topic_id'];
        $saved_scores[$key] = $score;
    }

    // ดึงคลังผลงานของผู้ใช้
    $stmt = $db->prepare("
        SELECT p.*, ea.aspect_name_th,
               p.max_usage_count - p.current_usage_count as remaining_uses
        FROM portfolios p
        JOIN evaluation_aspects ea ON p.aspect_id = ea.aspect_id
        WHERE p.user_id = ?
        ORDER BY ea.display_order, p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $portfolios = [];
    foreach ($stmt->fetchAll() as $portfolio) {
        $portfolios[$portfolio['aspect_id']][] = $portfolio;
    }

    // ดึงรายชื่อผู้บริหารที่สามารถเลือกได้ (role = 'manager' เท่านั้น)
    $stmt = $db->prepare("
        SELECT user_id, username, full_name_th
        FROM users
        WHERE role = 'manager'
        AND user_id != ?
        AND is_active = 1
        ORDER BY full_name_th ASC
    ");
    $stmt->execute([$user_id]);
    $available_managers = $stmt->fetchAll();

    // ดึงผู้บริหารที่เลือกไว้แล้วสำหรับแบบประเมินนี้
    $stmt = $db->prepare("
        SELECT em.*, u.full_name_th
        FROM evaluation_managers em
        JOIN users u ON em.manager_user_id = u.user_id
        WHERE em.evaluation_id = ?
        ORDER BY em.selection_order ASC
    ");
    $stmt->execute([$evaluation_id]);
    $selected_managers = $stmt->fetchAll();

    // บันทึกข้อมูล
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'])) {
        $db->beginTransaction();

        try {
            $topics = $_POST['topics'] ?? [];
            $scores = $_POST['scores'] ?? [];
            $evidences = $_POST['evidences'] ?? [];
            $portfolio_ids = $_POST['portfolio_ids'] ?? [];
            $notes = $_POST['notes'] ?? [];
            $evaluators = $_POST['evaluators'] ?? [];

            // ✅ ลบข้อมูลเก่าทั้งหมด
            // 1. ลบ evaluation_managers ก่อน
            $stmt = $db->prepare("DELETE FROM evaluation_managers WHERE evaluation_id = ?");
            $stmt->execute([$evaluation_id]);

            // 2. ลบ evaluation_portfolios (เพราะมี foreign key)
            $stmt = $db->prepare("
                DELETE ep FROM evaluation_portfolios ep
                INNER JOIN evaluation_details ed ON ep.detail_id = ed.detail_id
                WHERE ed.evaluation_id = ?
            ");
            $stmt->execute([$evaluation_id]);

            // 3. ลบ evaluation_details
            $stmt = $db->prepare("DELETE FROM evaluation_details WHERE evaluation_id = ?");
            $stmt->execute([$evaluation_id]);

            // 4. ลบ evaluation_scores
            $stmt = $db->prepare("DELETE FROM evaluation_scores WHERE evaluation_id = ?");
            $stmt->execute([$evaluation_id]);

            // ✅ เตรียม statements สำหรับบันทึกข้อมูลใหม่
            $stmt_detail = $db->prepare("
                INSERT INTO evaluation_details
                (evaluation_id, aspect_id, topic_id, score, self_assessment, evidence_description, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt_score = $db->prepare("
                INSERT INTO evaluation_scores
                (evaluation_id, aspect_id, topic_id, score, weighted_score, evidence, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt_portfolio = $db->prepare("
                INSERT INTO evaluation_portfolios
                (evaluation_id, detail_id, portfolio_id, is_claimed, claimed_at)
                VALUES (?, ?, ?, 1, NOW())
            ");

            $stmt_evaluator = $db->prepare("
                INSERT INTO evaluation_managers
                (evaluation_id, manager_user_id, selection_order, status)
                VALUES (?, ?, ?, 'pending')
            ");

            $total_score = 0;

            // ✅ บันทึกข้อมูลใหม่
            foreach ($topics as $topic_data) {
                list($aspect_id, $topic_id) = explode('_', $topic_data);
                $score = floatval($scores[$topic_data] ?? 0);
                $evidence = $evidences[$topic_data] ?? '';
                $note = $notes[$topic_data] ?? '';
                $portfolio_id = !empty($portfolio_ids[$topic_data]) ? intval($portfolio_ids[$topic_data]) : null;

                // คำนวณคะแนนถ่วงน้ำหนัก
                $weighted_score = $score;

                // 1. บันทึกลง evaluation_details
                $stmt_detail->execute([
                    $evaluation_id,
                    $aspect_id,
                    $topic_id,
                    $score,
                    $evidence, // self_assessment
                    $evidence, // evidence_description
                    $note
                ]);
                $detail_id = $db->lastInsertId();

                // 2. บันทึกลง evaluation_scores
                $stmt_score->execute([
                    $evaluation_id,
                    $aspect_id,
                    $topic_id,
                    $score,
                    $weighted_score,
                    $evidence,
                    $note
                ]);

                // 3. ถ้ามีการเลือก portfolio ให้บันทึกลง evaluation_portfolios
                if ($portfolio_id) {
                    $stmt_portfolio->execute([
                        $evaluation_id,
                        $detail_id,
                        $portfolio_id
                    ]);
                }

                $total_score += $score;
            }

            // ✅ บันทึกผู้บริหารที่เลือก
            foreach ($evaluators as $order => $manager_id) {
                if (!empty($manager_id)) {
                    $stmt_evaluator->execute([
                        $evaluation_id,
                        intval($manager_id),
                        intval($order) + 1
                    ]);
                }
            }

            // อัปเดตคะแนนรวม
            $stmt = $db->prepare("
                UPDATE evaluations
                SET total_score = ?, updated_at = NOW()
                WHERE evaluation_id = ?
            ");
            $stmt->execute([$total_score, $evaluation_id]);

            // Log activity
            if (function_exists('log_activity')) {
                log_activity('update_evaluation', 'evaluations', $evaluation_id, [
                    'total_score' => $total_score,
                    'topics_count' => count($topics)
                ]);
            }

            $db->commit();

            flash_success('บันทึกการแก้ไขแบบประเมินเรียบร้อยแล้ว');
            redirect('modules/evaluation/view.php?id=' . $evaluation_id);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Edit Evaluation Error: ' . $e->getMessage());
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

$page_title = 'แก้ไขแบบประเมิน';
include APP_ROOT . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">แก้ไขแบบประเมินผลการปฏิบัติงาน</h1>
                <p class="mt-1 text-gray-600">
                    <?php echo e($evaluation['period_name']); ?>
                    (<?php echo ($evaluation['year'] + 543); ?>
                    <?php if ($evaluation['semester']): ?>/<?php echo $evaluation['semester']; ?><?php endif; ?>)
                </p>
            </div>
            <a href="<?php echo url('modules/evaluation/view.php?id=' . $evaluation_id); ?>"
                class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2 text-black"></i>ย้อนกลับ
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Status Alert -->
    <?php if ($evaluation['status'] === 'returned'): ?>
        <div class="mb-6 bg-orange-50 border-l-4 border-orange-400 p-4 rounded-r">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-orange-400 text-xl mr-3"></i>
                <div class="flex-1">
                    <p class="text-sm font-medium text-orange-800">แบบประเมินถูกส่งกลับเพื่อแก้ไข</p>
                    <?php if (!empty($evaluation['remarks'])): ?>
                        <p class="mt-2 text-sm text-orange-700"><?php echo nl2br(e($evaluation['remarks'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Evaluation Form -->
    <form method="POST" id="evaluationForm" class="space-y-6">
        <?php echo csrf_field(); ?>

        <!-- Evaluators Card -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">ผู้ประเมิน</h3>
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <input type="text" id="evaluatorSearch" placeholder="ค้นหาชื่อผู้ประเมิน..."
                                   class="border rounded px-3 py-2 text-sm w-64"
                                   onkeyup="filterEvaluators()">
                            <div id="evaluatorDropdown" class="absolute top-full left-0 right-0 bg-white border rounded mt-1 max-h-40 overflow-y-auto z-50 hidden">
                                <?php foreach ($available_managers as $manager): ?>
                                    <?php
                                    $is_selected = false;
                                    foreach ($selected_managers as $selected) {
                                        if ($selected['manager_user_id'] == $manager['user_id']) {
                                            $is_selected = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <?php if (!$is_selected): ?>
                                        <div class="evaluator-option px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm"
                                             data-value="<?php echo $manager['user_id']; ?>"
                                             data-name="<?php echo e($manager['full_name_th']); ?>"
                                             onclick="selectEvaluator(<?php echo $manager['user_id']; ?>, '<?php echo e($manager['full_name_th']); ?>')">
                                            <?php echo e($manager['full_name_th']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="button" onclick="addEvaluator()"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                            <i class="fas fa-plus mr-1"></i>เพิ่ม
                        </button>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div id="selectedEvaluators" class="space-y-2">
                    <?php if (empty($selected_managers)): ?>
                        <p class="text-gray-500 text-sm">ยังไม่ได้เลือกผู้ประเมิน</p>
                    <?php else: ?>
                        <?php foreach ($selected_managers as $index => $manager): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                                <div class="flex items-center space-x-3">
                                    <span class="bg-blue-100 text-blue-800 text-sm font-medium px-2 py-1 rounded">
                                        <?php echo $manager['selection_order']; ?>
                                    </span>
                                    <span class="font-medium"><?php echo e($manager['full_name_th']); ?></span>
                                </div>
                                <button type="button" onclick="removeEvaluator(<?php echo $manager['manager_user_id']; ?>)"
                                        class="text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash"></i> ลบ
                                </button>
                                <input type="hidden" name="evaluators[<?php echo $index; ?>]"
                                       value="<?php echo $manager['manager_user_id']; ?>" id="evaluator_<?php echo $manager['manager_user_id']; ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <?php foreach ($aspects as $aspect): ?>
            <?php
            $topics = [];
            if ($aspect['topics']) {
                foreach (explode('||', $aspect['topics']) as $topic_str) {
                    $parts = explode(':', $topic_str);
                    if (count($parts) >= 3) {
                        $topics[] = [
                            'topic_id' => $parts[0],
                            'topic_name' => $parts[1],
                            'max_score' => $parts[2]
                        ];
                    }
                }
            }
            ?>

            <!-- Aspect Card -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                <?php echo e($aspect['aspect_name_th']); ?>
                            </h3>
                            <?php if (!empty($aspect['description'])): ?>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php echo e($aspect['description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            น้ำหนัก <?php echo number_format((float)$aspect['weight_percentage'], 2); ?>%
                        </span>
                    </div>
                </div>

                <div class="p-6">
                    <div class="space-y-6">
                        <?php foreach ($topics as $topic): ?>
                            <?php
                            $topic_key = $aspect['aspect_id'] . '_' . $topic['topic_id'];

                            // ดึงข้อมูลที่บันทึกไว้ (ลองจาก saved_details ก่อน แล้วค่อย saved_scores)
                            $saved = $saved_details[$topic_key] ?? $saved_scores[$topic_key] ?? null;
                            $aspect_portfolios = $portfolios[$aspect['aspect_id']] ?? [];
                            ?>
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <input type="hidden" name="topics[]" value="<?php echo $topic_key; ?>">

                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900">
                                            <?php echo e($topic['topic_name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-500 mt-1">
                                            คะแนนเต็ม: <?php echo number_format((float)$topic['max_score'], 2); ?> คะแนน
                                        </p>
                                    </div>

                                    <?php if ($saved && !empty($saved['portfolio_title'])): ?>
                                        <span class="px-3 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                            <i class="fas fa-file-alt mr-1"></i>มีผลงาน
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- คะแนน -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">คะแนนที่ได้ *</label>
                                        <input type="number" name="scores[<?php echo $topic_key; ?>]"
                                            class="w-full border rounded px-3 py-2" min="0"
                                            max="<?php echo $topic['max_score']; ?>" step="0.01"
                                            value="<?php echo $saved ? $saved['score'] : ''; ?>" required>
                                    </div>

                                    <!-- เลือกผลงานจากคลัง -->
                                    <?php if (!empty($aspect_portfolios)): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                เลือกผลงานจากคลัง
                                                <?php if ($saved && !empty($saved['portfolio_id'])): ?>
                                                    <span class="text-xs text-green-600">(เลือกแล้ว)</span>
                                                <?php endif; ?>
                                            </label>
                                            <select name="portfolio_ids[<?php echo $topic_key; ?>]"
                                                class="w-full border rounded px-3 py-2">
                                                <option value="">-- ไม่เลือก --</option>
                                                <?php foreach ($aspect_portfolios as $portfolio): ?>
                                                    <?php
                                                    $is_selected = ($saved && isset($saved['portfolio_id']) && $saved['portfolio_id'] == $portfolio['portfolio_id']);
                                                    $is_available = $portfolio['remaining_uses'] > 0 || $is_selected;
                                                    ?>
                                                    <option value="<?php echo $portfolio['portfolio_id']; ?>"
                                                        <?php echo $is_selected ? 'selected' : ''; ?>
                                                        <?php echo !$is_available ? 'disabled' : ''; ?>>
                                                        <?php echo e($portfolio['title']); ?>
                                                        <?php if ($is_available): ?>
                                                            (ใช้ได้อีก <?php echo $portfolio['remaining_uses']; ?> ครั้ง)
                                                        <?php else: ?>
                                                            (ใช้งานครบแล้ว)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <?php if ($saved && !empty($saved['portfolio_title'])): ?>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <i class="fas fa-info-circle"></i>
                                                    ปัจจุบัน: <?php echo e($saved['portfolio_title']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- หลักฐาน -->
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">หลักฐานประกอบ</label>
                                    <textarea name="evidences[<?php echo $topic_key; ?>]"
                                        class="w-full border rounded px-3 py-2" rows="2"
                                        placeholder="ระบุหลักฐานหรือรายละเอียดเพิ่มเติม"><?php
                                                                                            echo $saved ? e($saved['evidence_description'] ?? $saved['evidence'] ?? '') : '';
                                                                                            ?></textarea>
                                </div>

                                <!-- บันทึกเพิ่มเติม -->
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">บันทึกเพิ่มเติม</label>
                                    <textarea name="notes[<?php echo $topic_key; ?>]" class="w-full border rounded px-3 py-2"
                                        rows="2" placeholder="บันทึกหรือคำอธิบายเพิ่มเติม"><?php
                                                                                            echo $saved ? e($saved['notes'] ?? '') : '';
                                                                                            ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <a href="<?php echo url('modules/evaluation/view.php?id=' . $evaluation_id); ?>"
                    class="bg-white border border-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-50">
                    ยกเลิก
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                    <i class="fas fa-save mr-2"></i>บันทึกการแก้ไข
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    // Evaluators functionality
    let evaluatorCount = <?php echo count($selected_managers); ?>;
    let selectedEvaluatorId = null;
    let selectedEvaluatorName = null;

    // Show/hide dropdown when clicking on search input
    document.getElementById('evaluatorSearch').addEventListener('focus', function() {
        document.getElementById('evaluatorDropdown').classList.remove('hidden');
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const searchContainer = document.getElementById('evaluatorSearch').parentElement;
        if (!searchContainer.contains(event.target)) {
            document.getElementById('evaluatorDropdown').classList.add('hidden');
        }
    });

    // Filter evaluators based on search input
    function filterEvaluators() {
        const searchValue = document.getElementById('evaluatorSearch').value.toLowerCase();
        const dropdown = document.getElementById('evaluatorDropdown');
        const options = dropdown.querySelectorAll('.evaluator-option');

        let hasVisibleOptions = false;

        // Remove any existing "not found" message
        const existingNotFoundMsg = dropdown.querySelector('.not-found-message');
        if (existingNotFoundMsg) {
            existingNotFoundMsg.remove();
        }

        options.forEach(option => {
            const name = option.textContent.toLowerCase();
            if (name.includes(searchValue)) {
                option.style.display = 'block';
                hasVisibleOptions = true;
            } else {
                option.style.display = 'none';
            }
        });

        // Show "not found" message if no options match and search is not empty
        if (searchValue && !hasVisibleOptions) {
            const notFoundDiv = document.createElement('div');
            notFoundDiv.className = 'px-3 py-2 text-gray-500 text-sm italic not-found-message';
            notFoundDiv.textContent = 'ไม่พบผู้ประเมินที่ค้นหา';
            dropdown.appendChild(notFoundDiv);
        }
    }

    // Select an evaluator from dropdown
    function selectEvaluator(managerId, managerName) {
        selectedEvaluatorId = managerId;
        selectedEvaluatorName = managerName;
        document.getElementById('evaluatorSearch').value = managerName;
        document.getElementById('evaluatorDropdown').classList.add('hidden');
    }

    function addEvaluator() {
        if (!selectedEvaluatorId) {
            alert('กรุณาเลือกผู้ประเมินจากรายการค้นหา');
            return;
        }

        const managerId = selectedEvaluatorId;
        const managerName = selectedEvaluatorName;

        // Check if already added
        if (document.getElementById('evaluator_' + managerId)) {
            alert('ผู้ประเมินนี้ถูกเลือกแล้ว');
            return;
        }

        // Create new evaluator element
        const evaluatorDiv = document.createElement('div');
        evaluatorDiv.className = 'flex items-center justify-between p-3 bg-gray-50 rounded-lg border';
        evaluatorDiv.innerHTML = `
            <div class="flex items-center space-x-3">
                <span class="bg-blue-100 text-blue-800 text-sm font-medium px-2 py-1 rounded">
                    ${evaluatorCount + 1}
                </span>
                <span class="font-medium">${managerName}</span>
            </div>
            <button type="button" onclick="removeEvaluator(${managerId})"
                    class="text-red-600 hover:text-red-800 text-sm">
                <i class="fas fa-trash"></i> ลบ
            </button>
            <input type="hidden" name="evaluators[${evaluatorCount}]"
                   value="${managerId}" id="evaluator_${managerId}">
        `;

        // Add to the container
        const container = document.getElementById('selectedEvaluators');

        // Remove "no evaluators" message if exists
        const noEvaluatorsMsg = container.querySelector('.text-gray-500');
        if (noEvaluatorsMsg) {
            noEvaluatorsMsg.remove();
        }

        container.appendChild(evaluatorDiv);

        // Remove from dropdown
        const optionToRemove = document.querySelector(`[data-value="${managerId}"]`);
        if (optionToRemove) {
            optionToRemove.remove();
        }

        // Clear search and reset selection
        document.getElementById('evaluatorSearch').value = '';
        selectedEvaluatorId = null;
        selectedEvaluatorName = null;

        evaluatorCount++;
    }

    function removeEvaluator(managerId) {
        const element = document.getElementById('evaluator_' + managerId);
        if (element) {
            const evaluatorDiv = element.parentElement;
            // Get all font-medium elements and get the second one (the manager name)
            const fontMediumElements = evaluatorDiv.querySelectorAll('.font-medium');
            const managerName = fontMediumElements.length > 1 ? fontMediumElements[1].textContent : evaluatorDiv.querySelector('.font-medium').textContent;

            // Remove from selected list
            evaluatorDiv.remove();

            // Add back to dropdown
            const dropdown = document.getElementById('evaluatorDropdown');
            const optionDiv = document.createElement('div');
            optionDiv.className = 'evaluator-option px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm';
            optionDiv.setAttribute('data-value', managerId);
            optionDiv.setAttribute('data-name', managerName);
            optionDiv.onclick = function() {
                selectEvaluator(managerId, managerName);
            };
            optionDiv.textContent = managerName;
            dropdown.appendChild(optionDiv);

            // Reorder remaining evaluators
            const evaluators = document.querySelectorAll('#selectedEvaluators > div');
            evaluators.forEach((evaluator, index) => {
                const orderSpan = evaluator.querySelector('.bg-blue-100');
                if (orderSpan) {
                    orderSpan.textContent = index + 1;
                }
                const hiddenInput = evaluator.querySelector('input[type="hidden"]');
                if (hiddenInput) {
                    const currentName = hiddenInput.name;
                    hiddenInput.name = currentName.replace(/\[\d+\]/, `[${index}]`);
                }
            });

            evaluatorCount = evaluators.length;

            // Add "no evaluators" message if empty
            if (evaluatorCount === 0) {
                document.getElementById('selectedEvaluators').innerHTML =
                    '<p class="text-gray-500 text-sm">ยังไม่ได้เลือกผู้ประเมิน</p>';
            }
        }
    }

    // Auto-calculate total score
    document.querySelectorAll('input[name^="scores"]').forEach(input => {
        input.addEventListener('input', function() {
            const max = parseFloat(this.getAttribute('max'));
            const value = parseFloat(this.value);

            if (value > max) {
                this.value = max;
                alert('คะแนนไม่สามารถเกิน ' + max + ' คะแนนได้');
            }
        });
    });

    // Confirm before submit
    document.getElementById('evaluationForm').addEventListener('submit', function(e) {
        if (!confirm('คุณแน่ใจหรือไม่ว่าต้องการบันทึกการแก้ไขแบบประเมิน?')) {
            e.preventDefault();
        }
    });
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
