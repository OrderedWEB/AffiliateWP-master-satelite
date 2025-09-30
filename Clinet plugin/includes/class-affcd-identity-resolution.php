<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/*


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
     * Initialize WordPress hooks
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
     * Initialize matching algorithms
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
 * Behavioural pattern matching
 * Identifies similar user behaviour patterns
 * Uses statistical analysis and heuristic pattern recognition
 *
 * @param array $identity_data Identity data to match against
 * @return array Matched patterns with confidence scores
 */
private function match_behavioral_pattern($identity_data) {
    if (empty($identity_data['session_id'])) {
        return [];
    }

    global $wpdb;
    
    $matches = [];
    
    // Extract behavioural signature from current session
    $current_signature = $this->extract_behavioral_signature($identity_data);
    
    if (empty($current_signature)) {
        return [];
    }
    
    // Find sessions with similar behavioural patterns
    $candidate_patterns = $this->find_candidate_behavioral_patterns($identity_data);
    
    foreach ($candidate_patterns as $candidate) {
        // Calculate behavioural similarity score
        $similarity_score = $this->calculate_behavioral_similarity(
            $current_signature,
            $candidate
        );
        
        // Only include matches with meaningful similarity (>= 60%)
        if ($similarity_score >= 0.60) {
            $matches[] = [
                'identity_hash' => $candidate['identity_hash'],
                'match_type' => 'behavioral_pattern',
                'confidence' => $similarity_score,
                'matching_factors' => $candidate['matching_factors'],
                'collected_at' => $candidate['collected_at'],
                'pattern_strength' => $this->calculate_pattern_strength($candidate)
            ];
        }
    }
    
    // Sort by confidence score descending
    usort($matches, function($a, $b) {
        return $b['confidence'] <=> $a['confidence'];
    });
    
    // Return top 10 matches
    return array_slice($matches, 0, 10);
}

/**
 * Extract behavioural signature from identity data
 *
 * @param array $identity_data Identity data
 * @return array Behavioural signature components
 */
private function extract_behavioral_signature($identity_data) {
    $signature = [];
    
    // Parse additional data
    $additional_data = [];
    if (!empty($identity_data['additional_data'])) {
        $additional_data = is_array($identity_data['additional_data']) 
            ? $identity_data['additional_data'] 
            : json_decode($identity_data['additional_data'], true);
    }
    
    // Temporal patterns
    $signature['hour_of_day'] = intval(current_time('H'));
    $signature['day_of_week'] = intval(current_time('N'));
    $signature['time_category'] = $this->categorise_time($signature['hour_of_day']);
    
    // Navigation patterns
    $signature['pages_visited'] = intval($additional_data['pages_visited'] ?? 1);
    $signature['session_duration'] = intval($additional_data['session_duration'] ?? 0);
    $signature['avg_time_per_page'] = $signature['pages_visited'] > 0 
        ? $signature['session_duration'] / $signature['pages_visited'] 
        : 0;
    
    // Interaction patterns
    $signature['click_count'] = intval($additional_data['click_count'] ?? 0);
    $signature['scroll_depth'] = floatval($additional_data['scroll_depth'] ?? 0);
    $signature['form_interactions'] = intval($additional_data['form_interactions'] ?? 0);
    
    // Entry and referral patterns
    $signature['entry_page'] = sanitize_text_field($additional_data['entry_page'] ?? '');
    $signature['referrer_type'] = $this->categorise_referrer($additional_data['referrer'] ?? '');
    $signature['utm_source'] = sanitize_text_field($additional_data['utm_source'] ?? '');
    
    // Device and browser patterns
    $signature['device_type'] = sanitize_text_field($identity_data['device_type'] ?? 'unknown');
    $signature['browser_family'] = $this->extract_browser_family($identity_data['user_agent'] ?? '');
    $signature['screen_resolution'] = sanitize_text_field($additional_data['screen_resolution'] ?? '');
    
    // Engagement level categorisation
    $signature['engagement_level'] = $this->calculate_engagement_level($signature);
    
    // Conversion intent signals
    $signature['intent_signals'] = $this->extract_intent_signals($additional_data);
    
    return $signature;
}

/**
 * Find candidate behavioural patterns from database
 *
 * @param array $identity_data Current identity data
 * @return array Candidate patterns
 */
