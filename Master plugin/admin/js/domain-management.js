/**
 * Domain Management JavaScript
 *
 * Handles all client-side interactions for the domain management interface
 * including AJAX requests, form validation, and real-time testing.
 *
 * @package AffiliateWP_Cross_Domain_Plugin_Suite_Master
 * @version 1.0.0
 */

jQuery(document).ready(function ($) {
  "use strict";

  const DomainManagement = {
    /**
     * Initialse domain management
     */
    init: function () {
      this.bindEvents();
      this.initDataTables();
      this.setupFormValidation();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Test domain connection
      $(document).on(
        "click",
        ".test-domain-connection",
        this.testDomainConnection
      );

      // Generate API key
      $(document).on("click", ".generate-api-key", this.generateApiKey);

      // Test webhook
      $(document).on("click", ".test-webhook", this.testWebhook);

      // Add new domain
      $(document).on("submit", "#add-domain-form", this.addDomain);

      // Update domain
      $(document).on("submit", ".edit-domain-form", this.updateDomain);

      // Delete domain
      $(document).on("click", ".delete-domain", this.deleteDomain);

      // Verify domain
      $(document).on("click", ".verify-domain", this.verifyDomain);

      // Toggle domain status
      $(document).on(
        "change",
        ".domain-status-toggle",
        this.toggleDomainStatus
      );

      // Bulk actions
      $(document).on("click", "#bulk-action-apply", this.applyBulkAction);

      // Domain search
      $(document).on(
        "input",
        "#domain-search",
        this.debounce(this.searchDomains, 300)
      );

      // Refresh domain list
      $(document).on("click", ".refresh-domains", this.refreshDomainList);

      // Export domains
      $(document).on("click", ".export-domains", this.exportDomains);
    },

    /**
     * Initialse DataTables
     */
    initDataTables: function () {
      if ($("#domains-table").length) {
        $("#domains-table").DataTable({
          ajax: {
            url: ajaxurl,
            type: "POST",
            data: {
              action: "affcd_get_domains",
              nonce: affcdAjax.nonce,
            },
          },
          columns: [
            { data: "checkbox", orderable: false, searchable: false },
            { data: "domain_url" },
            { data: "domain_name" },
            { data: "status" },
            { data: "verification_status" },
            { data: "last_activity" },
            { data: "actions", orderable: false, searchable: false },
          ],
          order: [[5, "desc"]],
          pageLength: 25,
          responsive: true,
          processing: true,
          serverSide: false,
        });
      }
    },

    /**
     * Setup form validation
     */
    setupFormValidation: function () {
      // Domain URL validation
      $(document).on("input", ".domain-url-input", function () {
        const $input = $(this);
        const url = $input.val().trim();

        if (url && !DomainManagement.isValidUrl(url)) {
          $input.addClass("error");
          $input.next(".validation-message").text(affcdAjax.strings.invalidUrl);
        } else {
          $input.removeClass("error");
          $input.next(".validation-message").text("");
        }
      });

      // API key validation
      $(document).on("input", ".api-key-input", function () {
        const $input = $(this);
        const key = $input.val().trim();

        if (key && key.length < 20) {
          $input.addClass("error");
          $input
            .next(".validation-message")
            .text(affcdAjax.strings.apiKeyTooShort);
        } else {
          $input.removeClass("error");
          $input.next(".validation-message").text("");
        }
      });
    },

    /**
     * Test domain connection
     */
    testDomainConnection: function (e) {
      e.preventDefault();

      const $button = $(this);
      const domainId = $button.data("domain-id");
      const $status = $button.next(".test-status");

      if (!domainId) {
        DomainManagement.showNotice("error", affcdAjax.strings.invalidDomain);
        return;
      }

      $button.prop("disabled", true).text(affcdAjax.strings.testing);
      $status.removeClass("success error").addClass("testing").text("");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_test_domain_connection",
          domain_id: domainId,
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            $status
              .removeClass("testing")
              .addClass("success")
              .text(
                affcdAjax.strings.success +
                  " (" +
                  response.data.response_time +
                  "ms)"
              );
            DomainManagement.showNotice("success", response.data.message);
          } else {
            $status
              .removeClass("testing")
              .addClass("error")
              .text(affcdAjax.strings.error);
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.connectionFailed
            );
          }
        },
        error: function (xhr, status, error) {
          $status
            .removeClass("testing")
            .addClass("error")
            .text(affcdAjax.strings.error);
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.connectionError
          );
        },
        complete: function () {
          $button
            .prop("disabled", false)
            .text(affcdAjax.strings.testConnection);
        },
      });
    },

    /**
     * Generate API key
     */
    generateApiKey: function (e) {
      e.preventDefault();

      const $button = $(this);
      const $input = $button.siblings(".api-key-input");

      if (!confirm(affcdAjax.strings.confirmGenerateKey)) {
        return;
      }

      $button.prop("disabled", true).text(affcdAjax.strings.generating);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_generate_api_key",
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success && response.data.api_key) {
            $input.val(response.data.api_key);
            DomainManagement.showNotice(
              "success",
              affcdAjax.strings.keyGenerated
            );
          } else {
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.keyGenerationFailed
            );
          }
        },
        error: function () {
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.keyGenerationError
          );
        },
        complete: function () {
          $button.prop("disabled", false).text(affcdAjax.strings.generate);
        },
      });
    },

    /**
     * Test webhook
     */
    testWebhook: function (e) {
      e.preventDefault();

      const $button = $(this);
      const webhookUrl = $button.siblings(".webhook-url-input").val();
      const webhookSecret = $button.siblings(".webhook-secret-input").val();
      const $status = $button.next(".webhook-status");

      if (!webhookUrl) {
        DomainManagement.showNotice(
          "error",
          affcdAjax.strings.webhookUrlRequired
        );
        return;
      }

      $button.prop("disabled", true).text(affcdAjax.strings.testing);
      $status.removeClass("success error").addClass("testing").text("");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_test_webhook",
          webhook_url: webhookUrl,
          secret: webhookSecret,
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            $status
              .removeClass("testing")
              .addClass("success")
              .text(
                affcdAjax.strings.success +
                  " (" +
                  response.data.response_time +
                  "ms)"
              );
            DomainManagement.showNotice(
              "success",
              affcdAjax.strings.webhookTestSuccess
            );
          } else {
            $status
              .removeClass("testing")
              .addClass("error")
              .text(affcdAjax.strings.error);
            DomainManagement.showNotice(
              "error",
              response.data.error || affcdAjax.strings.webhookTestFailed
            );
          }
        },
        error: function () {
          $status
            .removeClass("testing")
            .addClass("error")
            .text(affcdAjax.strings.error);
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.webhookTestError
          );
        },
        complete: function () {
          $button.prop("disabled", false).text(affcdAjax.strings.testWebhook);
        },
      });
    },

    /**
     * Add new domain
     */
    addDomain: function (e) {
      e.preventDefault();

      const $form = $(this);
      const formData = $form.serialize();
      const $submitButton = $form.find('button[type="submit"]');

      if (!DomainManagement.validateForm($form)) {
        return;
      }

      $submitButton.prop("disabled", true).text(affcdAjax.strings.adding);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: formData + "&action=affcd_add_domain&nonce=" + affcdAjax.nonce,
        success: function (response) {
          if (response.success) {
            DomainManagement.showNotice("success", response.data.message);
            $form[0].reset();
            DomainManagement.refreshDomainList();

            // Close modal if present
            $(".domain-modal").hide();
          } else {
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.addDomainFailed
            );
          }
        },
        error: function () {
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.addDomainError
          );
        },
        complete: function () {
          $submitButton
            .prop("disabled", false)
            .text(affcdAjax.strings.addDomain);
        },
      });
    },

    /**
     * Update domain
     */
    updateDomain: function (e) {
      e.preventDefault();

      const $form = $(this);
      const formData = $form.serialize();
      const $submitButton = $form.find('button[type="submit"]');

      if (!DomainManagement.validateForm($form)) {
        return;
      }

      $submitButton.prop("disabled", true).text(affcdAjax.strings.updating);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: formData + "&action=affcd_update_domain&nonce=" + affcdAjax.nonce,
        success: function (response) {
          if (response.success) {
            DomainManagement.showNotice("success", response.data.message);
            DomainManagement.refreshDomainList();

            // Close modal if present
            $(".domain-modal").hide();
          } else {
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.updateDomainFailed
            );
          }
        },
        error: function () {
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.updateDomainError
          );
        },
        complete: function () {
          $submitButton
            .prop("disabled", false)
            .text(affcdAjax.strings.updateDomain);
        },
      });
    },

    /**
     * Delete domain
     */
    deleteDomain: function (e) {
      e.preventDefault();

      const $button = $(this);
      const domainId = $button.data("domain-id");
      const domainName = $button.data("domain-name") || "this domain";

      if (!confirm(affcdAjax.strings.confirmRemove.replace("%s", domainName))) {
        return;
      }

      $button.prop("disabled", true).text(affcdAjax.strings.deleting);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_delete_domain",
          domain_id: domainId,
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            DomainManagement.showNotice("success", response.data.message);
            DomainManagement.refreshDomainList();
          } else {
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.deleteDomainFailed
            );
            $button.prop("disabled", false).text(affcdAjax.strings.delete);
          }
        },
        error: function () {
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.deleteDomainError
          );
          $button.prop("disabled", false).text(affcdAjax.strings.delete);
        },
      });
    },

    /**
     * Verify domain
     */
    verifyDomain: function (e) {
      e.preventDefault();

      const $button = $(this);
      const domainId = $button.data("domain-id");
      const $status = $button.next(".verification-status");

      $button.prop("disabled", true).text(affcdAjax.strings.verifying);
      $status
        .removeClass("verified unverified")
        .addClass("verifying")
        .text(affcdAjax.strings.verifying);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_verify_domain",
          domain_id: domainId,
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            $status
              .removeClass("verifying")
              .addClass("verified")
              .text(affcdAjax.strings.verified);
            DomainManagement.showNotice("success", response.data.message);
            DomainManagement.refreshDomainList();
          } else {
            $status
              .removeClass("verifying")
              .addClass("unverified")
              .text(affcdAjax.strings.verificationFailed);
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.verificationFailed
            );
          }
        },
        error: function () {
          $status
            .removeClass("verifying")
            .addClass("unverified")
            .text(affcdAjax.strings.verificationError);
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.verificationError
          );
        },
        complete: function () {
          $button.prop("disabled", false).text(affcdAjax.strings.verify);
        },
      });
    },

    /**
     * Toggle domain status
     */
    toggleDomainStatus: function (e) {
      const $toggle = $(this);
      const domainId = $toggle.data("domain-id");
      const newStatus = $toggle.is(":checked") ? "active" : "inactive";

      $toggle.prop("disabled", true);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_toggle_domain_status",
          domain_id: domainId,
          status: newStatus,
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            DomainManagement.showNotice("success", response.data.message);
          } else {
            // Revert toggle state
            $toggle.prop("checked", !$toggle.is(":checked"));
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.statusUpdateFailed
            );
          }
        },
        error: function () {
          // Revert toggle state
          $toggle.prop("checked", !$toggle.is(":checked"));
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.statusUpdateError
          );
        },
        complete: function () {
          $toggle.prop("disabled", false);
        },
      });
    },

    /**
     * Apply bulk action
     */
    applyBulkAction: function (e) {
      e.preventDefault();

      const action = $("#bulk-actions").val();
      const selectedDomains = $('input[name="domain_ids[]"]:checked')
        .map(function () {
          return $(this).val();
        })
        .get();

      if (!action || action === "-1") {
        DomainManagement.showNotice(
          "error",
          affcdAjax.strings.selectBulkAction
        );
        return;
      }

      if (selectedDomains.length === 0) {
        DomainManagement.showNotice("error", affcdAjax.strings.selectDomains);
        return;
      }

      const actionText = $("#bulk-actions option:selected").text();
      if (
        !confirm(
          affcdAjax.strings.confirmBulkAction
            .replace("%s", actionText)
            .replace("%d", selectedDomains.length)
        )
      ) {
        return;
      }

      const $button = $(this);
      $button.prop("disabled", true).text(affcdAjax.strings.processing);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_bulk_domain_action",
          bulk_action: action,
          domain_ids: selectedDomains,
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            DomainManagement.showNotice("success", response.data.message);
            DomainManagement.refreshDomainList();
            $("#select-all-domains").prop("checked", false);
          } else {
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.bulkActionFailed
            );
          }
        },
        error: function () {
          DomainManagement.showNotice(
            "error",
            affcdAjax.strings.bulkActionError
          );
        },
        complete: function () {
          $button.prop("disabled", false).text(affcdAjax.strings.apply);
        },
      });
    },

    /**
     * Search domains
     */
    searchDomains: function () {
      const searchTerm = $("#domain-search").val().trim();

      if (
        $("#domains-table").length &&
        $.fn.DataTable.isDataTable("#domains-table")
      ) {
        $("#domains-table").DataTable().search(searchTerm).draw();
      }
    },

    /**
     * Refresh domain list
     */
    refreshDomainList: function () {
      if (
        $("#domains-table").length &&
        $.fn.DataTable.isDataTable("#domains-table")
      ) {
        $("#domains-table").DataTable().ajax.reload();
      } else {
        location.reload();
      }
    },

    /**
     * Export domains
     */
    exportDomains: function (e) {
      e.preventDefault();

      const format = $(this).data("format") || "csv";
      const $button = $(this);

      $button.prop("disabled", true).text(affcdAjax.strings.exporting);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "affcd_export_domains",
          format: format,
          nonce: affcdAjax.nonce,
        },
        success: function (response) {
          if (response.success && response.data.download_url) {
            // Create temporary download link
            const link = document.createElement("a");
            link.href = response.data.download_url;
            link.download = response.data.filename || "domains." + format;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            DomainManagement.showNotice("success", response.data.message);
          } else {
            DomainManagement.showNotice(
              "error",
              response.data.message || affcdAjax.strings.exportFailed
            );
          }
        },
        error: function () {
          DomainManagement.showNotice("error", affcdAjax.strings.exportError);
        },
        complete: function () {
          $button.prop("disabled", false).text(affcdAjax.strings.export);
        },
      });
    },

    /**
     * Validate form
     */
    validateForm: function ($form) {
      let isValid = true;

      $form.find(".required").each(function () {
        const $field = $(this);
        const value = $field.val().trim();

        if (!value) {
          $field.addClass("error");
          $field
            .next(".validation-message")
            .text(affcdAjax.strings.fieldRequired);
          isValid = false;
        } else {
          $field.removeClass("error");
          $field.next(".validation-message").text("");
        }
      });

      // Validate domain URL format
      $form.find(".domain-url-input").each(function () {
        const $input = $(this);
        const url = $input.val().trim();

        if (url && !DomainManagement.isValidUrl(url)) {
          $input.addClass("error");
          $input.next(".validation-message").text(affcdAjax.strings.invalidUrl);
          isValid = false;
        }
      });

      return isValid;
    },

    /**
     * Check if URL is valid
     */
    isValidUrl: function (url) {
      try {
        // Add protocol if missing
        if (!/^https?:\/\//i.test(url)) {
          url = "https://" + url;
        }

        const urlObj = new URL(url);
        return urlObj.protocol === "http:" || urlObj.protocol === "https:";
      } catch (e) {
        return false;
      }
    },

    /**
     * Show notice
     */
    showNotice: function (type, message) {
      // Remove existing notices
      $(".affcd-notice").remove();

      const notice = $(
        '<div class="notice notice-' +
          type +
          ' affcd-notice is-dismissible"><p>' +
          message +
          "</p></div>"
      );
      $(".wrap h1").after(notice);

      // Auto-hide after 5 seconds
      setTimeout(function () {
        notice.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);

      // Handle dismiss button
      notice.on("click", ".notice-dismiss", function () {
        notice.fadeOut(function () {
          $(this).remove();
        });
      });
    },

    /**
     * Debounce function
     */
    debounce: function (func, wait, immediate) {
      let timeout;
      return function () {
        const context = this;
        const args = arguments;
        const later = function () {
          timeout = null;
          if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
      };
    },

    /**
     * Format bytes
     */
    formatBytes: function (bytes, decimals = 2) {
      if (bytes === 0) return "0 Bytes";

      const k = 1024;
      const dm = decimals < 0 ? 0 : decimals;
      const sizes = ["Bytes", "KB", "MB", "GB"];

      const i = Math.floor(Math.log(bytes) / Math.log(k));

      return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
    },
  };

  // Global functions for backward compatibility
  window.testAllConnections = function () {
    if (!confirm(affcdAjax.strings.confirmTestAll)) {
      return;
    }

    $(".test-domain-connection").each(function () {
      const $button = $(this);
      setTimeout(function () {
        $button.trigger("click");
      }, Math.random() * 2000); // Stagger requests
    });
  };

  window.refreshDomainStats = function () {
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "affcd_get_domain_stats",
        nonce: affcdAjax.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          $(".total-domains").text(response.data.total);
          $(".active-domains").text(response.data.active);
          $(".verified-domains").text(response.data.verified);
          $(".failed-domains").text(response.data.failed);
        }
      },
    });
  };

  // Initialise on document ready
  DomainManagement.init();

  // Auto-refresh stats every 30 seconds
  setInterval(window.refreshDomainStats, 30000);

  // Handle select all checkbox
  $(document).on("change", "#select-all-domains", function () {
    $('input[name="domain_ids[]"]').prop("checked", $(this).prop("checked"));
  });

  // Handle individual checkboxes
  $(document).on("change", 'input[name="domain_ids[]"]', function () {
    const totalCheckboxes = $('input[name="domain_ids[]"]').length;
    const checkedCheckboxes = $('input[name="domain_ids[]"]:checked').length;

    $("#select-all-domains").prop(
      "checked",
      totalCheckboxes === checkedCheckboxes
    );
  });

  // Modal handling
  $(document).on("click", ".open-domain-modal", function (e) {
    e.preventDefault();
    const modalId = $(this).data("modal");
    $("#" + modalId).show();
  });

  $(document).on("click", ".close-modal, .modal-overlay", function (e) {
    if (e.target === this) {
      $(".domain-modal").hide();
    }
  });

  // Escape key to close modals
  $(document).on("keyup", function (e) {
    if (e.keyCode === 27) {
      // Escape key
      $(".domain-modal").hide();
    }
  });

  // Copy to clipboard functionality
  $(document).on("click", ".copy-to-clipboard", function (e) {
    e.preventDefault();

    const $button = $(this);
    const textToCopy = $button.data("text") || $button.prev("input").val();

    if (!textToCopy) return;

    navigator.clipboard
      .writeText(textToCopy)
      .then(function () {
        const originalText = $button.text();
        $button.text(affcdAjax.strings.copied);

        setTimeout(function () {
          $button.text(originalText);
        }, 2000);
      })
      .catch(function () {
        // Fallback for older browsers
        const textArea = document.createElement("textarea");
        textArea.value = textToCopy;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand("copy");
        document.body.removeChild(textArea);

        const originalText = $button.text();
        $button.text(affcdAjax.strings.copied);

        setTimeout(function () {
          $button.text(originalText);
        }, 2000);
      });
  });
});