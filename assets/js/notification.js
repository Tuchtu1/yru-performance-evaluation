/**
 * Notification Management System
 * assets/js/notification.js
 * ระบบจัดการการแจ้งเตือน สำหรับระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา
 */

(function () {
  ("use strict");

  // ==================== Configuration ====================
  const config = {
    // ใช้ API URL จาก PHP config
    apiUrl:
      window.APP_CONFIG?.apiUrl || getBaseUrl() + "/api/notifications.php",
    refreshInterval: 30000, // 30 วินาที
    maxNotifications: 10,
    autoHideDuration: 5000, // 5 วินาที
  };

  console.log("🔧 Notification Config:", {
    apiUrl: config.apiUrl,
    hasAPP_CONFIG: !!window.APP_CONFIG,
    APP_CONFIG_value: window.APP_CONFIG,
  });

  // ==================== State Management ====================
  const state = {
    notifications: [],
    unreadCount: 0,
    isDropdownOpen: false,
    intervalId: null,
  };

  // ==================== Toast Notification System ====================

  class ToastNotification {
    constructor() {
      this.container = this.createContainer();
    }

    createContainer() {
      let container = document.getElementById("toast-container");
      if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        container.className = "fixed top-4 right-4 z-50 space-y-3";
        document.body.appendChild(container);
      }
      return container;
    }

    show(message, type = "info", duration = config.autoHideDuration) {
      const toast = this.createToast(message, type);
      this.container.appendChild(toast);

      // Trigger animation
      setTimeout(() => toast.classList.add("show"), 10);

      // Auto hide
      setTimeout(() => {
        this.hide(toast);
      }, duration);

      return toast;
    }

    createToast(message, type) {
      const toast = document.createElement("div");
      toast.className = `toast toast-${type} transform translate-x-full transition-all duration-300`;

      const colors = {
        success: "bg-green-50 border-green-500 text-green-800",
        error: "bg-red-50 border-red-500 text-red-800",
        warning: "bg-yellow-50 border-yellow-500 text-yellow-800",
        info: "bg-blue-50 border-blue-500 text-blue-800",
      };

      const icons = {
        success: `<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>`,
        error: `<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>`,
        warning: `<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>`,
        info: `<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>`,
      };

      toast.innerHTML = `
                <div class="flex items-start space-x-3 p-4 rounded-lg shadow-lg border-l-4 ${colors[type]} min-w-[320px] max-w-md">
                    <div class="flex-shrink-0">
                        ${icons[type]}
                    </div>
                    <div class="flex-1">
                        <p class="font-medium">${message}</p>
                    </div>
                    <button class="flex-shrink-0 hover:opacity-70 transition-opacity" onclick="this.closest('.toast').remove()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            `;

      return toast;
    }

    hide(toast) {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }

    success(message, duration) {
      return this.show(message, "success", duration);
    }

    error(message, duration) {
      return this.show(message, "error", duration);
    }

    warning(message, duration) {
      return this.show(message, "warning", duration);
    }

    info(message, duration) {
      return this.show(message, "info", duration);
    }
  }

  // Create global toast instance
  window.toast = new ToastNotification();

  // ==================== Notification Dropdown ====================

  class NotificationDropdown {
    constructor(buttonId, dropdownId, badgeId) {
      this.button = document.getElementById(buttonId);
      this.dropdown = document.getElementById(dropdownId);
      this.badge = document.getElementById(badgeId);

      if (this.button && this.dropdown) {
        this.init();
      }
    }

    init() {
      // Toggle dropdown
      this.button.addEventListener("click", (e) => {
        e.stopPropagation();
        this.toggle();
      });

      // Close on outside click
      document.addEventListener("click", (e) => {
        if (
          !this.dropdown.contains(e.target) &&
          !this.button.contains(e.target)
        ) {
          this.close();
        }
      });

      // Prevent dropdown close on internal clicks
      this.dropdown.addEventListener("click", (e) => {
        e.stopPropagation();
      });
    }

    toggle() {
      if (state.isDropdownOpen) {
        this.close();
      } else {
        this.open();
      }
    }

    open() {
      this.dropdown.classList.remove("hidden");
      this.dropdown.classList.add("animate-fade-in");
      state.isDropdownOpen = true;
      this.loadNotifications();
    }

    close() {
      this.dropdown.classList.add("hidden");
      state.isDropdownOpen = false;
    }

    updateBadge(count) {
      if (this.badge) {
        if (count > 0) {
          this.badge.textContent = count > 99 ? "99+" : count;
          this.badge.classList.remove("hidden");
        } else {
          this.badge.classList.add("hidden");
        }
      }
    }

    async loadNotifications() {
      try {
        const response = await fetch(
          `${config.apiUrl}?action=recent&limit=${config.maxNotifications}`
        );

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
          state.notifications = data.data.notifications;
          state.unreadCount = data.data.unread_count;
          this.render(data.data.notifications);
          this.updateBadge(data.data.unread_count);
        } else {
          console.error("API Error:", data.message);
        }
      } catch (error) {
        console.error("Error loading notifications:", error);
        toast.error("ไม่สามารถโหลดการแจ้งเตือนได้");
      }
    }

    render(notifications) {
      const container = this.dropdown.querySelector("#notification-list");
      if (!container) return;

      if (notifications.length === 0) {
        container.innerHTML = `
                    <div class="p-8 text-center">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <p class="text-gray-500 text-sm">ไม่มีการแจ้งเตือน</p>
                    </div>
                `;
        return;
      }

      container.innerHTML = notifications
        .map((notif) => this.createNotificationItem(notif))
        .join("");
    }

    createNotificationItem(notification) {
      const isUnread = notification.is_read == 0;
      const timeAgo = this.formatTimeAgo(notification.created_at);

      return `
                <div class="notification-item ${
                  isUnread ? "bg-blue-50" : "bg-white"
                } hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0"
                     data-id="${notification.notification_id}">
                    <div class="p-4">
                        <div class="flex items-start space-x-3">
                            ${
                              isUnread
                                ? '<div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>'
                                : '<div class="w-2"></div>'
                            }
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">${
                                  notification.title
                                }</p>
                                <p class="text-sm text-gray-600 mt-1">${
                                  notification.message
                                }</p>
                                <p class="text-xs text-gray-500 mt-2">${timeAgo}</p>
                            </div>
                            <button onclick="notificationManager.markAsRead(${
                              notification.notification_id
                            })" 
                                    class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;
    }

    formatTimeAgo(dateString) {
      const date = new Date(dateString);
      const now = new Date();
      const seconds = Math.floor((now - date) / 1000);

      const intervals = {
        ปี: 31536000,
        เดือน: 2592000,
        วัน: 86400,
        ชั่วโมง: 3600,
        นาที: 60,
        วินาที: 1,
      };

      for (const [name, value] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / value);
        if (interval >= 1) {
          return `${interval} ${name}ที่แล้ว`;
        }
      }

      return "เมื่อสักครู่";
    }
  }

  // ==================== Notification Manager ====================

  class NotificationManager {
    constructor() {
      this.dropdown = null;
      this.init();
    }

    init() {
      // Initialize dropdown
      this.dropdown = new NotificationDropdown(
        "notification-button",
        "notification-dropdown",
        "notification-badge"
      );

      // Initial load
      this.refreshUnreadCount();

      // Start auto-refresh
      this.startAutoRefresh();

      // Listen for visibility change
      document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
          this.refreshUnreadCount();
        }
      });
    }

    startAutoRefresh() {
      if (state.intervalId) {
        clearInterval(state.intervalId);
      }

      state.intervalId = setInterval(() => {
        this.refreshUnreadCount();
      }, config.refreshInterval);
    }

    stopAutoRefresh() {
      if (state.intervalId) {
        clearInterval(state.intervalId);
        state.intervalId = null;
      }
    }

    async refreshUnreadCount() {
      try {
        const response = await fetch(`${config.apiUrl}?action=unread-count`);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          state.unreadCount = data.data.count;
          if (this.dropdown) {
            this.dropdown.updateBadge(data.data.count);
          }
        }
      } catch (error) {
        console.error("Error refreshing unread count:", error);
      }
    }

    async markAsRead(notificationId) {
      try {
        const response = await fetch(`${config.apiUrl}?action=mark-read`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ notification_id: notificationId }),
        });

        const data = await response.json();

        if (data.success) {
          // Update UI
          const item = document.querySelector(`[data-id="${notificationId}"]`);
          if (item) {
            item.remove();
          }

          state.unreadCount = Math.max(0, state.unreadCount - 1);
          this.dropdown.updateBadge(state.unreadCount);

          // Reload if dropdown is open
          if (state.isDropdownOpen) {
            this.dropdown.loadNotifications();
          }
        }
      } catch (error) {
        console.error("Error marking notification as read:", error);
        toast.error("เกิดข้อผิดพลาดในการอัพเดทการแจ้งเตือน");
      }
    }

    async markAllAsRead() {
      try {
        const response = await fetch(`${config.apiUrl}?action=mark-all-read`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
        });

        const data = await response.json();

        if (data.success) {
          state.unreadCount = 0;
          this.dropdown.updateBadge(0);

          if (state.isDropdownOpen) {
            this.dropdown.loadNotifications();
          }

          toast.success(data.message);
        }
      } catch (error) {
        console.error("Error marking all as read:", error);
        toast.error("เกิดข้อผิดพลาด");
      }
    }

    async deleteNotification(notificationId) {
      try {
        const response = await fetch(
          `${config.apiUrl}?action=delete&id=${notificationId}`,
          {
            method: "DELETE",
          }
        );

        const data = await response.json();

        if (data.success) {
          const item = document.querySelector(`[data-id="${notificationId}"]`);
          if (item) {
            item.remove();
          }

          if (state.isDropdownOpen) {
            this.dropdown.loadNotifications();
          }

          toast.success("ลบการแจ้งเตือนสำเร็จ");
        }
      } catch (error) {
        console.error("Error deleting notification:", error);
        toast.error("เกิดข้อผิดพลาด");
      }
    }

    // Public method to show notification from other parts of the app
    notify(title, message, type = "info") {
      toast.show(`<strong>${title}</strong><br>${message}`, type);
      this.refreshUnreadCount();
    }
  }

  // ==================== Initialize ====================

  // Wait for DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initNotifications);
  } else {
    initNotifications();
  }

  function initNotifications() {
    window.notificationManager = new NotificationManager();
    console.log("✅ Notification Manager initialized");
    console.log("📡 API URL:", config.apiUrl);
  }

  // ==================== Global Helper Functions ====================

  // Expose toast methods globally for backward compatibility
  window.showToast = (message, type = "info", duration) => {
    return toast.show(message, type, duration);
  };

  window.showSuccess = (message, duration) => {
    return toast.success(message, duration);
  };

  window.showError = (message, duration) => {
    return toast.error(message, duration);
  };

  window.showWarning = (message, duration) => {
    return toast.warning(message, duration);
  };

  window.showInfo = (message, duration) => {
    return toast.info(message, duration);
  };

  // Add CSS for toast animations
  const style = document.createElement("style");
  style.textContent = `
    .toast.show {
      transform: translateX(0) !important;
    }
    .animate-fade-in {
      animation: fadeIn 0.2s ease-out;
    }
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  `;
  document.head.appendChild(style);
})();
