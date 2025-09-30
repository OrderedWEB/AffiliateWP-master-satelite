<?php
if (!defined('ABSPATH')) { exit; }

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
     * Initialize WordPress hooks
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
     * Initialize viral triggers and thresholds
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

        $viral_token = sanitize_text_field($_POST['viral_token'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $action_type = sanitize_text_field($_POST['action_type'] ?? ''); // 'accept' or 'dismiss'

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
        
        $viral_coefficient = get_option('affcd_advanced_insights', [])['viral_coefficient'] ?? 0;
        
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
        
        $resolution_rate = get_option('affcd_advanced_insights', [])['identity_resolution_rate']['resolution_rate'] ?? 0;
        
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
        
        $confidence = get_option('affcd_advanced_insights', [])['attribution_accuracy']['confidence'] ?? 0;
        
        if ($confidence < 0.6) {
            $recommendations[] = '<li>📈 <strong>Collect more touchpoints:</strong> Track micro-conversions and engagement signals to improve attribution accuracy</li>';
            $recommendations[] = '<li>🎯 <strong>Implement cross-device tracking:</strong> Use fingerprinting or deterministic matching to connect user journeys across devices</li>';
        }
        
        $conversion_window = get_option('affcd_attribution_settings', [])['conversion_window'] ?? 30;
        
        if ($conversion_window < 7) {
            $recommendations[] = '<li>⏰ <strong>Extend conversion window:</strong> Consider increasing the attribution window to capture delayed conversions</li>';
        } elseif ($conversion_window > 90) {
            $recommendations[] = '<li>⚡ <strong>Reduce conversion window:</strong> A shorter window may provide more actionable insights for recent campaigns</li>';
        }
        
        $model_performance = $this->compare_attribution_models();
        $current_model = get_option('affcd_attribution_settings', [])['model'] ?? 'last_click';
        
        if ($model_performance[$current_model]['accuracy'] < 0.7) {
            $best_model = array_keys($model_performance, max($model_performance))[0];
            $recommendations[] = '<li>🔄 <strong>Switch attribution model:</strong> Consider using ' . esc_html($best_model) . ' model for better accuracy</li>';
        }
        
        $touchpoint_distribution = $this->analyse_touchpoint_distribution();
        
        if ($touchpoint_distribution['avg_touchpoints'] > 8) {
            $recommendations[] = '<li>🎨 <strong>Optimise customer journey:</strong> High touchpoint count suggests potential friction in the conversion path</li>';
        }
        
        $channel_performance = $this->analyse_channel_performance();
        
        foreach ($channel_performance as $channel => $metrics) {
            if ($metrics['conversion_rate'] < 0.02) {
                $recommendations[] = '<li>📊 <strong>Review ' . esc_html($channel) . ' performance:</strong> Low conversion rate may indicate targeting or messaging issues</li>';
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = '<li>✅ <strong>Attribution optimised:</strong> Current configuration is performing well</li>';
        }
        
        return $recommendations;
    }
    
    /**
     * Compare performance of different attribution models
     *
     * @return array Model performance metrics
     */
    private function compare_attribution_models() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_attribution_insights';
        
        $models = ['last_click', 'first_click', 'linear', 'time_decay', 'position_based', 'data_driven'];
        $performance = [];
        
        foreach ($models as $model) {
            $results = $wpdb->get_row($wpdb->prepare(
                "SELECT AVG(touchpoint_count) as avg_touchpoints,
                        AVG(avg_conversion_value) as avg_value,
                        COUNT(*) as total_conversions
                 FROM {$table_name}
                 WHERE attribution_model = %s
                 AND last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $model
            ), ARRAY_A);
            
            $performance[$model] = [
                'accuracy' => $this->calculate_model_accuracy($model),
                'avg_touchpoints' => floatval($results['avg_touchpoints'] ?? 0),
                'avg_value' => floatval($results['avg_value'] ?? 0),
                'conversions' => intval($results['total_conversions'] ?? 0)
            ];
        }
        
        return $performance;
    }
    
    /**
     * Calculate attribution model accuracy
     *
     * @param string $model Attribution model name
     * @return float Accuracy score between 0 and 1
     */
    private function calculate_model_accuracy($model) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_attribution_insights';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE attribution_model = %s
             AND last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $model
        ));
        
        if ($total == 0) {
            return 0.5;
        }
        
        $successful = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE attribution_model = %s
             AND total_conversions > 0
             AND last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $model
        ));
        
        return floatval($successful) / floatval($total);
    }
    
    /**
     * Analyse touchpoint distribution patterns
     *
     * @return array Distribution metrics
     */
    private function analyse_touchpoint_distribution() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_attribution_insights';
        
        $results = $wpdb->get_row(
            "SELECT AVG(avg_touchpoints) as avg_touchpoints,
                    MIN(avg_touchpoints) as min_touchpoints,
                    MAX(avg_touchpoints) as max_touchpoints,
                    STDDEV(avg_touchpoints) as stddev_touchpoints
             FROM {$table_name}
             WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            ARRAY_A
        );
        
        return [
            'avg_touchpoints' => floatval($results['avg_touchpoints'] ?? 0),
            'min_touchpoints' => floatval($results['min_touchpoints'] ?? 0),
            'max_touchpoints' => floatval($results['max_touchpoints'] ?? 0),
            'stddev_touchpoints' => floatval($results['stddev_touchpoints'] ?? 0)
        ];
    }
    
    /**
     * Analyse channel performance metrics
     *
     * @return array Channel performance data
     */
    private function analyse_channel_performance() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'affiliate_segment_performance';
        
        $results = $wpdb->get_results(
            "SELECT segment_value as channel,
                    SUM(orders_count) as total_orders,
                    SUM(revenue) as total_revenue,
                    AVG(conversion_rate) as avg_conversion_rate
             FROM {$table_name}
             WHERE segment_type = 'channel'
             AND last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY segment_value",
            ARRAY_A
        );
        
        $channel_data = [];
        
        foreach ($results as $row) {
            $channel_data[$row['channel']] = [
                'orders' => intval($row['total_orders']),
                'revenue' => floatval($row['total_revenue']),
                'conversion_rate' => floatval($row['avg_conversion_rate']) / 100
            ];
        }
        
        return $channel_data;
    }

    /**
     * Heuristic data-driven attribution weights
     */
    private function data_driven_weights($touchpoints, $advanced_state) {
          
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
     * Advanced superposition attribution weights
     */
    private function advanced_superposition_weights($touchpoints, $advanced_state) {
        if (empty($advanced_state['affiliate_probabilities'])) {
            return $this->linear_weights($touchpoints, $advanced_state);
        }

        $weights = [];
        $total_advanced_weight = 0;

        foreach ($touchpoints as $index => $touchpoint) {
            $affiliate_id = $touchpoint['affiliate_id'] ?? null;
            
            if (!$affiliate_id) {
                $weights[] = 0;
                continue;
            }

            // Get advanced probability for this affiliate
            $advanced_prob = $advanced_state['affiliate_probabilities'][$affiliate_id] ?? 0;
            
            // Apply advanced uncertainty principle
            $uncertainty = $advanced_state['attribution_entropy'] ?? 1.0;
            $advanced_weight = $advanced_prob * (1 + $uncertainty * 0.1);
            
            // Apply touchpoint quality modifier
            $quality = $touchpoint['interaction_quality'] ?? 0.5;
            $advanced_weight *= (0.5 + $quality * 0.5);

            $weights[] = $advanced_weight;
            $total_advanced_weight += $advanced_weight;
        }

        // Advanced normalization (maintains superposition)
        if ($total_advanced_weight > 0) {
            foreach ($weights as &$weight) {
                $weight /= $total_advanced_weight;
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
                'advanced_entropy' => $this->get_advanced_state($session_id)['attribution_entropy'] ?? 1.0,
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
            'advanced_enhanced' => true
        ];

        $this->parent->api_client->sync_attribution_data($sync_data);
    }

    /**
     * Inject attribution tracking script
     */
    public function inject_attribution_tracking() {
        ?>
        <script>
        // Advanced Attribution Tracking
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

/**
 * Predict conversion probability based on form submission data
 * Uses statistical analysis and behavioural heuristics
 *
 * @param array $form_data Form submission data
 * @return float Probability between 0 and 1
 */
private function predict_conversion_probability($form_data) {
    $probability = 0.15; // Conservative base probability
    $confidence_factors = [];
    
    // Analyse form type and intent signals (weight: 0.35)
    $intent_score = $this->calculate_intent_score($form_data);
    $probability += $intent_score * 0.35;
    $confidence_factors['intent'] = $intent_score;
    
    // Analyse email domain quality (weight: 0.20)
    $email_score = $this->calculate_email_domain_score($form_data);
    $probability += $email_score * 0.20;
    $confidence_factors['email_quality'] = $email_score;
    
    // Analyse data completeness and quality (weight: 0.15)
    $completeness_score = $this->calculate_data_completeness($form_data);
    $probability += $completeness_score * 0.15;
    $confidence_factors['data_quality'] = $completeness_score;
    
    // Analyse time-based patterns (weight: 0.10)
    $timing_score = $this->calculate_timing_score();
    $probability += $timing_score * 0.10;
    $confidence_factors['timing'] = $timing_score;
    
    // Analyse historical patterns for similar submissions (weight: 0.15)
    $historical_score = $this->calculate_historical_conversion_rate($form_data);
    $probability += $historical_score * 0.15;
    $confidence_factors['historical'] = $historical_score;
    
    // Analyse engagement signals (weight: 0.05)
    $engagement_score = $this->calculate_engagement_score($form_data);
    $probability += $engagement_score * 0.05;
    $confidence_factors['engagement'] = $engagement_score;
    
    // Apply decay factor for time since last interaction
    $decay_factor = $this->calculate_time_decay_factor();
    $probability *= $decay_factor;
    
    // Store prediction metadata for analysis
    $this->store_prediction_metadata($form_data, $probability, $confidence_factors);
    
    // Ensure probability stays within valid range
    return max(0.01, min($probability, 0.99));
}

/**
 * Calculate intent score based on form content and submission context
 *
 * @param array $form_data Form submission data
 * @return float Score between 0 and 1
 */
private function calculate_intent_score($form_data) {
    $score = 0.0;
    
    // High-intent form types and keywords
    $high_intent_indicators = [
        'demo' => 0.85,
        'quote' => 0.90,
        'consultation' => 0.80,
        'trial' => 0.75,
        'pricing' => 0.70,
        'purchase' => 0.95,
        'buy' => 0.92,
        'schedule' => 0.78,
        'book' => 0.82,
        'contact sales' => 0.88
    ];
    
    $medium_intent_indicators = [
        'newsletter' => 0.25,
        'download' => 0.45,
        'whitepaper' => 0.50,
        'webinar' => 0.55,
        'case study' => 0.52,
        'guide' => 0.48
    ];
    
    $low_intent_indicators = [
        'subscribe' => 0.15,
        'update' => 0.10,
        'feedback' => 0.12,
        'survey' => 0.08
    ];
    
    $form_content = strtolower(json_encode($form_data));
    
    // Check high-intent indicators
    foreach ($high_intent_indicators as $indicator => $weight) {
        if (stripos($form_content, $indicator) !== false) {
            $score = max($score, $weight);
        }
    }
    
    // Check medium-intent indicators if no high-intent found
    if ($score < 0.60) {
        foreach ($medium_intent_indicators as $indicator => $weight) {
            if (stripos($form_content, $indicator) !== false) {
                $score = max($score, $weight);
            }
        }
    }
    
    // Check low-intent indicators if no higher intent found
    if ($score < 0.20) {
        foreach ($low_intent_indicators as $indicator => $weight) {
            if (stripos($form_content, $indicator) !== false) {
                $score = max($score, $weight);
            }
        }
    }
    
    // Boost for multiple fields indicating serious interest
    $field_count = count($form_data);
    if ($field_count > 8) {
        $score *= 1.15;
    } elseif ($field_count > 5) {
        $score *= 1.08;
    }
    
    // Boost for company information provided
    if (!empty($form_data['company']) || !empty($form_data['organisation'])) {
        $score *= 1.12;
    }
    
    // Boost for phone number provided (strong buying signal)
    if (!empty($form_data['phone']) || !empty($form_data['telephone']) || !empty($form_data['mobile'])) {
        $score *= 1.20;
    }
    
    return min($score, 1.0);
}

/**
 * Calculate email domain quality score
 *
 * @param array $form_data Form submission data
 * @return float Score between 0 and 1
 */
private function calculate_email_domain_score($form_data) {
    if (empty($form_data['email'])) {
        return 0.3; // Neutral score for missing email
    }
    
    $email = strtolower(trim($form_data['email']));
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 0.1;
    }
    
    $domain = substr(strrchr($email, '@'), 1);
    
    // Free consumer email providers (lower conversion probability)
    $consumer_domains = [
        'gmail.com' => 0.35,
        'yahoo.com' => 0.30,
        'hotmail.com' => 0.32,
        'outlook.com' => 0.34,
        'aol.com' => 0.28,
        'icloud.com' => 0.33,
        'mail.com' => 0.30,
        'protonmail.com' => 0.38,
        'zoho.com' => 0.36
    ];
    
    if (isset($consumer_domains[$domain])) {
        return $consumer_domains[$domain];
    }
    
    // Disposable/temporary email domains (very low conversion)
    $disposable_patterns = ['temp', 'disposable', 'trash', 'guerrilla', '10minute', 'throwaway'];
    foreach ($disposable_patterns as $pattern) {
        if (stripos($domain, $pattern) !== false) {
            return 0.05;
        }
    }
    
    // Corporate/business email (higher conversion probability)
    $score = 0.75;
    
    // Known high-value TLDs
    $premium_tlds = ['.edu' => 1.10, '.gov' => 1.15, '.mil' => 1.15, '.ac.uk' => 1.10];
    foreach ($premium_tlds as $tld => $multiplier) {
        if (substr($domain, -strlen($tld)) === $tld) {
            $score *= $multiplier;
            break;
        }
    }
    
    // Check for role-based email (lower personal engagement)
    $role_based = ['info@', 'admin@', 'support@', 'sales@', 'noreply@', 'contact@'];
    foreach ($role_based as $role) {
        if (stripos($email, $role) === 0) {
            $score *= 0.70;
            break;
        }
    }
    
    // Check domain age and reputation (if available via API)
    $domain_reputation = $this->check_domain_reputation($domain);
    $score *= $domain_reputation;
    
    return min($score, 1.0);
}

/**
 * Calculate data completeness and quality score
 *
 * @param array $form_data Form submission data
 * @return float Score between 0 and 1
 */
private function calculate_data_completeness($form_data) {
    $total_fields = count($form_data);
    
    if ($total_fields === 0) {
        return 0.0;
    }
    
    $filled_fields = 0;
    $quality_score = 0;
    
    foreach ($form_data as $key => $value) {
        $value = trim($value);
        
        if (!empty($value) && strlen($value) > 0) {
            $filled_fields++;
            
            // Award quality points for substantive responses
            if (strlen($value) > 10) {
                $quality_score += 0.15;
            } elseif (strlen($value) > 3) {
                $quality_score += 0.08;
            } else {
                $quality_score += 0.03;
            }
            
            // Check for meaningful content vs spam patterns
            if (!$this->is_spam_pattern($value)) {
                $quality_score += 0.05;
            }
        }
    }
    
    $completeness_ratio = $filled_fields / $total_fields;
    $quality_ratio = min($quality_score / $total_fields, 1.0);
    
    // Combined score weighted 60% completeness, 40% quality
    return ($completeness_ratio * 0.60) + ($quality_ratio * 0.40);
}

/**
 * Calculate timing score based on submission time patterns
 *
 * @return float Score between 0 and 1
 */
private function calculate_timing_score() {
    $hour = intval(current_time('H'));
    $day = intval(current_time('N')); // 1 (Monday) through 7 (Sunday)
    
    $score = 0.5; // Base score
    
    // Business hours boost (09:00 - 17:00)
    if ($hour >= 9 && $hour < 17) {
        $score += 0.25;
    } elseif ($hour >= 7 && $hour < 21) {
        $score += 0.15;
    } else {
        $score -= 0.10; // Late night submissions often lower quality
    }
    
    // Weekday vs weekend
    if ($day >= 1 && $day <= 5) { // Monday to Friday
        $score += 0.15;
    } else {
        $score += 0.05; // Weekend submissions slightly lower conversion
    }
    
    // Peak engagement times (10:00-11:00, 14:00-15:00)
    if (($hour >= 10 && $hour < 11) || ($hour >= 14 && $hour < 15)) {
        $score += 0.10;
    }
    
    return min($score, 1.0);
}

/**
 * Calculate historical conversion rate for similar submissions
 *
 * @param array $form_data Current form submission data
 * @return float Score between 0 and 1
 */
private function calculate_historical_conversion_rate($form_data) {
    global $wpdb;
    
    // Extract form identifier
    $form_id = $form_data['form_id'] ?? 'unknown';
    
    $table_name = $wpdb->prefix . 'affiliate_form_conversions';
    
    // Get historical conversion rate for this form type
    $results = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_submissions,
            SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions
         FROM {$table_name}
         WHERE form_id = %s
         AND submission_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        $form_id
    ), ARRAY_A);
    
    $total = intval($results['total_submissions'] ?? 0);
    $conversions = intval($results['conversions'] ?? 0);
    
    if ($total < 10) {
        // Insufficient data, return neutral score
        return 0.50;
    }
    
    $conversion_rate = $conversions / $total;
    
    // Apply confidence interval based on sample size
    $confidence_adjustment = min($total / 100, 1.0);
    
    return $conversion_rate * $confidence_adjustment;
}

