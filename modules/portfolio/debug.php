<?php

/**
 * modules/portfolio/debug.php
 * ตรวจสอบปัญหาไฟล์
 */

define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/permission.php';

requireAuth();

$db = getDB();
$portfolio_id = $_GET['id'] ?? 0;

$debug_info = [];
$debug_info['portfolio_id'] = $portfolio_id;
$debug_info['user_id'] = $_SESSION['user']['user_id'];
$debug_info['app_root'] = APP_ROOT;
$debug_info['upload_path'] = UPLOAD_PATH;

// ดึงข้อมูลผลงาน
$stmt = $db->prepare("SELECT * FROM portfolios WHERE portfolio_id = ?");
$stmt->execute([$portfolio_id]);
$portfolio = $stmt->fetch();

$debug_info['portfolio'] = $portfolio;

if ($portfolio) {
    $file_path = UPLOAD_PATH . $portfolio['file_path'];
    $debug_info['full_file_path'] = $file_path;
    $debug_info['file_exists'] = file_exists($file_path);

    if (file_exists($file_path)) {
        $debug_info['file_readable'] = is_readable($file_path);
        $debug_info['file_size_actual'] = filesize($file_path);
        $debug_info['file_permissions'] = substr(sprintf('%o', fileperms($file_path)), -4);
    }

    $file_ext = strtolower(pathinfo($portfolio['file_name'], PATHINFO_EXTENSION));
    $debug_info['file_extension'] = $file_ext;
    $debug_info['can_preview'] = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Portfolio File</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        .info-block {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }

        .info-block h3 {
            margin-top: 0;
            color: #007bff;
        }

        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }

        .success {
            color: #28a745;
            font-weight: bold;
        }

        .error {
            color: #dc3545;
            font-weight: bold;
        }

        .warning {
            color: #ffc107;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        table td:first-child {
            font-weight: bold;
            width: 200px;
            background: #f8f9fa;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
        }

        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔍 Debug Portfolio File</h1>

        <div class="info-block">
            <h3>ข้อมูลพื้นฐาน</h3>
            <table>
                <tr>
                    <td>Portfolio ID</td>
                    <td><?php echo $portfolio_id; ?></td>
                </tr>
                <tr>
                    <td>User ID</td>
                    <td><?php echo $debug_info['user_id']; ?></td>
                </tr>
                <tr>
                    <td>APP_ROOT</td>
                    <td><?php echo $debug_info['app_root']; ?></td>
                </tr>
                <tr>
                    <td>UPLOAD_PATH</td>
                    <td><?php echo $debug_info['upload_path']; ?></td>
                </tr>
            </table>
        </div>

        <?php if ($portfolio): ?>
            <div class="info-block">
                <h3>ข้อมูลผลงาน</h3>
                <table>
                    <tr>
                        <td>ชื่อผลงาน</td>
                        <td><?php echo e($portfolio['title']); ?></td>
                    </tr>
                    <tr>
                        <td>ชื่อไฟล์</td>
                        <td><?php echo e($portfolio['file_name']); ?></td>
                    </tr>
                    <tr>
                        <td>File Path (DB)</td>
                        <td><?php echo e($portfolio['file_path']); ?></td>
                    </tr>
                    <tr>
                        <td>File Path (Full)</td>
                        <td><?php echo $debug_info['full_file_path']; ?></td>
                    </tr>
                    <tr>
                        <td>นามสกุลไฟล์</td>
                        <td><?php echo $debug_info['file_extension']; ?></td>
                    </tr>
                    <tr>
                        <td>ขนาดไฟล์ (DB)</td>
                        <td><?php echo format_bytes($portfolio['file_size']); ?></td>
                    </tr>
                </table>
            </div>

            <div class="info-block">
                <h3>สถานะไฟล์</h3>
                <table>
                    <tr>
                        <td>ไฟล์มีอยู่จริง</td>
                        <td>
                            <?php if ($debug_info['file_exists']): ?>
                                <span class="success">✅ มี</span>
                            <?php else: ?>
                                <span class="error">❌ ไม่มี</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($debug_info['file_exists']): ?>
                        <tr>
                            <td>อ่านไฟล์ได้</td>
                            <td>
                                <?php if ($debug_info['file_readable']): ?>
                                    <span class="success">✅ อ่านได้</span>
                                <?php else: ?>
                                    <span class="error">❌ อ่านไม่ได้</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>ขนาดไฟล์จริง</td>
                            <td><?php echo format_bytes($debug_info['file_size_actual']); ?></td>
                        </tr>
                        <tr>
                            <td>Permissions</td>
                            <td><?php echo $debug_info['file_permissions']; ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>สามารถ Preview ได้</td>
                        <td>
                            <?php if ($debug_info['can_preview']): ?>
                                <span class="success">✅ ได้</span>
                            <?php else: ?>
                                <span class="warning">⚠️ ไม่ได้ (ประเภทไฟล์ไม่รองรับ)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="info-block">
                <h3>การทดสอบ</h3>
                <?php if ($debug_info['file_exists'] && $debug_info['can_preview']): ?>
                    <a href="view-file.php?id=<?php echo $portfolio_id; ?>" target="_blank" class="btn">
                        🔍 ทดสอบดูไฟล์
                    </a>
                    <a href="download.php?id=<?php echo $portfolio_id; ?>" class="btn">
                        📥 ทดสอบดาวน์โหลด
                    </a>
                <?php else: ?>
                    <p class="error">❌ ไม่สามารถทดสอบได้เนื่องจากไฟล์มีปัญหา</p>
                <?php endif; ?>
            </div>

            <div class="info-block">
                <h3>ข้อมูล Debug แบบเต็ม</h3>
                <pre><?php echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            </div>

        <?php else: ?>
            <div class="info-block">
                <h3 class="error">❌ ไม่พบข้อมูลผลงาน</h3>
                <p>Portfolio ID: <?php echo $portfolio_id; ?> ไม่มีในระบบ</p>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="index.php" class="btn">← กลับไปรายการผลงาน</a>
        </div>
    </div>
</body>

</html>