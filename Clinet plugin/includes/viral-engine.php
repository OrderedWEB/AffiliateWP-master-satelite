<?php
/**
 * Revolutionary Features for Affiliate Cross Domain Satellite Plugin
 * 
 * Feature #21: Viral Coefficient Maximization
 * Feature #24: Cross-Platform Identity Resolution  
 * Feature #20: Quantum Attribution System
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Viral Coefficient Maximization System
 * Automatically turns customers into affiliates and maximizes viral growth
 */
class AFFCD_Viral_Engine {

    private $parent;
    private $viral_triggers = [];

    public function __construct($parent) {
        $this->parent = $parent;
        $this->init_viral_triggers();
        $this->init_hooks();
    }

    /**
     * Initialse WordPress hooks
     */
    private function init_hooks() {
        // Post-purchase hooks
        add_action('woocommerce_order_status_completed', [$this, 'trigger_post_purchase_viral'], 10, 1);
        add_action('edd_complete_purchase', [$this, 'trigger_edd_post_purchase_viral'], 10, 1);
        
        // Form submission viral triggers
        add_action('affcd_form_submission_processed', [$this, 'trigger_form_viral'], 10, 2);
        
        // Social sharing viral triggers
        add_action('wp_footer', [$this, 'inject_viral_sharing_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_affcd_viral_invite', [$this, 'ajax_viral_invite']);
        add_action('wp_ajax_nopriv_affcd_viral_invite', [$this, 'ajax_viral_invite']);
        add_action('wp_ajax_affcd_social_share_track', [$this, 'ajax_track_social_share']);
        add_action('wp_ajax_nopriv_affcd_social_share_track', [$this, 'ajax_track_social_share']);
        
        // Viral loop optimization
        add_action('affcd_viral_conversion', [$this, 'optimize_viral_loop'], 10, 3);
        
        // Shortcodes
        add_shortcode('affcd_viral_invite', [$this, 'viral_invite_shortcode']);
        add_shortcode('affcd_referral_widget', [$this, 'referral_widget_shortcode']);
    }

    /**
     * Initialse viral triggers and thresholds
     */
    private function init_viral_triggers() {
        $this->viral_triggers = [
            'post_purchase' => [
                'enabled' => get_option('affcd_viral_post_purchase', true),
                'delay' => get_option('affcd_viral_post_purchase_delay', 24), // hours
                'threshold' => get_option('affcd_viral_post_purchase_threshold', 50), // minimum order value
                'incentive' => get_option('affcd_viral_post_purchase_incentive', 10), // percentage
                'success_rate' => 0.15 // 15% conversion rate
            ],
            'high_engagement' => [
                'enabled' => get_option('affcd_viral_engagement', true),
                'page_views' => 5,
                'time_on_site' => 300, // 5 minutes
                'scroll_depth' => 75, // percentage
                'incentive' => 5,
                'success_rate' => 0.08
            ],
            'social_share' => [
                'enabled' => get_option('affcd_viral_social', true),
                'platforms' => ['facebook', 'twitter', 'linkedin', 'whatsapp'],
                'incentive' => 5,
                'viral_multiplier' => 2.3,
                'success_rate' => 0.12
            ],
            'form_completion' => [
                'enabled' => get_option('affcd_viral_forms', true),
                'high_value_forms' => ['contact', 'quote', 'demo'],
                'incentive' => 15,
                'success_rate' => 0.22
            ],
            'return_visitor' => [
                'enabled' => get_option('affcd_viral_return', true),
                'visit_threshold' => 3,
                'incentive' => 8,
                'success_rate' => 0.18
            ]
        ];
    }

    /**
     * Trigger post-purchase viral invitation (WooCommerce)
     */
    public function trigger_post_purchase_viral($order_id) {
        if (!$this->viral_triggers['post_purchase']['enabled']) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order_total = $order->get_total();
        $threshold = $this->viral_triggers['post_purchase']['threshold'];

        if ($order_total >= $threshold) {
            $customer_data = [
                'email' => $order->get_billing_email(),
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'order_id' => $order_id,
                'order_total' => $order_total,
                'trigger_type' => 'post_purchase'
            ];

            $this->schedule_viral_invitation($customer_data, $this->viral_triggers['post_purchase']['delay']);
        }
    }

    /**
     * Trigger viral invitation for EDD purchases
     */
    public function trigger_edd_post_purchase_viral($payment_id) {
        if (!$this->viral_triggers['post_purchase']['enabled']) {
            return;
        }

        $payment = edd_get_payment($payment_id);
        if (!$payment) {
            return;
        }

        $customer_data = [
            'email' => $payment->email,
            'name' => $payment->first_name . ' ' . $payment->last_name,
            'payment_id' => $payment_id,
            'payment_total' => $payment->total,
            'trigger_type' => 'post_purchase'
        ];

        $this->schedule_viral_invitation($customer_data, $this->viral_triggers['post_purchase']['delay']);
    }

    /**
     * Trigger viral invitation for form submissions
     */
    public function trigger_form_viral($form_data, $submission_meta) {
        if (!$this->viral_triggers['form_completion']['enabled']) {
            return;
        }

        $form_type = $this->detect_form_type($form_data);
        $high_value_forms = $this->viral_triggers['form_completion']['high_value_forms'];

        if (in_array($form_type, $high_value_forms)) {
            $customer_data = [
                'email' => $this->extract_email_from_form($form_data),
                'name' => $this->extract_name_from_form($form_data),
                'form_id' => $submission_meta['form_id'],
                'form_type' => $form_type,
                'trigger_type' => 'form_completion'
            ];

            if ($customer_data['email']) {
                $this->schedule_viral_invitation($customer_data, 1); // 1 hour delay
            }
        }
    }

    /**
     * Schedule viral invitation
     */
    private function schedule_viral_invitation($customer_data, $delay_hours) {
        $scheduled_time = time() + ($delay_hours * 3600);
        
        wp_schedule_single_event($scheduled_time, 'affcd_send_viral_invitation', [$customer_data]);
        
        // Store viral opportunity
        $this->store_viral_opportunity($customer_data);
    }

    /**
     * Store viral opportunity for tracking
     */
    private function store_viral_opportunity($customer_data) {
        global $wpdb;

        $viral_score = $this->calculate_viral_potential($customer_data);
        
        $wpdb->insert(
            $wpdb->prefix . 'affcd_viral_opportunities',
            [
                'customer_email' => $customer_data['email'],
                'customer_name' => $customer_data['name'],
                'trigger_type' => $customer_data['trigger_type'],
                'viral_score' => $viral_score,
                'incentive_offered' => $this->viral_triggers[$customer_data['trigger_type']]['incentive'],
                'metadata' => json_encode($customer_data),
                'status' => 'scheduled',
                'created_at' => current_time('mysql')
            ]
        );
    }

    /**
     * Calculate viral potential score
     */
    private function calculate_viral_potential($customer_data) {
        $base_score = 50;
        $trigger_type = $customer_data['trigger_type'];
        
        // Trigger-specific scoring
        switch ($trigger_type) {
            case 'post_purchase':
                $order_value = $customer_data['order_total'] ?? 0;
                $base_score += min(($order_value / 100) * 10, 30); // Up to 30 points for high-value orders
                break;
                
            case 'form_completion':
                $form_type = $customer_data['form_type'] ?? '';
                $form_scores = ['demo' => 25, 'quote' => 20, 'contact' => 15];
                $base_score += $form_scores[$form_type] ?? 10;
                break;
                
            case 'high_engagement':
                $base_score += 15; // High engagement users are more likely to refer
                break;
        }

        // Email domain scoring (corporate emails score higher)
        $email_domain = substr(strrchr($customer_data['email'], "@"), 1);
        $corporate_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        if (!in_array($email_domain, $corporate_domains)) {
            $base_score += 10; // Corporate email domains often have higher viral coefficient
        }

        // Historical data scoring
        $historical_score = $this->get_historical_viral_score($customer_data['email']);
        $base_score += $historical_score;

        return min($base_score, 100);
    }

    /**
     * Get historical viral performance for customer
     */
    private function get_historical_viral_score($email) {
        global $wpdb;

        $history = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_invites,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as conversions,
                AVG(viral_score) as avg_score
            FROM {$wpdb->prefix}affcd_viral_opportunities 
            WHERE customer_email = %s
        ", $email));

        if (!$history || $history->total_invites == 0) {
            return 0;
        }

        $conversion_rate = $history->conversions / $history->total_invites;
        
        // High performers get bonus points
        if ($conversion_rate > 0.3) {
            return 15;
        } elseif ($conversion_rate > 0.15) {
            return 10;
        } elseif ($conversion_rate > 0.05) {
            return 5;
        }

        return -5; // Penalty for poor performance
    }

    /**
     * Send viral invitation
     */
    public function send_viral_invitation($customer_data) {
        $viral_token = $this->generate_viral_token($customer_data);
        $incentive = $this->viral_triggers[$customer_data['trigger_type']]['incentive'];
        
        // Create personalized referral link
        $referral_link = $this->create_referral_link($customer_data['email'], $viral_token);
        
        // Send email invitation
        $email_sent = $this->send_viral_email($customer_data, $referral_link, $incentive);
        
        if ($email_sent) {
            $this->update_viral_status($customer_data['email'], 'sent');
            
            // Track viral campaign
            $this->track_viral_campaign_start($customer_data, $viral_token);
        }

        return $email_sent;
    }

    /**
     * Generate unique viral token
     */
    private function generate_viral_token($customer_data) {
        return hash('sha256', $customer_data['email'] . time() . wp_generate_password(32, false));
    }

    /**
     * Create personalized referral link
     */
    private function create_referral_link($email, $viral_token) {
        $base_url = home_url();
        $params = [
            'viral_ref' => $viral_token,
            'utm_source' => 'viral_referral',
            'utm_medium' => 'email',
            'utm_campaign' => 'customer_referral'
        ];

        return add_query_arg($params, $base_url);
    }

    /**
     * Send viral invitation email
     */
    private function send_viral_email($customer_data, $referral_link, $incentive) {
        $to = $customer_data['email'];
        $subject = sprintf(__('Earn %s%% sharing %s with friends!', 'affcd-satellite'), $incentive, get_bloginfo('name'));
        
        $template_data = [
            'customer_name' => $customer_data['name'],
            'incentive_percentage' => $incentive,
            'referral_link' => $referral_link,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'unsubscribe_link' => $this->get_unsubscribe_link($customer_data['email'])
        ];

        $message = $this->get_viral_email_template($template_data);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get viral email template
     */
    private function get_viral_email_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($data['site_name']); ?> - Referral Opportunity</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px 20px; background: #f9f9f9; }
                .cta-button { 
                    display: inline-block; 
                    background: #00a32a; 
                    color: white; 
                    padding: 15px 30px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    font-weight: bold;
                    margin: 20px 0;
                }
                .benefits { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>You Could Earn <?php echo esc_html($data['incentive_percentage']); ?>% Commission!</h1>
                    <p>Thanks for being an amazing customer, <?php echo esc_html($data['customer_name']); ?>!</p>
                </div>
                
                <div class="content">
                    <h2>Turn your positive experience into income!</h2>
                    
                    <p>Since you've had a great experience with <?php echo esc_html($data['site_name']); ?>, 
                    why not share it with friends and earn money for every person who makes a purchase?</p>
                    
                    <div class="benefits">
                        <h3>🎯 Here's how it works:</h3>
                        <ul>
                            <li><strong>Share your unique link</strong> with friends, family, or followers</li>
                            <li><strong>Earn <?php echo esc_html($data['incentive_percentage']); ?>% commission</strong> on every sale they make</li>
                            <li><strong>Get paid monthly</strong> via PayPal or bank transfer</li>
                            <li><strong>No limits</strong> on how much you can earn</li>
                            <li><strong>Professional marketing materials</strong> provided</li>
                        </ul>
                    </div>

                    <div style="text-align: center;">
                        <a href="<?php echo esc_url($data['referral_link']); ?>" class="cta-button">
                            🚀 Start Earning Now - It's Free!
                        </a>
                    </div>

                    <h3>💰 Earning Potential Examples:</h3>
                    <ul>
                        <li>Refer 1 friend (avg. $100 purchase) = <strong>$<?php echo number_format(100 * ($data['incentive_percentage'] / 100), 2); ?></strong></li>
                        <li>Refer 10 friends = <strong>$<?php echo number_format(1000 * ($data['incentive_percentage'] / 100), 2); ?></strong></li>
                        <li>Refer 50 people = <strong>$<?php echo number_format(5000 * ($data['incentive_percentage'] / 100), 2); ?></strong></li>
                    </ul>

                    <p><em>Some of our top referrers earn $500-2000+ per month just by sharing with their network!</em></p>

                    <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <strong>🎁 Special Bonus:</strong> Your first 5 referrals get an extra 2% commission boost!
                    </div>
                </div>

                <div class="footer">
                    <p>This invitation was sent because you're a valued customer of <?php echo esc_html($data['site_name']); ?>.</p>
                    <p><a href="<?php echo esc_url($data['unsubscribe_link']); ?>">Unsubscribe from viral invitations</a> | 
                       <a href="<?php echo esc_url($data['site_url']); ?>">Visit our website</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Handle viral invitation acceptance
     */
    public function ajax_viral_invite() {
        check_ajax_referer('affcd_satellite_nonce', 'nonce');

        $viral_token = Sanitise_text_field($_POST['viral_token'] ?? '');
        $email = Sanitise_email($_POST['email'] ?? '');
        $action_type = Sanitise_text_field($_POST['action_type'] ?? ''); // 'accept' or 'dismiss'

        if (empty($viral_token) || empty($email)) {
            wp_send_json_error('Missing required data');
        }

        $result = $this->process_viral_response($viral_token, $email, $action_type);

        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Process viral invitation response
     */
    private function process_viral_response($viral_token, $email, $action_type) {
        global $wpdb;

        // Verify viral token
        $opportunity = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}affcd_viral_opportunities 
            WHERE customer_email = %s AND viral_token = %s
        ", $email, $viral_token));

        if (!$opportunity) {
            return ['success' => false, 'message' => 'Invalid viral token'];
        }

        if ($action_type === 'accept') {
            // Create affiliate account or get existing
            $affiliate_id = $this->create_viral_affiliate($email, $opportunity);
            
            if ($affiliate_id) {
                // Update opportunity status
                $wpdb->update(
                    $wpdb->prefix . 'affcd_viral_opportunities',
                    ['status' => 'converted', 'affiliate_id' => $affiliate_id],
                    ['id' => $opportunity->id]
                );

                // Track viral conversion
                do_action('affcd_viral_conversion', $opportunity, $affiliate_id, 'accepted');

                return [
                    'success' => true,
                    'data' => [
                        'affiliate_id' => $affiliate_id,
                        'dashboard_url' => $this->get_affiliate_dashboard_url($affiliate_id),
                        'message' => 'Welcome to our affiliate program!'
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create affiliate account'];
            }
        } else {
            // Mark as dismissed
            $wpdb->update(
                $wpdb->prefix . 'affcd_viral_opportunities',
                ['status' => 'dismissed'],
                ['id' => $opportunity->id]
            );

            return ['success' => true, 'data' => ['message' => 'Invitation dismissed']];
        }
    }

    /**
     * Create affiliate account from viral invitation
     */
    private function create_viral_affiliate($email, $opportunity) {
        // Send to master site to create affiliate
        $affiliate_data = [
            'email' => $email,
            'name' => $opportunity->customer_name,
            'source' => 'viral_invitation',
            'viral_score' => $opportunity->viral_score,
            'referring_trigger' => $opportunity->trigger_type,
            'site_url' => home_url()
        ];

        $response = $this->parent->api_client->create_viral_affiliate($affiliate_data);

        if ($response['success']) {
            return $response['data']['affiliate_id'];
        }

        return false;
    }

    /**
     * Viral invite shortcode
     */
    public function viral_invite_shortcode($atts) {
        $atts = shortcode_atts([
            'style' => 'default',
            'title' => 'Earn Money Sharing Our Products',
            'description' => 'Join our affiliate program and earn commission on every sale.',
            'button_text' => 'Become an Affiliate',
            'show_stats' => 'true'
        ], $atts, 'affcd_viral_invite');

        ob_start();
        ?>
        <div class="affcd-viral-invite-widget affcd-style-<?php echo esc_attr($atts['style']); ?>">
            <div class="viral-invite-content">
                <h3><?php echo esc_html($atts['title']); ?></h3>
                <p><?php echo esc_html($atts['description']); ?></p>
                
                <?php if ($atts['show_stats'] === 'true'): ?>
                    <div class="viral-stats">
                        <?php echo $this->get_viral_stats_display(); ?>
                    </div>
                <?php endif; ?>

                <div class="viral-invite-form">
                    <form id="affcd-viral-signup" method="post">
                        <?php wp_nonce_field('affcd_satellite_nonce', 'viral_nonce'); ?>
                        
                        <div class="form-group">
                            <input type="email" 
                                   name="viral_email" 
                                   placeholder="Enter your email address" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" 
                                   name="viral_name" 
                                   placeholder="Your name" 
                                   required>
                        </div>

                        <button type="submit" class="viral-submit-btn">
                            <?php echo esc_html($atts['button_text']); ?>
                        </button>
                    </form>
                </div>

                <div class="viral-benefits">
                    <h4>💰 Why Join Our Affiliate Program?</h4>
                    <ul>
                        <li>✅ Earn up to <?php echo get_option('affcd_max_commission_rate', 30); ?>% commission</li>
                        <li>✅ Professional marketing materials provided</li>
                        <li>✅ Real-time tracking and analytics</li>
                        <li>✅ Monthly payments via PayPal</li>
                        <li>✅ No minimum sales required</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get viral statistics for display
     */
    private function get_viral_stats_display() {
        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_opportunities,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as conversions,
                AVG(viral_score) as avg_viral_score
            FROM {$wpdb->prefix}affcd_viral_opportunities
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $conversion_rate = $stats->total_opportunities > 0 ? 
            ($stats->conversions / $stats->total_opportunities) * 100 : 0;

        ob_start();
        ?>
        <div class="viral-stats-display">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($stats->conversions); ?></span>
                <span class="stat-label">New Affiliates (30 days)</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($conversion_rate, 1); ?>%</span>
                <span class="stat-label">Join Rate</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">$<?php echo number_format($this->get_avg_affiliate_earnings(), 0); ?></span>
                <span class="stat-label">Avg Monthly Earnings</span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate average affiliate earnings
     */
    private function get_avg_affiliate_earnings() {
        // This would call the master site API to get average earnings
        $response = $this->parent->api_client->get_affiliate_stats();
        return $response['success'] ? $response['data']['avg_monthly_earnings'] : 500;
    }

    /**
     * Inject viral sharing scripts
     */
    public function inject_viral_sharing_scripts() {
        if (!$this->viral_triggers['social_share']['enabled']) {
            return;
        }

        ?>
        <script>
        // Viral sharing tracking
        jQuery(document).ready(function($) {
            // Track social shares
            $(document).on('click', '.social-share-btn', function() {
                var platform = $(this).data('platform');
                var url = $(this).attr('href');
                
                $.post(affcdSatellite.ajaxUrl, {
                    action: 'affcd_social_share_track',
                    nonce: affcdSatellite.nonce,
                    platform: platform,
                    shared_url: url,
                    page_url: window.location.href
                });
            });

            // High engagement detection
            var engagementScore = 0;
            var startTime = Date.now();
            var maxScroll = 0;

            // Track scroll depth
            $(window).scroll(function() {
                var scrollPercent = ($(window).scrollTop() / ($(document).height() - $(window).height())) * 100;
                maxScroll = Math.max(maxScroll, scrollPercent);
                
                if (maxScroll > 75 && !sessionStorage.getItem('affcd_viral_triggered')) {
                    triggerViralEngagement();
                }
            });

            // Track time on site
            setInterval(function() {
                var timeOnSite = (Date.now() - startTime) / 1000;
                if (timeOnSite > 300 && maxScroll > 50 && !sessionStorage.getItem('affcd_viral_triggered')) {
                    triggerViralEngagement();
                }
            }, 30000);

            function triggerViralEngagement() {
                sessionStorage.setItem('affcd_viral_triggered', '1');
                
                // Show viral invitation popup
                showViralPopup('high_engagement');
            }

            function showViralPopup(trigger) {
                var popup = $('<div class="affcd-viral-popup-overlay"><div class="affcd-viral-popup">' +
                    '<h3>🎉 You seem to love what we offer!</h3>' +
                    '<p>Want to earn money by sharing with friends?</p>' +
                    '<div class="viral-actions">' +
                        '<button class="btn-viral-yes">Yes, tell me more!</button>' +
                        '<button class="btn-viral-no">No thanks</button>' +
                    '</div>' +
                '</div></div>');

                $('body').append(popup);
                popup.fadeIn();

                popup.find('.btn-viral-yes').click(function() {
                    window.location.href = '<?php echo home_url('/affiliate-signup/'); ?>?trigger=' + trigger;
                });

                popup.find('.btn-viral-no, .affcd-viral-popup-overlay').click(function() {
                    popup.fadeOut(function() { popup.remove(); });
                });
            }
        });
        </script>

        

        <style>
        .affcd-viral-popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            display: none;
        }
        
        .affcd-viral-popup {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
</style>
<?php
    /**
     * Generate viral recommendations
     */
    private function generate_viral_recommendations() {
        $recommendations = [];
        
        $viral_coefficient = get_option('affcd_revolutionary_insights', [])['viral_coefficient'] ?? 0;
        
        if ($viral_coefficient < 0.1) {
            $recommendations[] = '<li>🎯 <strong>Increase incentives:</strong> Your viral coefficient is low. Consider increasing referral rewards to 15-20%.</li>';
            $recommendations[] = '<li>📧 <strong>Optimize email timing:</strong> Test sending viral invitations 2-3 hours after purchase instead of 24 hours.</li>';
            $recommendations[] = '<li>🎨 <strong>Improve creative assets:</strong> A/B test different email templates and social sharing graphics.</li>';
        } elseif ($viral_coefficient < 0.5) {
            $recommendations[] = '<li>🚀 <strong>Add urgency:</strong> Create limited-time bonus rewards for referrals made within 48 hours.</li>';
            $recommendations[] = '<li>📱 <strong>Mobile optimization:</strong> Ensure viral sharing works seamlessly on mobile devices.</li>';
            $recommendations[] = '<li>🎁 <strong>Gamification:</strong> Add progress bars and achievement badges for referral milestones.</li>';
        } else {
            $recommendations[] = '<li>🏆 <strong>Excellent performance!</strong> Your viral coefficient is strong. Focus on scaling successful campaigns.</li>';
            $recommendations[] = '<li>📊 <strong>Advanced targeting:</strong> Segment high-performers and create VIP referral programs.</li>';
            $recommendations[] = '<li>🌍 <strong>Geographic expansion:</strong> Test viral campaigns in new markets with cultural adaptations.</li>';
        }
        
        return implode('', $recommendations);
    }

    /**
     * Generate identity recommendations
     */
    private function generate_identity_recommendations() {
        $recommendations = [];
        
        $resolution_rate = get_option('affcd_revolutionary_insights', [])['identity_resolution_rate']['resolution_rate'] ?? 0;
        
        if ($resolution_rate < 0.3) {
            $recommendations[] = '<li>🔧 <strong>Enhance fingerprinting:</strong> Add canvas and WebGL fingerprinting for better device identification.</li>';
            $recommendations[] = '<li>📊 <strong>Collect more data points:</strong> Implement phone number collection in key forms.</li>';
            $recommendations[] = '<li>🤖 <strong>Machine learning:</strong> Train behavioral pattern recognition algorithms with more data.</li>';
        } elseif ($resolution_rate < 0.7) {
            $recommendations[] = '<li>⚡ <strong>Real-time matching:</strong> Implement live identity matching during form submissions.</li>';
            $recommendations[] = '<li>🔗 <strong>Cross-device tracking:</strong> Add probabilistic matching for household-level attribution.</li>';
            $recommendations[] = '<li>📧 <strong>Email verification:</strong> Implement double opt-in to improve email-based matching accuracy.</li>';
        } else {
            $recommendations[] = '<li>🎉 <strong>Outstanding resolution rate!</strong> Your identity system is performing excellently.</li>';
            $recommendations[] = '<li>🧠 <strong>AI enhancement:</strong> Consider implementing deep learning models for even better accuracy.</li>';
            $recommendations[] = '<li>🔮 <strong>Predictive matching:</strong> Build models to predict identity links before they happen.</li>';
        }
        
        return implode('', $recommendations);
    }

    /**
     * Generate attribution recommendations
     */
    private function generate_attribution_recommendations() {
        $recommendations = [];
        
        $confidence = get_option('affcd_revolutionary_insights', [])['attribution_accuracy']['confidence'] ?? 0;
        
        if ($confidence < 0.6) {
            $recommendations[] = '<li>📈 <strong>Collect more touchpoints:</strong> Track micro-conversions and engagement signals        
            
            
            
            
            
            
    

    /**
     * Data-driven attribution weights
     */
    private function data_driven_weights($touchpoints, $quantum_state) {
        // This would use machine learning to determine optimal weights
        // For now, we'll use a sophisticated heuristic approach
        
        $weights = [];
        $total_weight = 0;

        foreach ($touchpoints as $index => $touchpoint) {
            if (empty($touchpoint['affiliate_id'])) {
                $weights[] = 0;
                continue;
            }

            // Calculate weight based on multiple factors
            $quality_score = $touchpoint['interaction_quality'] ?? 0.5;
            $conversion_prob = $touchpoint['conversion_probability'] ?? 0.5;
            $recency_factor = $this->calculate_recency_factor($touchpoint['timestamp']);
            $position_factor = $this->calculate_position_factor($index, count($touchpoints));

            $weight = $quality_score * $conversion_prob * $recency_factor * $position_factor;
            $weights[] = $weight;
            $total_weight += $weight;
        }

        // Normalize weights
        if ($total_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_weight;
            }
        }

        return $weights;
    }

    /**
     * Quantum superposition attribution weights
     */
    private function quantum_superposition_weights($touchpoints, $quantum_state) {
        if (empty($quantum_state['affiliate_probabilities'])) {
            return $this->linear_weights($touchpoints, $quantum_state);
        }

        $weights = [];
        $total_quantum_weight = 0;

        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = $touchpoint['affiliate_id'] ?? null;
            
            if (!$affiliate_id) {
                $weights[] = 0;
                continue;
            }

            // Get quantum probability for this affiliate
            $quantum_prob = $quantum_state['affiliate_probabilities'][$affiliate_id] ?? 0;
            
            // Apply quantum uncertainty principle
            $uncertainty = $quantum_state['attribution_entropy'] ?? 1.0;
            $quantum_weight = $quantum_prob * (1 + $uncertainty * 0.1);
            
            // Apply touchpoint quality modifier
            $quality = $touchpoint['interaction_quality'] ?? 0.5;
            $quantum_weight *= (0.5 + $quality * 0.5);

            $weights[] = $quantum_weight;
            $total_quantum_weight += $quantum_weight;
        }

        // Quantum normalization (maintains superposition)
        if ($total_quantum_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_quantum_weight;
            }
        }

        return $weights;
    }

    /**
     * Calculate weighted final attribution
     */
    private function calculate_weighted_attribution($attribution_results) {
        $final_attribution = [];

        foreach ($attribution_results as $model_name => $model_attribution) {
            $model_weight = $this->attribution_weights[$model_name] ?? 0;
            
            foreach ($model_attribution as $affiliate_id => $value) {
                if (!isset($final_attribution[$affiliate_id])) {
                    $final_attribution[$affiliate_id] = 0;
                }
                $final_attribution[$affiliate_id] += $value * $model_weight;
            }
        }

        return $final_attribution;
    }

    /**
     * Store attribution results
     */
    private function store_attribution_results($order_id, $session_id, $model_results, $final_attribution) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'affcd_attribution_results',
            [
                'order_id' => $order_id,
                'session_id' => $session_id,
                'model_results' => json_encode($model_results),
                'final_attribution' => json_encode($final_attribution),
                'attribution_confidence' => $this->calculate_attribution_confidence($model_results),
                'quantum_entropy' => $this->get_quantum_state($session_id)['attribution_entropy'] ?? 1.0,
                'created_at' => current_time('mysql'),
                'site_url' => home_url()
            ]
        );
    }

    /**
     * Calculate attribution confidence
     */
    private function calculate_attribution_confidence($model_results) {
        if (empty($model_results)) {
            return 0;
        }

        // Calculate consistency across models
        $affiliate_totals = [];
        foreach ($model_results as $model_name => $results) {
            foreach ($results as $affiliate_id => $value) {
                if (!isset($affiliate_totals[$affiliate_id])) {
                    $affiliate_totals[$affiliate_id] = [];
                }
                $affiliate_totals[$affiliate_id][$model_name] = $value;
            }
        }

        // Calculate variance for each affiliate
        $total_variance = 0;
        $affiliate_count = 0;

        foreach ($affiliate_totals as $affiliate_id => $model_values) {
            if (count($model_values) > 1) {
                $mean = array_sum($model_values) / count($model_values);
                $variance = 0;
                
                foreach ($model_values as $value) {
                    $variance += pow($value - $mean, 2);
                }
                $variance /= count($model_values);
                
                $total_variance += $variance;
                $affiliate_count++;
            }
        }

        if ($affiliate_count === 0) {
            return 1.0;
        }

        $avg_variance = $total_variance / $affiliate_count;
        
        // Convert variance to confidence (inverse relationship)
        return max(0, min(1, 1 - ($avg_variance / 100)));
    }

    /**
     * Sync attribution with master site
     */
    private function sync_attribution_with_master($order_id, $final_attribution, $model_results) {
        $sync_data = [
            'order_id' => $order_id,
            'site_url' => home_url(),
            'final_attribution' => $final_attribution,
            'model_results' => $model_results,
            'attribution_timestamp' => current_time('mysql'),
            'quantum_enhanced' => true
        ];

        $this->parent->api_client->sync_attribution_data($sync_data);
    }

    /**
     * Inject attribution tracking script
     */
    public function inject_attribution_tracking() {
        ?>
        <script>
        // Quantum Attribution Tracking
        (function() {
            var attributionData = {
                session_id: affcdSatellite.sessionId,
                page_interactions: [],
                micro_conversions: [],
                engagement_score: 0,
                conversion_signals: []
            };

            // Track micro-conversions
            trackMicroConversions();
            
            // Track engagement quality
            trackEngagementQuality();
            
            // Track conversion signals
            trackConversionSignals();

            function trackMicroConversions() {
                // Track scroll milestones
                var scrollMilestones = [25, 50, 75, 90];
                var trackedMilestones = [];

                jQuery(window).scroll(function() {
                    var scrollPercent = (jQuery(window).scrollTop() / (jQuery(document).height() - jQuery(window).height())) * 100;
                    
                    scrollMilestones.forEach(function(milestone) {
                        if (scrollPercent >= milestone && trackedMilestones.indexOf(milestone) === -1) {
                            trackedMilestones.push(milestone);
                            attributionData.micro_conversions.push({
                                type: 'scroll_milestone',
                                value: milestone,
                                timestamp: Date.now()
                            });
                        }
                    });
                });

                // Track time on page milestones
                var timeMilestones = [30, 60, 120, 300]; // seconds
                var startTime = Date.now();

                timeMilestones.forEach(function(milestone) {
                    setTimeout(function() {
                        if (document.visibilityState === 'visible') {
                            attributionData.micro_conversions.push({
                                type: 'time_milestone',
                                value: milestone,
                                timestamp: Date.now()
                            });
                        }
                    }, milestone * 1000);
                });

                // Track interaction events
                jQuery(document).on('click', 'a, button', function() {
                    attributionData.page_interactions.push({
                        type: 'click',
                        element: this.tagName,
                        timestamp: Date.now()
                    });
                });
            }

            function trackEngagementQuality() {
                var engagementFactors = {
                    timeOnPage: 0,
                    scrollDepth: 0,
                    interactions: 0,
                    returnVisitor: localStorage.getItem('affcd_return_visitor') ? 1 : 0
                };

                // Mark as return visitor
                if (!localStorage.getItem('affcd_return_visitor')) {
                    localStorage.setItem('affcd_return_visitor', Date.now());
                }

                // Calculate engagement score periodically
                setInterval(function() {
                    engagementFactors.timeOnPage = (Date.now() - startTime) / 1000;
                    engagementFactors.scrollDepth = (jQuery(window).scrollTop() / (jQuery(document).height() - jQuery(window).height())) * 100;
                    engagementFactors.interactions = attributionData.page_interactions.length;

                    // Calculate weighted engagement score
                    var score = (
                        Math.min(engagementFactors.timeOnPage / 300, 1) * 0.3 +
                        Math.min(engagementFactors.scrollDepth / 100, 1) * 0.3 +
                        Math.min(engagementFactors.interactions / 10, 1) * 0.2 +
                        engagementFactors.returnVisitor * 0.2
                    );

                    attributionData.engagement_score = score;
                }, 10000); // Update every 10 seconds
            }

            function trackConversionSignals() {
                // Track form focus events
                jQuery('form input, form textarea').on('focus', function() {
                    attributionData.conversion_signals.push({
                        type: 'form_focus',
                        form_id: jQuery(this).closest('form').attr('id'),
                        timestamp: Date.now()
                    });
                });

                // Track add to cart events (if WooCommerce)
                jQuery(document).on('click', '.add_to_cart_button', function() {
                    attributionData.conversion_signals.push({
                        type: 'add_to_cart_intent',
                        product_id: jQuery(this).data('product_id'),
                        timestamp: Date.now()
                    });
                });

                // Track checkout page visits
                if (window.location.href.indexOf('checkout') !== -1) {
                    attributionData.conversion_signals.push({
                        type: 'checkout_page_visit',
                        timestamp: Date.now()
                    });
                }
            }

            // Send attribution data periodically
            setInterval(function() {
                if (attributionData.micro_conversions.length > 0 || attributionData.conversion_signals.length > 0) {
                    jQuery.post(affcdSatellite.ajaxUrl, {
                        action: 'affcd_attribution_touchpoint',
                        nonce: affcdSatellite.nonce,
                        attribution_data: attributionData
                    });

                    // Clear sent data
                    attributionData.micro_conversions = [];
                    attributionData.conversion_signals = [];
                }
            }, 30000); // Every 30 seconds

            // Send final data on page unload
            window.addEventListener('beforeunload', function() {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(affcdSatellite.ajaxUrl, new URLSearchParams({
                        action: 'affcd_attribution_touchpoint',
                        nonce: affcdSatellite.nonce,
                        attribution_data: JSON.stringify(attributionData),
                        final_send: true
                    }));
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Helper methods
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['affcd_session_id'])) {
            $_SESSION['affcd_session_id'] = 'affcd_' . uniqid() . '_' . time();
        }
        
        return $_SESSION['affcd_session_id'];
    }

    private function detect_affiliate_from_touchpoint() {
        // Check URL parameters, cookies, etc.
        if (isset($_GET['ref'])) {
            return intval($_GET['ref']);
        }
        
        if (isset($_COOKIE['affcd_affiliate_id'])) {
            return intval($_COOKIE['affcd_affiliate_id']);
        }
        
        return get_option('affcd_default_affiliate_id', null);
    }

    private function extract_campaign_data() {
        return [
            'utm_source' => $_GET['utm_source'] ?? null,
            'utm_medium' => $_GET['utm_medium'] ?? null,
            'utm_campaign' => $_GET['utm_campaign'] ?? null,
            'utm_term' => $_GET['utm_term'] ?? null,
            'utm_content' => $_GET['utm_content'] ?? null
        ];
    }

    private function calculate_interaction_quality($form_data) {
        // Analyze form data quality
        $quality_score = 0.5; // Base score
        
        // Check for complete email
        if (!empty($form_data['email']) && filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $quality_score += 0.2;
        }
        
        // Check for phone number
        if (!empty($form_data['phone'])) {
            $quality_score += 0.1;
        }
        
        // Check for name completeness
        if (!empty($form_data['name']) && str_word_count($form_data['name']) >= 2) {
            $quality_score += 0.1;
        }
        
        return min($quality_score, 1.0);
    }

    private function predict_conversion_probability($form_data) {
        // Simple heuristic for conversion probability
        // In production, this would use machine learning
        
        $probability = 0.3; // Base probability
        
        // High-value form types
        $high_value_indicators = ['demo', 'quote', 'consultation', 'trial'];
        foreach ($high_value_indicators as $indicator) {
            if (stripos(json_encode($form_data), $indicator) !== false) {
                $probability += 0.3;
                break;
            }
        }
        
        // Corporate email domains
        if (!empty($form_data['email'])) {
            $domain = substr(strrchr($form_data['email'], "@"), 1);
            $consumer_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
            if (!in_array($domain, $consumer_domains)) {
                $probability += 0.2;
            }
        }
        
        return min($probability, 1.0);
    }

    private function calculate_recency_factor($timestamp) {
        $hours_ago = (time() - strtotime($timestamp)) / 3600;
        return max(0.1, 1 - ($hours_ago / 168)); // Decay over 1 week
    }

    private function calculate_position_factor($index, $total) {
        if ($total <= 1) return 1.0;
        
        // U-shaped curve: higher weight for first and last positions
        $normalized_position = $index / ($total - 1);
        return 0.5 + 0.5 * abs(2 * $normalized_position - 1);
    }

    private function get_conversion_value($order_id) {
        if (function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            return $order ? $order->get_total() : 0;
        }
        return 100; // Default value
    }

    private function get_session_touchpoints($session_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}affcd_attribution_touchpoints 
            WHERE session_id = %s 
            ORDER BY timestamp ASC
        ", $session_id), ARRAY_A);
    }
}

    

    /**
     * Optimize viral loop based on performance
     */
    public function optimize_viral_loop($opportunity, $affiliate_id, $action) {
        // Machine learning optimization would go here
        // For now, we'll do basic optimization
        
        if ($action === 'accepted') {
            $trigger_type = $opportunity->trigger_type;
            
            // Increase success rate for this trigger type
            $current_rate = $this->viral_triggers[$trigger_type]['success_rate'];
            $this->viral_triggers[$trigger_type]['success_rate'] = min($current_rate * 1.05, 0.5);
            
            // Update trigger settings
            update_option('affcd_viral_triggers', $this->viral_triggers);
        }
    }
}

/**
 * Cross-Platform Identity Resolution System
 * Unifies customer identity across all devices and platforms
 */
class AFFCD_Identity_Resolution {

    private $parent;
    private $identity_graphs = [];
    private $matching_algorithms = [];

    public function __construct($parent) {
        $this->parent = $parent;
        $this->init_matching_algorithms();
        $this->init_hooks();
    }

    /**
     * Initialse WordPress hooks
     */
    private function init_hooks() {
        // Identity collection hooks
        add_action('wp_footer', [$this, 'inject_identity_collection_script']);
        add_action('affcd_form_submission', [$this, 'extract_identity_from_form'], 5, 3);
        add_action('user_register', [$this, 'extract_identity_from_registration'], 10, 1);
        add_action('wp_login', [$this, 'extract_identity_from_login'], 10, 2);
        
        // E-commerce hooks
        add_action('woocommerce_checkout_order_processed', [$this, 'extract_identity_from_order'], 10, 1);
        add_action('edd_complete_purchase', [$this, 'extract_identity_from_edd'], 10, 1);
        
        // AJAX handlers
        add_action('wp_ajax_affcd_identity_match', [$this, 'ajax_identity_match']);
        add_action('wp_ajax_nopriv_affcd_identity_match', [$this, 'ajax_identity_match']);
        add_action('wp_ajax_affcd_identity_sync', [$this, 'ajax_identity_sync']);
        
        // Cross-platform tracking
        add_action('wp_head', [$this, 'inject_cross_platform_tracking']);
        
        // Scheduled identity resolution
        add_action('affcd_identity_resolution_cron', [$this, 'run_identity_resolution']);
    }

    /**
     * Initialse matching algorithms
     */
    private function init_matching_algorithms() {
        $this->matching_algorithms = [
            'email_exact' => [
                'weight' => 100,
                'confidence' => 0.95,
                'method' => [$this, 'match_email_exact']
            ],
            'email_hash' => [
                'weight' => 90,
                'confidence' => 0.85,
                'method' => [$this, 'match_email_hash']
            ],
            'phone_exact' => [
                'weight' => 85,
                'confidence' => 0.80,
                'method' => [$this, 'match_phone_exact']
            ],
            'name_email_domain' => [
                'weight' => 70,
                'confidence' => 0.65,
                'method' => [$this, 'match_name_email_domain']
            ],
            'device_fingerprint' => [
                'weight' => 60,
                'confidence' => 0.55,
                'method' => [$this, 'match_device_fingerprint']
            ],
            'behavioral_pattern' => [
                'weight' => 50,
                'confidence' => 0.45,
                'method' => [$this, 'match_behavioral_pattern']
            ],
            'ip_geolocation' => [
                'weight' => 30,
                'confidence' => 0.25,
                'method' => [$this, 'match_ip_geolocation']
            ],
            'household_clustering' => [
                'weight' => 40,
                'confidence' => 0.35,
                'method' => [$this, 'match_household_clustering']
            ]
        ];
    }

    /**
     * Inject identity collection script
     */
    public function inject_identity_collection_script() {
        ?>
        <script>
        // Advanced Identity Resolution Collection
        (function() {
            var identityData = {
                session_id: affcdSatellite.sessionId || generateSessionId(),
                page_url: window.location.href,
                referrer: document.referrer,
                timestamp: Date.now(),
                user_agent: navigator.userAgent,
                screen_resolution: screen.width + 'x' + screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                language: navigator.language,
                platform: navigator.platform,
                cookie_enabled: navigator.cookieEnabled,
                local_storage_enabled: typeof(Storage) !== "undefined",
                device_fingerprint: generateDeviceFingerprint()
            };

            // Collect additional browser data
            if (navigator.connection) {
                identityData.connection_type = navigator.connection.effectiveType;
                identityData.connection_downlink = navigator.connection.downlink;
            }

            // Canvas fingerprinting
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('Identity fingerprint text 🎯', 2, 2);
            identityData.canvas_fingerprint = canvas.toDataURL().slice(-50);

            // WebGL fingerprinting
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                identityData.webgl_vendor = gl.getParameter(gl.VENDOR);
                identityData.webgl_renderer = gl.getParameter(gl.RENDERER);
            }

            // Audio fingerprinting
            if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
                var audioContext = new (window.AudioContext || window.webkitAudioContext)();
                var oscillator = audioContext.createOscillator();
                var analyser = audioContext.createAnalyser();
                var gain = audioContext.createGain();
                
                oscillator.type = 'triangle';
                oscillator.frequency.setValueAtTime(10000, audioContext.currentTime);
                
                gain.gain.setValueAtTime(0.05, audioContext.currentTime);
                oscillator.connect(analyser);
                analyser.connect(gain);
                gain.connect(audioContext.destination);
                
                oscillator.start(0);
                setTimeout(function() {
                    var samples = new Float32Array(analyser.frequencyBinCount);
                    analyser.getFloatFrequencyData(samples);
                    oscillator.stop();
                    identityData.audio_fingerprint = Array.from(samples.slice(0, 5)).join(',');
                }, 100);
            }

            // Store identity data
            storeIdentityData(identityData);

            // Track interactions
            trackUserInteractions(identityData.session_id);

            function generateSessionId() {
                return 'affcd_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            }

            function generateDeviceFingerprint() {
                var fingerprint = [
                    navigator.userAgent,
                    navigator.language,
                    screen.width + 'x' + screen.height,
                    screen.colorDepth,
                    new Date().getTimezoneOffset(),
                    !!window.sessionStorage,
                    !!window.localStorage,
                    navigator.platform
                ].join('|');

                return btoa(fingerprint).substr(0, 32);
            }

            function storeIdentityData(data) {
                // Store locally
                if (localStorage) {
                    localStorage.setItem('affcd_identity', JSON.stringify(data));
                }

                // Send to server
                jQuery.post(affcdSatellite.ajaxUrl, {
                    action: 'affcd_identity_collect',
                    nonce: affcdSatellite.nonce,
                    identity_data: data
                });
            }

            function trackUserInteractions(sessionId) {
                var interactions = [];
                var startTime = Date.now();

                // Mouse movement tracking
                var mouseData = [];
                document.addEventListener('mousemove', function(e) {
                    if (mouseData.length < 100) { // Limit data collection
                        mouseData.push({
                            x: e.clientX,
                            y: e.clientY,
                            t: Date.now() - startTime
                        });
                    }
                });

                // Scroll tracking
                var scrollData = [];
                window.addEventListener('scroll', function() {
                    scrollData.push({
                        y: window.pageYOffset,
                        t: Date.now() - startTime
                    });
                });

                // Click tracking
                document.addEventListener('click', function(e) {
                    interactions.push({
                        type: 'click',
                        element: e.target.tagName,
                        x: e.clientX,
                        y: e.clientY,
                        timestamp: Date.now()
                    });
                });

                // Send interaction data periodically
                setInterval(function() {
                    if (interactions.length > 0 || mouseData.length > 0 || scrollData.length > 0) {
                        jQuery.post(affcdSatellite.ajaxUrl, {
                            action: 'affcd_interaction_track',
                            nonce: affcdSatellite.nonce,
                            session_id: sessionId,
                            interactions: interactions,
                            mouse_data: mouseData.slice(-50), // Last 50 points
                            scroll_data: scrollData.slice(-20) // Last 20 points
                        });

                        interactions = [];
                        mouseData = mouseData.slice(-50);
                        scrollData = scrollData.slice(-20);
                    }
                }, 30000); // Every 30 seconds
            }
        })();
        </script>
        <?php
    }

    /**
     * Extract identity from form submission
     */
    public function extract_identity_from_form($form_data, $form_id, $plugin_type) {
        $identity_data = [
            'source' => 'form_submission',
            'form_id' => $form_id,
            'plugin_type' => $plugin_type,
            'timestamp' => current_time('mysql')
        ];

        // Extract email
        $email = $this->extract_email_from_data($form_data);
        if ($email) {
            $identity_data['email'] = $email;
            $identity_data['email_hash'] = hash('sha256', strtolower(trim($email)));
        }

        // Extract phone
        $phone = $this->extract_phone_from_data($form_data);
        if ($phone) {
            $identity_data['phone'] = $this->normalize_phone($phone);
        }

        // Extract name
        $name = $this->extract_name_from_data($form_data);
        if ($name) {
            $identity_data['full_name'] = $name;
            $identity_data['name_parts'] = $this->parse_name($name);
        }

        // Store identity
        $this->store_identity_data($identity_data);

        // Trigger identity matching
        $this->trigger_identity_matching($identity_data);
    }

    /**
     * Extract identity from order
     */
    public function extract_identity_from_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $identity_data = [
            'source' => 'ecommerce_order',
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'full_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'address' => [
                'street' => $order->get_billing_address_1(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'zip' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country()
            ],
            'timestamp' => current_time('mysql')
        ];

        // Add payment method info
        $identity_data['payment_method'] = $order->get_payment_method();
        
        // Add last 4 digits of credit card if available (PCI compliant)
        $payment_tokens = WC_Payment_Tokens::get_order_tokens($order_id);
        if (!empty($payment_tokens)) {
            $token = reset($payment_tokens);
            if (method_exists($token, 'get_last4')) {
                $identity_data['card_last4'] = $token->get_last4();
            }
        }

        // Hash sensitive data
        $identity_data['email_hash'] = hash('sha256', strtolower(trim($identity_data['email'])));
        $identity_data['name_parts'] = $this->parse_name($identity_data['full_name']);

        $this->store_identity_data($identity_data);
        $this->trigger_identity_matching($identity_data);
    }

    /**
     * Store identity data
     */
    private function store_identity_data($identity_data) {
        global $wpdb;

        // Generate unique identity hash
        $identity_hash = $this->generate_identity_hash($identity_data);
        
        $wpdb->insert(
            $wpdb->prefix . 'affcd_identity_data',
            [
                'identity_hash' => $identity_hash,
                'source' => $identity_data['source'],
                'email' => $identity_data['email'] ?? null,
                'email_hash' => $identity_data['email_hash'] ?? null,
                'phone' => $identity_data['phone'] ?? null,
                'full_name' => $identity_data['full_name'] ?? null,
                'name_parts' => json_encode($identity_data['name_parts'] ?? []),
                'device_fingerprint' => $identity_data['device_fingerprint'] ?? null,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'additional_data' => json_encode($identity_data),
                'session_id' => $identity_data['session_id'] ?? null,
                'site_url' => home_url(),
                'collected_at' => $identity_data['timestamp']
            ]
        );

        return $identity_hash;
    }

    /**
     * Generate unique identity hash
     */
    private function generate_identity_hash($identity_data) {
        $key_fields = [
            $identity_data['email'] ?? '',
            $identity_data['phone'] ?? '',
            $identity_data['device_fingerprint'] ?? '',
            $identity_data['session_id'] ?? '',
            time()
        ];

        return hash('sha256', implode('|', $key_fields));
    }

    /**
     * Trigger identity matching process
     */
    private function trigger_identity_matching($identity_data) {
        // Run matching algorithms
        $matches = $this->run_matching_algorithms($identity_data);
        
        if (!empty($matches)) {
            $this->process_identity_matches($identity_data, $matches);
        }
    }

    /**
     * Run all matching algorithms
     */
    private function run_matching_algorithms($identity_data) {
        $matches = [];
        
        foreach ($this->matching_algorithms as $algorithm_name => $algorithm) {
            $algorithm_matches = call_user_func($algorithm['method'], $identity_data);
            
            if (!empty($algorithm_matches)) {
                foreach ($algorithm_matches as $match) {
                    $match['algorithm'] = $algorithm_name;
                    $match['weight'] = $algorithm['weight'];
                    $match['confidence'] = $algorithm['confidence'];
                    $matches[] = $match;
                }
            }
        }

        // Sort by confidence score
        usort($matches, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $matches;
    }

    /**
     * Email exact matching
     */
    private function match_email_exact($identity_data) {
        if (empty($identity_data['email'])) {
            return [];
        }

        global $wpdb;

        $matches = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT identity_hash, email, collected_at, additional_data
            FROM {$wpdb->prefix}affcd_identity_data 
            WHERE email = %s AND identity_hash != %s
        ", $identity_data['email'], $identity_data['identity_hash'] ?? ''));

        return $this->format_matches($matches, 'email_exact');
    }

    /**
     * Email hash matching (privacy-preserving)
     */
    private function match_email_hash($identity_data) {
        if (empty($identity_data['email_hash'])) {
            return [];
        }

        global $wpdb;

        $matches = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT identity_hash, email_hash, collected_at, additional_data
            FROM {$wpdb->prefix}affcd_identity_data 
            WHERE email_hash = %s AND identity_hash != %s
        ", $identity_data['email_hash'], $identity_data['identity_hash'] ?? ''));

        return $this->format_matches($matches, 'email_hash');
    }

    /**
     * Device fingerprint matching
     */
    private function match_device_fingerprint($identity_data) {
        if (empty($identity_data['device_fingerprint'])) {
            return [];
        }

        global $wpdb;

        $matches = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT identity_hash, device_fingerprint, collected_at, additional_data
            FROM {$wpdb->prefix}affcd_identity_data 
            WHERE device_fingerprint = %s AND identity_hash != %s
        ", $identity_data['device_fingerprint'], $identity_data['identity_hash'] ?? ''));

        return $this->format_matches($matches, 'device_fingerprint');
    }

    /**
     * Behavioral pattern matching
     */
    private function match_behavioral_pattern($identity_data) {
        // This would use machine learning to match behavioral patterns
        // For now, we'll do basic pattern matching
        
        if (empty($identity_data['session_id'])) {
            return [];
        }

        global $wpdb;

        // Match similar interaction patterns
        $patterns = $wpdb->get_results($wpdb->prepare("
            SELECT i1.identity_hash, i1.additional_data, i1.collected_at
            FROM {$wpdb->prefix}affcd_identity_data i1
            JOIN {$wpdb->prefix}affcd_identity_data i2 ON i1.ip_address = i2.ip_address
            WHERE i2.session_id = %s 
            AND i1.identity_hash != i2.identity_hash
            AND i1.collected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $identity_data['session_id']));

        return $this->format_matches($patterns, 'behavioral_pattern');
    }

    /**
     * Household clustering matching
     */
    private function match_household_clustering($identity_data) {
        global $wpdb;

        $ip_address = $this->get_client_ip();
        if (empty($ip_address)) {
            return [];
        }

        // Find other identities from same IP address
        $household_matches = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT identity_hash, ip_address, collected_at, additional_data
            FROM {$wpdb->prefix}affcd_identity_data 
            WHERE ip_address = %s 
            AND identity_hash != %s
            AND collected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $ip_address, $identity_data['identity_hash'] ?? ''));

        return $this->format_matches($household_matches, 'household_clustering');
    }

    /**
     * Format matches for processing
     */
    private function format_matches($raw_matches, $match_type) {
        $formatted = [];

        foreach ($raw_matches as $match) {
            $additional_data = json_decode($match->additional_data, true) ?? [];
            
            $formatted[] = [
                'identity_hash' => $match->identity_hash,
                'match_type' => $match_type,
                'collected_at' => $match->collected_at,
                'data' => $additional_data
            ];
        }

        return $formatted;
    }

    /**
     * Process identity matches
     */
    private function process_identity_matches($identity_data, $matches) {
        // Group matches by confidence
        $high_confidence = array_filter($matches, function($match) {
            return $match['confidence'] >= 0.8;
        });

        $medium_confidence = array_filter($matches, function($match) {
            return $match['confidence'] >= 0.5 && $match['confidence'] < 0.8;
        });

        // Process high confidence matches immediately
        foreach ($high_confidence as $match) {
            $this->create_identity_link($identity_data, $match, 'high_confidence');
        }

        // Queue medium confidence matches for review
        foreach ($medium_confidence as $match) {
            $this->queue_identity_review($identity_data, $match);
        }

        // Send consolidated identity to master site
        $this->sync_identity_with_master($identity_data, $matches);
    }

    /**
     * Create identity link
     */
    private function create_identity_link($identity1, $match, $confidence_level) {
        global $wpdb;

        $link_strength = $this->calculate_link_strength($identity1, $match);

        $wpdb->insert(
            $wpdb->prefix . 'affcd_identity_links',
            [
                'identity_hash_1' => $identity1['identity_hash'] ?? '',
                'identity_hash_2' => $match['identity_hash'],
                'link_type' => $match['algorithm'],
                'confidence_level' => $confidence_level,
                'link_strength' => $link_strength,
                'match_data' => json_encode($match),
                'created_at' => current_time('mysql'),
                'status' => 'active'
            ]
        );

        // Trigger attribution recovery
        do_action('affcd_identity_linked', $identity1, $match, $link_strength);
    }

    /**
     * Calculate link strength between identities
     */
    private function calculate_link_strength($identity1, $match) {
        $strength = $match['confidence'] * 100;

        // Bonus for multiple matching fields
        $matching_fields = 0;
        
        if (!empty($identity1['email']) && !empty($match['data']['email']) && 
            $identity1['email'] === $match['data']['email']) {
            $matching_fields++;
            $strength += 20;
        }

        if (!empty($identity1['phone']) && !empty($match['data']['phone']) && 
            $identity1['phone'] === $match['data']['phone']) {
            $matching_fields++;
            $strength += 15;
        }

        if (!empty($identity1['device_fingerprint']) && !empty($match['data']['device_fingerprint']) && 
            $identity1['device_fingerprint'] === $match['data']['device_fingerprint']) {
            $matching_fields++;
            $strength += 10;
        }

        // Temporal proximity bonus
        $time_diff = strtotime($identity1['timestamp']) - strtotime($match['collected_at']);
        if (abs($time_diff) < 3600) { // Within 1 hour
            $strength += 10;
        } elseif (abs($time_diff) < 86400) { // Within 24 hours
            $strength += 5;
        }

        return min($strength, 100);
    }

    /**
     * Sync identity with master site
     */
    private function sync_identity_with_master($identity_data, $matches) {
        $sync_data = [
            'identity_data' => $identity_data,
            'matches' => $matches,
            'site_url' => home_url(),
            'sync_timestamp' => current_time('mysql')
        ];

        $this->parent->api_client->sync_identity_data($sync_data);
    }

    /**
     * Extract email from mixed data
     */
    private function extract_email_from_data($data) {
        if (is_string($data) && filter_var($data, FILTER_VALIDATE_EMAIL)) {
            return $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
                if (is_array($value)) {
                    $email = $this->extract_email_from_data($value);
                    if ($email) return $email;
                }
            }
        }

        return null;
    }

    /**
     * Normalize phone number
     */
    private function normalize_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^\d]/', '', $phone);
        