/**
 * Calculate engagement score based on user behaviour signals
 *
 * @param array $form_data Form submission data
 * @return float Score between 0 and 1
 */
private function calculate_engagement_score($form_data) {
    $score = 0.5;
    
    // Check for referrer information
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    if (!empty($referrer)) {
        // Internal referral (browsed multiple pages)
        if (stripos($referrer, $_SERVER['HTTP_HOST']) !== false) {
            $score += 0.25;
        } else {
            $score += 0.10;
        }
    }
    
    // Check for UTM parameters indicating campaign engagement
    if (!empty($form_data['utm_source']) || !empty($_GET['utm_source'])) {
        $score += 0.15;
    }
    
    // Check session depth
    $session_pages = intval($_COOKIE['session_pages'] ?? 1);
    if ($session_pages > 5) {
        $score += 0.20;
    } elseif ($session_pages > 2) {
        $score += 0.10;
    }
    
    // Check time on site
    $session_duration = intval($_COOKIE['session_duration'] ?? 0);
    if ($session_duration > 300) { // 5+ minutes
        $score += 0.15;
    } elseif ($session_duration > 120) { // 2+ minutes
        $score += 0.08;
    }
    
    return min($score, 1.0);
}

/**
 * Calculate time decay factor for prediction accuracy
 *
 * @return float Decay factor between 0.8 and 1.0
 */