private function find_candidate_behavioral_patterns($identity_data) {
    global $wpdb;
    
    $session_id = $identity_data['session_id'];
    $current_ip = $identity_data['ip_address'] ?? '';
    
    // Query for similar patterns using multiple criteria
    $patterns = $wpdb->get_results($wpdb->prepare("
        SELECT 
            i.identity_hash,
            i.additional_data,
            i.collected_at,
            i.device_type,
            i.user_agent,
            i.ip_address,
            COUNT(DISTINCT DATE(i.collected_at)) as visit_days,
            AVG(JSON_EXTRACT(i.additional_data, '$.session_duration')) as avg_session_duration,
            AVG(JSON_EXTRACT(i.additional_data, '$.pages_visited')) as avg_pages_visited
        FROM {$wpdb->prefix}affcd_identity_data i
        WHERE i.collected_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND i.session_id != %s
        AND (
            i.ip_address = %s
            OR i.device_type = %s
            OR HOUR(i.collected_at) BETWEEN %d AND %d
        )
        GROUP BY i.identity_hash
        HAVING visit_days >= 1
        ORDER BY i.collected_at DESC
        LIMIT 50
    ", 
        $session_id,
        $current_ip,
        $identity_data['device_type'] ?? '',
        max(0, intval(current_time('H')) - 2),
        min(23, intval(current_time('H')) + 2)
    ), ARRAY_A);
    
    // Enrich candidate patterns with behavioural signatures
    foreach ($patterns as &$pattern) {
        $pattern_data = [
            'session_id' => $pattern['identity_hash'],
            'additional_data' => $pattern['additional_data'],
            'device_type' => $pattern['device_type'],
            'user_agent' => $pattern['user_agent'],
            'ip_address' => $pattern['ip_address']
        ];
        
        $pattern['signature'] = $this->extract_behavioral_signature($pattern_data);
        $pattern['matching_factors'] = [];
    }
    
    return $patterns;
}

/**
 * Calculate behavioural similarity between two signatures
 *
 * @param array $signature1 First behavioural signature
 * @param array $candidate Candidate pattern with signature
 * @return float Similarity score between 0 and 1
 */
private function calculate_behavioral_similarity($signature1, $candidate) {
    $signature2 = $candidate['signature'] ?? [];
    
    if (empty($signature2)) {
        return 0.0;
    }
    
    $similarity_scores = [];
    $matching_factors = [];
    
    // Temporal similarity (weight: 0.15)
    $temporal_similarity = 0.0;
    if (isset($signature1['time_category']) && isset($signature2['time_category'])) {
        if ($signature1['time_category'] === $signature2['time_category']) {
            $temporal_similarity += 0.6;
            $matching_factors[] = 'time_of_day';
        }
        
        $hour_diff = abs($signature1['hour_of_day'] - $signature2['hour_of_day']);
        if ($hour_diff <= 2) {
            $temporal_similarity += 0.4;
            $matching_factors[] = 'similar_hours';
        }
    }
    $similarity_scores['temporal'] = $temporal_similarity * 0.15;
    
    // Navigation pattern similarity (weight: 0.25)
    $navigation_similarity = $this->compare_navigation_patterns($signature1, $signature2);
    if ($navigation_similarity > 0.6) {
        $matching_factors[] = 'navigation_pattern';
    }
    $similarity_scores['navigation'] = $navigation_similarity * 0.25;
    
    // Interaction pattern similarity (weight: 0.20)
    $interaction_similarity = $this->compare_interaction_patterns($signature1, $signature2);
    if ($interaction_similarity > 0.6) {
        $matching_factors[] = 'interaction_pattern';
    }
    $similarity_scores['interaction'] = $interaction_similarity * 0.20;
    
    // Device and browser similarity (weight: 0.15)
    $device_similarity = 0.0;
    if ($signature1['device_type'] === $signature2['device_type']) {
        $device_similarity += 0.5;
        $matching_factors[] = 'device_type';
    }
    if ($signature1['browser_family'] === $signature2['browser_family']) {
        $device_similarity += 0.5;
        $matching_factors[] = 'browser_family';
    }
    $similarity_scores['device'] = $device_similarity * 0.15;
    
    // Engagement level similarity (weight: 0.15)
    $engagement_similarity = 0.0;
    if ($signature1['engagement_level'] === $signature2['engagement_level']) {
        $engagement_similarity = 1.0;
        $matching_factors[] = 'engagement_level';
    } elseif (abs($signature1['engagement_level'] - $signature2['engagement_level']) <= 1) {
        $engagement_similarity = 0.5;
    }
    $similarity_scores['engagement'] = $engagement_similarity * 0.15;
    
    // Referral pattern similarity (weight: 0.10)
    $referral_similarity = 0.0;
    if ($signature1['referrer_type'] === $signature2['referrer_type']) {
        $referral_similarity += 0.7;
        $matching_factors[] = 'referrer_type';
    }
    if (!empty($signature1['utm_source']) && $signature1['utm_source'] === $signature2['utm_source']) {
        $referral_similarity = 1.0;
        $matching_factors[] = 'utm_source';
    }
    $similarity_scores['referral'] = $referral_similarity * 0.10;
    
    // Store matching factors in candidate
    $candidate['matching_factors'] = $matching_factors;
    
    // Calculate total weighted similarity
    $total_similarity = array_sum($similarity_scores);
    
    return min($total_similarity, 1.0);
}