        // Add country code if missing (assume US)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }

        return $phone;
    }

    /**
     * Parse name into components
     */
    private function parse_name($full_name) {
        $parts = explode(' ', trim($full_name));
        
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => end($parts) ?? '',
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : ''
        ];
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        return AFFCD_Utils::get_client_ip();
    }
}

/**
 * Quantum Attribution System
 * Revolutionary multi-dimensional attribution modeling
 */
class AFFCD_Quantum_Attribution {

    private $parent;
    private $attribution_models = [];
    private $quantum_states = [];
    private $attribution_weights = [];

    public function __construct($parent) {
        $this->parent = $parent;
        $this->init_attribution_models();
        $this->init_hooks();
    }

    /**
     * Initialse WordPress hooks
     */
    private function init_hooks() {
        // Attribution tracking hooks
        add_action('affcd_form_submission', [$this, 'track_attribution_touchpoint'], 5, 3);
        add_action('affcd_identity_linked', [$this, 'recalculate_attribution'], 10, 3);
        add_action('woocommerce_checkout_order_processed', [$this, 'finalize_attribution'], 10, 1);
        
        // Real-time attribution updates
        add_action('wp_footer', [$this, 'inject_attribution_tracking']);
        
        // AJAX handlers
        add_action('wp_ajax_affcd_attribution_touchpoint', [$this, 'ajax_attribution_touchpoint']);
        add_action('wp_ajax_nopriv_affcd_attribution_touchpoint', [$this, 'ajax_attribution_touchpoint']);
        
        // Scheduled attribution optimization
        add_action('affcd_quantum_attribution_optimization', [$this, 'optimize_attribution_models']);
        
        // Attribution reporting
        add_action('affcd_generate_attribution_report', [$this, 'generate_attribution_insights']);
    }