private function calculate_time_decay_factor() {
    // Get time since last affiliate interaction
    $last_interaction = get_transient('affcd_last_interaction_time');
    
    if (!$last_interaction) {
        return 1.0;
    }
    
    $minutes_elapsed = (time() - $last_interaction) / 60;
    
    // Apply exponential decay
    // 100% within 30 minutes, 95% at 2 hours, 90% at 6 hours, 80% at 24+ hours
    if ($minutes_elapsed <= 30) {
        return 1.0;
    } elseif ($minutes_elapsed <= 120) {
        return 0.95;
    } elseif ($minutes_elapsed <= 360) {
        return 0.90;
    } elseif ($minutes_elapsed <= 1440) {
        return 0.85;
    } else {
        return 0.80;
    }
}

/**
 * Check domain reputation score
 *
 * @param string $domain Email domain
 * @return float Reputation multiplier between 0.5 and 1.2
 */
private function check_domain_reputation($domain) {
    // Check cached reputation
    $cache_key = 'affcd_domain_rep_' . md5($domain);
    $cached_score = get_transient($cache_key);
    
    if ($cached_score !== false) {
        return floatval($cached_score);
    }
    
    $score = 1.0;
    
    // Check against known spam domains list
    $spam_domains = get_option('affcd_spam_domains', []);
    if (in_array($domain, $spam_domains)) {
        $score = 0.5;
    }
    
    // Check against verified business domains
    $verified_domains = get_option('affcd_verified_domains', []);
    if (in_array($domain, $verified_domains)) {
        $score = 1.2;
    }
    
    // Cache for 7 days
    set_transient($cache_key, $score, 7 * DAY_IN_SECONDS);
    
    return $score;
}

