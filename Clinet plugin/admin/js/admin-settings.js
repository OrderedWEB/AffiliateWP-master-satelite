/**
 * Admin Settings JavaScript
 * File: /wp-content/plugins/affiliate-client-integration/admin/js/admin-settings.js
 * Plugin: Affiliate Client Integration
 */

(function ($) {
  "use strict";

  /**
   * Admin Settings Manager
   */
  const ACI_Admin = {
    // Configuration
    config: {
      ajaxUrl: aci_admin.ajax_url,
      nonce: aci_admin.nonce,
      strings: aci_admin.strings,
    },

    // State
    state: {
      saving: false,
      testing: false,
      syncing: false,
    },

    /**
     * Initialize admin functionality
     */
    init: function () {
      this.bindEvents();
      this.initializeComponents();
      this.checkInitialState();

      console.log("ACI Admin initialized");
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Connection testing
      $("#aci-test-connection").on("click", this.testConnection.bind(this));

      // Sync operations
      $("#aci-sync-codes, #aci-sync-now").on(
        "click",
        this.syncAffiliateData.bind(this)
      );

      // Cache operations
      $("#aci-clear-cache").on("click", this.clearCache.bind(this));

      // Export operations
      $("#aci-export-settings").on("click", this.exportSettings.bind(this));

      // Log operations
      $("#aci-view-logs").on("click", this.viewLogs.bind(this));
      $("#aci-download-logs").on("click", this.downloadLogs.bind(this));

      // Modal operations
      $(".aci-modal-close").on("click", this.closeModal.bind(this));
      $(document).on("keydown", this.handleKeydown.bind(this));

      // Form validation
      $("#aci-settings-form").on("submit", this.validateForm.bind(this));

      // Input validation
      $("#aci_master_domain").on("blur", this.validateDomain.bind(this));
      $("#aci_api_key").on("input", this.validateApiKey.bind(this));

      // Auto-save functionality
      this.setupAutoSave();

      // Real-time connection status
      this.setupConnectionMonitoring();
    },

    /**
     * Initialize components
     */
    initializeComponents: function () {
      // Initialize tooltips
      this.initializeTooltips();

      // Initialize progress indicators
      this.initializeProgressIndicators();

      // Initialize status indicators
      this.updateConnectionStatus();

      // Initialize form state
      this.saveFormState();
    },

    /**
     * Check initial state
     */
    checkInitialState: function () {
      const masterDomain = $("#aci_master_domain").val();
      const apiKey = $("#aci_api_key").val();

      if (masterDomain && apiKey) {
        // Auto-test connection on page load if both values are present
        setTimeout(() => {
          this.testConnection(null, true);
        }, 1000);
      }
    },

    /**
     * Test connection to master domain
     */
    testConnection: function (event, silent = false) {
      if (event) event.preventDefault();

      if (this.state.testing) return;

      const $button = $("#aci-test-connection");
      const $result = $("#aci-connection-result");
      const $banner = $(".aci-status-banner");

      this.state.testing = true;

      // Update UI
      $button
        .prop("disabled", true)
        .html(
          '<span class="aci-spinner"></span> ' +
            this.config.strings.testing_connection
        );

      if (!silent) {
        $result.removeClass("aci-success aci-error").hide();
      }

      // Get form values
      const masterDomain = $("#aci_master_domain").val().trim();
      const apiKey = $("#aci_api_key").val().trim();

      if (!masterDomain || !apiKey) {
        this.showConnectionResult(
          "error",
          "Master domain and API key are required"
        );
        this.resetTestButton($button);
        return;
      }

      // Test connection
      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_test_connection",
          nonce: this.config.nonce,
          master_domain: masterDomain,
          api_key: apiKey,
        },
        timeout: 15000,
        success: (response) => {
          if (response.success) {
            this.showConnectionResult(
              "success",
              this.config.strings.connection_success
            );
            $banner.removeClass("aci-disconnected").addClass("aci-connected");

            // Update status details
            this.updateConnectionDetails(masterDomain);

            // Track successful connection
            this.trackEvent("connection_test_success");
          } else {
            const message =
              response.data || this.config.strings.connection_failed;
            this.showConnectionResult(
              "error",
              this.config.strings.connection_failed + " " + message
            );
            $banner.removeClass("aci-connected").addClass("aci-disconnected");

            // Track failed connection
            this.trackEvent("connection_test_failed", { error: message });
          }
        },
        error: (xhr, status, error) => {
          let message = this.config.strings.error_occurred + " " + error;

          if (status === "timeout") {
            message =
              "Connection timeout. Please check the domain URL and try again.";
          } else if (xhr.status === 0) {
            message = "Network error. Please check your internet connection.";
          }

          this.showConnectionResult("error", message);
          $banner.removeClass("aci-connected").addClass("aci-disconnected");

          this.trackEvent("connection_test_error", {
            status: status,
            error: error,
            xhr_status: xhr.status,
          });
        },
        complete: () => {
          this.resetTestButton($button);
          this.state.testing = false;
        },
      });
    },

    /**
     * Reset test button state
     */
    resetTestButton: function ($button) {
      $button
        .prop("disabled", false)
        .html('<span class="aci-button-icon">🔗</span> Test Connection');
    },

    /**
     * Show connection result
     */
    showConnectionResult: function (type, message) {
      const $result = $("#aci-connection-result");

      $result
        .removeClass("aci-success aci-error")
        .addClass("aci-" + type)
        .html(message)
        .slideDown();

      // Auto-hide success messages
      if (type === "success") {
        setTimeout(() => {
          $result.slideUp();
        }, 5000);
      }
    },

    /**
     * Update connection details
     */
    updateConnectionDetails: function (domain) {
      const hostname = new URL(domain).hostname;
      const $details = $(".aci-status-details");

      $details
        .find(".aci-status-detail:first-child strong + span")
        .text(hostname);
    },

    /**
     * Sync affiliate data
     */
    syncAffiliateData: function (event) {
      if (event) event.preventDefault();

      if (this.state.syncing) return;

      const $button = $(event.currentTarget);
      const $result = $("#aci-action-results");

      this.state.syncing = true;

      // Update UI
      $button
        .prop("disabled", true)
        .html(
          '<span class="aci-spinner"></span> ' +
            this.config.strings.syncing_codes
        );

      $result.removeClass("aci-success aci-error").hide();

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_sync_affiliate_codes",
          nonce: this.config.nonce,
        },
        timeout: 30000,
        success: (response) => {
          if (response.success) {
            this.showActionResult("success", this.config.strings.sync_complete);

            // Update last sync time
            this.updateLastSyncTime();

            this.trackEvent("sync_success");
          } else {
            this.showActionResult("error", response.data || "Sync failed");
            this.trackEvent("sync_failed", { error: response.data });
          }
        },
        error: (xhr, status, error) => {
          this.showActionResult(
            "error",
            this.config.strings.error_occurred + " " + error
          );
          this.trackEvent("sync_error", { status: status, error: error });
        },
        complete: () => {
          $button
            .prop("disabled", false)
            .html(
              '<span class="aci-button-icon">🔄</span> Sync Affiliate Codes'
            );
          this.state.syncing = false;
        },
      });
    },

    /**
     * Clear cache
     */
    clearCache: function (event) {
      if (event) event.preventDefault();

      const $button = $(event.currentTarget);
      const $result = $("#aci-action-results");

      $button
        .prop("disabled", true)
        .html(
          '<span class="aci-spinner"></span> ' +
            this.config.strings.clearing_cache
        );

      $result.removeClass("aci-success aci-error").hide();

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_clear_cache",
          nonce: this.config.nonce,
        },
        success: (response) => {
          if (response.success) {
            this.showActionResult("success", this.config.strings.cache_cleared);
            this.trackEvent("cache_cleared");
          } else {
            this.showActionResult(
              "error",
              response.data || "Cache clear failed"
            );
            this.trackEvent("cache_clear_failed");
          }
        },
        error: (xhr, status, error) => {
          this.showActionResult(
            "error",
            this.config.strings.error_occurred + " " + error
          );
        },
        complete: () => {
          $button
            .prop("disabled", false)
            .html('<span class="aci-button-icon">🗑️</span> Clear Cache');
        },
      });
    },

    /**
     * Export settings
     */
    exportSettings: function (event) {
      if (event) event.preventDefault();

      const $button = $(event.currentTarget);

      $button
        .prop("disabled", true)
        .html('<span class="aci-spinner"></span> Exporting...');

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_export_settings",
          nonce: this.config.nonce,
        },
        success: (response) => {
          if (response.success) {
            // Create download
            const blob = new Blob([JSON.stringify(response.data, null, 2)], {
              type: "application/json",
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download =
              "aci-settings-export-" +
              new Date().toISOString().split("T")[0] +
              ".json";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            this.showActionResult("success", "Settings exported successfully");
            this.trackEvent("settings_exported");
          } else {
            this.showActionResult("error", response.data || "Export failed");
          }
        },
        error: (xhr, status, error) => {
          this.showActionResult(
            "error",
            this.config.strings.error_occurred + " " + error
          );
        },
        complete: () => {
          $button
            .prop("disabled", false)
            .html('<span class="aci-button-icon">📥</span> Export Settings');
        },
      });
    },

    /**
     * View logs
     */
    viewLogs: function (event) {
      if (event) event.preventDefault();

      const $modal = $("#aci-logs-modal");
      const $content = $("#aci-logs-content");

      // Show modal
      $modal.show();

      // Load logs
      $content.html('<div class="aci-loading">Loading logs...</div>');

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_get_logs",
          nonce: this.config.nonce,
          limit: 100,
        },
        success: (response) => {
          if (response.success && response.data.logs) {
            this.renderLogs(response.data.logs);
          } else {
            $content.html('<div class="aci-no-logs">No logs available</div>');
          }
        },
        error: () => {
          $content.html('<div class="aci-error">Failed to load logs</div>');
        },
      });
    },

    /**
     * Render logs in modal
     */
    renderLogs: function (logs) {
      const $content = $("#aci-logs-content");
      let html = '<div class="aci-logs-container">';

      if (logs.length === 0) {
        html += '<div class="aci-no-logs">No logs available</div>';
      } else {
        html += '<table class="aci-logs-table">';
        html +=
          "<thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead>";
        html += "<tbody>";

        logs.forEach((log) => {
          const time = new Date(log.timestamp).toLocaleString();
          const level = log.level || "info";
          const message = this.escapeHtml(log.message || "");

          html += `<tr class="aci-log-${level}">`;
          html += `<td class="aci-log-time">${time}</td>`;
          html += `<td class="aci-log-level">${level.toUpperCase()}</td>`;
          html += `<td class="aci-log-message">${message}</td>`;
          html += "</tr>";
        });

        html += "</tbody></table>";
      }

      html += "</div>";
      $content.html(html);
    },

    /**
     * Download logs
     */
    downloadLogs: function (event) {
      if (event) event.preventDefault();

      const $button = $(event.currentTarget);

      $button.prop("disabled", true).text("Downloading...");

      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_get_logs",
          nonce: this.config.nonce,
          format: "text",
          limit: 1000,
        },
        success: (response) => {
          if (response.success) {
            // Create download
            const blob = new Blob([response.data.content], {
              type: "text/plain",
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download =
              "aci-logs-" + new Date().toISOString().split("T")[0] + ".txt";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }
        },
        complete: () => {
          $button.prop("disabled", false).text("Download Logs");
        },
      });
    },

    /**
     * Close modal
     */
    closeModal: function (event) {
      if (event) event.preventDefault();

      $(".aci-modal").hide();
    },

    /**
     * Handle keyboard events
     */
    handleKeydown: function (event) {
      if (event.key === "Escape") {
        this.closeModal();
      }
    },

    /**
     * Show action result
     */
    showActionResult: function (type, message) {
      const $result = $("#aci-action-results");

      $result
        .removeClass("aci-success aci-error")
        .addClass("aci-" + type)
        .html(message)
        .slideDown();

      // Auto-hide after 5 seconds
      setTimeout(() => {
        $result.slideUp();
      }, 5000);
    },

    /**
     * Update last sync time
     */
    updateLastSyncTime: function () {
      const now = new Date();
      const timeString = "Just now";

      $(".aci-status-detail").each(function () {
        const $detail = $(this);
        if ($detail.text().includes("Last Sync:")) {
          $detail.html("<strong>Last Sync:</strong> " + timeString);
        }
      });
    },

    /**
     * Validate form before submission
     */
    validateForm: function (event) {
      const masterDomain = $("#aci_master_domain").val().trim();
      const apiKey = $("#aci_api_key").val().trim();

      // Basic validation
      if (!masterDomain || !apiKey) {
        alert("Master domain and API key are required");
        event.preventDefault();
        return false;
      }

      // URL validation
      try {
        new URL(masterDomain);
      } catch (e) {
        alert("Please enter a valid URL for the master domain");
        $("#aci_master_domain").focus();
        event.preventDefault();
        return false;
      }

      // Set saving state
      this.state.saving = true;
      const $button = $("#aci-save-settings");
      $button
        .prop("disabled", true)
        .val("Saving...")
        .append(' <span class="aci-spinner"></span>');

      return true;
    },

    /**
     * Validate domain URL
     */
    validateDomain: function (event) {
      const $input = $(event.target);
      const value = $input.val().trim();

      if (!value) return;

      try {
        const url = new URL(value);
        if (url.protocol !== "https:") {
          this.showFieldError($input, "HTTPS is required for security");
          return;
        }
        this.clearFieldError($input);
      } catch (e) {
        this.showFieldError($input, "Please enter a valid URL");
      }
    },

    /**
     * Validate API key
     */
    validateApiKey: function (event) {
      const $input = $(event.target);
      const value = $input.val().trim();

      if (!value) {
        this.clearFieldError($input);
        return;
      }

      if (value.length < 10) {
        this.showFieldError($input, "API key seems too short");
      } else {
        this.clearFieldError($input);
      }
    },

    /**
     * Show field error
     */
    showFieldError: function ($input, message) {
      $input.addClass("aci-error");

      let $error = $input.siblings(".aci-field-error");
      if (!$error.length) {
        $error = $('<div class="aci-field-error"></div>');
        $input.after($error);
      }

      $error.text(message).show();
    },

    /**
     * Clear field error
     */
    clearFieldError: function ($input) {
      $input.removeClass("aci-error");
      $input.siblings(".aci-field-error").hide();
    },

    /**
     * Setup auto-save functionality
     */
    setupAutoSave: function () {
      const saveState = () => {
        const formData = $("#aci-settings-form").serialize();
        localStorage.setItem("aci_form_backup", formData);
      };

      // Save form state periodically
      setInterval(saveState, 30000); // Every 30 seconds

      // Save on input changes
      $("#aci-settings-form input, #aci-settings-form select").on(
        "change",
        saveState
      );
    },

    /**
     * Setup connection monitoring
     */
    setupConnectionMonitoring: function () {
      // Check connection status periodically
      setInterval(() => {
        if (!this.state.testing && !this.state.saving) {
          this.updateConnectionStatus();
        }
      }, 60000); // Every minute
    },

    /**
     * Update connection status
     */
    updateConnectionStatus: function () {
      const masterDomain = $("#aci_master_domain").val().trim();
      const apiKey = $("#aci_api_key").val().trim();

      if (!masterDomain || !apiKey) return;

      // Quick ping to check status
      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_ping_connection",
          nonce: this.config.nonce,
        },
        timeout: 5000,
        success: (response) => {
          const $banner = $(".aci-status-banner");
          if (response.success) {
            $banner.removeClass("aci-disconnected").addClass("aci-connected");
          } else {
            $banner.removeClass("aci-connected").addClass("aci-disconnected");
          }
        },
      });
    },

    /**
     * Initialize tooltips
     */
    initializeTooltips: function () {
      $("[title]").each(function () {
        const $element = $(this);
        const title = $element.attr("title");

        $element
          .removeAttr("title")
          .on("mouseenter", function () {
            const tooltip = $('<div class="aci-tooltip"></div>').text(title);
            $("body").append(tooltip);

            const rect = this.getBoundingClientRect();
            tooltip.css({
              top: rect.bottom + 5,
              left: rect.left + rect.width / 2 - tooltip.outerWidth() / 2,
            });
          })
          .on("mouseleave", function () {
            $(".aci-tooltip").remove();
          });
      });
    },

    /**
     * Initialize progress indicators
     */
    initializeProgressIndicators: function () {
      // Add CSS for spinners
      if (!$("#aci-admin-styles").length) {
        $('<style id="aci-admin-styles">')
          .text(
            `
                        .aci-spinner {
                            display: inline-block;
                            width: 16px;
                            height: 16px;
                            border: 2px solid rgba(255,255,255,0.3);
                            border-top: 2px solid #fff;
                            border-radius: 50%;
                            animation: aci-spin 1s linear infinite;
                        }
                        
                        @keyframes aci-spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                        
                        .aci-field-error {
                            color: #d63638;
                            font-size: 13px;
                            margin-top: 4px;
                        }
                        
                        .aci-error {
                            border-color: #d63638 !important;
                        }
                        
                        .aci-tooltip {
                            position: absolute;
                            background: #000;
                            color: #fff;
                            padding: 6px 10px;
                            border-radius: 4px;
                            font-size: 12px;
                            z-index: 10000;
                            white-space: nowrap;
                        }
                        
                        .aci-logs-table {
                            width: 100%;
                            border-collapse: collapse;
                            font-size: 12px;
                        }
                        
                        .aci-logs-table th,
                        .aci-logs-table td {
                            padding: 8px;
                            border-bottom: 1px solid #e2e4e7;
                            text-align: left;
                        }
                        
                        .aci-logs-table th {
                            background: #f6f7f7;
                            font-weight: 600;
                        }
                        
                        .aci-log-error {
                            background: #fef7f7;
                        }
                        
                        .aci-log-warning {
                            background: #fffbf0;
                        }
                        
                        .aci-log-time {
                            white-space: nowrap;
                            width: 140px;
                        }
                        
                        .aci-log-level {
                            width: 80px;
                            font-weight: 600;
                        }
                        
                        .aci-log-message {
                            word-break: break-word;
                        }
                    `
          )
          .appendTo("head");
      }
    },

    /**
     * Save form state
     */
    saveFormState: function () {
      this.initialFormState = $("#aci-settings-form").serialize();
    },

    /**
     * Check if form has changes
     */
    hasFormChanges: function () {
      return this.initialFormState !== $("#aci-settings-form").serialize();
    },

    /**
     * Track events for analytics
     */
    trackEvent: function (eventName, data = {}) {
      // Send to server for tracking
      $.ajax({
        url: this.config.ajaxUrl,
        type: "POST",
        data: {
          action: "aci_track_admin_event",
          nonce: this.config.nonce,
          event: eventName,
          data: JSON.stringify(data),
        },
      });
    },

    /**
     * Escape HTML for safe display
     */
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    ACI_Admin.init();

    // Warn about unsaved changes
    $(window).on("beforeunload", function () {
      if (ACI_Admin.hasFormChanges() && !ACI_Admin.state.saving) {
        return "You have unsaved changes. Are you sure you want to leave?";
      }
    });

    // Clear warning after successful save
    $(document).on("submit", "#aci-settings-form", function () {
      $(window).off("beforeunload");
    });
  });

  // Export for external use
  window.ACI_Admin = ACI_Admin;
})(jQuery);