    /**
     * Initialse attribution models
     */
    private function init_attribution_models() {
        $this->attribution_models = [
            'last_click' => [
                'name' => 'Last Click',
                'description' => 'All credit to the last touchpoint before conversion',
                'weight_function' => [$this, 'last_click_weights'],
                'default_weight' => 0.15
            ],
            'first_click' => [
                'name' => 'First Click',
                'description' => 'All credit to the first touchpoint',
                'weight_function' => [$this, 'first_click_weights'],
                'default_weight' => 0.10
            ],
            'linear' => [
                'name' => 'Linear',
                'description' => 'Equal credit distributed across all touchpoints',
                'weight_function' => [$this, 'linear_weights'],
                'default_weight' => 0.20
            ],
            'time_decay' => [
                'name' => 'Time Decay',
                'description' => 'More credit to recent touchpoints',
                'weight_function' => [$this, 'time_decay_weights'],
                'default_weight' => 0.25
            ],
            'position_based' => [
                'name' => 'Position Based (U-Shaped)',
                'description' => '40% first, 40% last, 20% middle touchpoints',
                'weight_function' => [$this, 'position_based_weights'],
                'default_weight' => 0.15
            ],
            'data_driven' => [
                'name' => 'Data-Driven',
                'description' => 'Machine learning optimized attribution',
                'weight_function' => [$this, 'data_driven_weights'],
                'default_weight' => 0.35
            ],
            'quantum_superposition' => [
                'name' => 'Quantum Superposition',
                'description' => 'Multi-dimensional probability-based attribution',
                'weight_function' => [$this, 'quantum_superposition_weights'],
                'default_weight' => 0.40
            ]
        ];

 // Load custom attribution weights
        $this->attribution_weights = get_option('affcd_attribution_weights', [
            'last_click' => 0.15,
            'first_click' => 0.10,
            'linear' => 0.20,
            'time_decay' => 0.25,
            'position_based' => 0.15,
            'data_driven' => 0.35,
            'quantum_superposition' => 0.40
        ]);
    }

