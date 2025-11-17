/**
 * Form Validation System
 * ระบบตรวจสอบความถูกต้องของฟอร์ม สำหรับระบบประเมินผลการปฏิบัติงาน มหาวิทยาลัยราชภัฏยะลา
 */

(function () {
  "use strict";

  // ==================== Configuration ====================
  const config = {
    errorClass: "form-input-error",
    errorMessageClass: "form-error",
    successClass: "form-input-success",
    realTimeValidation: true,
    scrollToError: true,
    scrollOffset: 100,
  };

  // ==================== Validation Rules ====================
  const validationRules = {
    required: (value) => {
      if (typeof value === "string") {
        return value.trim().length > 0;
      }
      return value !== null && value !== undefined && value !== "";
    },

    email: (value) => {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(value);
    },

    minLength: (value, min) => {
      return value.length >= min;
    },

    maxLength: (value, max) => {
      return value.length <= max;
    },

    min: (value, min) => {
      const num = parseFloat(value);
      return !isNaN(num) && num >= min;
    },

    max: (value, max) => {
      const num = parseFloat(value);
      return !isNaN(num) && num <= max;
    },

    pattern: (value, pattern) => {
      const regex = new RegExp(pattern);
      return regex.test(value);
    },

    numeric: (value) => {
      return /^\d+$/.test(value);
    },

    alpha: (value) => {
      return /^[a-zA-Zก-๙\s]+$/.test(value);
    },

    alphanumeric: (value) => {
      return /^[a-zA-Z0-9ก-๙\s]+$/.test(value);
    },

    url: (value) => {
      try {
        new URL(value);
        return true;
      } catch {
        return false;
      }
    },

    phone: (value) => {
      // Thai phone number format
      return /^(\+66|66|0)[0-9]{8,9}$/.test(value.replace(/[\s-]/g, ""));
    },

    idcard: (value) => {
      // Thai ID card number
      const cleaned = value.replace(/[\s-]/g, "");
      if (!/^\d{13}$/.test(cleaned)) return false;

      let sum = 0;
      for (let i = 0; i < 12; i++) {
        sum += parseInt(cleaned.charAt(i)) * (13 - i);
      }
      const checkDigit = (11 - (sum % 11)) % 10;
      return checkDigit === parseInt(cleaned.charAt(12));
    },

    password: (value) => {
      // อย่างน้อย 6 ตัวอักษร
      return value.length >= 6;
    },

    passwordStrong: (value) => {
      // อย่างน้อย 8 ตัวอักษร, มีตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก, ตัวเลข
      return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(value);
    },

    match: (value, matchField) => {
      const matchValue = document.querySelector(
        `[name="${matchField}"]`
      )?.value;
      return value === matchValue;
    },

    file: (input, options = {}) => {
      if (!input.files || input.files.length === 0) {
        return !options.required;
      }

      const file = input.files[0];

      // Check file size
      if (options.maxSize && file.size > options.maxSize) {
        return false;
      }

      // Check file type
      if (options.accept) {
        const acceptedTypes = options.accept.split(",").map((t) => t.trim());
        const fileExt = "." + file.name.split(".").pop().toLowerCase();
        const fileType = file.type;

        const isAccepted = acceptedTypes.some((type) => {
          if (type.startsWith(".")) {
            return fileExt === type;
          }
          return fileType.match(type.replace("*", ".*"));
        });

        if (!isAccepted) {
          return false;
        }
      }

      return true;
    },

    date: (value) => {
      const date = new Date(value);
      return !isNaN(date.getTime());
    },

    dateMin: (value, minDate) => {
      const date = new Date(value);
      const min = new Date(minDate);
      return date >= min;
    },

    dateMax: (value, maxDate) => {
      const date = new Date(value);
      const max = new Date(maxDate);
      return date <= max;
    },

    custom: (value, validatorFn) => {
      return validatorFn(value);
    },
  };

  // ==================== Error Messages ====================
  const errorMessages = {
    required: "กรุณากรอกข้อมูลนี้",
    email: "กรุณากรอกอีเมลที่ถูกต้อง",
    minLength: "ต้องมีอย่างน้อย {min} ตัวอักษร",
    maxLength: "ต้องไม่เกิน {max} ตัวอักษร",
    min: "ต้องมากกว่าหรือเท่ากับ {min}",
    max: "ต้องน้อยกว่าหรือเท่ากับ {max}",
    pattern: "รูปแบบไม่ถูกต้อง",
    numeric: "ต้องเป็นตัวเลขเท่านั้น",
    alpha: "ต้องเป็นตัวอักษรเท่านั้น",
    alphanumeric: "ต้องเป็นตัวอักษรและตัวเลขเท่านั้น",
    url: "กรุณากรอก URL ที่ถูกต้อง",
    phone: "กรุณากรอกเบอร์โทรศัพท์ที่ถูกต้อง",
    idcard: "กรุณากรอกเลขบัตรประชาชนที่ถูกต้อง",
    password: "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร",
    passwordStrong:
      "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร ประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก และตัวเลข",
    match: "ข้อมูลไม่ตรงกัน",
    file: "ไฟล์ไม่ถูกต้อง",
    fileSize: "ไฟล์มีขนาดใหญ่เกินกำหนด",
    fileType: "ประเภทไฟล์ไม่ถูกต้อง",
    date: "กรุณากรอกวันที่ที่ถูกต้อง",
    dateMin: "วันที่ต้องไม่ก่อน {min}",
    dateMax: "วันที่ต้องไม่หลัง {max}",
  };

  // ==================== Validator Class ====================

  class FormValidator {
    constructor(formId, options = {}) {
      this.form =
        typeof formId === "string" ? document.getElementById(formId) : formId;
      this.options = { ...config, ...options };
      this.rules = {};
      this.customMessages = {};

      if (this.form) {
        this.init();
      }
    }

    init() {
      // Prevent default form submission
      this.form.addEventListener("submit", (e) => {
        if (!this.validateAll()) {
          e.preventDefault();

          if (this.options.scrollToError) {
            this.scrollToFirstError();
          }
        }
      });

      // Real-time validation
      if (this.options.realTimeValidation) {
        this.setupRealTimeValidation();
      }
    }

    setupRealTimeValidation() {
      const inputs = this.form.querySelectorAll("input, select, textarea");

      inputs.forEach((input) => {
        // Validate on blur
        input.addEventListener("blur", () => {
          if (input.value || input.hasAttribute("data-validated")) {
            this.validateField(input);
            input.setAttribute("data-validated", "true");
          }
        });

        // Validate on input (for already validated fields)
        input.addEventListener("input", () => {
          if (input.hasAttribute("data-validated")) {
            this.validateField(input);
          }
        });
      });
    }

    addRule(fieldName, rule, params = null, customMessage = null) {
      if (!this.rules[fieldName]) {
        this.rules[fieldName] = [];
      }

      this.rules[fieldName].push({ rule, params, customMessage });
      return this;
    }

    addRules(fieldName, rules) {
      Object.entries(rules).forEach(([rule, params]) => {
        if (typeof params === "object" && params.message) {
          this.addRule(fieldName, rule, params.value, params.message);
        } else {
          this.addRule(fieldName, rule, params);
        }
      });
      return this;
    }

    setCustomMessage(fieldName, rule, message) {
      if (!this.customMessages[fieldName]) {
        this.customMessages[fieldName] = {};
      }
      this.customMessages[fieldName][rule] = message;
      return this;
    }

    validateField(input) {
      const fieldName = input.name;
      const value = input.value;
      const rules = this.rules[fieldName] || [];

      // Clear previous errors
      this.clearFieldError(input);

      // Check each rule
      for (const { rule, params, customMessage } of rules) {
        let isValid = false;

        if (typeof validationRules[rule] === "function") {
          if (input.type === "file") {
            isValid = validationRules[rule](input, params);
          } else if (params !== null && params !== undefined) {
            isValid = validationRules[rule](value, params);
          } else {
            isValid = validationRules[rule](value);
          }
        } else {
          console.warn(`Validation rule "${rule}" not found`);
          continue;
        }

        if (!isValid) {
          const message =
            customMessage ||
            this.customMessages[fieldName]?.[rule] ||
            this.getErrorMessage(rule, params);
          this.showFieldError(input, message);
          return false;
        }
      }

      this.showFieldSuccess(input);
      return true;
    }

    validateAll() {
      let isValid = true;
      const inputs = this.form.querySelectorAll("input, select, textarea");

      inputs.forEach((input) => {
        if (input.name && this.rules[input.name]) {
          if (!this.validateField(input)) {
            isValid = false;
          }
        }
      });

      return isValid;
    }

    showFieldError(input, message) {
      input.classList.add(this.options.errorClass);
      input.classList.remove(this.options.successClass);

      const errorElement = this.getOrCreateErrorElement(input);
      errorElement.textContent = message;
      errorElement.style.display = "block";
    }

    showFieldSuccess(input) {
      input.classList.remove(this.options.errorClass);
      input.classList.add(this.options.successClass);

      const errorElement = this.getOrCreateErrorElement(input);
      errorElement.style.display = "none";
    }

    clearFieldError(input) {
      input.classList.remove(this.options.errorClass);
      input.classList.remove(this.options.successClass);

      const errorElement = this.getOrCreateErrorElement(input);
      errorElement.style.display = "none";
    }

    getOrCreateErrorElement(input) {
      const errorId = `${input.name}-error`;
      let errorElement = document.getElementById(errorId);

      if (!errorElement) {
        errorElement = document.createElement("p");
        errorElement.id = errorId;
        errorElement.className = this.options.errorMessageClass;
        errorElement.style.display = "none";

        const parent = input.parentElement;
        parent.appendChild(errorElement);
      }

      return errorElement;
    }

    getErrorMessage(rule, params) {
      let message = errorMessages[rule] || "ข้อมูลไม่ถูกต้อง";

      if (params && typeof params === "object") {
        Object.entries(params).forEach(([key, value]) => {
          message = message.replace(`{${key}}`, value);
        });
      } else if (params !== null && params !== undefined) {
        message = message.replace("{min}", params).replace("{max}", params);
      }

      return message;
    }

    scrollToFirstError() {
      const firstError = this.form.querySelector(`.${this.options.errorClass}`);
      if (firstError) {
        const top =
          firstError.getBoundingClientRect().top +
          window.pageYOffset -
          this.options.scrollOffset;
        window.scrollTo({ top, behavior: "smooth" });
        firstError.focus();
      }
    }

    reset() {
      const inputs = this.form.querySelectorAll("input, select, textarea");
      inputs.forEach((input) => {
        this.clearFieldError(input);
        input.removeAttribute("data-validated");
      });
    }
  }

  // ==================== Quick Validation Functions ====================

  const Validator = {
    // Quick field validation
    validate(input, rules) {
      const validator = new FormValidator(input.form);
      validator.addRules(input.name, rules);
      return validator.validateField(input);
    },

    // Validate single value
    check(value, rule, params = null) {
      if (validationRules[rule]) {
        return validationRules[rule](value, params);
      }
      return false;
    },

    // Add custom rule
    addRule(name, validator, message) {
      validationRules[name] = validator;
      errorMessages[name] = message;
    },

    // Thai-specific validators
    thai: {
      idCard: (value) => validationRules.idcard(value),
      phone: (value) => validationRules.phone(value),
      name: (value) => /^[ก-๙\s]+$/.test(value),
      address: (value) => /^[ก-๙0-9\s\/\-.,]+$/.test(value),
    },

    // File validators
    file: {
      image: (input) => {
        return validationRules.file(input, {
          accept: "image/*",
          maxSize: 5 * 1024 * 1024, // 5MB
        });
      },

      document: (input) => {
        return validationRules.file(input, {
          accept: ".pdf,.doc,.docx",
          maxSize: 10 * 1024 * 1024, // 10MB
        });
      },

      excel: (input) => {
        return validationRules.file(input, {
          accept: ".xls,.xlsx",
          maxSize: 10 * 1024 * 1024,
        });
      },
    },
  };

  // ==================== Helper Functions ====================

  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
  }

  // ==================== Auto-init forms with data attributes ====================

  function autoInitForms() {
    document.querySelectorAll("[data-validate]").forEach((form) => {
      const validator = new FormValidator(form);

      // Parse validation rules from data attributes
      form.querySelectorAll("[data-rules]").forEach((input) => {
        try {
          const rules = JSON.parse(input.dataset.rules);
          validator.addRules(input.name, rules);
        } catch (e) {
          console.error("Error parsing validation rules:", e);
        }
      });
    });
  }

  // Initialize on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoInitForms);
  } else {
    autoInitForms();
  }

  // ==================== Export to global scope ====================

  window.FormValidator = FormValidator;
  window.Validator = Validator;

  // Backward compatibility
  window.validateForm = function (formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const validator = new FormValidator(form);
    return validator.validateAll();
  };
})();