/**
 * Check if value matches spam patterns
 *
 * @param string $value Field value to check
 * @return bool True if spam pattern detected
 */
private function is_spam_pattern($value) {
    $spam_patterns = [
        '/\b(viagra|cialis|casino|lottery|winner)\b/i',
        '/(.)\1{4,}/', // Repeated characters
        '/\b\d{4,}\b/', // Long number sequences
        '/<script|javascript:/i',
        '/\b(http:\/\/|https:\/\/|www\.)/i' // URLs in unexpected fields
    ];
    
    foreach ($spam_patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Store prediction metadata for analysis and improvement
 *
 * @param array $form_data Form submission data
 * @param float $probability Predicted probability
 * @param array $confidence_factors Individual factor scores
 * @return void
 */
private function store_prediction_metadata($form_data, $probability, $confidence_factors) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_conversion_predictions';
    
    $wpdb->insert(
        $table_name,
        [
            'form_id' => $form_data['form_id'] ?? 'unknown',
            'predicted_probability' => $probability,
            'intent_score' => $confidence_factors['intent'] ?? 0,
            'email_score' => $confidence_factors['email_quality'] ?? 0,
            'completeness_score' => $confidence_factors['data_quality'] ?? 0,
            'timing_score' => $confidence_factors['timing'] ?? 0,
            'historical_score' => $confidence_factors['historical'] ?? 0,
            'engagement_score' => $confidence_factors['engagement'] ?? 0,
            'prediction_date' => current_time('mysql'),
            'converted' => 0 // Will be updated when actual conversion is recorded
        ],
        ['%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%d']
    );
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
 * Optimise viral loop based on performance data
 * Uses statistical analysis and adaptive algorithms without
 *
 * @param object $opportunity The viral opportunity object
 * @param int $affiliate_id The affiliate ID
 * @param string $action The action taken (accepted, declined, converted, shared)
 * @return void
 */
public function optimize_viral_loop($opportunity, $affiliate_id, $action) {
    $trigger_type = $opportunity->trigger_type ?? 'unknown';
    
    // Record the action for historical analysis
    $this->record_viral_action($opportunity, $affiliate_id, $action);
    
    // Get current trigger performance metrics
    $trigger_metrics = $this->get_trigger_performance_metrics($trigger_type);
    
    // Apply action-specific optimisations
    switch ($action) {
        case 'accepted':
            $this->optimise_for_acceptance($trigger_type, $trigger_metrics, $opportunity);
            break;
            
        case 'declined':
            $this->optimise_for_decline($trigger_type, $trigger_metrics, $opportunity);
            break;
            
        case 'converted':
            $this->optimise_for_conversion($trigger_type, $trigger_metrics, $opportunity);
            break;
            
        case 'shared':
            $this->optimise_for_sharing($trigger_type, $trigger_metrics, $opportunity);
            break;
            
        case 'ignored':
            $this->optimise_for_ignorance($trigger_type, $trigger_metrics, $opportunity);
            break;
    }
    
    // Perform cross-trigger optimisation
    $this->optimise_trigger_mix();
    
    // Adjust timing and frequency based on performance
    $this->optimise_trigger_timing($trigger_type, $action);
    
    // Optimise audience targeting
    $this->optimise_audience_targeting($trigger_type, $affiliate_id, $action);
    
    // Clean up underperforming variations
    $this->prune_poor_performers();
    
    // Update global viral loop settings
    update_option('affcd_viral_triggers', $this->viral_triggers);
    update_option('affcd_viral_optimisation_last_run', current_time('timestamp'));
}

/**
 * Record viral action for historical analysis
 *
 * @param object $opportunity Viral opportunity object
 * @param int $affiliate_id Affiliate ID
 * @param string $action Action taken
 * @return void
 */
private function record_viral_action($opportunity, $affiliate_id, $action) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_viral_actions';
    
    $wpdb->insert(
        $table_name,
        [
            'opportunity_id' => $opportunity->id ?? 0,
            'affiliate_id' => $affiliate_id,
            'trigger_type' => $opportunity->trigger_type ?? 'unknown',
            'action' => $action,
            'timestamp' => current_time('mysql'),
            'context_data' => json_encode([
                'time_of_day' => intval(current_time('H')),
                'day_of_week' => intval(current_time('N')),
                'affiliate_tier' => $this->get_affiliate_tier($affiliate_id),
                'previous_actions' => $this->get_recent_actions($affiliate_id, 5)
            ])
        ],
        ['%d', '%d', '%s', '%s', '%s', '%s']
    );
}

/**
 * Get comprehensive performance metrics for a trigger type
 *
 * @param string $trigger_type Trigger type identifier
 * @return array Performance metrics
 */
private function get_trigger_performance_metrics($trigger_type) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_viral_actions';
    
    // Get metrics from last 90 days
    $metrics = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_shown,
            SUM(CASE WHEN action = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN action = 'declined' THEN 1 ELSE 0 END) as declined,
            SUM(CASE WHEN action = 'converted' THEN 1 ELSE 0 END) as converted,
            SUM(CASE WHEN action = 'shared' THEN 1 ELSE 0 END) as shared,
            SUM(CASE WHEN action = 'ignored' THEN 1 ELSE 0 END) as ignored,
            AVG(CASE WHEN action = 'accepted' THEN 1 ELSE 0 END) as acceptance_rate,
            AVG(CASE WHEN action = 'converted' THEN 1 ELSE 0 END) as conversion_rate
         FROM {$table_name}
         WHERE trigger_type = %s
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        $trigger_type
    ), ARRAY_A);
    
    // Calculate additional derived metrics
    $total = intval($metrics['total_shown'] ?? 0);
    
    if ($total > 0) {
        $metrics['engagement_rate'] = (intval($metrics['accepted']) + intval($metrics['shared'])) / $total;
        $metrics['negative_rate'] = (intval($metrics['declined']) + intval($metrics['ignored'])) / $total;
        $metrics['viral_coefficient'] = floatval($metrics['shared']) / max(intval($metrics['accepted']), 1);
    } else {
        $metrics['engagement_rate'] = 0;
        $metrics['negative_rate'] = 0;
        $metrics['viral_coefficient'] = 0;
    }
    
    return $metrics;
}