    /**
     * Track attribution touchpoint
     */
    public function track_attribution_touchpoint($form_data, $form_id, $plugin_type) {
        $touchpoint_data = [
            'type' => 'form_submission',
            'form_id' => $form_id,
            'plugin_type' => $plugin_type,
            'timestamp' => current_time('mysql'),
            'page_url' => $_SERVER['REQUEST_URI'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => AFFCD_Utils::get_client_ip(),
            'session_id' => $this->get_session_id(),
            'affiliate_id' => $this->detect_affiliate_from_touchpoint(),
            'campaign_data' => $this->extract_campaign_data(),
            'interaction_quality' => $this->calculate_interaction_quality($form_data),
            'conversion_probability' => $this->predict_conversion_probability($form_data)
        ];

        $this->store_attribution_touchpoint($touchpoint_data);
        $this->update_quantum_states($touchpoint_data);
    }

    /**
     * Store attribution touchpoint
     */
    private function store_attribution_touchpoint($touchpoint_data) {
        global $wpdb;

        $touchpoint_id = $wpdb->insert(
            $wpdb->prefix . 'affcd_attribution_touchpoints',
            [
                'session_id' => $touchpoint_data['session_id'],
                'affiliate_id' => $touchpoint_data['affiliate_id'],
                'touchpoint_type' => $touchpoint_data['type'],
                'page_url' => $touchpoint_data['page_url'],
                'referrer' => $touchpoint_data['referrer'],
                'campaign_data' => json_encode($touchpoint_data['campaign_data']),
                'interaction_quality' => $touchpoint_data['interaction_quality'],
                'conversion_probability' => $touchpoint_data['conversion_probability'],
                'ip_address' => $touchpoint_data['ip_address'],
                'user_agent' => $touchpoint_data['user_agent'],
                'metadata' => json_encode($touchpoint_data),
                'timestamp' => $touchpoint_data['timestamp'],
                'site_url' => home_url()
            ]
        );

        return $wpdb->insert_id;
    }

    /**
     * Update quantum states for attribution
     */
    private function update_quantum_states($touchpoint_data) {
        $session_id = $touchpoint_data['session_id'];
        
        // Get existing quantum state for this session
        $quantum_state = $this->get_quantum_state($session_id);
        
        // Calculate new quantum probabilities
        $new_probabilities = $this->calculate_quantum_probabilities($quantum_state, $touchpoint_data);
        
        // Update quantum state
        $this->store_quantum_state($session_id, $new_probabilities);
    }

    /**
     * Get quantum state for session
     */
    private function get_quantum_state($session_id) {
        global $wpdb;

        $state = $wpdb->get_var($wpdb->prepare("
            SELECT quantum_state FROM {$wpdb->prefix}affcd_quantum_states 
            WHERE session_id = %s
        ", $session_id));

        return $state ? json_decode($state, true) : [
            'affiliate_probabilities' => [],
            'touchpoint_weights' => [],
            'conversion_likelihood' => 0.5,
            'attribution_entropy' => 1.0
        ];
    }

    /**
     * Calculate quantum probabilities
     */
    private function calculate_quantum_probabilities($current_state, $touchpoint_data) {
        $affiliate_id = $touchpoint_data['affiliate_id'];
        $interaction_quality = $touchpoint_data['interaction_quality'];
        $conversion_probability = $touchpoint_data['conversion_probability'];

        // Initialise if first touchpoint
        if (empty($current_state['affiliate_probabilities'])) {
            $current_state['affiliate_probabilities'] = [];
            $current_state['touchpoint_weights'] = [];
        }

        // Update affiliate probability using quantum superposition
        if ($affiliate_id) {
            $current_prob = $current_state['affiliate_probabilities'][$affiliate_id] ?? 0;
            
            // Quantum interference calculation
            $new_prob = $this->quantum_interference($current_prob, $interaction_quality, $conversion_probability);
            
            // Normalize probabilities to maintain quantum coherence
            $current_state['affiliate_probabilities'][$affiliate_id] = $new_prob;
            $current_state = $this->normalize_quantum_probabilities($current_state);
        }

        // Update conversion likelihood
        $current_state['conversion_likelihood'] = min(
            $current_state['conversion_likelihood'] + ($conversion_probability * 0.1),
            1.0
        );

        // Calculate attribution entropy (measure of uncertainty)
        $current_state['attribution_entropy'] = $this->calculate_attribution_entropy($current_state['affiliate_probabilities']);

        // Store touchpoint weight
        $touchpoint_weight = $this->calculate_touchpoint_weight($touchpoint_data, $current_state);
        $current_state['touchpoint_weights'][] = $touchpoint_weight;

        return $current_state;
    }

    /**
     * Quantum interference calculation
     */
    private function quantum_interference($current_prob, $quality, $conversion_prob) {
        // Quantum wave function interference
        $amplitude = sqrt($current_prob);
        $new_amplitude = sqrt($conversion_prob * $quality);
        
        // Constructive/destructive interference
        $phase_difference = $this->calculate_phase_difference($quality);
        $interference = $amplitude + $new_amplitude * cos($phase_difference);
        
        // Convert back to probability
        return min(pow($interference, 2), 1.0);
    }

    /**
     * Calculate phase difference for quantum interference
     */
    private function calculate_phase_difference($quality) {
        // High quality interactions have constructive interference
        // Low quality interactions have destructive interference
        return ($quality - 0.5) * pi();
    }

    /**
     * Normalize quantum probabilities
     */
    private function normalize_quantum_probabilities($state) {
        $total_prob = array_sum($state['affiliate_probabilities']);
        
        if ($total_prob > 1.0) {
            foreach ($state['affiliate_probabilities'] as $affiliate_id => $prob) {
                $state['affiliate_probabilities'][$affiliate_id] = $prob / $total_prob;
            }
        }

        return $state;
    }

    /**
     * Calculate attribution entropy
     */
    private function calculate_attribution_entropy($probabilities) {
        if (empty($probabilities)) {
            return 1.0;
        }

        $entropy = 0;
        foreach ($probabilities as $prob) {
            if ($prob > 0) {
                $entropy -= $prob * log($prob, 2);
            }
        }

        return $entropy;
    }

    /**
     * Store quantum state
     */
    private function store_quantum_state($session_id, $quantum_state) {
        global $wpdb;

        $wpdb->replace(
            $wpdb->prefix . 'affcd_quantum_states',
            [
                'session_id' => $session_id,
                'quantum_state' => json_encode($quantum_state),
                'last_updated' => current_time('mysql'),
                'site_url' => home_url()
            ]
        );
    }

    /**
     * Finalize attribution on conversion
     */
    public function finalize_attribution($order_id) {
        $session_id = $this->get_session_id();
        $conversion_value = $this->get_conversion_value($order_id);
        
        // Get all touchpoints for this session
        $touchpoints = $this->get_session_touchpoints($session_id);
        
        if (empty($touchpoints)) {
            return;
        }

        // Get quantum state
        $quantum_state = $this->get_quantum_state($session_id);

        // Calculate attribution for each model
        $attribution_results = [];
        foreach ($this->attribution_models as $model_name => $model) {
            $attribution_results[$model_name] = $this->calculate_model_attribution(
                $touchpoints, 
                $conversion_value, 
                $model, 
                $quantum_state
            );
        }

        // Calculate weighted final attribution
        $final_attribution = $this->calculate_weighted_attribution($attribution_results);

        // Store attribution results
        $this->store_attribution_results($order_id, $session_id, $attribution_results, $final_attribution);

        // Send to master site
        $this->sync_attribution_with_master($order_id, $final_attribution, $attribution_results);
    }

    /**
     * Calculate model-specific attribution
     */
    private function calculate_model_attribution($touchpoints, $conversion_value, $model, $quantum_state) {
        $weight_function = $model['weight_function'];
        $weights = call_user_func($weight_function, $touchpoints, $quantum_state);
        
        $attribution = [];
        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = $touchpoint['affiliate_id'];
            if ($affiliate_id) {
                $attributed_value = $conversion_value * $weights[$index];
                
                if (!isset($attribution[$affiliate_id])) {
                    $attribution[$affiliate_id] = 0;
                }
                $attribution[$affiliate_id] += $attributed_value;
            }
        }

        return $attribution;
    }

    /**
     * Last click attribution weights
     */
    private function last_click_weights($touchpoints, $quantum_state) {
        $weights = array_fill(0, count($touchpoints), 0);
        
        // Find last touchpoint with affiliate
        for ($i = count($touchpoints) - 1; $i >= 0; $i--) {
            if (!empty($touchpoints[$i]['affiliate_id'])) {
                $weights[$i] = 1.0;
                break;
            }
        }

        return $weights;
    }

    /**
     * First click attribution weights
     */
    private function first_click_weights($touchpoints, $quantum_state) {
        $weights = array_fill(0, count($touchpoints), 0);
        
        // Find first touchpoint with affiliate
        foreach ($touchpoints as $index => $touchpoint) {
            if (!empty($touchpoint['affiliate_id'])) {
                $weights[$index] = 1.0;
                break;
            }
        }

        return $weights;
    }

    /**
     * Linear attribution weights
     */
    private function linear_weights($touchpoints, $quantum_state) {
        $affiliate_touchpoints = array_filter($touchpoints, function($tp) {
            return !empty($tp['affiliate_id']);
        });

        $count = count($affiliate_touchpoints);
        if ($count === 0) {
            return array_fill(0, count($touchpoints), 0);
        }

        $weight_per_touchpoint = 1.0 / $count;
        $weights = [];

        foreach ($touchpoints as $touchpoint) {
            $weights[] = !empty($touchpoint['affiliate_id']) ? $weight_per_touchpoint : 0;
        }

        return $weights;
    }

    /**
     * Time decay attribution weights
     */
    private function time_decay_weights($touchpoints, $quantum_state) {
        $weights = [];
        $total_weight = 0;
        $decay_rate = 0.7; // 7-day half-life

        $conversion_time = time();

        // Calculate decay weights
        foreach ($touchpoints as $touchpoint) {
            if (empty($touchpoint['affiliate_id'])) {
                $weights[] = 0;
                continue;
            }

            $touchpoint_time = strtotime($touchpoint['timestamp']);
            $time_diff_days = ($conversion_time - $touchpoint_time) / 86400;
            
            $weight = pow($decay_rate, $time_diff_days);
            $weights[] = $weight;
            $total_weight += $weight;
        }

        // Normalize weights
        if ($total_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_weight;
            }
        }

        return $weights;
    }

    /**
     * Position-based (U-shaped) attribution weights
     */
    private function position_based_weights($touchpoints, $quantum_state) {
        $affiliate_indices = [];
        foreach ($touchpoints as $index => $touchpoint) {
            if (!empty($touchpoint['affiliate_id'])) {
                $affiliate_indices[] = $index;
            }
        }

        $weights = array_fill(0, count($touchpoints), 0);
        $count = count($affiliate_indices);

        if ($count === 0) {
            return $weights;
        }

        if ($count === 1) {
            $weights[$affiliate_indices[0]] = 1.0;
        } elseif ($count === 2) {
            $weights[$affiliate_indices[0]] = 0.5; // First
            $weights[$affiliate_indices[1]] = 0.5; // Last
        } else {
            // First gets 40%, last gets 40%, middle gets 20% split
            $weights[$affiliate_indices[0]] = 0.4; // First
            $weights[$affiliate_indices[$count - 1]] = 0.4; // Last
            
            $middle_weight = 0.2 / ($count - 2);
            for ($i = 1; $i < $count - 1; $i++) {
                $weights[$affiliate_indices[$i]] = $middle_weight;
            }
        }

        return $weights;
    }

    /**
     * Data-driven attribution weights
     */
    private function data_driven_weights($touchpoints, $quantum_state) {
        // This would use machine learning to determine optimal weights
        // For now, we'll use a sophisticated heuristic approach
        
        $weights = [];
        $total_weight = 0;

        foreach ($touchpoints as $index => $touchpoint) {
            if (empty($touchpoint['affiliate_id'])) {
                $weights[] = 0;
                continue;
            }

            // Calculate weight based on multiple factors
            $quality_score = $touchpoint['interaction_quality'] ?? 0.5;
            $conversion_prob = $touchpoint['conversion_probability'] ?? 0.5;
            $recency_factor = $this->calculate_recency_factor($touchpoint['timestamp']);
            $position_factor = $this->calculate_position_factor($index, count($touchpoints));

            $weight = $quality_score * $conversion_prob * $recency_factor * $position_factor;
            $weights[] = $weight;
            $total_weight += $weight;
        }

        // Normalize weights
        if ($total_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_weight;
            }
        }

        return $weights;
    }

