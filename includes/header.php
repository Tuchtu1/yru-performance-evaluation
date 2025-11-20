<?php
//includes/header.php
if (!defined('APP_ROOT')) {
    require_once '../config/app.php';
}

$current_user = $_SESSION['user'] ?? null;
$page_title = $page_title ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>

    <!-- Font Thai -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- ⭐ Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Sarabun', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Custom CSS Files -->
    <link rel="stylesheet" href="<?php echo url('assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/custom.css'); ?>">

    <!-- 🎨 Modal & Component Styles -->
    <style>
        /* ==================== Base Styles ==================== */
        body {
            font-family: 'Sarabun', sans-serif;
        }

        /* ==================== Modal Styles ==================== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex !important;
        }

        .modal-content {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
            position: relative;
        }

        /* Modal Sizes */
        .modal-content.max-w-sm {
            max-width: 384px;
        }

        .modal-content.max-w-md {
            max-width: 448px;
        }

        .modal-content.max-w-lg {
            max-width: 512px;
        }

        .modal-content.max-w-xl {
            max-width: 576px;
        }

        .modal-content.max-w-2xl {
            max-width: 672px;
        }

        .modal-content.max-w-3xl {
            max-width: 768px;
        }

        .modal-content.max-w-4xl {
            max-width: 896px;
        }

        /* Modal Header */
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
        }

        .modal-header button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
            color: #6b7280;
        }

        .modal-header button:hover {
            background-color: #e5e7eb;
            color: #374151;
        }

        /* Modal Body */
        .modal-body {
            padding: 1.5rem;
            max-height: calc(90vh - 180px);
            overflow-y: auto;
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            background-color: #f9fafb;
            border-bottom-left-radius: 0.75rem;
            border-bottom-right-radius: 0.75rem;
        }

        /* Modal Animation */
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Modal Scrollbar */
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }

        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f9fafb;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        /* Prevent body scroll when modal is open */
        body.modal-open {
            overflow: hidden;
        }

        /* ==================== Form Styles ==================== */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-checkbox {
            width: 1.125rem;
            height: 1.125rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .form-checkbox:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }

        /* ==================== Button Styles ==================== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2563eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-outline {
            background-color: white;
            color: #374151;
            border-color: #d1d5db;
        }

        .btn-outline:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
        }

        .btn-danger {
            background-color: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }

        .btn-icon {
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }

        .btn-icon:hover {
            background-color: #f3f4f6;
        }

        /* ==================== Utility Classes ==================== */
        .space-y-4>*+* {
            margin-top: 1rem;
        }

        .gap-4 {
            gap: 1rem;
        }

        .grid {
            display: grid;
        }

        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        /* ==================== Responsive Design ==================== */
        @media (max-width: 640px) {
            .modal-content {
                width: 95%;
                max-height: 95vh;
                border-radius: 0.5rem;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1rem;
            }

            .modal-header h3 {
                font-size: 1rem;
            }

            .grid-cols-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="bg-gray-50">

    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo & App Name -->
                <div class="flex items-center">
                    <a href="<?php echo url(); ?>" class="flex items-center space-x-3">
                        <div
                            class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="hidden md:block">
                            <div class="text-sm font-semibold text-gray-900">ระบบประเมินผลการปฏิบัติงาน</div>
                            <div class="text-xs text-gray-500">มหาวิทยาลัยราชภัฏยะลา</div>
                        </div>
                    </a>
                </div>

                <!-- User Menu -->
                <?php if ($current_user): ?>
                    <div class="flex items-center space-x-4">

                        <!-- 🔔 Notification Dropdown -->
                        <div class="relative">
                            <button id="notification-button" type="button"
                                class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span id="notification-badge"
                                    class="hidden absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full">0</span>
                            </button>

                            <div id="notification-dropdown"
                                class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-[600px] flex flex-col">
                                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                                    <h3 class="text-lg font-semibold text-gray-900">การแจ้งเตือน</h3>
                                    <button onclick="notificationManager.markAllAsRead()"
                                        class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                        ทำเครื่องหมายทั้งหมดว่าอ่านแล้ว
                                    </button>
                                </div>

                                <div id="notification-list" class="flex-1 overflow-y-auto">
                                    <div class="p-8 text-center">
                                        <div
                                            class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-3">
                                        </div>
                                        <p class="text-gray-500 text-sm">กำลังโหลด...</p>
                                    </div>
                                </div>

                                <div class="p-3 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                                    <a href="<?php echo url('modules/notifications/index.php'); ?>"
                                        class="block text-center text-sm text-blue-600 hover:text-blue-700 font-medium py-2">
                                        ดูการแจ้งเตือนทั้งหมด →
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- User Profile Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open"
                                class="flex items-center space-x-3 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                <div
                                    class="w-9 h-9 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white font-medium">
                                    <?php echo mb_substr($current_user['full_name_th'], 0, 2); ?>
                                </div>
                                <div class="hidden md:block text-left">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $current_user['full_name_th']; ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo $role_names[$current_user['role']] ?? ''; ?></div>
                                </div>
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div x-show="open" @click.away="open = false"
                                class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1"
                                style="display: none;">
                                <a href="<?php echo url('modules/users/profile.php'); ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    โปรไฟล์ของฉัน
                                </a>
                                <a href="<?php echo url('modules/users/change-password.php'); ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                    เปลี่ยนรหัสผ่าน
                                </a>
                                <hr class="my-1">
                                <a href="<?php echo url('modules/auth/logout.php'); ?>"
                                    class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="min-h-screen">
        <?php if ($current_user): ?>
            <div class="flex">
                <!-- Sidebar -->
                <?php include 'sidebar.php'; ?>

                <!-- Main Content -->
                <main class="flex-1 p-6">
                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded-r">
                            <div class="flex">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-green-700"><?php echo $_SESSION['success'];
                                                                    unset($_SESSION['success']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-r">
                            <div class="flex">
                                <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd" />
                                </svg>
                                <p class="text-sm text-red-700"><?php echo $_SESSION['error'];
                                                                unset($_SESSION['error']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <main class="container mx-auto px-4 py-8">
                    <?php endif; ?>

                    <!-- Alpine.js -->
                    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

                    <?php if (isset($current_user) && $current_user): ?>
                        <!-- API Configuration -->
                        <script>
                            window.APP_CONFIG = {
                                baseUrl: '<?php echo APP_URL; ?>',
                                apiUrl: '<?php echo APP_URL; ?>/api/notifications.php',
                                assetsUrl: '<?php echo APP_URL; ?>/assets'
                            };
                            console.log('📡 APP_CONFIG Loaded:', window.APP_CONFIG);
                            Object.freeze(window.APP_CONFIG);
                        </script>

                        <!-- Load notification.js -->
                        <script src="<?php echo asset('js/notification.js'); ?>"></script>

                        <!-- Verify notification system -->
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                if (!window.notificationManager) {
                                    console.error('❌ Notification Manager failed to load!');
                                } else {
                                    console.log('✅ Notification Manager ready');
                                }
                            });
                        </script>
                    <?php endif; ?>
