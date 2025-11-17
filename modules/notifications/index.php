<?php

/**
 * modules/notifications/index.php
 * หน้าจัดการการแจ้งเตือน
 */

session_start();
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/permission.php';

// ตรวจสอบ authentication
requireAuth();

$page_title = 'การแจ้งเตือน';
$current_user = $_SESSION['user'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDB();

    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$current_user['user_id']]);
        $_SESSION['success'] = 'ทำเครื่องหมายทั้งหมดว่าอ่านแล้ว';
        redirect('modules/notifications/index.php');
    }

    if ($_POST['action'] === 'delete_all_read') {
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
        $stmt->execute([$current_user['user_id']]);
        $_SESSION['success'] = 'ลบการแจ้งเตือนที่อ่านแล้วทั้งหมด';
        redirect('modules/notifications/index.php');
    }
}

// Fetch notifications
try {
    $db = getDB();

    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Filter
    $filter = $_GET['filter'] ?? 'all'; // all, unread, read

    $where = "WHERE user_id = ?";
    $params = [$current_user['user_id']];

    if ($filter === 'unread') {
        $where .= " AND is_read = 0";
    } elseif ($filter === 'read') {
        $where .= " AND is_read = 1";
    }

    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications $where");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $limit);

    // Get notifications
    $stmt = $db->prepare("
        SELECT *
        FROM notifications
        $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([...$params, $limit, $offset]);
    $notifications = $stmt->fetchAll();

    // Count unread
    $unread_stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_stmt->execute([$current_user['user_id']]);
    $unread_count = $unread_stmt->fetch()['unread'];
} catch (PDOException $e) {
    error_log($e->getMessage());
    $notifications = [];
    $total = 0;
    $total_pages = 0;
    $unread_count = 0;
}

include APP_ROOT . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">การแจ้งเตือน</h1>
                <p class="text-gray-600 mt-2">จัดการและดูการแจ้งเตือนทั้งหมดของคุณ</p>
            </div>
            <div class="flex items-center space-x-3">
                <?php if ($current_user['role'] === 'admin'): ?>
                    <a href="<?php echo APP_URL; ?>/modules/notifications/create.php"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        สร้างการแจ้งเตือน
                    </a>
                <?php endif; ?>
                <div class="flex items-center space-x-3">
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn-secondary">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                ทำเครื่องหมายทั้งหมดว่าอ่านแล้ว
                            </button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" class="inline"
                        onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบการแจ้งเตือนที่อ่านแล้วทั้งหมด?');">
                        <input type="hidden" name="action" value="delete_all_read">
                        <button type="submit" class="btn-danger">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            ลบที่อ่านแล้ว
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">ทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">ยังไม่อ่าน</p>
                        <p class="text-3xl font-bold text-orange-600"><?php echo number_format($unread_count); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">อ่านแล้ว</p>
                        <p class="text-3xl font-bold text-green-600">
                            <?php echo number_format($total - $unread_count); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex">
                    <a href="?filter=all"
                        class="<?php echo $filter === 'all' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                        ทั้งหมด (<?php echo number_format($total); ?>)
                    </a>
                    <a href="?filter=unread"
                        class="<?php echo $filter === 'unread' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                        ยังไม่อ่าน (<?php echo number_format($unread_count); ?>)
                    </a>
                    <a href="?filter=read"
                        class="<?php echo $filter === 'read' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                        อ่านแล้ว (<?php echo number_format($total - $unread_count); ?>)
                    </a>
                </nav>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="bg-white rounded-lg shadow">
            <?php if (empty($notifications)): ?>
                <div class="p-12 text-center">
                    <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">ไม่มีการแจ้งเตือน</h3>
                    <p class="text-gray-500">คุณไม่มีการแจ้งเตือนในขณะนี้</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $is_unread = $notification['is_read'] == 0;
                        $type_icons = [
                            'general' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                            'evaluation' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                            'reminder' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                            'broadcast' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
                        ];
                        $icon = $type_icons[$notification['type']] ?? $type_icons['general'];
                        ?>
                        <div class="notification-item p-6 hover:bg-gray-50 transition-colors <?php echo $is_unread ? 'bg-blue-50' : ''; ?>"
                            data-id="<?php echo $notification['notification_id']; ?>">
                            <div class="flex items-start space-x-4">
                                <!-- Icon -->
                                <div class="flex-shrink-0">
                                    <div
                                        class="w-12 h-12 <?php echo $is_unread ? 'bg-blue-100' : 'bg-gray-100'; ?> rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 <?php echo $is_unread ? 'text-blue-600' : 'text-gray-600'; ?>"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="<?php echo $icon; ?>" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h3
                                                class="text-base font-semibold text-gray-900 <?php echo $is_unread ? 'font-bold' : ''; ?>">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php if ($is_unread): ?>
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                                                        ใหม่
                                                    </span>
                                                <?php endif; ?>
                                            </h3>
                                            <p class="mt-1 text-sm text-gray-600">
                                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                            </p>
                                            <div class="mt-2 flex items-center text-xs text-gray-500">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <?php
                                                $date = new DateTime($notification['created_at']);
                                                echo $date->format('d/m/Y H:i');
                                                ?>
                                                <span class="mx-2">•</span>
                                                <span class="capitalize"><?php echo $notification['type']; ?></span>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex items-center space-x-2 ml-4">
                                            <?php if ($is_unread): ?>
                                                <button onclick="markAsRead(<?php echo $notification['notification_id']; ?>)"
                                                    class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                    title="ทำเครื่องหมายว่าอ่านแล้ว">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            <?php endif; ?>

                                            <button
                                                onclick="deleteNotification(<?php echo $notification['notification_id']; ?>)"
                                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="ลบ">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                แสดง <?php echo ($offset + 1); ?> ถึง <?php echo min($offset + $limit, $total); ?> จาก
                                <?php echo number_format($total); ?> รายการ
                            </div>

                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        ก่อนหน้า
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"
                                        class="px-4 py-2 border rounded-lg text-sm font-medium <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        ถัดไป
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mark notification as read
        async function markAsRead(notificationId) {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/notifications.php?action=mark-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        notification_id: notificationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
        }

        // Delete notification
        async function deleteNotification(notificationId) {
            if (!confirm('คุณแน่ใจหรือไม่ที่จะลบการแจ้งเตือนนี้?')) {
                return;
            }

            try {
                const response = await fetch(
                    `<?php echo APP_URL; ?>/api/notifications.php?action=delete&id=${notificationId}`, {
                        method: 'DELETE'
                    });

                const data = await response.json();

                if (data.success) {
                    // Remove item from DOM
                    const item = document.querySelector(`[data-id="${notificationId}"]`);
                    if (item) {
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 300);
                    }

                    showSuccess('ลบการแจ้งเตือนสำเร็จ');

                    // Reload after 1 second
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('เกิดข้อผิดพลาด: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
        }
    </script>

    <style>
        .btn-secondary {
            @apply inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover: bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors;
        }

        .btn-danger {
            @apply inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-red-600 hover: bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors;
        }

        .notification-item {
            transition: all 0.3s ease;
        }
    </style>

    <?php include APP_ROOT . '/includes/footer.php'; ?>