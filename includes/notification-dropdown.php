<!-- Notification Dropdown Component -->
<!-- ใส่ใน Header/Navbar -->

<div class="relative">
    <!-- Notification Button -->
    <button id="notification-button" type="button"
        class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
        <!-- Bell Icon -->
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        <!-- Unread Badge -->
        <span id="notification-badge"
            class="hidden absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full">
            0
        </span>
    </button>

    <!-- Dropdown Panel -->
    <div id="notification-dropdown"
        class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-[600px] flex flex-col">

        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">การแจ้งเตือน</h3>
            <button onclick="notificationManager.markAllAsRead()"
                class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                ทำเครื่องหมายทั้งหมดว่าอ่านแล้ว
            </button>
        </div>

        <!-- Notification List -->
        <div id="notification-list" class="flex-1 overflow-y-auto">
            <!-- Notifications will be loaded here by JavaScript -->
            <div class="p-8 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-3"></div>
                <p class="text-gray-500 text-sm">กำลังโหลด...</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-3 border-t border-gray-200 bg-gray-50 rounded-b-lg">
            <a href="<?php echo url('modules/notifications/index.php'); ?>"
                class="block text-center text-sm text-blue-600 hover:text-blue-700 font-medium py-2">
                ดูการแจ้งเตือนทั้งหมด →
            </a>
        </div>
    </div>
</div>

<!-- Load Notification JS -->
<script src="<?php echo asset('js/notification.js'); ?>"></script>