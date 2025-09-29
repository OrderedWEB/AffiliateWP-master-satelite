/**
 * ACI Admin JavaScript
 *
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/aci-admin.js
 * Author: Richard King, starneconsulting.com
 * Email: r.king@starneconsulting.com
 *
 * Handles admin interface functionality including settings management,
 * connection testing, code validation, and analytics display.
 * Renamed from affci-admin.js to aci-admin.js.
 */

(function ($) {
  "use strict";

  /**
   * ACI Admin Controller
   */
  const ACI_Admin = {
    // Configuration from localized script
    config: window.AFFCIAdmin || window.aciAdmin || {},

    // State management
    state: {
      connectionTested: false,
      lastTestResult: null,
      saving: false,
      syncing: false,
    },

    /**
     * Initialize admin functionality
     */
    init: function () {
      console.log("ACI Admin: Initializing...");

      this.bindEvents();
      this.initializeComponents();
      this.checkInitialState();

      console.log("ACI Admin: Initialized successfully");
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      const self = this;

      // Connection testing
      $("#aci-test-connection, .aci-test-connection").on("click", function (e) {
        e.preventDefault();
        self.testConnection();
      });

      // Settings form submission
      $("#aci-settings-form").on("submit", function (e) {
        if (!self.validateForm()) {
          e.preventDefault();
        }
      });

      // Sync operations
      $("#aci-sync-codes, #aci-sync-now").on("click", function (e) {
        e.preventDefault();
        self.syncAffiliateData();
      });

      // Cache operations
      $("#aci-clear-cache").on("click", function (e) {
        e.preventDefault();
        self.clearCache();
      });

      // Export operations
      $("#aci-export-settings").on("click", function (e) {
        e.preventDefault();
        self.exportSettings();
      });

      // Import operations
      $("#aci-import-settings").on("change", function (e) {
        self.importSettings(e.target.files[0]);
      });

      // Log operations
      $("#aci-view-logs").on("click", function (e) {
        e.preventDefault();
        self.viewLogs();
      });

      $("#aci-download-logs").on("click", function (e) {
        e.preventDefault();
        self.downloadLogs();
      });

      $("#aci-clear-logs").on("click", function (e) {
        e.preventDefault();
        if (confirm(self.config.i18n?.confirm || "Are you sure?")) {
          self.clearLogs();
        }
      });

      // Code testing
      $("#aci-test-code-form").on("submit", function (e) {
        e.preventDefault();
        const code = $("#aci-test-code-input").val().trim();
        if (code) {
          self.testAffiliateCode(code);
        }
      });

      // Modal operations
      $(".aci-modal-close, .aci-modal-overlay").on("click", function (e) {
        if (e.target === this) {
          self.closeModal();
        }
      });

      // Keyboard shortcuts
      $(document).on("keydown", function (e) {
        self.handleKeydown(e);
      });

      // Input validation
      $("#aci_master_domain").on("blur", function () {
        self.validateMasterDomain($(this));
      });

      $("#aci_api_key").on("blur", function () {
        self.validateApiKey($(this));
      });

      // API key visibility toggle
      $(".aci-toggle-api-key").on("click", function () {
        self.toggleApiKeyVisibility();
      });

      // Copy to clipboard
      $(".aci-copy-button").on("click", function () {
        const target = $(this).data("copy-target");
        self.copyToClipboard(target);
      });

      // Tab navigation
      $(".aci-tab-button").on("click", function () {
        const tabId = $(this).data("tab");
        self.switchTab(tabId);
      });

      // Regenerate API key
      $("#aci-regenerate-api-key").on("click", function (e) {
        e.preventDefault();
        if (confirm("This will invalidate the current API key. Continue?")) {
          self.regenerateApiKey();
        }
      });

      // Real-time validation
      $("input[required]").on("blur", function () {
        self.validateField($(this));
      });
    },

    /**
     * Initialize components
     */
    initializeComponents: function () {
      // Initialize tooltips
      this.initializeTooltips();

      // Initialize charts if on analytics page
      if ($("#aci-analytics-chart").length) {
        this.initializeCharts();
      }

      // Initialize data tables
      if ($(".aci-data-table").length) {
        this.initializeDataTables();
      }

      // Check for notices
      this.checkNotices();
    },

    /**
     * Check initial state
     */
    checkInitialState: function () {
      // Check if connection settings are configured
      const masterDomain = $("#aci_master_domain").val();
      const apiKey = $("#aci_api_key").val();

      if (!masterDomain || !apiKey) {
        this.showNotice(
          "Please configure your master domain and API key to get started.",
          "warning"
        );
      } else if (!this.state.connectionTested) {
        this.showNotice(
          "Test your connection to ensure everything is working correctly.",
          "info"
        );
      }
    },

    /**
     * Test connection to master domain
     */
    testConnection: function () {
      const self = this;
      const $button = $("#aci-test-connection, .aci-test-connection");
      const $result = $("#aci-connection-result, .aci-connection-result");
      const masterDomain = $("#aci_master_domain").val();
      const apiKey = $("#aci_api_key").val();

      if (!masterDomain || !apiKey) {
        this.showMessage(
          $result,
          "error",
          "Please enter both Master Domain and API Key"
        );
        return;
      }

      // Show loading state
      $button.prop("disabled", true).addClass("loading");
      $button
        .find(".button-text")
        .text(this.config.i18n?.testing || "Testing connection...");
      $result
        .removeClass("aci-success aci-error aci-info")
        .addClass("aci-info")
        .text("Testing connection...")
        .fadeIn();

      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_test_connection",
          nonce: this.config.nonce,
          master_domain: masterDomain,
          api_key: apiKey,
        },
        success: function (response) {
          if (response.success) {
            self.state.connectionTested = true;
            self.state.lastTestResult = response.data;
            self.showMessage(
              $result,
              "success",
              response.data.message || "Connection successful!"
            );
            self.showNotice("Connection test successful!", "success");

            // Update status indicator
            $(".aci-status-banner")
              .removeClass("aci-disconnected aci-error")
              .addClass("aci-connected");
            $(".aci-status-icon")
              .removeClass("aci-warning aci-error")
              .addClass("aci-success")
              .text("✓");
            $(".aci-status-text").text("Connected");
          } else {
            self.showMessage(
              $result,
              "error",
              response.data?.message || "Connection failed"
            );
            self.showNotice(
              "Connection test failed. Please check your settings.",
              "error"
            );
          }
        },
        error: function (xhr) {
          const message =
            xhr.responseJSON?.data?.message ||
            "Connection error. Please try again.";
          self.showMessage($result, "error", message);
          self.showNotice("Connection test failed: " + message, "error");
        },
        complete: function () {
          $button.prop("disabled", false).removeClass("loading");
          $button.find(".button-text").text("Test Connection");
        },
      });
    },

    /**
     * Sync affiliate data from master
     */
    syncAffiliateData: function () {
      const self = this;
      const $button = $("#aci-sync-codes, #aci-sync-now");

      if (this.state.syncing) {
        return;
      }

      this.state.syncing = true;
      $button.prop("disabled", true).addClass("loading");
      $button
        .find(".button-text")
        .text(this.config.i18n?.syncing || "Syncing...");

      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_sync_affiliate_codes",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.showNotice(
              response.data.message || "Sync completed successfully!",
              "success"
            );

            // Update last sync time
            $(".aci-last-sync").text("Just now");
          } else {
            self.showNotice(response.data?.message || "Sync failed", "error");
          }
        },
        error: function () {
          self.showNotice("Sync failed. Please try again.", "error");
        },
        complete: function () {
          self.state.syncing = false;
          $button.prop("disabled", false).removeClass("loading");
          $button.find(".button-text").text("Sync Now");
        },
      });
    },

    /**
     * Clear cache
     */
    clearCache: function () {
      const self = this;

      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_clear_cache",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.showNotice(
              response.data.message || "Cache cleared successfully!",
              "success"
            );
          } else {
            self.showNotice("Failed to clear cache", "error");
          }
        },
        error: function () {
          self.showNotice("Error clearing cache", "error");
        },
      });
    },

    /**
     * Export settings
     */
    exportSettings: function () {
      const settings = this.getFormData("#aci-settings-form");
      const dataStr = JSON.stringify(settings, null, 2);
      const dataBlob = new Blob([dataStr], { type: "application/json" });

      const url = URL.createObjectURL(dataBlob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "aci-settings-" + Date.now() + ".json";
      link.click();

      URL.revokeObjectURL(url);

      this.showNotice("Settings exported successfully!", "success");
    },

    /**
     * Import settings
     */
    importSettings: function (file) {
      const self = this;

      if (!file) return;

      const reader = new FileReader();
      reader.onload = function (e) {
        try {
          const settings = JSON.parse(e.target.result);
          self.applySettings(settings);
          self.showNotice("Settings imported successfully!", "success");
        } catch (error) {
          self.showNotice("Invalid settings file", "error");
        }
      };
      reader.readAsText(file);
    },

    /**
     * Apply imported settings
     */
    applySettings: function (settings) {
      Object.keys(settings).forEach((key) => {
        const $field = $('[name="' + key + '"]');
        if ($field.length) {
          if ($field.is(":checkbox")) {
            $field.prop("checked", settings[key]);
          } else {
            $field.val(settings[key]);
          }
        }
      });
    },

    /**
     * Test affiliate code
     */
    testAffiliateCode: function (code) {
      const self = this;
      const $result = $("#aci-test-result");
      const $button = $("#aci-test-code-button");

      $button.prop("disabled", true).addClass("loading");
      $result.html('<div class="aci-loading">Testing code...</div>').fadeIn();

      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_test_affiliate_code",
          nonce: this.config.nonce,
          code: code,
        },
        success: function (response) {
          if (response.success && response.data.valid) {
            const data = response.data;
            const html = `
                            <div class="aci-test-success">
                                <h4>✓ Valid Code</h4>
                                <div class="aci-test-details">
                                    <p><strong>Code:</strong> ${data.code}</p>
                                    <p><strong>Affiliate ID:</strong> ${
                                      data.affiliate_id
                                    }</p>
                                    <p><strong>Discount Type:</strong> ${
                                      data.discount_type
                                    }</p>
                                    <p><strong>Discount Value:</strong> ${
                                      data.discount_value
                                    }${
              data.discount_type === "percentage" ? "%" : ""
            }</p>
                                    ${
                                      data.expires
                                        ? "<p><strong>Expires:</strong> " +
                                          data.expires +
                                          "</p>"
                                        : ""
                                    }
                                </div>
                            </div>
                        `;
            $result.html(html);
          } else {
            $result.html(
              '<div class="aci-test-error"><h4>✗ Invalid Code</h4><p>' +
                (response.data?.message || "Code not found or expired") +
                "</p></div>"
            );
          }
        },
        error: function () {
          $result.html(
            '<div class="aci-test-error"><h4>✗ Error</h4><p>Failed to test code. Please try again.</p></div>'
          );
        },
        complete: function () {
          $button.prop("disabled", false).removeClass("loading");
        },
      });
    },

    /**
     * View logs
     */
    viewLogs: function () {
      const self = this;

      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_get_logs",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.showModal(
              "Activity Logs",
              self.formatLogs(response.data.logs)
            );
          }
        },
        error: function () {
          self.showNotice("Failed to load logs", "error");
        },
      });
    },

    /**
     * Download logs
     */
    downloadLogs: function () {
      window.location.href =
        this.config.ajaxUrl +
        "?action=aci_download_logs&nonce=" +
        this.config.nonce;
    },

    /**
     * Clear logs
     */
    clearLogs: function () {
      const self = this;

      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_clear_logs",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            self.showNotice("Logs cleared successfully!", "success");
          }
        },
      });
    },

    /**
     * Format logs for display
     */
    formatLogs: function (logs) {
      if (!logs || logs.length === 0) {
        return '<p class="aci-empty-logs">No logs found</p>';
      }

      let html = '<div class="aci-logs-container">';
      logs.forEach((log) => {
        html += `
                    <div class="aci-log-entry aci-log-${log.level}">
                        <div class="aci-log-time">${log.timestamp}</div>
                        <div class="aci-log-level">${log.level.toUpperCase()}</div>
                        <div class="aci-log-message">${log.message}</div>
                    </div>
                `;
      });
      html += "</div>";

      return html;
    },

    /**
     * Validate form
     */
    validateForm: function () {
      let valid = true;

      $("#aci-settings-form input[required]").each(function () {
        if (!$(this).val().trim()) {
          valid = false;
          $(this).addClass("invalid");
        } else {
          $(this).removeClass("invalid");
        }
      });

      if (!valid) {
        this.showNotice("Please fill in all required fields", "error");
      }

      return valid;
    },

    /**
     * Validate individual field
     */
    validateField: function ($field) {
      const value = $field.val().trim();
      const type = $field.attr("type");
      const required = $field.prop("required");

      if (required && !value) {
        $field.addClass("invalid");
        return false;
      }

      if (type === "url" && value) {
        const urlPattern = /^https?:\/\/.+/i;
        if (!urlPattern.test(value)) {
          $field.addClass("invalid");
          return false;
        }
      }

      $field.removeClass("invalid");
      return true;
    },

    /**
     * Validate master domain
     */
    validateMasterDomain: function ($input) {
      const value = $input.val().trim();

      if (!value) {
        return;
      }

      if (!/^https?:\/\/.+/i.test(value)) {
        this.showFieldError(
          $input,
          "Please enter a valid URL starting with http:// or https://"
        );
        return false;
      }

      this.clearFieldError($input);
      return true;
    },

    /**
     * Validate API key
     */
    validateApiKey: function ($input) {
      const value = $input.val().trim();

      if (!value) {
        return;
      }

      if (value.length < 20) {
        this.showFieldError($input, "API key should be at least 20 characters");
        return false;
      }

      this.clearFieldError($input);
      return true;
    },

    /**
     * Show field error
     */
    showFieldError: function ($field, message) {
      $field.addClass("invalid");

      let $error = $field.siblings(".aci-field-error");
      if (!$error.length) {
        $error = $('<div class="aci-field-error"></div>');
        $field.after($error);
      }

      $error.text(message).fadeIn();
    },

    /**
     * Clear field error
     */
    clearFieldError: function ($field) {
      $field.removeClass("invalid");
      $field.siblings(".aci-field-error").fadeOut(function () {
        $(this).remove();
      });
    },

    /**
     * Toggle API key visibility
     */
    toggleApiKeyVisibility: function () {
      const $input = $("#aci_api_key");
      const $button = $(".aci-toggle-api-key");

      if ($input.attr("type") === "password") {
        $input.attr("type", "text");
        $button
          .find(".dashicons")
          .removeClass("dashicons-visibility")
          .addClass("dashicons-hidden");
      } else {
        $input.attr("type", "password");
        $button
          .find(".dashicons")
          .removeClass("dashicons-hidden")
          .addClass("dashicons-visibility");
      }
    },

    /**
     * Copy to clipboard
     */
    copyToClipboard: function (target) {
      const $target = $(target);
      const text = $target.is("input, textarea")
        ? $target.val()
        : $target.text();

      const $temp = $("<textarea>");
      $("body").append($temp);
      $temp.val(text).select();
      document.execCommand("copy");
      $temp.remove();

      this.showNotice("Copied to clipboard!", "success");
    },

    /**
     * Switch tab
     */
    switchTab: function (tabId) {
      $(".aci-tab-button").removeClass("active");
      $('.aci-tab-button[data-tab="' + tabId + '"]').addClass("active");

      $(".aci-tab-content").removeClass("active");
      $("#" + tabId).addClass("active");

      // Save active tab to localStorage
      localStorage.setItem("aci_active_tab", tabId);
    },

    /**
     * Regenerate API key
     */
    regenerateApiKey: function () {
      const self = this;

      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_regenerate_api_key",
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            $("#aci_api_key").val(response.data.api_key);
            self.showNotice("API key regenerated successfully!", "success");
          } else {
            self.showNotice("Failed to regenerate API key", "error");
          }
        },
        error: function () {
          self.showNotice("Error regenerating API key", "error");
        },
      });
    },

    /**
     * Initialize tooltips
     */
    initializeTooltips: function () {
      $("[data-tooltip]").each(function () {
        const $this = $(this);
        const tooltip = $this.data("tooltip");

        $this.on("mouseenter", function () {
          const $tooltip = $('<div class="aci-tooltip">' + tooltip + "</div>");
          $("body").append($tooltip);

          const offset = $this.offset();
          $tooltip
            .css({
              top: offset.top - $tooltip.outerHeight() - 10,
              left:
                offset.left +
                $this.outerWidth() / 2 -
                $tooltip.outerWidth() / 2,
            })
            .fadeIn(200);

          $this.data("tooltip-element", $tooltip);
        });

        $this.on("mouseleave", function () {
          const $tooltip = $this.data("tooltip-element");
          if ($tooltip) {
            $tooltip.fadeOut(200, function () {
              $(this).remove();
            });
          }
        });
      });
    },

    /**
     * Initialize charts
     */
    initializeCharts: function () {
      if (typeof Chart === "undefined") {
        console.warn("Chart.js not loaded");
        return;
      }

      const $canvas = $("#aci-analytics-chart");
      if (!$canvas.length) return;

      const ctx = $canvas[0].getContext("2d");

      // Fetch chart data
      $.ajax({
        url: this.config.ajaxUrl || ajaxurl,
        method: "POST",
        data: {
          action: "aci_get_analytics",
          nonce: this.config.nonce,
          period: "last_30_days",
        },
        success: function (response) {
          if (response.success) {
            new Chart(ctx, {
              type: "line",
              data: {
                labels: response.data.labels,
                datasets: [
                  {
                    label: "Visits",
                    data: response.data.visits,
                    borderColor: "#0073aa",
                    backgroundColor: "rgba(0, 115, 170, 0.1)",
                    tension: 0.4,
                  },
                  {
                    label: "Conversions",
                    data: response.data.conversions,
                    borderColor: "#46b450",
                    backgroundColor: "rgba(70, 180, 80, 0.1)",
                    tension: 0.4,
                  },
                ],
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                  legend: {
                    position: "bottom",
                  },
                },
                scales: {
                  y: {
                    beginAtZero: true,
                  },
                },
              },
            });
          }
        },
      });
    },

    /**
     * Initialize data tables
     */
    initializeDataTables: function () {
      $(".aci-data-table").each(function () {
        const $table = $(this);

        // Add sorting functionality
        $table.find("th[data-sortable]").on("click", function () {
          const $th = $(this);
          const column = $th.index();
          const order = $th.hasClass("sort-asc") ? "desc" : "asc";

          // Remove existing sort classes
          $table.find("th").removeClass("sort-asc sort-desc");

          // Add new sort class
          $th.addClass("sort-" + order);

          // Sort rows
          const rows = $table.find("tbody tr").get();
          rows.sort(function (a, b) {
            const aVal = $(a).find("td").eq(column).text();
            const bVal = $(b).find("td").eq(column).text();

            if (order === "asc") {
              return aVal.localeCompare(bVal);
            } else {
              return bVal.localeCompare(aVal);
            }
          });

          $.each(rows, function (index, row) {
            $table.find("tbody").append(row);
          });
        });
      });
    },

    /**
     * Show modal
     */
    showModal: function (title, content) {
      const modalHtml = `
                <div class="aci-modal">
                    <div class="aci-modal-content">
                        <div class="aci-modal-header">
                            <h2>${title}</h2>
                            <button class="aci-modal-close">&times;</button>
                        </div>
                        <div class="aci-modal-body">
                            ${content}
                        </div>
                        <div class="aci-modal-footer">
                            <button class="button aci-modal-close">Close</button>
                        </div>
                    </div>
                </div>
            `;

      $("body").append(modalHtml);

      // Bind close events
      $(".aci-modal-close").on("click", this.closeModal.bind(this));
    },

    /**
     * Close modal
     */
    closeModal: function () {
      $(".aci-modal").fadeOut(300, function () {
        $(this).remove();
      });
    },

    /**
     * Show notice
     */
    showNotice: function (message, type) {
      const $notice = $(`
                <div class="notice notice-${type} is-dismissible aci-notice">
                    <p>${message}</p>
                </div>
            `);

      $(".wrap > h1").after($notice);

      // Make dismissible
      $notice.on("click", ".notice-dismiss", function () {
        $notice.fadeOut(300, function () {
          $(this).remove();
        });
      });

      // Auto-dismiss after 5 seconds
      setTimeout(function () {
        $notice.fadeOut(300, function () {
          $(this).remove();
        });
      }, 5000);
    },

    /**
     * Check for notices in URL
     */
    checkNotices: function () {
      const urlParams = new URLSearchParams(window.location.search);
      const notice = urlParams.get("notice");

      if (notice) {
        const messages = {
          "settings-saved": "Settings saved successfully!",
          "connection-tested": "Connection test completed",
          "cache-cleared": "Cache cleared successfully!",
          "sync-completed": "Sync completed successfully!",
        };

        if (messages[notice]) {
          this.showNotice(messages[notice], "success");
        }
      }
    },

    /**
     * Show message in element
     */
    showMessage: function ($element, type, message) {
      $element
        .removeClass("aci-success aci-error aci-info aci-warning")
        .addClass("aci-" + type)
        .html(message)
        .fadeIn();
    },

    /**
     * Get form data as object
     */
    getFormData: function (formSelector) {
      const data = {};
      $(formSelector)
        .find("input, select, textarea")
        .each(function () {
          const $field = $(this);
          const name = $field.attr("name");

          if (!name) return;

          if ($field.is(":checkbox")) {
            data[name] = $field.is(":checked");
          } else if ($field.is(":radio")) {
            if ($field.is(":checked")) {
              data[name] = $field.val();
            }
          } else {
            data[name] = $field.val();
          }
        });

      return data;
    },

    /**
     * Handle keyboard shortcuts
     */
    handleKeydown: function (e) {
      // Escape key closes modals
      if (e.key === "Escape") {
        this.closeModal();
      }

      // Ctrl/Cmd + S saves form
      if ((e.ctrlKey || e.metaKey) && e.key === "s") {
        e.preventDefault();
        $("#aci-settings-form").submit();
      }
    },

    /**
     * Debounce helper
     */
    debounce: function (func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    },
  };

  /**
   * Initialize on document ready
   */
  $(document).ready(function () {
    ACI_Admin.init();

    // Restore active tab if saved
    const activeTab = localStorage.getItem("aci_active_tab");
    if (activeTab && $("#" + activeTab).length) {
      ACI_Admin.switchTab(activeTab);
    }
  });

  // Expose to global scope for external access
  window.ACI_Admin = ACI_Admin;
})(jQuery);