/**
 * Compare navigation patterns between two signatures
 *
 * @param array $sig1 First signature
 * @param array $sig2 Second signature
 * @return float Similarity score between 0 and 1
 */
private function compare_navigation_patterns($sig1, $sig2) {
    $similarity = 0.0;
    
    // Compare pages visited
    $pages_diff = abs($sig1['pages_visited'] - $sig2['pages_visited']);
    $pages_similarity = max(0, 1 - ($pages_diff / 10));
    $similarity += $pages_similarity * 0.35;
    
    // Compare session duration
    $duration_diff = abs($sig1['session_duration'] - $sig2['session_duration']);
    $duration_similarity = max(0, 1 - ($duration_diff / 600)); // 10 minute tolerance
    $similarity += $duration_similarity * 0.35;
    
    // Compare average time per page
    $time_per_page_diff = abs($sig1['avg_time_per_page'] - $sig2['avg_time_per_page']);
    $time_per_page_similarity = max(0, 1 - ($time_per_page_diff / 120)); // 2 minute tolerance
    $similarity += $time_per_page_similarity * 0.30;
    
    return $similarity;
}

/**
 * Compare interaction patterns between two signatures
 *
 * @param array $sig1 First signature
 * @param array $sig2 Second signature
 * @return float Similarity score between 0 and 1
 */
private function compare_interaction_patterns($sig1, $sig2) {
    $similarity = 0.0;
    
    // Compare click behaviour
    $click_diff = abs($sig1['click_count'] - $sig2['click_count']);
    $click_similarity = max(0, 1 - ($click_diff / 20));
    $similarity += $click_similarity * 0.40;
    
    // Compare scroll behaviour
    $scroll_diff = abs($sig1['scroll_depth'] - $sig2['scroll_depth']);
    $scroll_similarity = max(0, 1 - ($scroll_diff / 100));
    $similarity += $scroll_similarity * 0.30;
    
    // Compare form interactions
    $form_diff = abs($sig1['form_interactions'] - $sig2['form_interactions']);
    $form_similarity = max(0, 1 - ($form_diff / 5));
    $similarity += $form_similarity * 0.30;
    
    return $similarity;
}

/**
 * Categorise time of day into meaningful periods
 *
 * @param int $hour Hour of day (0-23)
 * @return string Time category
 */
private function categorise_time($hour) {
    if ($hour >= 6 && $hour < 9) return 'early_morning';
    if ($hour >= 9 && $hour < 12) return 'morning';
    if ($hour >= 12 && $hour < 14) return 'lunch';
    if ($hour >= 14 && $hour < 17) return 'afternoon';
    if ($hour >= 17 && $hour < 20) return 'evening';
    if ($hour >= 20 && $hour < 23) return 'night';
    return 'late_night';
}

/**
 * Categorise referrer source
 *
 * @param string $referrer Referrer URL
 * @return string Referrer category
 */
private function categorise_referrer($referrer) {
    if (empty($referrer)) {
        return 'direct';
    }
    
    $referrer = strtolower($referrer);
    
    // Search engines
    $search_engines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu'];
    foreach ($search_engines as $engine) {
        if (stripos($referrer, $engine) !== false) {
            return 'search';
        }
    }
    
    // Social media
    $social_platforms = ['facebook', 'twitter', 'linkedin', 'instagram', 'pinterest', 'reddit'];
    foreach ($social_platforms as $platform) {
        if (stripos($referrer, $platform) !== false) {
            return 'social';
        }
    }
    
    // Email
    if (stripos($referrer, 'mail') !== false || stripos($referrer, 'email') !== false) {
        return 'email';
    }
    
    // Internal
    if (stripos($referrer, $_SERVER['HTTP_HOST']) !== false) {
        return 'internal';
    }
    
    return 'referral';
}

/**
 * Extract browser family from user agent
 *
 * @param string $user_agent User agent string
 * @return string Browser family
 */