/**
 * Optimise trigger for acceptance actions
 *
 * @param string $trigger_type Trigger type
 * @param array $metrics Current performance metrics
 * @param object $opportunity Opportunity object
 * @return void
 */
private function optimise_for_acceptance($trigger_type, $metrics, $opportunity) {
    if (!isset($this->viral_triggers[$trigger_type])) {
        return;
    }
    
    $current_rate = $this->viral_triggers[$trigger_type]['success_rate'] ?? 0.1;
    $total_shown = intval($metrics['total_shown'] ?? 0);
    $accepted = intval($metrics['accepted'] ?? 0);
    
    // Use Bayesian updating for success rate
    if ($total_shown >= 20) {
        // Calculate empirical rate with confidence adjustment
        $empirical_rate = $accepted / $total_shown;
        
        // Apply confidence based on sample size
        $confidence = min($total_shown / 100, 1.0);
        
        // Weighted average: more weight to empirical as confidence grows
        $new_rate = ($current_rate * (1 - $confidence)) + ($empirical_rate * $confidence);
        
        // Gradual increase with acceptance (5% boost, capped at 0.8)
        $new_rate = min($new_rate * 1.05, 0.8);
        
        $this->viral_triggers[$trigger_type]['success_rate'] = $new_rate;
    } else {
        // Small sample: conservative update
        $this->viral_triggers[$trigger_type]['success_rate'] = min($current_rate * 1.03, 0.5);
    }
    
    // Increase trigger frequency if performing well
    $acceptance_rate = floatval($metrics['acceptance_rate'] ?? 0);
    if ($acceptance_rate > 0.3) {
        $current_frequency = $this->viral_triggers[$trigger_type]['frequency'] ?? 1;
        $this->viral_triggers[$trigger_type]['frequency'] = min($current_frequency * 1.1, 3);
    }
    
    // Adjust reward amounts based on conversion performance
    if (floatval($metrics['conversion_rate'] ?? 0) > 0.15) {
        $current_reward = $this->viral_triggers[$trigger_type]['reward_multiplier'] ?? 1.0;
        $this->viral_triggers[$trigger_type]['reward_multiplier'] = min($current_reward * 1.05, 2.0);
    }
}

/**
 * Optimise trigger for decline actions
 *
 * @param string $trigger_type Trigger type
 * @param array $metrics Current performance metrics
 * @param object $opportunity Opportunity object
 * @return void
 */