    /**
     * Quantum superposition attribution weights
     */
    private function quantum_superposition_weights($touchpoints, $quantum_state) {
        if (empty($quantum_state['affiliate_probabilities'])) {
            return $this->linear_weights($touchpoints, $quantum_state);
        }

        $weights = [];
        $total_quantum_weight = 0;

        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = $touchpoint['affiliate_id'] ?? null;
            
            if (!$affiliate_id) {
                $weights[] = 0;
                continue;
            }

            // Get quantum probability for this affiliate
            $quantum_prob = $quantum_state['affiliate_probabilities'][$affiliate_id] ?? 0;
            
            // Apply quantum uncertainty principle
            $uncertainty = $quantum_state['attribution_entropy'] ?? 1.0;
            $quantum_weight = $quantum_prob * (1 + $uncertainty * 0.1);
            
            // Apply touchpoint quality modifier
            $quality = $touchpoint['interaction_quality'] ?? 0.5;
            $quantum_weight *= (0.5 + $quality * 0.5);

            $weights[] = $quantum_weight;
            $total_quantum_weight += $quantum_weight;
        }

        // Quantum normalization (maintains superposition)
        if ($total_quantum_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_quantum_weight;
            }
        }

        return $weights;
    }

    /**
     * Calculate weighted final attribution
     */
    private function calculate_weighted_attribution($attribution_results) {
        $final_attribution = [];

        foreach ($attribution_results as $model_name => $model_attribution) {
            $model_weight = $this->attribution_weights[$model_name] ?? 0;
            
            foreach ($model_attribution as $affiliate_id => $value) {
                if (!isset($final_attribution[$affiliate_id])) {
                    $final_attribution[$affiliate_id] = 0;
                }
                $final_attribution[$affiliate_id] += $value * $model_weight;
            }
        }

        return $final_attribution;
    }

    /**
     * Store attribution results
     */
    private function store_attribution_results($order_id, $session_id, $model_results, $final_attribution) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'affcd_attribution_results',
            [
                'order_id' => $order_id,
                'session_id' => $session_id,
                'model_results' => json_encode($model_results),
                'final_attribution' => json_encode($final_attribution),
                'attribution_confidence' => $this->calculate_attribution_confidence($model_results),
                'quantum_entropy' => $this->get_quantum_state($session_id)['attribution_entropy'] ?? 1.0,
                'created_at' => current_time('mysql'),
                'site_url' => home_url()
            ]
        );
    }

    /**
     * Calculate attribution confidence
     */
    private function calculate_attribution_confidence($model_results) {
        if (empty($model_results)) {
            return 0;
        }

        // Calculate consistency across models
        $affiliate_totals = [];
        foreach ($model_results as $model_name => $results) {
            foreach ($results as $affiliate_id => $value) {
                if (!isset($affiliate_totals[$affiliate_id])) {
                    $affiliate_totals[$affiliate_id] = [];
                }
                $affiliate_totals[$affiliate_id][$model_name] = $value;
            }
        }

        // Calculate variance for each affiliate
        $total_variance = 0;
        $affiliate_count = 0;

        foreach ($affiliate_totals as $affiliate_id => $model_values) {
            if (count($model_values) > 1) {
                $mean = array_sum($model_values) / count($model_values);
                $variance = 0;
                
                foreach ($model_values as $value) {
                    $variance += pow($value - $mean, 2);
                }
                $variance /= count($model_values);
                
                $total_variance += $variance;
                $affiliate_count++;
            }
        }

        if ($affiliate_count === 0) {
            return 1.0;
        }

        $avg_variance = $total_variance / $affiliate_count;
        
        // Convert variance to confidence (inverse relationship)
        return max(0, min(1, 1 - ($avg_variance / 100)));
    }

    /**
     * Sync attribution with master site
     */
    private function sync_attribution_with_master($order_id, $final_attribution, $model_results) {
        $sync_data = [
            'order_id' => $order_id,
            'site_url' => home_url(),
            'final_attribution' => $final_attribution,
            'model_results' => $model_results,
            'attribution_timestamp' => current_time('mysql'),
            'quantum_enhanced' => true
        ];

        $this->parent->api_client->sync_attribution_data($sync_data);
    }

    /**
     * Inject attribution tracking script
     */
    public function inject_attribution_tracking() {
        ?>
        <script>
        // Quantum Attribution Tracking
        (function() {
            var attributionData = {
                session_id: affcdSatellite.sessionId,
                page_interactions: [],
                micro_conversions: [],
                engagement_score: 0,
                conversion_signals: []
            };

            // Track micro-conversions
            trackMicroConversions();
            
            // Track engagement quality
            trackEngagementQuality();
            
            // Track conversion signals
            trackConversionSignals();

            function trackMicroConversions() {
                // Track scroll milestones
                var scrollMilestones = [25, 50, 75, 90];
                var trackedMilestones = [];

                jQuery(window).scroll(function() {
                    var scrollPercent = (jQuery(window).scrollTop() / (jQuery(document).height() - jQuery(window).height())) * 100;
                    
                    scrollMilestones.forEach(function(milestone) {
                        if (scrollPercent >= milestone && trackedMilestones.indexOf(milestone) === -1) {
                            trackedMilestones.push(milestone);
                            attributionData.micro_conversions.push({
                                type: 'scroll_milestone',
                                value: milestone,
                                timestamp: Date.now()
                            });
                        }
                    });
                });

                // Track time on page milestones
                var timeMilestones = [30, 60, 120, 300]; // seconds
                var startTime = Date.now();

                timeMilestones.forEach(function(milestone) {
                    setTimeout(function() {
                        if (document.visibilityState === 'visible') {
                            attributionData.micro_conversions.push({
                                type: 'time_milestone',
                                value: milestone,
                                timestamp: Date.now()
                            });
                        }
                    }, milestone * 1000);
                });

                // Track interaction events
                jQuery(document).on('click', 'a, button', function() {
                    attributionData.page_interactions.push({
                        type: 'click',
                        element: this.tagName,
                        timestamp: Date.now()
                    });
                });
            }

            function trackEngagementQuality() {
                var engagementFactors = {
                    timeOnPage: 0,
                    scrollDepth: 0,
                    interactions: 0,
                    returnVisitor: localStorage.getItem('affcd_return_visitor') ? 1 : 0
                };

                // Mark as return visitor
                if (!localStorage.getItem('affcd_return_visitor')) {
                    localStorage.setItem('affcd_return_visitor', Date.now());
                }

                // Calculate engagement score periodically
                setInterval(function() {
                    engagementFactors.timeOnPage = (Date.now() - startTime) / 1000;
                    engagementFactors.scrollDepth = (jQuery(window).scrollTop() / (jQuery(document).height() - jQuery(window).height())) * 100;
                    engagementFactors.interactions = attributionData.page_interactions.length;

                    // Calculate weighted engagement score
                    var score = (
                        Math.min(engagementFactors.timeOnPage / 300, 1) * 0.3 +
                        Math.min(engagementFactors.scrollDepth / 100, 1) * 0.3 +
                        Math.min(engagementFactors.interactions / 10, 1) * 0.2 +
                        engagementFactors.returnVisitor * 0.2
                    );

                    attributionData.engagement_score = score;
                }, 10000); // Update every 10 seconds
            }

            function trackConversionSignals() {
                // Track form focus events
                jQuery('form input, form textarea').on('focus', function() {
                    attributionData.conversion_signals.push({
                        type: 'form_focus',
                        form_id: jQuery(this).closest('form').attr('id'),
                        timestamp: Date.now()
                    });
                });

                // Track add to cart events (if WooCommerce)
                jQuery(document).on('click', '.add_to_cart_button', function() {
                    attributionData.conversion_signals.push({
                        type: 'add_to_cart_intent',
                        product_id: jQuery(this).data('product_id'),
                        timestamp: Date.now()
                    });
                });

                // Track checkout page visits
                if (window.location.href.indexOf('checkout') !== -1) {
                    attributionData.conversion_signals.push({
                        type: 'checkout_page_visit',
                        timestamp: Date.now()
                    });
                }
            }

            // Send attribution data periodically
            setInterval(function() {
                if (attributionData.micro_conversions.length > 0 || attributionData.conversion_signals.length > 0) {
                    jQuery.post(affcdSatellite.ajaxUrl, {
                        action: 'affcd_attribution_touchpoint',
                        nonce: affcdSatellite.nonce,
                        attribution_data: attributionData
                    });

                    // Clear sent data
                    attributionData.micro_conversions = [];
                    attributionData.conversion_signals = [];
                }
            }, 30000); // Every 30 seconds

            // Send final data on page unload
            window.addEventListener('beforeunload', function() {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(affcdSatellite.ajaxUrl, new URLSearchParams({
                        action: 'affcd_attribution_touchpoint',
                        nonce: affcdSatellite.nonce,
                        attribution_data: JSON.stringify(attributionData),
                        final_send: true
                    }));
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Helper methods
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['affcd_session_id'])) {
            $_SESSION['affcd_session_id'] = 'affcd_' . uniqid() . '_' . time();
        }
        
        return $_SESSION['affcd_session_id'];
    }

    private function detect_affiliate_from_touchpoint() {
        // Check URL parameters, cookies, etc.
        if (isset($_GET['ref'])) {
            return intval($_GET['ref']);
        }
        
        if (isset($_COOKIE['affcd_affiliate_id'])) {
            return intval($_COOKIE['affcd_affiliate_id']);
        }
        
        return get_option('affcd_default_affiliate_id', null);
    }

    private function extract_campaign_data() {
        return [
            'utm_source' => $_GET['utm_source'] ?? null,
            'utm_medium' => $_GET['utm_medium'] ?? null,
            'utm_campaign' => $_GET['utm_campaign'] ?? null,
            'utm_term' => $_GET['utm_term'] ?? null,
            'utm_content' => $_GET['utm_content'] ?? null
        ];
    }

    private function calculate_interaction_quality($form_data) {
        // Analyze form data quality
        $quality_score = 0.5; // Base score
        
        // Check for complete email
        if (!empty($form_data['email']) && filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $quality_score += 0.2;
        }
        
        // Check for phone number
        if (!empty($form_data['phone'])) {
            $quality_score += 0.1;
        }
        
        // Check for name completeness
        if (!empty($form_data['name']) && str_word_count($form_data['name']) >= 2) {
            $quality_score += 0.1;
        }
        
        return min($quality_score, 1.0);
    }

    private function predict_conversion_probability($form_data) {
        // Simple heuristic for conversion probability
        // In production, this would use machine learning
        
        $probability = 0.3; // Base probability
        
        // High-value form types
        $high_value_indicators = ['demo', 'quote', 'consultation', 'trial'];
        foreach ($high_value_indicators as $indicator) {
            if (stripos(json_encode($form_data), $indicator) !== false) {
                $probability += 0.3;
                break;
            }
        }
        
        // Corporate email domains
        if (!empty($form_data['email'])) {
            $domain = substr(strrchr($form_data['email'], "@"), 1);
            $consumer_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
            if (!in_array($domain, $consumer_domains)) {
                $probability += 0.2;
            }
        }
        
        return min($probability, 1.0);
    }

    private function calculate_recency_factor($timestamp) {
        $hours_ago = (time() - strtotime($timestamp)) / 3600;
        return max(0.1, 1 - ($hours_ago / 168)); // Decay over 1 week
    }

    private function calculate_position_factor($index, $total) {
        if ($total <= 1) return 1.0;
        
        // U-shaped curve: higher weight for first and last positions
        $normalized_position = $index / ($total - 1);
        return 0.5 + 0.5 * abs(2 * $normalized_position - 1);
    }

    private function get_conversion_value($order_id) {
        if (function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            return $order ? $order->get_total() : 0;
        }
        return 100; // Default value
    }

    private function get_session_touchpoints($session_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}affcd_attribution_touchpoints 
            WHERE session_id = %s 
            ORDER BY timestamp ASC
        ", $session_id), ARRAY_A);
    }
}

