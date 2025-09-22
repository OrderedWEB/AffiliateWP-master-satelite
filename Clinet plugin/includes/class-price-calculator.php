<?php
/**
 * Price Calculator Class
 * File: /wp-content/plugins/affiliate-client-integration/includes/class-price-calculator.php
 * Plugin: Affiliate Client Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ACI_Price_Calculator {

    /**
     * Tax rates by region
     */
    private $tax_rates;

    /**
     * Discount rules
     */
    private $discount_rules;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_tax_rates();
        $this->load_discount_rules();
        
        add_action('wp_ajax_aci_calculate_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_aci_calculate_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_aci_apply_discount', [$this, 'ajax_apply_discount']);
        add_action('wp_ajax_nopriv_aci_apply_discount', [$this, 'ajax_apply_discount']);
    }

    /**
     * Load tax rates from settings
     */
    private function load_tax_rates() {
        $this->tax_rates = get_option('aci_tax_rates', [
            'US' => [
                'CA' => 0.0875, // California
                'NY' => 0.08,   // New York
                'TX' => 0.0625, // Texas
                'FL' => 0.06,   // Florida
                'default' => 0.05
            ],
            'CA' => [
                'ON' => 0.13,   // Ontario
                'BC' => 0.12,   // British Columbia
                'AB' => 0.05,   // Alberta
                'default' => 0.10
            ],
            'GB' => 0.20,       // UK VAT
            'DE' => 0.19,       // Germany VAT
            'FR' => 0.20,       // France VAT
            'default' => 0.00
        ]);
    }

    /**
     * Load discount rules from settings
     */
    private function load_discount_rules() {
        $this->discount_rules = get_option('aci_discount_rules', [
            'percentage_based' => [
                'min_amount' => 100,
                'max_discount' => 50,
                'tiers' => [
                    100 => 5,   // 5% off orders over $100
                    250 => 10,  // 10% off orders over $250
                    500 => 15,  // 15% off orders over $500
                    1000 => 20  // 20% off orders over $1000
                ]
            ],
            'fixed_amount' => [
                'enabled' => true,
                'max_discount' => 100
            ],
            'product_specific' => [
                'enabled' => true,
                'rules' => []
            ]
        ]);
    }

    /**
     * Calculate total price with taxes and discounts
     */
    public function calculate_total_price($items, $location = [], $affiliate_code = null, $options = []) {
        $calculation = [
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'total' => 0,
            'breakdown' => [],
            'applied_discounts' => [],
            'tax_details' => []
        ];

        // Calculate subtotal
        foreach ($items as $item) {
            $item_total = $this->calculate_item_price($item);
            $calculation['subtotal'] += $item_total;
            
            $calculation['breakdown'][] = [
                'name' => $item['name'] ?? 'Item',
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['price'] ?? 0,
                'total' => $item_total,
                'tax_category' => $item['tax_category'] ?? 'standard'
            ];
        }

        // Apply affiliate discount
        if ($affiliate_code) {
            $discount = $this->calculate_affiliate_discount($affiliate_code, $calculation['subtotal'], $items);
            if ($discount > 0) {
                $calculation['discount_amount'] = $discount;
                $calculation['applied_discounts'][] = [
                    'type' => 'affiliate',
                    'code' => $affiliate_code,
                    'amount' => $discount,
                    'description' => sprintf(__('Affiliate discount (%s)', 'affiliate-client-integration'), $affiliate_code)
                ];
            }
        }

        // Apply additional discounts
        if (!empty($options['discount_codes'])) {
            foreach ($options['discount_codes'] as $discount_code) {
                $discount = $this->apply_discount_code($discount_code, $calculation['subtotal'], $items);
                if ($discount > 0 && is_array($discount)) {
                    $calculation['discount_amount'] += $discount['amount'];
                    $calculation['applied_discounts'][] = $discount;
                }
            }
        }

        // Calculate discounted subtotal
        $discounted_subtotal = $calculation['subtotal'] - $calculation['discount_amount'];

        // Calculate shipping
        if (!empty($options['shipping'])) {
            $calculation['shipping_amount'] = $this->calculate_shipping($items, $location, $options['shipping'], $discounted_subtotal);
        }

        // Calculate taxes
        $tax_calculation = $this->calculate_taxes($discounted_subtotal, $calculation['shipping_amount'], $location, $items);
        $calculation['tax_amount'] = $tax_calculation['total_tax'];
        $calculation['tax_details'] = $tax_calculation['details'];

        // Calculate final total
        $calculation['total'] = $discounted_subtotal + $calculation['tax_amount'] + $calculation['shipping_amount'];

        // Apply any final adjustments
        $calculation = apply_filters('aci_price_calculation_final', $calculation, $items, $location, $affiliate_code, $options);

        return $calculation;
    }

    /**
     * Calculate individual item price
     */
    private function calculate_item_price($item) {
        $base_price = floatval($item['price'] ?? 0);
        $quantity = intval($item['quantity'] ?? 1);

        // Apply item-specific modifiers
        if (!empty($item['modifiers'])) {
            foreach ($item['modifiers'] as $modifier) {
                switch ($modifier['type']) {
                    case 'percentage':
                        $base_price = $base_price * (1 + floatval($modifier['value']) / 100);
                        break;
                    case 'fixed':
                        $base_price += floatval($modifier['value']);
                        break;
                }
            }
        }

        return $base_price * $quantity;
    }

    /**
     * Calculate affiliate discount
     */
    public function calculate_affiliate_discount($affiliate_code, $subtotal, $items = []) {
        // Get affiliate discount settings
        $affiliate_discount = $this->get_affiliate_discount_rate($affiliate_code);
        
        if (!$affiliate_discount || empty($affiliate_discount['rate']) || $affiliate_discount['rate'] <= 0) {
            return 0;
        }

        $discount_amount = 0;

        switch ($affiliate_discount['type']) {
            case 'percentage':
                $discount_amount = $subtotal * (floatval($affiliate_discount['rate']) / 100);
                break;
                
            case 'fixed':
                $discount_amount = floatval($affiliate_discount['rate']);
                break;
                
            case 'tiered':
                $discount_amount = $this->calculate_tiered_discount($subtotal, $affiliate_discount['tiers']);
                break;
                
            case 'product_specific':
                $discount_amount = $this->calculate_product_specific_discount($items, $affiliate_discount['products']);
                break;
        }

        // Apply minimum and maximum constraints
        if (!empty($affiliate_discount['min_order_amount']) && $subtotal < $affiliate_discount['min_order_amount']) {
            return 0;
        }

        if (!empty($affiliate_discount['max_discount']) && $discount_amount > $affiliate_discount['max_discount']) {
            $discount_amount = $affiliate_discount['max_discount'];
        }

        // Ensure discount doesn't exceed subtotal
        if ($discount_amount > $subtotal) {
            $discount_amount = $subtotal;
        }

        return round($discount_amount, 2);
    }

    /**
     * Get affiliate discount rate from master plugin
     */
    private function get_affiliate_discount_rate($affiliate_code) {
        // Check local cache first
        $cached_rate = get_transient('aci_affiliate_discount_' . $affiliate_code);
        if ($cached_rate !== false) {
            return $cached_rate;
        }

        // Query master plugin API
        $master_domain = get_option('aci_master_domain', '');
        $api_key = get_option('aci_api_key', '');

        if (empty($master_domain) || empty($api_key)) {
            return false;
        }

        $response = wp_remote_get($master_domain . '/wp-json/affcd/v1/affiliate/' . $affiliate_code . '/discount', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'X-Domain' => home_url()
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            error_log('ACI: Failed to get affiliate discount rate - ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['data']['discount'])) {
            $discount_data = $body['data']['discount'];
            
            // Cache for 5 minutes
            set_transient('aci_affiliate_discount_' . $affiliate_code, $discount_data, 300);
            
            return $discount_data;
        }

        return false;
    }

    /**
     * Calculate tiered discount
     */
    private function calculate_tiered_discount($amount, $tiers) {
        $discount = 0;
        
        foreach ($tiers as $threshold => $rate) {
            if ($amount >= $threshold) {
                $discount = $amount * ($rate / 100);
            }
        }
        
        return $discount;
    }

    /**
     * Calculate product-specific discount
     */
    private function calculate_product_specific_discount($items, $product_discounts) {
        $total_discount = 0;
        
        foreach ($items as $item) {
            $product_id = $item['product_id'] ?? '';
            $product_sku = $item['sku'] ?? '';
            
            foreach ($product_discounts as $discount_rule) {
                $applies = false;
                
                if (!empty($discount_rule['product_ids']) && in_array($product_id, $discount_rule['product_ids'])) {
                    $applies = true;
                } elseif (!empty($discount_rule['skus']) && in_array($product_sku, $discount_rule['skus'])) {
                    $applies = true;
                } elseif (!empty($discount_rule['categories'])) {
                    $product_categories = $item['categories'] ?? [];
                    if (array_intersect($product_categories, $discount_rule['categories'])) {
                        $applies = true;
                    }
                }
                
                if ($applies) {
                    $item_price = $this->calculate_item_price($item);
                    
                    if ($discount_rule['type'] === 'percentage') {
                        $total_discount += $item_price * ($discount_rule['value'] / 100);
                    } else {
                        $total_discount += $discount_rule['value'] * ($item['quantity'] ?? 1);
                    }
                    break; // Apply first matching rule only
                }
            }
        }
        
        return $total_discount;
    }

    /**
     * Calculate taxes based on location
     */
    private function calculate_taxes($subtotal, $shipping_amount, $location, $items = []) {
        $tax_calculation = [
            'total_tax' => 0,
            'details' => []
        ];

        $country = $location['country'] ?? '';
        $state = $location['state'] ?? '';
        
        if (empty($country)) {
            return $tax_calculation;
        }

        // Get tax rate for location
        $tax_rate = $this->get_tax_rate($country, $state);
        
        if ($tax_rate <= 0) {
            return $tax_calculation;
        }

        // Calculate tax on subtotal
        $subtotal_tax = $subtotal * $tax_rate;
        $tax_calculation['total_tax'] += $subtotal_tax;
        $tax_calculation['details'][] = [
            'name' => 'Sales Tax',
            'rate' => $tax_rate * 100,
            'amount' => $subtotal_tax,
            'taxable_amount' => $subtotal
        ];

        // Calculate tax on shipping if applicable
        $shipping_taxable = get_option('aci_shipping_taxable', false);
        if ($shipping_taxable && $shipping_amount > 0) {
            $shipping_tax = $shipping_amount * $tax_rate;
            $tax_calculation['total_tax'] += $shipping_tax;
            $tax_calculation['details'][] = [
                'name' => 'Shipping Tax',
                'rate' => $tax_rate * 100,
                'amount' => $shipping_tax,
                'taxable_amount' => $shipping_amount
            ];
        }

        return $tax_calculation;
    }

    /**
     * Get tax rate for location
     */
    private function get_tax_rate($country, $state = '') {
        $country = strtoupper($country);
        $state = strtoupper($state);

        if (isset($this->tax_rates[$country])) {
            if (is_array($this->tax_rates[$country])) {
                if (!empty($state) && isset($this->tax_rates[$country][$state])) {
                    return $this->tax_rates[$country][$state];
                }
                return $this->tax_rates[$country]['default'] ?? 0;
            }
            return $this->tax_rates[$country];
        }

        return $this->tax_rates['default'] ?? 0;
    }

    /**
     * Calculate shipping costs
     */
    private function calculate_shipping($items, $location, $shipping_options, $subtotal = 0) {
        $shipping_amount = 0;
        $total_weight = 0;
        $total_items = 0;

        // Calculate totals
        foreach ($items as $item) {
            $total_weight += floatval($item['weight'] ?? 0) * intval($item['quantity'] ?? 1);
            $total_items += intval($item['quantity'] ?? 1);
        }

        // Apply shipping rules
        switch ($shipping_options['method']) {
            case 'flat_rate':
                $shipping_amount = floatval($shipping_options['rate'] ?? 0);
                break;
                
            case 'weight_based':
                $shipping_amount = $total_weight * floatval($shipping_options['rate_per_unit'] ?? 0);
                break;
                
            case 'item_based':
                $shipping_amount = $total_items * floatval($shipping_options['rate_per_item'] ?? 0);
                break;
                
            case 'zone_based':
                $zone = $this->get_shipping_zone($location);
                $shipping_amount = $shipping_options['zones'][$zone] ?? 0;
                break;
                
            case 'free':
                $shipping_amount = 0;
                break;
        }

        // Apply free shipping thresholds
        $free_shipping_threshold = floatval($shipping_options['free_shipping_threshold'] ?? 0);
        if ($free_shipping_threshold > 0 && $subtotal >= $free_shipping_threshold) {
            $shipping_amount = 0;
        }

        return $shipping_amount;
    }

    /**
     * Get shipping zone for location
     */
    private function get_shipping_zone($location) {
        $country = $location['country'] ?? '';
        $state = $location['state'] ?? '';
        
        $shipping_zones = get_option('aci_shipping_zones', [
            'domestic' => ['US'],
            'international' => ['CA', 'GB', 'AU'],
            'europe' => ['DE', 'FR', 'IT', 'ES', 'NL']
        ]);

        foreach ($shipping_zones as $zone => $countries) {
            if (in_array($country, $countries)) {
                return $zone;
            }
        }

        return 'default';
    }

    /**
     * Apply discount code
     */
    public function apply_discount_code($discount_code, $subtotal, $items = []) {
        $discount_data = $this->get_discount_code_data($discount_code);
        
        if (!$discount_data || !$this->validate_discount_code($discount_data, $subtotal, $items)) {
            return 0;
        }

        $discount_amount = 0;

        switch ($discount_data['type']) {
            case 'percentage':
                $discount_amount = $subtotal * (floatval($discount_data['value']) / 100);
                break;
                
            case 'fixed':
                $discount_amount = floatval($discount_data['value']);
                break;
        }

        // Apply constraints
        if (!empty($discount_data['max_discount']) && $discount_amount > $discount_data['max_discount']) {
            $discount_amount = $discount_data['max_discount'];
        }

        if ($discount_amount > $subtotal) {
            $discount_amount = $subtotal;
        }

        return [
            'type' => 'discount_code',
            'code' => $discount_code,
            'amount' => round($discount_amount, 2),
            'description' => $discount_data['description'] ?? sprintf(__('Discount code: %s', 'affiliate-client-integration'), $discount_code)
        ];
    }

    /**
     * Get discount code data
     */
    private function get_discount_code_data($discount_code) {
        $discount_codes = get_option('aci_discount_codes', []);
        return $discount_codes[$discount_code] ?? false;
    }

    /**
     * Validate discount code
     */
    private function validate_discount_code($discount_data, $subtotal, $items) {
        // Check if active
        if (empty($discount_data['active'])) {
            return false;
        }

        // Check date range
        $now = current_time('timestamp');
        if (!empty($discount_data['start_date']) && $now < strtotime($discount_data['start_date'])) {
            return false;
        }
        if (!empty($discount_data['end_date']) && $now > strtotime($discount_data['end_date'])) {
            return false;
        }

        // Check minimum order amount
        if (!empty($discount_data['min_order_amount']) && $subtotal < $discount_data['min_order_amount']) {
            return false;
        }

        // Check usage limits
        if (!empty($discount_data['usage_limit'])) {
            $current_usage = get_option('aci_discount_usage_' . $discount_data['code'], 0);
            if ($current_usage >= $discount_data['usage_limit']) {
                return false;
            }
        }

        return true;
    }

    /**
     * AJAX handler for price calculation
     */
    public function ajax_calculate_price() {
        check_ajax_referer('aci_nonce', 'nonce');

        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);
        $location = $_POST['location'] ?? [];
        $affiliate_code = $_POST['affiliate_code'] ?? '';
        $options = $_POST['options'] ?? [];

        // Sanitize inputs
        $items = $this->sanitize_items($items);
        $location = $this->sanitize_location($location);
        $affiliate_code = sanitize_text_field($affiliate_code);

        try {
            $calculation = $this->calculate_total_price($items, $location, $affiliate_code, $options);

            wp_send_json_success([
                'calculation' => $calculation,
                'formatted' => $this->format_price_calculation($calculation)
            ]);
        } catch (Exception $e) {
            wp_send_json_error(__('Price calculation failed', 'affiliate-client-integration'));
        }
    }

    /**
     * AJAX handler for applying discount
     */
    public function ajax_apply_discount() {
        check_ajax_referer('aci_nonce', 'nonce');

        $affiliate_code = sanitize_text_field($_POST['affiliate_code'] ?? '');
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);
        $items = $this->sanitize_items($items);

        if (empty($affiliate_code) || $subtotal <= 0) {
            wp_send_json_error(__('Invalid discount request', 'affiliate-client-integration'));
        }

        $discount = $this->calculate_affiliate_discount($affiliate_code, $subtotal, $items);

        if ($discount > 0) {
            wp_send_json_success([
                'discount_amount' => $discount,
                'formatted_discount' => $this->format_currency($discount),
                'new_total' => $subtotal - $discount,
                'formatted_new_total' => $this->format_currency($subtotal - $discount),
                'message' => sprintf(
                    __('Affiliate discount of %s applied!', 'affiliate-client-integration'),
                    $this->format_currency($discount)
                )
            ]);
        } else {
            wp_send_json_error(__('Invalid or expired affiliate code', 'affiliate-client-integration'));
        }
    }

    /**
     * Sanitize items array
     */
    private function sanitize_items($items) {
        if (!is_array($items)) {
            return [];
        }

        $sanitized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            
            $sanitized[] = [
                'name' => sanitize_text_field($item['name'] ?? ''),
                'price' => floatval($item['price'] ?? 0),
                'quantity' => intval($item['quantity'] ?? 1),
                'weight' => floatval($item['weight'] ?? 0),
                'product_id' => sanitize_text_field($item['product_id'] ?? ''),
                'sku' => sanitize_text_field($item['sku'] ?? ''),
                'tax_category' => sanitize_text_field($item['tax_category'] ?? 'standard'),
                'categories' => array_map('sanitize_text_field', $item['categories'] ?? []),
                'modifiers' => is_array($item['modifiers'] ?? null) ? $item['modifiers'] : []
            ];
        }

        return $sanitized;
    }

    /**
     * Sanitize location array
     */
    private function sanitize_location($location) {
        if (!is_array($location)) {
            return [];
        }

        return [
            'country' => sanitize_text_field($location['country'] ?? ''),
            'state' => sanitize_text_field($location['state'] ?? ''),
            'city' => sanitize_text_field($location['city'] ?? ''),
            'zip' => sanitize_text_field($location['zip'] ?? '')
        ];
    }

    /**
     * Format price calculation for display
     */
    private function format_price_calculation($calculation) {
        return [
            'subtotal' => $this->format_currency($calculation['subtotal']),
            'discount_amount' => $this->format_currency($calculation['discount_amount']),
            'tax_amount' => $this->format_currency($calculation['tax_amount']),
            'shipping_amount' => $this->format_currency($calculation['shipping_amount']),
            'total' => $this->format_currency($calculation['total']),
            'savings' => $this->format_currency($calculation['discount_amount'])
        ];
    }

    /**
     * Format currency amount
     */
    private function format_currency($amount) {
        $currency_symbol = get_option('aci_currency_symbol', '$');
        $currency_position = get_option('aci_currency_position', 'before');
        $decimals = get_option('aci_currency_decimals', 2);
        
        $formatted_amount = number_format($amount, $decimals);
        
        if ($currency_position === 'before') {
            return $currency_symbol . $formatted_amount;
        } else {
            return $formatted_amount . $currency_symbol;
        }
    }

    /**
     * Get price breakdown for display
     */
    public function get_price_breakdown($calculation) {
        $breakdown = [];

        $breakdown[] = [
            'label' => __('Subtotal', 'affiliate-client-integration'),
            'amount' => $calculation['subtotal'],
            'formatted' => $this->format_currency($calculation['subtotal'])
        ];

        if ($calculation['discount_amount'] > 0) {
            $breakdown[] = [
                'label' => __('Discount', 'affiliate-client-integration'),
                'amount' => -$calculation['discount_amount'],
                'formatted' => '-' . $this->format_currency($calculation['discount_amount']),
                'type' => 'discount'
            ];
        }

        if ($calculation['shipping_amount'] > 0) {
            $breakdown[] = [
                'label' => __('Shipping', 'affiliate-client-integration'),
                'amount' => $calculation['shipping_amount'],
                'formatted' => $this->format_currency($calculation['shipping_amount'])
            ];
        }

        if ($calculation['tax_amount'] > 0) {
            $breakdown[] = [
                'label' => __('Tax', 'affiliate-client-integration'),
                'amount' => $calculation['tax_amount'],
                'formatted' => $this->format_currency($calculation['tax_amount'])
            ];
        }

        $breakdown[] = [
            'label' => __('Total', 'affiliate-client-integration'),
            'amount' => $calculation['total'],
            'formatted' => $this->format_currency($calculation['total']),
            'type' => 'total'
        ];

        return $breakdown;
    }
}
?>