private function extract_browser_family($user_agent) {
    $user_agent = strtolower($user_agent);
    
    if (stripos($user_agent, 'edge') !== false) return 'edge';
    if (stripos($user_agent, 'chrome') !== false) return 'chrome';
    if (stripos($user_agent, 'safari') !== false) return 'safari';
    if (stripos($user_agent, 'firefox') !== false) return 'firefox';
    if (stripos($user_agent, 'opera') !== false) return 'opera';
    if (stripos($user_agent, 'msie') !== false || stripos($user_agent, 'trident') !== false) return 'ie';
    
    return 'other';
}

/**
 * Calculate engagement level from signature
 *
 * @param array $signature Behavioural signature
 * @return int Engagement level (1-5)
 */
private function calculate_engagement_level($signature) {
    $score = 0;
    
    // Pages visited scoring
    if ($signature['pages_visited'] >= 10) $score += 2;
    elseif ($signature['pages_visited'] >= 5) $score += 1;
    
    // Session duration scoring
    if ($signature['session_duration'] >= 600) $score += 2; // 10+ minutes
    elseif ($signature['session_duration'] >= 300) $score += 1; // 5+ minutes
    
    // Interaction scoring
    if ($signature['click_count'] >= 10) $score += 1;
    if ($signature['scroll_depth'] >= 75) $score += 1;
    if ($signature['form_interactions'] > 0) $score += 1;
    
    // Convert score to level (1-5)
    if ($score >= 6) return 5; // Very high engagement
    if ($score >= 5) return 4; // High engagement
    if ($score >= 3) return 3; // Medium engagement
    if ($score >= 1) return 2; // Low engagement
    return 1; // Very low engagement
}

/**
 * Extract intent signals from additional data
 *
 * @param array $additional_data Additional behavioural data
 * @return array Intent signals
 */
private function extract_intent_signals($additional_data) {
    $signals = [];
    
    // High-intent page visits
    $high_intent_pages = ['pricing', 'checkout', 'quote', 'demo', 'trial', 'contact-sales'];
    $visited_pages = $additional_data['visited_pages'] ?? [];
    
    foreach ($high_intent_pages as $intent_page) {
        if (is_array($visited_pages)) {
            foreach ($visited_pages as $page) {
                if (stripos($page, $intent_page) !== false) {
                    $signals[] = 'visited_' . $intent_page;
                }
            }
        }
    }
    
    // Cart or wishlist activity
    if (!empty($additional_data['cart_items'])) {
        $signals[] = 'has_cart_items';
    }
    
    // Product comparison
    if (!empty($additional_data['compared_products'])) {
        $signals[] = 'compared_products';
    }
    
    // Download activity
    if (!empty($additional_data['downloads'])) {
        $signals[] = 'downloaded_content';
    }
    
    return $signals;
}

/**
 * Calculate pattern strength based on historical consistency
 *
 * @param array $candidate Candidate pattern data
 * @return float Pattern strength between 0 and 1
 */
private function calculate_pattern_strength($candidate) {
    $visit_days = intval($candidate['visit_days'] ?? 1);
    $avg_session_duration = floatval($candidate['avg_session_duration'] ?? 0);
    $avg_pages_visited = floatval($candidate['avg_pages_visited'] ?? 1);
    
    $strength = 0.0;
    
    // Consistency over time
    if ($visit_days >= 5) $strength += 0.4;
    elseif ($visit_days >= 3) $strength += 0.25;
    elseif ($visit_days >= 2) $strength += 0.1;
    
    // Sustained engagement
    if ($avg_session_duration >= 300) $strength += 0.3;
    elseif ($avg_session_duration >= 120) $strength += 0.15;
    
    // Depth of interaction
    if ($avg_pages_visited >= 5) $strength += 0.3;
    elseif ($avg_pages_visited >= 3) $strength += 0.15;
    
    return min($strength, 1.0);
}

/**
 * Format matched patterns for return
 * (This method was referenced in the original code)
 *
 * @param array $patterns Raw pattern matches
 * @param string $match_type Type of match
 * @return array Formatted matches
 */
private function format_matches($patterns, $match_type) {
    $formatted = [];
    
    foreach ($patterns as $pattern) {
        $formatted[] = [
            'identity_hash' => $pattern['identity_hash'] ?? '',
            'match_type' => $match_type,
            'collected_at' => $pattern['collected_at'] ?? '',
            'additional_data' => $pattern['additional_data'] ?? null
        ];
    }
    
    return $formatted;
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