/**
 * Create database tables for revolutionary features
 */
function create_affcd_revolutionary_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

 // Viral opportunities table
    $sql_viral = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_viral_opportunities (
        id int(11) NOT NULL AUTO_INCREMENT,
        customer_email varchar(255) NOT NULL,
        customer_name varchar(255),
        trigger_type varchar(50) NOT NULL,
        viral_score decimal(5,2) DEFAULT 50.00,
        incentive_offered decimal(5,2) DEFAULT 0.00,
        viral_token varchar(255),
        metadata longtext,
        status varchar(50) DEFAULT 'scheduled',
        affiliate_id int(11),
        conversion_date datetime NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY email_status (customer_email, status),
        KEY trigger_viral_score (trigger_type, viral_score),
        KEY created_status (created_at, status)
    ) $charset_collate;";

    // Identity data table
    $sql_identity = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_identity_data (
        id int(11) NOT NULL AUTO_INCREMENT,
        identity_hash varchar(255) NOT NULL,
        source varchar(100) NOT NULL,
        email varchar(255),
        email_hash varchar(255),
        phone varchar(50),
        full_name varchar(255),
        name_parts json,
        device_fingerprint varchar(255),
        ip_address varchar(45),
        user_agent text,
        session_id varchar(255),
        additional_data longtext,
        site_url varchar(255),
        collected_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY identity_hash (identity_hash),
        KEY email_hash (email_hash),
        KEY device_fingerprint (device_fingerprint),
        KEY session_collected (session_id, collected_at),
        KEY source_site (source, site_url)
    ) $charset_collate;";

    // Identity links table
    $sql_identity_links = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_identity_links (
        id int(11) NOT NULL AUTO_INCREMENT,
        identity_hash_1 varchar(255) NOT NULL,
        identity_hash_2 varchar(255) NOT NULL,
        link_type varchar(100) NOT NULL,
        confidence_level varchar(50) NOT NULL,
        link_strength decimal(5,2) NOT NULL,
        match_data longtext,
        status varchar(50) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        verified_at datetime NULL,
        PRIMARY KEY (id),
        KEY identity_pair (identity_hash_1, identity_hash_2),
        KEY link_confidence (link_type, confidence_level),
        KEY strength_status (link_strength, status)
    ) $charset_collate;";

    // Attribution touchpoints table
    $sql_attribution = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_attribution_touchpoints (
        id int(11) NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        affiliate_id int(11),
        touchpoint_type varchar(100) NOT NULL,
        page_url text,
        referrer text,
        campaign_data json,
        interaction_quality decimal(3,2) DEFAULT 0.50,
        conversion_probability decimal(3,2) DEFAULT 0.50,
        ip_address varchar(45),
        user_agent text,
        metadata longtext,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        site_url varchar(255),
        PRIMARY KEY (id),
        KEY session_affiliate (session_id, affiliate_id),
        KEY touchpoint_timestamp (touchpoint_type, timestamp),
        KEY quality_probability (interaction_quality, conversion_probability)
    ) $charset_collate;";

    // Quantum states table
    $sql_quantum = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_quantum_states (
        id int(11) NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        quantum_state longtext NOT NULL,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        site_url varchar(255),
        PRIMARY KEY (id),
        UNIQUE KEY session_site (session_id, site_url),
        KEY updated_site (last_updated, site_url)
    ) $charset_collate;";

    // Attribution results table
    $sql_results = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_attribution_results (
        id int(11) NOT NULL AUTO_INCREMENT,
        order_id varchar(255) NOT NULL,
        session_id varchar(255) NOT NULL,
        model_results longtext NOT NULL,
        final_attribution longtext NOT NULL,
        attribution_confidence decimal(3,2) DEFAULT 0.50,
        quantum_entropy decimal(3,2) DEFAULT 1.00,
        total_conversion_value decimal(10,2) DEFAULT 0.00,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        site_url varchar(255),
        synced_to_master tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY order_session (order_id, session_id),
        KEY confidence_entropy (attribution_confidence, quantum_entropy),
        KEY created_synced (created_at, synced_to_master)
    ) $charset_collate;";

    // Viral performance tracking table
    $sql_viral_performance = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_viral_performance (
        id int(11) NOT NULL AUTO_INCREMENT,
        viral_opportunity_id int(11) NOT NULL,
        metric_type varchar(100) NOT NULL,
        metric_value decimal(10,2) NOT NULL,
        measurement_date date NOT NULL,
        affiliate_id int(11),
        referral_count int(11) DEFAULT 0,
        revenue_generated decimal(10,2) DEFAULT 0.00,
        viral_coefficient decimal(5,3) DEFAULT 0.000,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY opportunity_metric (viral_opportunity_id, metric_type),
        KEY affiliate_performance (affiliate_id, measurement_date),
        KEY viral_coefficient_date (viral_coefficient, measurement_date)
    ) $charset_collate;";

    // Cross-platform sessions table
    $sql_sessions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}affcd_cross_platform_sessions (
        id int(11) NOT NULL AUTO_INCREMENT,
        unified_session_id varchar(255) NOT NULL,
        platform_session_id varchar(255) NOT NULL,
        platform_type varchar(100) NOT NULL,
        device_info json,
        first_seen datetime DEFAULT CURRENT_TIMESTAMP,
        last_seen datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        total_interactions int(11) DEFAULT 1,
        conversion_events int(11) DEFAULT 0,
        site_url varchar(255),
        PRIMARY KEY (id),
        UNIQUE KEY platform_session (platform_session_id, platform_type),
        KEY unified_platform (unified_session_id, platform_type),
        KEY activity_tracking (last_seen, total_interactions)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_viral);
    dbDelta($sql_identity);
    dbDelta($sql_identity_links);
    dbDelta($sql_attribution);
    dbDelta($sql_quantum);
    dbDelta($sql_results);
    dbDelta($sql_viral_performance);
    dbDelta($sql_sessions);
}