private function optimise_for_decline($trigger_type, $metrics, $opportunity) {
    if (!isset($this->viral_triggers[$trigger_type])) {
        return;
    }
    
    $current_rate = $this->viral_triggers[$trigger_type]['success_rate'] ?? 0.1;
    $declined = intval($metrics['declined'] ?? 0);
    $total_shown = intval($metrics['total_shown'] ?? 1);
    
    // Decrease success rate for declined opportunities
    $decline_rate = $declined / $total_shown;
    
    if ($decline_rate > 0.4) {
        // High decline rate: significant reduction
        $this->viral_triggers[$trigger_type]['success_rate'] = max($current_rate * 0.90, 0.05);
        
        // Reduce frequency
        $current_frequency = $this->viral_triggers[$trigger_type]['frequency'] ?? 1;
        $this->viral_triggers[$trigger_type]['frequency'] = max($current_frequency * 0.85, 0.5);
    } else {
        // Moderate decline rate: gentle reduction
        $this->viral_triggers[$trigger_type]['success_rate'] = max($current_rate * 0.97, 0.05);
    }
    
    // Analyse decline reasons if available
    $decline_context = $this->analyse_decline_context($trigger_type);
    
    if ($decline_context['timing_issue']) {
        // Adjust trigger timing
        $this->viral_triggers[$trigger_type]['optimal_hours'] = $decline_context['better_hours'];
    }
    
    if ($decline_context['audience_mismatch']) {
        // Tighten audience targeting
        $this->viral_triggers[$trigger_type]['min_tier'] = max(
            $this->viral_triggers[$trigger_type]['min_tier'] ?? 1,
            2
        );
    }
}

/**
 * Optimise trigger for conversion actions
 *
 * @param string $trigger_type Trigger type
 * @param array $metrics Current performance metrics
 * @param object $opportunity Opportunity object
 * @return void
 */
private function optimise_for_conversion($trigger_type, $metrics, $opportunity) {
    if (!isset($this->viral_triggers[$trigger_type])) {
        return;
    }
    
    // Conversion is the ultimate success metric
    $conversion_rate = floatval($metrics['conversion_rate'] ?? 0);
    
    if ($conversion_rate > 0.1) {
        // Excellent conversion rate: boost significantly
        $current_rate = $this->viral_triggers[$trigger_type]['success_rate'] ?? 0.1;
        $this->viral_triggers[$trigger_type]['success_rate'] = min($current_rate * 1.15, 0.85);
        
        // Increase frequency and priority
        $current_frequency = $this->viral_triggers[$trigger_type]['frequency'] ?? 1;
        $this->viral_triggers[$trigger_type]['frequency'] = min($current_frequency * 1.2, 3);
        $this->viral_triggers[$trigger_type]['priority'] = 'high';
        
        // Increase reward to reinforce behaviour
        $current_reward = $this->viral_triggers[$trigger_type]['reward_multiplier'] ?? 1.0;
        $this->viral_triggers[$trigger_type]['reward_multiplier'] = min($current_reward * 1.10, 2.5);
    }
    
    // Analyse conversion patterns
    $conversion_patterns = $this->analyse_conversion_patterns($trigger_type);
    
    // Optimise for successful conversion contexts
    if (!empty($conversion_patterns['optimal_day'])) {
        $this->viral_triggers[$trigger_type]['optimal_days'] = $conversion_patterns['optimal_day'];
    }
    
    if (!empty($conversion_patterns['optimal_affiliate_tier'])) {
        $this->viral_triggers[$trigger_type]['target_tiers'] = $conversion_patterns['optimal_affiliate_tier'];
    }
}

/**
 * Optimise trigger for sharing actions
 *
 * @param string $trigger_type Trigger type
 * @param array $metrics Current performance metrics
 * @param object $opportunity Opportunity object
 * @return void
 */
private function optimise_for_sharing($trigger_type, $metrics, $opportunity) {
    if (!isset($this->viral_triggers[$trigger_type])) {
        return;
    }
    
    // Sharing indicates high viral potential
    $viral_coefficient = floatval($metrics['viral_coefficient'] ?? 0);
    
    if ($viral_coefficient > 0.5) {
        // High viral coefficient: this trigger spreads well
        $current_rate = $this->viral_triggers[$trigger_type]['success_rate'] ?? 0.1;
        $this->viral_triggers[$trigger_type]['success_rate'] = min($current_rate * 1.12, 0.8);
        
        // Enable social sharing features
        $this->viral_triggers[$trigger_type]['enable_social_share'] = true;
        $this->viral_triggers[$trigger_type]['share_bonus_multiplier'] = 1.5;
    }
    
    // Analyse what makes this trigger shareable
    $shareability_factors = $this->analyse_shareability($trigger_type);
    
    // Apply learnings to similar triggers
    $this->apply_shareability_insights($shareability_factors);
}

/**
 * Optimise trigger for ignored opportunities
 *
 * @param string $trigger_type Trigger type
 * @param array $metrics Current performance metrics
 * @param object $opportunity Opportunity object
 * @return void
 */
private function optimise_for_ignorance($trigger_type, $metrics, $opportunity) {
    if (!isset($this->viral_triggers[$trigger_type])) {
        return;
    }
    
    $ignored = intval($metrics['ignored'] ?? 0);
    $total_shown = intval($metrics['total_shown'] ?? 1);
    $ignore_rate = $ignored / $total_shown;
    
    if ($ignore_rate > 0.5) {
        // High ignore rate: trigger not engaging
        $current_rate = $this->viral_triggers[$trigger_type]['success_rate'] ?? 0.1;
        $this->viral_triggers[$trigger_type]['success_rate'] = max($current_rate * 0.85, 0.03);
        
        // Dramatically reduce frequency
        $current_frequency = $this->viral_triggers[$trigger_type]['frequency'] ?? 1;
        $this->viral_triggers[$trigger_type]['frequency'] = max($current_frequency * 0.7, 0.3);
        
        // Flag for review
        $this->viral_triggers[$trigger_type]['requires_review'] = true;
        $this->viral_triggers[$trigger_type]['ignore_rate'] = $ignore_rate;
    }
}

