/**
 * Discount Block JavaScript for Gutenberg Editor
 *
 * Plugin: Affiliate Client Integration (Satellite)
 * File: /wp-content/plugins/affiliate-client-integration/assets/js/discount-block.js
 * Author: Richard King, starneconsulting.com
 *
 * Provides Gutenberg block functionality for displaying affiliate discount codes
 * with comprehensive editor controls and live preview capabilities.
 */

(function (wp) {
  "use strict";

  const { registerBlockType } = wp.blocks;
  const { InspectorControls, RichText, PanelColorSettings } =
    wp.blockEditor || wp.editor;
  const {
    PanelBody,
    TextControl,
    SelectControl,
    ToggleControl,
    RangeControl,
    DateTimePicker,
    Button,
  } = wp.components;
  const { Fragment } = wp.element;
  const { __ } = wp.i18n;

  /**
   * Register Discount Code Display Block
   */
  registerBlockType("aci/discount-display", {
    title: __("Affiliate Discount Code", "affiliate-client-integration"),
    description: __(
      "Display an affiliate discount code with copy and apply functionality",
      "affiliate-client-integration"
    ),
    icon: "tickets-alt",
    category: "widgets",
    keywords: [
      __("discount", "affiliate-client-integration"),
      __("coupon", "affiliate-client-integration"),
      __("affiliate", "affiliate-client-integration"),
      __("promo", "affiliate-client-integration"),
    ],

    attributes: {
      code: {
        type: "string",
        default: "",
      },
      type: {
        type: "string",
        default: "discount",
      },
      affiliateId: {
        type: "string",
        default: "",
      },
      title: {
        type: "string",
        default: "",
      },
      description: {
        type: "string",
        default: "",
      },
      expiry: {
        type: "string",
        default: "",
      },
      style: {
        type: "string",
        default: "default",
      },
      color: {
        type: "string",
        default: "#4CAF50",
      },
      textColor: {
        type: "string",
        default: "#ffffff",
      },
      size: {
        type: "string",
        default: "medium",
      },
      showCopy: {
        type: "boolean",
        default: true,
      },
      showApply: {
        type: "boolean",
        default: true,
      },
      autoApply: {
        type: "boolean",
        default: false,
      },
      trackClicks: {
        type: "boolean",
        default: true,
      },
      buttonText: {
        type: "string",
        default: "",
      },
      copyText: {
        type: "string",
        default: "Copy Code",
      },
      successText: {
        type: "string",
        default: "Code Copied!",
      },
    },

    /**
     * Edit function - Editor interface
     */
    edit: function (props) {
      const { attributes, setAttributes, className } = props;

      return (
        <Fragment>
          {/* Inspector Controls - Sidebar Settings */}
          <InspectorControls>
            {/* Basic Settings */}
            <PanelBody
              title={__("Discount Settings", "affiliate-client-integration")}
              initialOpen={true}
            >
              <TextControl
                label={__("Discount Code", "affiliate-client-integration")}
                value={attributes.code}
                onChange={(value) =>
                  setAttributes({ code: value.toUpperCase() })
                }
                help={__(
                  "The discount code to display (will be converted to uppercase)",
                  "affiliate-client-integration"
                )}
                placeholder="SAVE20"
              />

              <SelectControl
                label={__("Code Type", "affiliate-client-integration")}
                value={attributes.type}
                options={[
                  {
                    label: __("Discount", "affiliate-client-integration"),
                    value: "discount",
                  },
                  {
                    label: __("Coupon", "affiliate-client-integration"),
                    value: "coupon",
                  },
                  {
                    label: __("Promo", "affiliate-client-integration"),
                    value: "promo",
                  },
                  {
                    label: __("Voucher", "affiliate-client-integration"),
                    value: "voucher",
                  },
                ]}
                onChange={(value) => setAttributes({ type: value })}
              />

              <TextControl
                label={__("Affiliate ID", "affiliate-client-integration")}
                value={attributes.affiliateId}
                onChange={(value) => setAttributes({ affiliateId: value })}
                help={__(
                  "Associate this discount with a specific affiliate",
                  "affiliate-client-integration"
                )}
              />

              <TextControl
                label={__("Title", "affiliate-client-integration")}
                value={attributes.title}
                onChange={(value) => setAttributes({ title: value })}
                placeholder={__(
                  "Special Discount",
                  "affiliate-client-integration"
                )}
              />

              <TextControl
                label={__("Description", "affiliate-client-integration")}
                value={attributes.description}
                onChange={(value) => setAttributes({ description: value })}
                placeholder={__(
                  "Save 20% on your purchase",
                  "affiliate-client-integration"
                )}
              />

              <TextControl
                label={__("Expiry Date", "affiliate-client-integration")}
                value={attributes.expiry}
                onChange={(value) => setAttributes({ expiry: value })}
                type="date"
                help={__(
                  "When this discount expires (optional)",
                  "affiliate-client-integration"
                )}
              />
            </PanelBody>

            {/* Display Settings */}
            <PanelBody
              title={__("Display Settings", "affiliate-client-integration")}
              initialOpen={false}
            >
              <SelectControl
                label={__("Style", "affiliate-client-integration")}
                value={attributes.style}
                options={[
                  {
                    label: __("Default", "affiliate-client-integration"),
                    value: "default",
                  },
                  {
                    label: __("Modern", "affiliate-client-integration"),
                    value: "modern",
                  },
                  {
                    label: __("Minimal", "affiliate-client-integration"),
                    value: "minimal",
                  },
                  {
                    label: __("Card", "affiliate-client-integration"),
                    value: "card",
                  },
                  {
                    label: __("Badge", "affiliate-client-integration"),
                    value: "badge",
                  },
                ]}
                onChange={(value) => setAttributes({ style: value })}
              />

              <SelectControl
                label={__("Size", "affiliate-client-integration")}
                value={attributes.size}
                options={[
                  {
                    label: __("Small", "affiliate-client-integration"),
                    value: "small",
                  },
                  {
                    label: __("Medium", "affiliate-client-integration"),
                    value: "medium",
                  },
                  {
                    label: __("Large", "affiliate-client-integration"),
                    value: "large",
                  },
                ]}
                onChange={(value) => setAttributes({ size: value })}
              />
            </PanelBody>

            {/* Color Settings */}
            <PanelColorSettings
              title={__("Color Settings", "affiliate-client-integration")}
              initialOpen={false}
              colorSettings={[
                {
                  value: attributes.color,
                  onChange: (value) => setAttributes({ color: value }),
                  label: __("Background Color", "affiliate-client-integration"),
                },
                {
                  value: attributes.textColor,
                  onChange: (value) => setAttributes({ textColor: value }),
                  label: __("Text Color", "affiliate-client-integration"),
                },
              ]}
            />

            {/* Button Settings */}
            <PanelBody
              title={__("Button Settings", "affiliate-client-integration")}
              initialOpen={false}
            >
              <ToggleControl
                label={__("Show Copy Button", "affiliate-client-integration")}
                checked={attributes.showCopy}
                onChange={(value) => setAttributes({ showCopy: value })}
              />

              <ToggleControl
                label={__("Show Apply Button", "affiliate-client-integration")}
                checked={attributes.showApply}
                onChange={(value) => setAttributes({ showApply: value })}
              />

              {attributes.showApply && (
                <ToggleControl
                  label={__(
                    "Auto-apply on Page Load",
                    "affiliate-client-integration"
                  )}
                  checked={attributes.autoApply}
                  onChange={(value) => setAttributes({ autoApply: value })}
                  help={__(
                    "Automatically apply this discount when the page loads",
                    "affiliate-client-integration"
                  )}
                />
              )}

              {attributes.showApply && (
                <TextControl
                  label={__(
                    "Apply Button Text",
                    "affiliate-client-integration"
                  )}
                  value={attributes.buttonText}
                  onChange={(value) => setAttributes({ buttonText: value })}
                  placeholder={__(
                    "Apply Discount",
                    "affiliate-client-integration"
                  )}
                />
              )}

              {attributes.showCopy && (
                <Fragment>
                  <TextControl
                    label={__(
                      "Copy Button Text",
                      "affiliate-client-integration"
                    )}
                    value={attributes.copyText}
                    onChange={(value) => setAttributes({ copyText: value })}
                    placeholder={__(
                      "Copy Code",
                      "affiliate-client-integration"
                    )}
                  />

                  <TextControl
                    label={__(
                      "Success Message",
                      "affiliate-client-integration"
                    )}
                    value={attributes.successText}
                    onChange={(value) => setAttributes({ successText: value })}
                    placeholder={__(
                      "Code Copied!",
                      "affiliate-client-integration"
                    )}
                  />
                </Fragment>
              )}
            </PanelBody>

            {/* Tracking Settings */}
            <PanelBody
              title={__("Tracking Settings", "affiliate-client-integration")}
              initialOpen={false}
            >
              <ToggleControl
                label={__("Track Clicks", "affiliate-client-integration")}
                checked={attributes.trackClicks}
                onChange={(value) => setAttributes({ trackClicks: value })}
                help={__(
                  "Track when users copy or apply this discount code",
                  "affiliate-client-integration"
                )}
              />
            </PanelBody>
          </InspectorControls>

          {/* Block Preview in Editor */}
          <div
            className={`${className} aci-discount-block-editor aci-discount-style-${attributes.style} aci-discount-size-${attributes.size}`}
          >
            <div
              className="aci-discount-preview"
              style={{
                backgroundColor: attributes.color,
                color: attributes.textColor,
                padding: "20px",
                borderRadius: "8px",
                border: "2px solid #ddd",
              }}
            >
              {attributes.title && (
                <div
                  style={{
                    fontSize: "18px",
                    fontWeight: "600",
                    marginBottom: "10px",
                  }}
                >
                  {attributes.title}
                </div>
              )}

              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  marginBottom: "10px",
                }}
              >
                <div>
                  <div
                    style={{
                      fontSize: "12px",
                      opacity: 0.9,
                      marginBottom: "5px",
                    }}
                  >
                    {attributes.type.toUpperCase()} CODE:
                  </div>
                  <div
                    style={{
                      fontSize: "24px",
                      fontWeight: "700",
                      fontFamily: "monospace",
                      letterSpacing: "2px",
                    }}
                  >
                    {attributes.code ||
                      __("ENTER-CODE", "affiliate-client-integration")}
                  </div>
                </div>

                {attributes.showCopy && (
                  <div style={{ marginLeft: "10px" }}>
                    <button
                      style={{
                        padding: "8px 16px",
                        backgroundColor: "rgba(0,0,0,0.1)",
                        border: "none",
                        borderRadius: "4px",
                        cursor: "not-allowed",
                        color: attributes.textColor,
                      }}
                      disabled
                    >
                      {attributes.copyText ||
                        __("Copy Code", "affiliate-client-integration")}
                    </button>
                  </div>
                )}
              </div>

              {attributes.description && (
                <div
                  style={{
                    fontSize: "14px",
                    opacity: 0.9,
                    marginBottom: "10px",
                  }}
                >
                  {attributes.description}
                </div>
              )}

              {attributes.expiry && (
                <div
                  style={{
                    fontSize: "12px",
                    opacity: 0.8,
                    marginBottom: "10px",
                  }}
                >
                  {__("Expires:", "affiliate-client-integration")}{" "}
                  {attributes.expiry}
                </div>
              )}

              {attributes.showApply && (
                <div>
                  <button
                    style={{
                      width: "100%",
                      padding: "12px",
                      backgroundColor: "rgba(0,0,0,0.2)",
                      border: "none",
                      borderRadius: "4px",
                      cursor: "not-allowed",
                      color: attributes.textColor,
                      fontWeight: "600",
                    }}
                    disabled
                  >
                    {attributes.buttonText ||
                      __("Apply Discount", "affiliate-client-integration")}
                  </button>
                </div>
              )}

              <div
                style={{
                  marginTop: "10px",
                  padding: "10px",
                  backgroundColor: "rgba(0,0,0,0.1)",
                  borderRadius: "4px",
                  fontSize: "12px",
                }}
              >
                <strong>
                  {__("Editor Preview", "affiliate-client-integration")}
                </strong>
                <br />
                {__(
                  "Buttons are disabled in the editor",
                  "affiliate-client-integration"
                )}
                {attributes.autoApply && (
                  <div>
                    ⚡{" "}
                    {__(
                      "Auto-apply enabled on frontend",
                      "affiliate-client-integration"
                    )}
                  </div>
                )}
                {attributes.trackClicks && (
                  <div>
                    📊{" "}
                    {__(
                      "Click tracking enabled",
                      "affiliate-client-integration"
                    )}
                  </div>
                )}
              </div>
            </div>

            {!attributes.code && (
              <div
                style={{
                  marginTop: "10px",
                  padding: "10px",
                  backgroundColor: "#fff3cd",
                  border: "1px solid #ffc107",
                  borderRadius: "4px",
                  color: "#856404",
                }}
              >
                ⚠️{" "}
                {__(
                  "Please enter a discount code in the block settings",
                  "affiliate-client-integration"
                )}
              </div>
            )}
          </div>
        </Fragment>
      );
    },

    /**
     * Save function - Frontend output
     * Returns null because we use dynamic rendering via PHP
     */
    save: function () {
      return null;
    },
  });

  /**
   * Register Discount Form Block
   */
  registerBlockType("aci/discount-form", {
    title: __("Affiliate Discount Form", "affiliate-client-integration"),
    description: __(
      "Add a form where users can enter and apply discount codes",
      "affiliate-client-integration"
    ),
    icon: "edit",
    category: "widgets",
    keywords: [
      __("discount", "affiliate-client-integration"),
      __("form", "affiliate-client-integration"),
      __("coupon", "affiliate-client-integration"),
      __("input", "affiliate-client-integration"),
    ],

    attributes: {
      placeholder: {
        type: "string",
        default: "Enter discount code",
      },
      buttonText: {
        type: "string",
        default: "Apply",
      },
      style: {
        type: "string",
        default: "default",
      },
      size: {
        type: "string",
        default: "medium",
      },
      color: {
        type: "string",
        default: "#4CAF50",
      },
      showValidation: {
        type: "boolean",
        default: true,
      },
      autoValidate: {
        type: "boolean",
        default: true,
      },
      trackUsage: {
        type: "boolean",
        default: true,
      },
      redirectAfter: {
        type: "string",
        default: "",
      },
      successMessage: {
        type: "string",
        default: "",
      },
      errorMessage: {
        type: "string",
        default: "",
      },
      headerContent: {
        type: "string",
        default: "",
      },
    },

    /**
     * Edit function - Editor interface
     */
    edit: function (props) {
      const { attributes, setAttributes, className } = props;

      return (
        <Fragment>
          <InspectorControls>
            {/* Form Settings */}
            <PanelBody
              title={__("Form Settings", "affiliate-client-integration")}
              initialOpen={true}
            >
              <TextControl
                label={__("Placeholder Text", "affiliate-client-integration")}
                value={attributes.placeholder}
                onChange={(value) => setAttributes({ placeholder: value })}
                placeholder={__(
                  "Enter discount code",
                  "affiliate-client-integration"
                )}
              />

              <TextControl
                label={__("Button Text", "affiliate-client-integration")}
                value={attributes.buttonText}
                onChange={(value) => setAttributes({ buttonText: value })}
                placeholder={__("Apply", "affiliate-client-integration")}
              />

              <TextControl
                label={__("Header Content", "affiliate-client-integration")}
                value={attributes.headerContent}
                onChange={(value) => setAttributes({ headerContent: value })}
                help={__(
                  "Optional text to display above the form",
                  "affiliate-client-integration"
                )}
              />
            </PanelBody>

            {/* Display Settings */}
            <PanelBody
              title={__("Display Settings", "affiliate-client-integration")}
              initialOpen={false}
            >
              <SelectControl
                label={__("Style", "affiliate-client-integration")}
                value={attributes.style}
                options={[
                  {
                    label: __("Default", "affiliate-client-integration"),
                    value: "default",
                  },
                  {
                    label: __("Inline", "affiliate-client-integration"),
                    value: "inline",
                  },
                  {
                    label: __("Stacked", "affiliate-client-integration"),
                    value: "stacked",
                  },
                  {
                    label: __("Minimal", "affiliate-client-integration"),
                    value: "minimal",
                  },
                ]}
                onChange={(value) => setAttributes({ style: value })}
              />

              <SelectControl
                label={__("Size", "affiliate-client-integration")}
                value={attributes.size}
                options={[
                  {
                    label: __("Small", "affiliate-client-integration"),
                    value: "small",
                  },
                  {
                    label: __("Medium", "affiliate-client-integration"),
                    value: "medium",
                  },
                  {
                    label: __("Large", "affiliate-client-integration"),
                    value: "large",
                  },
                ]}
                onChange={(value) => setAttributes({ size: value })}
              />
            </PanelBody>

            {/* Color Settings */}
            <PanelColorSettings
              title={__("Color Settings", "affiliate-client-integration")}
              initialOpen={false}
              colorSettings={[
                {
                  value: attributes.color,
                  onChange: (value) => setAttributes({ color: value }),
                  label: __("Primary Color", "affiliate-client-integration"),
                },
              ]}
            />

            {/* Validation Settings */}
            <PanelBody
              title={__("Validation Settings", "affiliate-client-integration")}
              initialOpen={false}
            >
              <ToggleControl
                label={__(
                  "Show Validation Messages",
                  "affiliate-client-integration"
                )}
                checked={attributes.showValidation}
                onChange={(value) => setAttributes({ showValidation: value })}
              />

              <ToggleControl
                label={__(
                  "Auto-validate on Input",
                  "affiliate-client-integration"
                )}
                checked={attributes.autoValidate}
                onChange={(value) => setAttributes({ autoValidate: value })}
                help={__(
                  "Validate the code as the user types",
                  "affiliate-client-integration"
                )}
              />

              <TextControl
                label={__(
                  "Custom Success Message",
                  "affiliate-client-integration"
                )}
                value={attributes.successMessage}
                onChange={(value) => setAttributes({ successMessage: value })}
                placeholder={__(
                  "Discount applied successfully!",
                  "affiliate-client-integration"
                )}
              />

              <TextControl
                label={__(
                  "Custom Error Message",
                  "affiliate-client-integration"
                )}
                value={attributes.errorMessage}
                onChange={(value) => setAttributes({ errorMessage: value })}
                placeholder={__(
                  "Invalid discount code",
                  "affiliate-client-integration"
                )}
              />
            </PanelBody>

            {/* Behaviour Settings */}
            <PanelBody
              title={__("Behaviour Settings", "affiliate-client-integration")}
              initialOpen={false}
            >
              <ToggleControl
                label={__("Track Form Usage", "affiliate-client-integration")}
                checked={attributes.trackUsage}
                onChange={(value) => setAttributes({ trackUsage: value })}
              />

              <TextControl
                label={__(
                  "Redirect After Success",
                  "affiliate-client-integration"
                )}
                value={attributes.redirectAfter}
                onChange={(value) => setAttributes({ redirectAfter: value })}
                type="url"
                placeholder="https://example.com/checkout"
                help={__(
                  "Optional URL to redirect to after successful code application",
                  "affiliate-client-integration"
                )}
              />
            </PanelBody>
          </InspectorControls>

          {/* Block Preview in Editor */}
          <div
            className={`${className} aci-discount-form-block-editor aci-form-style-${attributes.style} aci-form-size-${attributes.size}`}
            style={{
              padding: "20px",
              backgroundColor: "#f9f9f9",
              borderRadius: "8px",
            }}
          >
            {attributes.headerContent && (
              <div
                style={{
                  marginBottom: "15px",
                  fontSize: "16px",
                  fontWeight: "500",
                }}
              >
                {attributes.headerContent}
              </div>
            )}

            <div style={{ display: "flex", gap: "10px", marginBottom: "10px" }}>
              <input
                type="text"
                placeholder={attributes.placeholder}
                style={{
                  flex: 1,
                  padding: "12px",
                  border: `2px solid ${attributes.color}`,
                  borderRadius: "6px",
                  fontSize: "16px",
                  fontFamily: "monospace",
                }}
                disabled
              />
              <button
                style={{
                  padding: "12px 24px",
                  backgroundColor: attributes.color,
                  color: "#fff",
                  border: "none",
                  borderRadius: "6px",
                  fontWeight: "600",
                  cursor: "not-allowed",
                }}
                disabled
              >
                {attributes.buttonText}
              </button>
            </div>

            <div
              style={{
                padding: "12px",
                backgroundColor: "rgba(33, 150, 243, 0.1)",
                border: "1px solid rgba(33, 150, 243, 0.3)",
                borderRadius: "6px",
                fontSize: "13px",
                color: "#1976d2",
              }}
            >
              <strong>
                {__("Editor Preview", "affiliate-client-integration")}
              </strong>
              <br />
              {__(
                "Form is disabled in the editor",
                "affiliate-client-integration"
              )}
              {attributes.autoValidate && (
                <div>
                  ⚡{" "}
                  {__(
                    "Auto-validation enabled",
                    "affiliate-client-integration"
                  )}
                </div>
              )}
              {attributes.trackUsage && (
                <div>
                  📊{" "}
                  {__("Usage tracking enabled", "affiliate-client-integration")}
                </div>
              )}
              {attributes.redirectAfter && (
                <div>
                  🔄 {__("Redirects to:", "affiliate-client-integration")}{" "}
                  {attributes.redirectAfter}
                </div>
              )}
            </div>
          </div>
        </Fragment>
      );
    },

    /**
     * Save function - Frontend output
     */
    save: function () {
      return null;
    },
  });
})(window.wp);