/**
 * Initialse Revolutionary Features
 */
class AFFCD_Revolutionary_Features_Manager {

    private $viral_engine;
    private $identity_resolution;
    private $quantum_attribution;

    public function __construct($parent_plugin) {
        // Initialise revolutionary features
        $this->viral_engine = new AFFCD_Viral_Engine($parent_plugin);
        $this->identity_resolution = new AFFCD_Identity_Resolution($parent_plugin);
        $this->quantum_attribution = new AFFCD_Quantum_Attribution($parent_plugin);

        $this->init_hooks();
    }

    /**
     * Initialse feature coordination hooks
     */
    private function init_hooks() {
        // Coordinate between systems
        add_action('affcd_identity_linked', [$this, 'trigger_attribution_recalculation'], 10, 3);
        add_action('affcd_viral_conversion', [$this, 'update_identity_viral_score'], 10, 3);
        add_action('affcd_attribution_finalized', [$this, 'trigger_viral_opportunities'], 10, 2);

        // Advanced reporting
        add_action('affcd_generate_advanced_reports', [$this, 'generate_revolutionary_insights']);
        
        // Performance optimization
        add_action('affcd_optimize_revolutionary_features', [$this, 'optimize_feature_performance']);

        // Data cleanup
        add_action('affcd_cleanup_revolutionary_data', [$this, 'cleanup_old_data']);

        // Admin interface enhancements
        add_action('admin_menu', [$this, 'add_revolutionary_admin_pages']);
        
        // API endpoints for advanced features
        add_action('rest_api_init', [$this, 'register_revolutionary_endpoints']);
    }

