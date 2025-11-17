/**
 * Main JavaScript for YRU Performance Evaluation System
 * ไฟล์ JavaScript หลักสำหรับระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา
 */

(function () {
  "use strict";

  // ==================== Toast Notification System ====================

  window.showToast = function (message, type = "info", duration = 3000) {
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
            <div class="flex items-center">
                <div class="mr-3">
                    ${getToastIcon(type)}
                </div>
                <div class="flex-1">
                    <p class="font-medium">${message}</p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = "slideInRight 0.3s ease-out reverse";
      setTimeout(() => toast.remove(), 300);
    }, duration);
  };

  function getToastIcon(type) {
    const icons = {
      success:
        '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
      error:
        '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
      warning:
        '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
      info: '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
    };
    return icons[type] || icons.info;
  }

  // ==================== Confirm Dialog ====================

  window.confirmAction = function (message, callback) {
    if (confirm(message)) {
      callback();
    }
  };

  // ==================== Form Validation ====================

  window.validateForm = function (formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    let isValid = true;
    const requiredFields = form.querySelectorAll("[required]");

    requiredFields.forEach((field) => {
      if (!field.value.trim()) {
        isValid = false;
        field.classList.add("border-red-500");

        let errorMsg = field.parentElement.querySelector(".error-message");
        if (!errorMsg) {
          errorMsg = document.createElement("p");
          errorMsg.className = "error-message text-red-500 text-sm mt-1";
          errorMsg.textContent = "กรุณากรอกข้อมูลนี้";
          field.parentElement.appendChild(errorMsg);
        }
      } else {
        field.classList.remove("border-red-500");
        const errorMsg = field.parentElement.querySelector(".error-message");
        if (errorMsg) errorMsg.remove();
      }
    });

    return isValid;
  };

  // ==================== File Upload Preview ====================

  window.previewFile = function (input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;

    if (input.files && input.files[0]) {
      const file = input.files[0];
      const reader = new FileReader();

      // Check file size (10MB max)
      if (file.size > 10 * 1024 * 1024) {
        showToast("ไฟล์มีขนาดใหญ่เกิน 10 MB", "error");
        input.value = "";
        return;
      }

      reader.onload = function (e) {
        if (file.type.startsWith("image/")) {
          preview.innerHTML = `<img src="${e.target.result}" class="max-w-full h-auto rounded" alt="Preview">`;
        } else {
          preview.innerHTML = `
                        <div class="flex items-center space-x-3 p-4 bg-gray-50 rounded">
                            <svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="font-medium">${file.name}</p>
                                <p class="text-sm text-gray-500">${formatFileSize(
                                  file.size
                                )}</p>
                            </div>
                        </div>
                    `;
        }
      };

      reader.readAsDataURL(file);
    }
  };

  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
  }

  // ==================== Loading Overlay ====================

  window.showLoading = function () {
    const overlay = document.createElement("div");
    overlay.className = "loading-overlay";
    overlay.id = "loadingOverlay";
    overlay.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(overlay);
  };

  window.hideLoading = function () {
    const overlay = document.getElementById("loadingOverlay");
    if (overlay) overlay.remove();
  };

  // ==================== AJAX Helper ====================

  window.ajaxRequest = function (
    url,
    method = "GET",
    data = null,
    callback = null
  ) {
    showLoading();

    const options = {
      method: method,
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    };

    if (data && method !== "GET") {
      options.body = JSON.stringify(data);
    }

    fetch(url, options)
      .then((response) => response.json())
      .then((data) => {
        hideLoading();
        if (callback) callback(data);
      })
      .catch((error) => {
        hideLoading();
        showToast("เกิดข้อผิดพลาด: " + error.message, "error");
        console.error("Error:", error);
      });
  };

  // ==================== Search/Filter Table ====================

  window.searchTable = function (inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);

    if (!input || !table) return;

    input.addEventListener("keyup", function () {
      const filter = this.value.toLowerCase();
      const rows = table.getElementsByTagName("tr");

      for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName("td");
        let found = false;

        for (let j = 0; j < cells.length; j++) {
          const cell = cells[j];
          if (cell.textContent.toLowerCase().indexOf(filter) > -1) {
            found = true;
            break;
          }
        }

        row.style.display = found ? "" : "none";
      }
    });
  };

  // ==================== Auto-save Form ====================

  window.enableAutoSave = function (formId, saveUrl, interval = 30000) {
    const form = document.getElementById(formId);
    if (!form) return;

    let autoSaveTimer;

    const saveFormData = () => {
      const formData = new FormData(form);
      const data = {};
      formData.forEach((value, key) => {
        data[key] = value;
      });

      ajaxRequest(saveUrl, "POST", data, (response) => {
        if (response.success) {
          console.log("Auto-saved at", new Date().toLocaleTimeString("th-TH"));
        }
      });
    };

    form.addEventListener("input", () => {
      clearTimeout(autoSaveTimer);
      autoSaveTimer = setTimeout(saveFormData, interval);
    });
  };

  // ==================== Copy to Clipboard ====================

  window.copyToClipboard = function (text) {
    navigator.clipboard
      .writeText(text)
      .then(() => {
        showToast("คัดลอกแล้ว", "success", 2000);
      })
      .catch((err) => {
        showToast("ไม่สามารถคัดลอกได้", "error");
        console.error("Error:", err);
      });
  };

  // ==================== Print Page ====================

  window.printPage = function () {
    window.print();
  };

  // ==================== Initialize on Page Load ====================

  document.addEventListener("DOMContentLoaded", function () {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll(".alert-auto-hide");
    alerts.forEach((alert) => {
      setTimeout(() => {
        alert.style.opacity = "0";
        setTimeout(() => alert.remove(), 300);
      }, 5000);
    });

    // Add smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener("click", function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute("href"));
        if (target) {
          target.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        }
      });
    });

    // Confirm before delete
    document.querySelectorAll(".confirm-delete").forEach((button) => {
      button.addEventListener("click", function (e) {
        if (!confirm("คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้?")) {
          e.preventDefault();
        }
      });
    });

    // Initialize tooltips if any
    const tooltips = document.querySelectorAll("[data-tooltip]");
    tooltips.forEach((element) => {
      element.addEventListener("mouseenter", function () {
        const tooltip = document.createElement("div");
        tooltip.className =
          "absolute bg-gray-900 text-white text-sm px-2 py-1 rounded shadow-lg z-50";
        tooltip.textContent = this.getAttribute("data-tooltip");
        tooltip.style.top = this.offsetTop - 30 + "px";
        tooltip.style.left = this.offsetLeft + "px";
        this.appendChild(tooltip);
      });

      element.addEventListener("mouseleave", function () {
        const tooltip = this.querySelector(".absolute");
        if (tooltip) tooltip.remove();
      });
    });

    console.log("YRU Performance Evaluation System initialized successfully");
  });
})();