/**
 * Optimise the mix of triggers shown to affiliates
 *
 * @return void
 */
private function optimise_trigger_mix() {
    $all_metrics = [];
    
    // Gather performance data for all triggers
    foreach ($this->viral_triggers as $trigger_type => $config) {
        $metrics = $this->get_trigger_performance_metrics($trigger_type);
        $all_metrics[$trigger_type] = $metrics;
    }
    
    // Calculate total engagement across all triggers
    $total_engagement = 0;
    foreach ($all_metrics as $metrics) {
        $total_engagement += floatval($metrics['engagement_rate'] ?? 0);
    }
    
    // Adjust trigger weights based on relative performance
    foreach ($this->viral_triggers as $trigger_type => $config) {
        $engagement = floatval($all_metrics[$trigger_type]['engagement_rate'] ?? 0);
        
        if ($total_engagement > 0) {
            $relative_performance = $engagement / $total_engagement;
            
            // Adjust display probability based on relative performance
            $this->viral_triggers[$trigger_type]['display_weight'] = max($relative_performance * 10, 0.5);
        }
    }
}

/**
 * Optimise trigger timing based on performance patterns
 *
 * @param string $trigger_type Trigger type
 * @param string $action Action taken
 * @return void
 */
private function optimise_trigger_timing($trigger_type, $action) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_viral_actions';
    
    // Analyse hour-of-day performance
    $hourly_performance = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            HOUR(timestamp) as hour,
            COUNT(*) as total,
            SUM(CASE WHEN action IN ('accepted', 'converted', 'shared') THEN 1 ELSE 0 END) as positive
         FROM {$table_name}
         WHERE trigger_type = %s
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY HOUR(timestamp)",
        $trigger_type
    ), ARRAY_A);
    
    $best_hours = [];
    foreach ($hourly_performance as $hour_data) {
        $hour = intval($hour_data['hour']);
        $success_rate = floatval($hour_data['positive']) / max(intval($hour_data['total']), 1);
        
        if ($success_rate > 0.2) {
            $best_hours[] = $hour;
        }
    }
    
    if (!empty($best_hours)) {
        $this->viral_triggers[$trigger_type]['optimal_hours'] = $best_hours;
    }
    
    // Analyse day-of-week performance
    $daily_performance = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            DAYOFWEEK(timestamp) as day,
            COUNT(*) as total,
            SUM(CASE WHEN action IN ('accepted', 'converted', 'shared') THEN 1 ELSE 0 END) as positive
         FROM {$table_name}
         WHERE trigger_type = %s
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
         GROUP BY DAYOFWEEK(timestamp)",
        $trigger_type
    ), ARRAY_A);
    
    $best_days = [];
    foreach ($daily_performance as $day_data) {
        $day = intval($day_data['day']);
        $success_rate = floatval($day_data['positive']) / max(intval($day_data['total']), 1);
        
        if ($success_rate > 0.2) {
            $best_days[] = $day;
        }
    }
    
    if (!empty($best_days)) {
        $this->viral_triggers[$trigger_type]['optimal_days'] = $best_days;
    }
}

/**
 * Optimise audience targeting for triggers
 *
 * @param string $trigger_type Trigger type
 * @param int $affiliate_id Affiliate ID
 * @param string $action Action taken
 * @return void
 */
private function optimise_audience_targeting($trigger_type, $affiliate_id, $action) {
    $affiliate_tier = $this->get_affiliate_tier($affiliate_id);
    
    // Track which tiers respond best
    $tier_performance = get_option('affcd_trigger_tier_performance', []);
    
    if (!isset($tier_performance[$trigger_type])) {
        $tier_performance[$trigger_type] = [];
    }
    
    if (!isset($tier_performance[$trigger_type][$affiliate_tier])) {
        $tier_performance[$trigger_type][$affiliate_tier] = [
            'shown' => 0,
            'positive' => 0
        ];
    }
    
    $tier_performance[$trigger_type][$affiliate_tier]['shown']++;
    
    if (in_array($action, ['accepted', 'converted', 'shared'])) {
        $tier_performance[$trigger_type][$affiliate_tier]['positive']++;
    }
    
    // Calculate success rate per tier
    $tier_success_rates = [];
    foreach ($tier_performance[$trigger_type] as $tier => $data) {
        if ($data['shown'] >= 10) {
            $tier_success_rates[$tier] = $data['positive'] / $data['shown'];
        }
    }
    
    // Identify best performing tiers
    if (!empty($tier_success_rates)) {
        arsort($tier_success_rates);
        $best_tiers = array_slice(array_keys($tier_success_rates), 0, 3);
        $this->viral_triggers[$trigger_type]['target_tiers'] = $best_tiers;
    }
    
    update_option('affcd_trigger_tier_performance', $tier_performance);
}

/**
 * Remove or reduce poorly performing trigger variations
 *
 * @return void
 */
private function prune_poor_performers() {
    $pruned_count = 0;
    
    foreach ($this->viral_triggers as $trigger_type => $config) {
        $metrics = $this->get_trigger_performance_metrics($trigger_type);
        $total_shown = intval($metrics['total_shown'] ?? 0);
        
        // Only prune if we have sufficient data
        if ($total_shown < 50) {
            continue;
        }
        
        $engagement_rate = floatval($metrics['engagement_rate'] ?? 0);
        $negative_rate = floatval($metrics['negative_rate'] ?? 0);
        
        // Prune if engagement is very low and negative feedback is high
        if ($engagement_rate < 0.05 && $negative_rate > 0.6) {
            $this->viral_triggers[$trigger_type]['enabled'] = false;
            $this->viral_triggers[$trigger_type]['pruned'] = true;
            $this->viral_triggers[$trigger_type]['pruned_date'] = current_time('mysql');
            $pruned_count++;
        }
    }
    
    if ($pruned_count > 0) {
        $this->log_optimisation_event("Pruned {$pruned_count} poor performing triggers");
    }
}

/**
 * Analyse decline context to understand why triggers are declined
 *
 * @param string $trigger_type Trigger type
 * @return array Context analysis results
 */