    /**
     * Trigger attribution recalculation when identities are linked
     */
    public function trigger_attribution_recalculation($identity1, $match, $link_strength) {
        // When identities are linked, recalculate all related attributions
        $this->quantum_attribution->recalculate_linked_attributions($identity1, $match);
    }

    /**
     * Update identity viral score when viral conversion happens
     */
    public function update_identity_viral_score($opportunity, $affiliate_id, $action) {
        if ($action === 'accepted') {
            // Boost viral score for this customer's identity
            $this->identity_resolution->boost_viral_score($opportunity->customer_email, 15);
        }
    }

    /**
     * Trigger viral opportunities based on attribution patterns
     */
    public function trigger_viral_opportunities($attribution_results, $order_data) {
        // High-value conversions with strong attribution confidence = good viral candidates
        if ($attribution_results['attribution_confidence'] > 0.8 && $order_data['value'] > 200) {
            $this->viral_engine->schedule_high_value_viral_invitation($order_data);
        }
    }

    /**
     * Generate revolutionary insights
     */
    public function generate_revolutionary_insights() {
        $insights = [
            'viral_coefficient' => $this->calculate_viral_coefficient(),
            'identity_resolution_rate' => $this->calculate_identity_resolution_rate(),
            'attribution_accuracy' => $this->calculate_attribution_accuracy(),
            'cross_platform_engagement' => $this->analyze_cross_platform_engagement(),
            'quantum_attribution_advantage' => $this->measure_quantum_advantage()
        ];

        // Store insights
        update_option('affcd_revolutionary_insights', $insights);

        // Send to master site
        $this->sync_insights_with_master($insights);

        return $insights;
    }

    /**
     * Calculate viral coefficient
     */
    private function calculate_viral_coefficient() {
        global $wpdb;

        $viral_data = $wpdb->get_row("
            SELECT 
                COUNT(DISTINCT v.id) as total_invitations,
                COUNT(DISTINCT CASE WHEN v.status = 'converted' THEN v.id END) as conversions,
                COUNT(DISTINCT vp.referral_count) as total_referrals,
                AVG(vp.viral_coefficient) as avg_coefficient
            FROM {$wpdb->prefix}affcd_viral_opportunities v
            LEFT JOIN {$wpdb->prefix}affcd_viral_performance vp ON v.id = vp.viral_opportunity_id
            WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        if (!$viral_data || $viral_data->total_invitations == 0) {
            return 0;
        }

        $conversion_rate = $viral_data->conversions / $viral_data->total_invitations;
        $avg_referrals_per_convert = $viral_data->total_referrals / max($viral_data->conversions, 1);
        
        return $conversion_rate * $avg_referrals_per_convert;
    }

    /**
     * Calculate identity resolution rate
     */
    private function calculate_identity_resolution_rate() {
        global $wpdb;

        $resolution_data = $wpdb->get_row("
            SELECT 
                COUNT(DISTINCT identity_hash) as total_identities,
                COUNT(DISTINCT il.identity_hash_1) as linked_identities,
                AVG(il.link_strength) as avg_link_strength
            FROM {$wpdb->prefix}affcd_identity_data id
            LEFT JOIN {$wpdb->prefix}affcd_identity_links il ON id.identity_hash = il.identity_hash_1
            WHERE id.collected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        if (!$resolution_data || $resolution_data->total_identities == 0) {
            return 0;
        }

        return [
            'resolution_rate' => $resolution_data->linked_identities / $resolution_data->total_identities,
            'avg_link_strength' => $resolution_data->avg_link_strength ?? 0
        ];
    }

    /**
     * Calculate attribution accuracy
     */
    private function calculate_attribution_accuracy() {
        global $wpdb;

        $accuracy_data = $wpdb->get_row("
            SELECT 
                AVG(attribution_confidence) as avg_confidence,
                AVG(quantum_entropy) as avg_entropy,
                COUNT(*) as total_attributions
            FROM {$wpdb->prefix}affcd_attribution_results
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        return [
            'confidence' => $accuracy_data->avg_confidence ?? 0.5,
            'entropy' => $accuracy_data->avg_entropy ?? 1.0,
            'total_attributions' => $accuracy_data->total_attributions ?? 0
        ];
    }

    /**
     * Add revolutionary admin pages
     */
    public function add_revolutionary_admin_pages() {
        add_submenu_page(
            'affcd-satellite',
            __('Revolutionary Analytics', 'affcd-satellite'),
            __('🚀 Advanced Analytics', 'affcd-satellite'),
            'manage_options',
            'affcd-revolutionary',
            [$this, 'render_revolutionary_dashboard']
        );
    }

    /**
     * Render revolutionary dashboard
     */
    public function render_revolutionary_dashboard() {
        $insights = get_option('affcd_revolutionary_insights', []);
        ?>
        <div class="wrap affcd-revolutionary-dashboard">
            <h1><?php _e('🚀 Revolutionary Analytics Dashboard', 'affcd-satellite'); ?></h1>

            <div class="revolutionary-stats-grid">
                <!-- Viral Coefficient Card -->
                <div class="stat-card viral-coefficient">
                    <div class="stat-header">
                        <h3>🦠 Viral Coefficient</h3>
                        <div class="stat-value"><?php echo number_format($insights['viral_coefficient'] ?? 0, 3); ?></div>
                    </div>
                    <div class="stat-description">
                        <p>Average number of new users each customer brings through viral sharing.</p>
                        <div class="stat-benchmark">
                            <?php $vc = $insights['viral_coefficient'] ?? 0; ?>
                            <span class="benchmark-label">
                                <?php if ($vc > 1): ?>
                                    <span class="excellent">🔥 Viral Growth!</span>
                                <?php elseif ($vc > 0.5): ?>
                                    <span class="good">✨ Strong Viral Effect</span>
                                <?php elseif ($vc > 0.1): ?>
                                    <span class="moderate">📈 Moderate Viral Growth</span>
                                <?php else: ?>
                                    <span class="poor">💡 Optimization Needed</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Identity Resolution Card -->
                <div class="stat-card identity-resolution">
                    <div class="stat-header">
                        <h3>🔗 Identity Resolution</h3>
                        <div class="stat-value"><?php echo number_format(($insights['identity_resolution_rate']['resolution_rate'] ?? 0) * 100, 1); ?>%</div>
                    </div>
                    <div class="stat-description">
                        <p>Percentage of customer identities successfully linked across platforms.</p>
                        <div class="sub-stat">
                            Link Strength: <?php echo number_format($insights['identity_resolution_rate']['avg_link_strength'] ?? 0, 1); ?>/100
                        </div>
                    </div>
                </div>

                <!-- Attribution Accuracy Card -->
                <div class="stat-card attribution-accuracy">
                    <div class="stat-header">
                        <h3>🎯 Attribution Accuracy</h3>
                        <div class="stat-value"><?php echo number_format(($insights['attribution_accuracy']['confidence'] ?? 0) * 100, 1); ?>%</div>
                    </div>
                    <div class="stat-description">
                        <p>Confidence level in quantum attribution model results.</p>
                        <div class="sub-stat">
                            Entropy: <?php echo number_format($insights['attribution_accuracy']['entropy'] ?? 0, 2); ?>
                        </div>
                    </div>
                </div>

                <!-- Quantum Advantage Card -->
                <div class="stat-card quantum-advantage">
                    <div class="stat-header">
                        <h3>⚛️ Quantum Advantage</h3>
                        <div class="stat-value"><?php echo number_format(($insights['quantum_attribution_advantage'] ?? 0) * 100, 1); ?>%</div>
                    </div>
                    <div class="stat-description">
                        <p>Revenue increase from quantum attribution vs. traditional models.</p>
                        <div class="sub-stat">
                            Additional revenue recovered through advanced attribution
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature Performance Section -->
            <div class="feature-performance-section">
                <h2><?php _e('Feature Performance Analysis', 'affcd-satellite'); ?></h2>
                
                <div class="performance-tabs">
                    <button class="tab-button active" data-tab="viral">Viral Engine</button>
                    <button class="tab-button" data-tab="identity">Identity Resolution</button>
                    <button class="tab-button" data-tab="attribution">Quantum Attribution</button>
                </div>

                <div class="tab-content">
                    <!-- Viral Performance Tab -->
                    <div id="viral-tab" class="tab-pane active">
                        <div class="viral-performance-charts">
                            <canvas id="viral-coefficient-chart" width="400" height="200"></canvas>
                        </div>
                        
                        <div class="viral-insights">
                            <h4>🎯 Viral Optimization Recommendations</h4>
                            <ul class="recommendations-list">
                                <?php echo $this->generate_viral_recommendations(); ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Identity Resolution Tab -->
                    <div id="identity-tab" class="tab-pane">
                        <div class="identity-resolution-metrics">
                            <canvas id="identity-resolution-chart" width="400" height="200"></canvas>
                        </div>
                        
                        <div class="identity-insights">
                            <h4>🔍 Identity Resolution Insights</h4>
                            <ul class="recommendations-list">
                                <?php echo $this->generate_identity_recommendations(); ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Attribution Tab -->
                    <div id="attribution-tab" class="tab-pane">
                        <div class="attribution-model-comparison">
                            <canvas id="attribution-comparison-chart" width="400" height="200"></canvas>
                        </div>
                        
                        <div class="attribution-insights">
                            <h4>⚛️ Quantum Attribution Insights</h4>
                            <ul class="recommendations-list">
                                <?php echo $this->generate_attribution_recommendations(); ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real-time Monitoring -->
            <div class="realtime-monitoring">
                <h2><?php _e('🔴 Real-Time Revolutionary Metrics', 'affcd-satellite'); ?></h2>
                
                <div class="realtime-grid">
                    <div class="realtime-metric">
                        <span class="metric-label">Active Viral Campaigns</span>
                        <span class="metric-value" id="active-viral-campaigns">-</span>
                    </div>
                    
                    <div class="realtime-metric">
                        <span class="metric-label">Identities Resolved (24h)</span>
                        <span class="metric-value" id="identities-resolved-24h">-</span>
                    </div>
                    
                    <div class="realtime-metric">
                        <span class="metric-label">Quantum Attributions</span>
                        <span class="metric-value" id="quantum-attributions">-</span>
                    </div>
                    
                    <div class="realtime-metric">
                        <span class="metric-label">Revenue Recovery</span>
                        <span class="metric-value" id="revenue-recovery">$-</span>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .affcd-revolutionary-dashboard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .revolutionary-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .stat-header h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .stat-description {
            font-size: 14px;
            opacity: 0.8;
            line-height: 1.4;
        }

        .sub-stat {
            margin-top: 8px;
            font-size: 12px;
            opacity: 0.7;
        }

        .benchmark-label .excellent { color: #00ff88; }
        .benchmark-label .good { color: #88ff00; }
        .benchmark-label .moderate { color: #ffaa00; }
        .benchmark-label .poor { color: #ff4444; }

        .feature-performance-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
        }

        .performance-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-button {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-button:hover, .tab-button.active {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .recommendations-list {
            list-style: none;
            padding: 0;
        }

        .recommendations-list li {
            background: rgba(255, 255, 255, 0.1);
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #00ff88;
        }

        .realtime-monitoring {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }

        .realtime-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .realtime-metric {
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .metric-label {
            display: block;
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .metric-value {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #00ff88;
        }
        </style>

  <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.tab-button').click(function() {
                $('.tab-button').removeClass('active');
                $('.tab-pane').removeClass('active');
                
                $(this).addClass('active');
                $('#' + $(this).data('tab') + '-tab').addClass('active');
            });

            // Real-time updates
            updateRealtimeMetrics();
            setInterval(updateRealtimeMetrics, 30000);

            function updateRealtimeMetrics() {
                $.post(ajaxurl, {
                    action: 'affcd_get_realtime_revolutionary_metrics',
                    nonce: '<?php echo wp_create_nonce("affcd_realtime_metrics"); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#active-viral-campaigns').text(response.data.viral_campaigns);
                        $('#identities-resolved-24h').text(response.data.identities_resolved);
                        $('#quantum-attributions').text(response.data.quantum_attributions);
                        $('#revenue-recovery').text('      