private function analyse_decline_context($trigger_type) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_viral_actions';
    
    // Analyse timing of declines
    $timing_data = $wpdb->get_results($wpdb->prepare(
        "SELECT HOUR(timestamp) as hour, COUNT(*) as count
         FROM {$table_name}
         WHERE trigger_type = %s
         AND action = 'declined'
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY HOUR(timestamp)
         ORDER BY count DESC",
        $trigger_type
    ), ARRAY_A);
    
    $worst_hours = array_slice(array_column($timing_data, 'hour'), 0, 6);
    $all_hours = range(0, 23);
    $better_hours = array_diff($all_hours, $worst_hours);
    
    // Analyse affiliate tier patterns
    $tier_data = $wpdb->get_results($wpdb->prepare(
        "SELECT JSON_EXTRACT(context_data, '$.affiliate_tier') as tier, COUNT(*) as count
         FROM {$table_name}
         WHERE trigger_type = %s
         AND action = 'declined'
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY tier
         ORDER BY count DESC",
        $trigger_type
    ), ARRAY_A);
    
    $audience_mismatch = !empty($tier_data) && intval($tier_data[0]['count']) > 10;
    
    return [
        'timing_issue' => !empty($worst_hours),
        'better_hours' => $better_hours,
        'audience_mismatch' => $audience_mismatch
    ];
}

/**
 * Analyse conversion patterns for optimisation
 *
 * @param string $trigger_type Trigger type
 * @return array Conversion pattern analysis
 */
private function analyse_conversion_patterns($trigger_type) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_viral_actions';
    
    // Find optimal day of week
    $day_analysis = $wpdb->get_row($wpdb->prepare(
        "SELECT DAYOFWEEK(timestamp) as best_day, COUNT(*) as conversions
         FROM {$table_name}
         WHERE trigger_type = %s
         AND action = 'converted'
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
         GROUP BY DAYOFWEEK(timestamp)
         ORDER BY conversions DESC
         LIMIT 1",
        $trigger_type
    ), ARRAY_A);
    
    // Find optimal affiliate tier
    $tier_analysis = $wpdb->get_row($wpdb->prepare(
        "SELECT JSON_EXTRACT(context_data, '$.affiliate_tier') as best_tier, COUNT(*) as conversions
         FROM {$table_name}
         WHERE trigger_type = %s
         AND action = 'converted'
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
         GROUP BY best_tier
         ORDER BY conversions DESC
         LIMIT 1",
        $trigger_type
    ), ARRAY_A);
    
    return [
        'optimal_day' => $day_analysis['best_day'] ?? null,
        'optimal_affiliate_tier' => $tier_analysis['best_tier'] ?? null
    ];
}

/**
 * Analyse what makes a trigger shareable
 *
 * @param string $trigger_type Trigger type
 * @return array Shareability factors
 */
private function analyse_shareability($trigger_type) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_viral_actions';
    
    $share_data = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            AVG(JSON_EXTRACT(context_data, '$.affiliate_tier')) as avg_sharer_tier,
            COUNT(DISTINCT affiliate_id) as unique_sharers,
            COUNT(*) as total_shares
         FROM {$table_name}
         WHERE trigger_type = %s
         AND action = 'shared'
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)",
        $trigger_type
    ), ARRAY_A);
    
    return [
        'avg_sharer_tier' => floatval($share_data['avg_sharer_tier'] ?? 0),
        'viral_reach' => intval($share_data['total_shares'] ?? 0),
        'trigger_type' => $trigger_type
    ];
}

/**
 * Apply shareability insights to similar triggers
 *
 * @param array $factors Shareability factors
 * @return void
 */
private function apply_shareability_insights($factors) {
    $viral_reach = $factors['viral_reach'];
    
    if ($viral_reach > 50) {
        // This trigger type is highly viral, boost similar triggers
        foreach ($this->viral_triggers as $trigger_type => $config) {
            if ($trigger_type !== $factors['trigger_type']) {
                // Apply modest boost to related triggers
                $this->viral_triggers[$trigger_type]['share_incentive'] = 1.2;
            }
        }
    }
}

/**
 * Get affiliate tier level
 *
 * @param int $affiliate_id Affiliate ID
 * @return int Tier level (1-5)
 */
private function get_affiliate_tier($affiliate_id) {
    global $wpdb;
    
    $lifetime_value = $wpdb->get_var($wpdb->prepare(
        "SELECT total_revenue FROM {$wpdb->prefix}affiliate_lifetime_value
         WHERE affiliate_id = %d",
        $affiliate_id
    ));
    
    $lifetime_value = floatval($lifetime_value ?? 0);
    
    if ($lifetime_value >= 50000) return 5;
    if ($lifetime_value >= 25000) return 4;
    if ($lifetime_value >= 10000) return 3;
    if ($lifetime_value >= 1000) return 2;
    return 1;
}

/**
 * Get recent actions for an affiliate
 *
 * @param int $affiliate_id Affiliate ID
 * @param int $limit Number of recent actions
 * @return array Recent actions
 */
private function get_recent_actions($affiliate_id, $limit = 5) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'affiliate_viral_actions';
    
    $actions = $wpdb->get_col($wpdb->prepare(
        "SELECT action FROM {$table_name}
         WHERE affiliate_id = %d
         ORDER BY timestamp DESC
         LIMIT %d",
        $affiliate_id,
        $limit
    ));
    
    return $actions ?: [];
}

/**
 * Log optimisation event for tracking and debugging
 *
 * @param string $message Event message
 * @return void
 */
private function log_optimisation_event($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log('Viral Loop Optimisation: ' . $message);
    }
    
    // Store in options for dashboard display
    $log = get_option('affcd_optimisation_log', []);
    $log[] = [
        'timestamp' => current_time('mysql'),
        'message' => $message
    ];
    
    // Keep only last 50 events
    $log = array_slice($log, -50);
    update_option('affcd_optimisation_log', $log);
}