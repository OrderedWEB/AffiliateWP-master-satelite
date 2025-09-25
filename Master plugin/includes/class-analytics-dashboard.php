<?php
/**
 * Analytics Dashboard (Full)
 *
 * Plugin: Affiliate Cross Domain System (Master)
 * File: /wp-content/plugins/affiliate-cross-domain-system/admin/class-analytics-dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class AFFCD_Analytics_Dashboard {

    private $cache_group = 'affcd_analytics';
    private $cache_ttl   = 900; // 15 minutes

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX
        add_action('wp_ajax_affcd_get_analytics_data', [$this, 'ajax_get_analytics_data']);
        add_action('wp_ajax_affcd_export_analytics',   [$this, 'ajax_export_analytics']);

        // (Optional) scheduled cache warming hooks you can wire in your activator:
        add_action('affcd_update_analytics_cache', [$this, 'warm_cache']);
    }

    /* --------------------------
     * Admin UI
     * -------------------------- */

    public function add_admin_menu() {
        add_submenu_page(
            'affiliate-wp',
            __('Analytics Dashboard', 'affiliatewp-cross-domain-plugin-suite'),
            __('Analytics', 'affiliatewp-cross-domain-plugin-suite'),
            'manage_affiliates',
            'affcd-analytics',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'affcd-analytics') === false) return;

        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            [],
            '3.9.1',
            true
        );

        // Admin CSS (optional – provide a minimal baseline)
        wp_add_inline_style('wp-admin', '
        .affcd-analytics-dashboard{margin:20px 20px 0 2px}
        .affcd-date-controls{margin:20px 0;padding:15px;background:#fff;border:1px solid #ddd;border-radius:4px}
        .affcd-date-controls select,.affcd-date-controls input{margin-right:10px}
        .nav-tab-wrapper{margin-top:15px}
        .affcd-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin:20px 0}
        .affcd-stat-card{background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;text-align:center}
        .affcd-stat-value{font-size:2em;font-weight:700;margin:5px 0}
        .affcd-chart-container{background:#fff;border:1px solid #ddd;border-radius:4px;padding:20px;margin:20px 0}
        .affcd-data-table{width:100%;background:#fff;border-collapse:collapse;margin:20px 0}
        .affcd-data-table th,.affcd-data-table td{padding:12px;text-align:left;border-bottom:1px solid #eee}
        .affcd-data-table th{background:#f9f9f9;font-weight:700}
        .affcd-loading{text-align:center;padding:40px}
        ');

        // Inline JS boot (tiny); the heavy lifting happens server-side
        wp_add_inline_script('chartjs', '
        window.AFFCD = window.AFFCD || {};
        ', 'before');

        wp_register_script(
            'affcd-analytics-js',
            false,
            ['jquery','chartjs'],
            defined('AFFCD_VERSION') ? AFFCD_VERSION : '1.0.0',
            true
        );

        wp_add_inline_script('affcd-analytics-js', '
        (function($){
            const ajaxUrl = "'.esc_js(admin_url('admin-ajax.php')).'";
            const nonce   = "'.esc_js(wp_create_nonce('affcd_analytics_nonce')).'";

            function loadTab(tab){
                const period    = $("#affcd-date-range").val() || "7d";
                const startDate = $("#affcd-start-date").val() || "";
                const endDate   = $("#affcd-end-date").val() || "";

                $("#affcd-loading").show();
                $.post(ajaxUrl, {
                    action: "affcd_get_analytics_data",
                    nonce,
                    tab,
                    period,
                    start_date: startDate,
                    end_date: endDate
                }).done(function(res){
                    $("#affcd-loading").hide();
                    if(!res.success){ alert(res.data || "Error"); return; }
                    renderTab(tab, res.data, res.date_range);
                }).fail(function(){
                    $("#affcd-loading").hide();
                    alert("'.esc_js(__('Error loading data', 'affiliatewp-cross-domain-plugin-suite')).'");
                });
            }

            function renderMetricCards(container, metrics){
                const $c = $(container).empty();
                const list = [
                    ["total_usage","Total Usage"],
                    ["total_conversions","Conversions"],
                    ["total_revenue","Revenue"],
                    ["conversion_rate","Conversion Rate"],
                    ["unique_sessions","Unique Sessions"],
                    ["active_domains","Active Domains"]
                ];
                list.forEach(([key,label])=>{
                    if(typeof metrics[key] === "undefined") return;
                    let value = metrics[key];
                    if(key === "total_revenue") value = new Intl.NumberFormat().format(parseFloat(value || 0).toFixed(2));
                    if(key === "conversion_rate") value = (value || 0)+"%";
                    $c.append(`
                        <div class="affcd-stat-card">
                            <div class="affcd-stat-label">${label}</div>
                            <div class="affcd-stat-value">${value}</div>
                        </div>
                    `);
                });
            }

            // Simple chart helper
            function makeLineChart(canvasId, labels, datasets){
                const ctx = document.getElementById(canvasId);
                if(!ctx) return;
                new Chart(ctx.getContext("2d"), {
                    type: "line",
                    data: { labels, datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins:{ legend:{ display: true } },
                        scales: { x: { display: true }, y: { display: true, beginAtZero: true } }
                    }
                });
            }

            function makePieChart(canvasId, labels, values){
                const ctx = document.getElementById(canvasId);
                if(!ctx) return;
                new Chart(ctx.getContext("2d"), {
                    type: "pie",
                    data: { labels, datasets:[{ data: values }]},
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }

            function renderTab(tab, payload, range){
                if(tab === "overview"){
                    renderMetricCards("#affcd-key-metrics", payload.metrics || {});
                    // charts
                    (function(){
                        const rows = payload.traffic_trends || [];
                        const labels = rows.map(r=>r.date);
                        const usage  = rows.map(r=>parseInt(r.usage_count || 0,10));
                        const conv   = rows.map(r=>parseInt(r.conversions || 0,10));
                        const rev    = rows.map(r=>parseFloat(r.revenue || 0));
                        makeLineChart("affcd-traffic-chart", labels, [
                            { label:"Usage", data: usage },
                            { label:"Conversions", data: conv },
                            { label:"Revenue", data: rev }
                        ]);
                    })();
                    // top codes table
                    (function(){
                        const $tb = $("#affcd-top-codes tbody").empty();
                        (payload.top_codes || []).forEach(row=>{
                            $tb.append(`<tr>
                              <td>${row.code || ""}</td>
                              <td>${row.usage_count || 0}</td>
                              <td>${row.conversions || 0}</td>
                              <td>${(parseFloat(row.revenue||0)).toFixed(2)}</td>
                              <td>${row.conversion_rate ? row.conversion_rate+"%" : "0%"}</td>
                            </tr>`);
                        });
                    })();
                    // activity list
                    (function(){
                        const $box = $("#affcd-recent-activity").empty();
                        (payload.recent_activity || []).forEach(r=>{
                            $box.append(`<div>${r.created_at} — <strong>${r.code || "-"}</strong> @ ${r.domain_from || "-"} ${r.conversion_value ? " → £"+(parseFloat(r.conversion_value).toFixed(2)) : ""}</div>`);
                        });
                    })();
                }

                if(tab === "performance"){
                    // Device/Browser/OS pies
                    (function(){
                        const d = payload.device_stats || [];
                        makePieChart("affcd-device-chart", d.map(x=>x.device_type||"Unknown"), d.map(x=>parseInt(x.count||0,10)));
                        const b = payload.browser_stats || [];
                        makePieChart("affcd-browser-chart", b.map(x=>x.browser||"Unknown"), b.map(x=>parseInt(x.count||0,10)));
                        const o = payload.os_stats || [];
                        makePieChart("affcd-os-chart", o.map(x=>x.os||"Unknown"), o.map(x=>parseInt(x.count||0,10)));
                    })();
                    // Revenue line
                    (function(){
                        const rows = payload.revenue_trends || [];
                        const labels = rows.map(r=>r.date);
                        const revenue = rows.map(r=>parseFloat(r.revenue||0));
                        const conv    = rows.map(r=>parseInt(r.conversions||0,10));
                        makeLineChart("affcd-revenue-chart", labels, [
                            { label:"Revenue", data: revenue },
                            { label:"Conversions", data: conv }
                        ]);
                    })();
                }

                if(tab === "affiliates"){
                    const $tb = $("#affcd-affiliate-performance tbody").empty();
                    (payload.performance || []).forEach(r=>{
                        $tb.append(`<tr>
                          <td>${(r.affiliate_name||"")+" "+(r.affiliate_email?"<br><small>"+r.affiliate_email+"</small>":"")}</td>
                          <td>${r.total_codes||0}</td>
                          <td>${r.total_usage||0}</td>
                          <td>${r.conversions||0}</td>
                          <td>${(parseFloat(r.revenue||0)).toFixed(2)}</td>
                          <td>${r.avg_order_value ? parseFloat(r.avg_order_value).toFixed(2) : "0.00"}</td>
                          <td>${r.conversion_rate? r.conversion_rate+"%":"0%"}</td>
                        </tr>`);
                    });
                }

                if(tab === "domains"){
                    const $tb = $("#affcd-domain-performance tbody").empty();
                    (payload.performance || []).forEach(r=>{
                        $tb.append(`<tr>
                          <td>${r.domain||r.domain_from||"-"}</td>
                          <td>${r.total_requests||0}</td>
                          <td>${r.successful_validations||0}</td>
                          <td>${r.conversions||0}</td>
                          <td>${(parseFloat(r.revenue||0)).toFixed(2)}</td>
                          <td>${r.success_rate ? r.success_rate+"%":"0%"}</td>
                          <td>${r.status||"—"}</td>
                        </tr>`);
                    });

                    (function(){
                        const rows = payload.activity || [];
                        const labels = rows.map(r=>r.date);
                        const totals = rows.map(r=>parseInt(r.total_requests||0,10));
                        makeLineChart("affcd-domain-activity", labels, [{label:"Requests", data: totals}]);
                    })();
                }

                if(tab === "geographic"){
                    const $tb = $("#affcd-geographic-performance tbody").empty();
                    (payload.performance || []).forEach(r=>{
                        $tb.append(`<tr>
                          <td>${r.country||"-"}</td>
                          <td>${r.region||"-"}</td>
                          <td>${r.sessions||0}</td>
                          <td>${r.conversions||0}</td>
                          <td>${(parseFloat(r.revenue||0)).toFixed(2)}</td>
                          <td>${r.conversion_rate? r.conversion_rate+"%":"0%"}</td>
                          <td>${r.avg_order_value? parseFloat(r.avg_order_value).toFixed(2):"0.00"}</td>
                        </tr>`);
                    });
                }

                if(tab === "security"){
                    const $metrics = $("#affcd-security-metrics").empty();
                    (payload.metrics || []).forEach(m=>{
                        $metrics.append(`<div class="affcd-stat-card">
                          <div class="affcd-stat-label">${m.label}</div>
                          <div class="affcd-stat-value">${m.value}</div>
                        </div>`);
                    });

                    (function(){
                        const rows = payload.events_over_time || [];
                        const labels = rows.map(r=>r.date);
                        const totals = rows.map(r=>parseInt(r.total||0,10));
                        makeLineChart("affcd-security-events", labels, [{label:"Events", data: totals}]);
                    })();

                    const $tb = $("#affcd-security-events-table tbody").empty();
                    (payload.recent || []).forEach(r=>{
                        $tb.append(`<tr>
                          <td>${r.created_at||""}</td>
                          <td>${r.event_type||""}</td>
                          <td>${r.severity||""}</td>
                          <td>${r.ip_address||""}</td>
                          <td>${r.domain||""}</td>
                          <td>${r.details||""}</td>
                        </tr>`);
                    });
                }
            }

            $(document).on("change", "#affcd-date-range", function(){
                const v = $(this).val();
                $("#affcd-custom-date-range").toggle(v==="custom");
            });

            $("#affcd-apply-custom-range, #affcd-refresh-data").on("click", function(){
                const tab = $(".nav-tab.nav-tab-active").data("tab") || "overview";
                loadTab(tab);
            });

            $(".nav-tab-wrapper").on("click", ".nav-tab", function(e){
                e.preventDefault();
                $(".nav-tab").removeClass("nav-tab-active");
                $(this).addClass("nav-tab-active");
                loadTab($(this).data("tab"));
                history.replaceState(null, "", $(this).attr("href"));
            });

            // initial load
            $(function(){
                const tab = $(".nav-tab.nav-tab-active").data("tab") || "overview";
                loadTab(tab);
            });

            // Export
            $("#affcd-export-data").on("click", function(){
                const period = $("#affcd-date-range").val() || "7d";
                $.post(ajaxUrl, { action:"affcd_export_analytics", nonce, period, format:"csv"}).done(function(res){
                    if(res.success && res.data && res.data.download_url){
                        window.location = res.data.download_url;
                    }else{
                        alert(res.data || "Export failed");
                    }
                });
            });

        })(jQuery);
        ');

        wp_enqueue_script('affcd-analytics-js');
    }

    public function render_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        $tabs = ['overview','performance','affiliates','domains','geographic','security'];

        if (!in_array($current_tab, $tabs, true)) $current_tab = 'overview';

        ?>
        <div class="wrap affcd-analytics-dashboard">
            <h1><?php _e('Analytics Dashboard', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>

            <div class="affcd-date-controls">
                <select id="affcd-date-range">
                    <option value="24h"><?php _e('Last 24 Hours', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                    <option value="7d" selected><?php _e('Last 7 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                    <option value="30d"><?php _e('Last 30 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                    <option value="90d"><?php _e('Last 90 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                    <option value="custom"><?php _e('Custom Range', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                </select>
                <span id="affcd-custom-date-range" style="display:none">
                    <input type="date" id="affcd-start-date" />
                    <span>to</span>
                    <input type="date" id="affcd-end-date" />
                    <button type="button" id="affcd-apply-custom-range" class="button"><?php _e('Apply', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                </span>
                <button type="button" id="affcd-refresh-data" class="button"><?php _e('Refresh', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                <button type="button" id="affcd-export-data" class="button button-primary"><?php _e('Export Data', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $t): ?>
                    <?php
                        $href = esc_url(add_query_arg(['page'=>'affcd-analytics','tab'=>$t], admin_url('admin.php')));
                        $active = $current_tab === $t ? ' nav-tab-active' : '';
                    ?>
                    <a href="<?php echo $href; ?>" data-tab="<?php echo esc_attr($t); ?>" class="nav-tab<?php echo $active; ?>">
                        <?php echo esc_html(ucfirst($t)); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div id="affcd-loading" class="affcd-loading" style="display:none;">
                <div class="spinner is-active"></div>
                <span><?php _e('Loading analytics data...', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
            </div>

            <div class="tab-content" id="affcd-analytics-content">
                <?php if ($current_tab === 'overview'): ?>
                    <div class="affcd-stats-grid" id="affcd-key-metrics"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;min-height:280px;">
                        <div class="affcd-chart-container">
                            <div class="affcd-chart-title"><?php _e('Traffic & Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                            <div style="height:260px"><canvas id="affcd-traffic-chart"></canvas></div>
                        </div>
                        <div class="affcd-chart-container">
                            <div class="affcd-chart-title"><?php _e('Recent Activity', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                            <div id="affcd-recent-activity" style="max-height:260px;overflow:auto"></div>
                        </div>
                    </div>
                    <div class="affcd-chart-container">
                        <div class="affcd-chart-title"><?php _e('Top Performing Vanity Codes', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                        <table class="affcd-data-table" id="affcd-top-codes">
                            <thead>
                                <tr>
                                    <th><?php _e('Code', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Usage', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Revenue', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Conversion Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                <?php elseif ($current_tab === 'performance'): ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;min-height:280px;">
                        <div class="affcd-chart-container">
                            <div class="affcd-chart-title"><?php _e('Revenue & Conversions Over Time', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                            <div style="height:260px"><canvas id="affcd-revenue-chart"></canvas></div>
                        </div>
                        <div class="affcd-chart-container">
                            <div class="affcd-chart-title"><?php _e('Device / Browser / OS', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;min-height:260px;">
                                <canvas id="affcd-device-chart"></canvas>
                                <canvas id="affcd-browser-chart"></canvas>
                                <canvas id="affcd-os-chart"></canvas>
                            </div>
                        </div>
                    </div>
                <?php elseif ($current_tab === 'affiliates'): ?>
                    <div class="affcd-chart-container">
                        <div class="affcd-chart-title"><?php _e('Affiliate Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                        <table class="affcd-data-table" id="affcd-affiliate-performance">
                            <thead>
                                <tr>
                                    <th><?php _e('Affiliate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Codes', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Total Usage', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Revenue', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Avg. Order Value', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Conversion Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                <?php elseif ($current_tab === 'domains'): ?>
                    <div class="affcd-chart-container">
                        <div class="affcd-chart-title"><?php _e('Domain Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                        <table class="affcd-data-table" id="affcd-domain-performance">
                            <thead>
                                <tr>
                                    <th><?php _e('Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Total Requests', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Successful Validations', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Revenue', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Success Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="affcd-chart-container">
                        <div class="affcd-chart-title"><?php _e('Domain Activity Over Time', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                        <div style="height:260px"><canvas id="affcd-domain-activity"></canvas></div>
                    </div>
                <?php elseif ($current_tab === 'geographic'): ?>
                    <div class="affcd-chart-container">
                        <div class="affcd-chart-title"><?php _e('Geographic Performance', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                        <table class="affcd-data-table" id="affcd-geographic-performance">
                            <thead>
                                <tr>
                                    <th><?php _e('Country', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Region', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Sessions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Conversions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Revenue', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Conversion Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Avg. Order Value', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                <?php elseif ($current_tab === 'security'): ?>
                    <div class="affcd-stats-grid" id="affcd-security-metrics"></div>
                    <div class="affcd-chart-container">
                        <div class="affcd-chart-title"><?php _e('Security Events Over Time', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                        <div style="height:260px"><canvas id="affcd-security-events"></canvas></div>
                    </div>
                    <div class="affcd-chart-container">
                        <div class="affcd-chart-title"><?php _e('Recent Security Events', 'affiliatewp-cross-domain-plugin-suite'); ?></div>
                        <table class="affcd-data-table" id="affcd-security-events-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Timestamp', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Event Type', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Severity', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Source IP', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Domain', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Details', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* --------------------------
     * AJAX endpoints
     * -------------------------- */

    public function ajax_get_analytics_data() {
        check_ajax_referer('affcd_analytics_nonce', 'nonce');
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $tab       = sanitize_key($_POST['tab'] ?? 'overview');
        $period    = sanitize_text_field($_POST['period'] ?? '7d');
        $start     = sanitize_text_field($_POST['start_date'] ?? '');
        $end       = sanitize_text_field($_POST['end_date'] ?? '');

        [$from, $to] = $this->date_range($period, $start, $end);

        $cache_key = md5($tab.$from.$to);
        $cached = wp_cache_get($cache_key, $this->cache_group);
        if ($cached !== false) {
            wp_send_json_success($cached);
        }

        $data_map = [
            'overview'   => [$this, 'data_overview'],
            'performance'=> [$this, 'data_performance'],
            'affiliates' => [$this, 'data_affiliates'],
            'domains'    => [$this, 'data_domains'],
            'geographic' => [$this, 'data_geographic'],
            'security'   => [$this, 'data_security'],
        ];

        if (!isset($data_map[$tab])) $tab = 'overview';

        $payload = call_user_func($data_map[$tab], $from, $to);
        $resp = ['data' => $payload, 'date_range' => ['from'=>$from,'to'=>$to]];

        wp_cache_set($cache_key, $resp, $this->cache_group, $this->cache_ttl);
        wp_send_json_success($resp);
    }

    public function ajax_export_analytics() {
        check_ajax_referer('affcd_analytics_nonce', 'nonce');
        if (!current_user_can('manage_affiliates')) {
            wp_send_json_error(__('Insufficient permissions.', 'affiliatewp-cross-domain-plugin-suite'));
        }

        $period = sanitize_text_field($_POST['period'] ?? '7d');
        [$from, $to] = $this->date_range($period, '', '');

        // Export a compact snapshot CSV of key overview + top codes
        $snap = $this->data_overview($from, $to);

        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']).'affcd-exports/';
        if (!file_exists($dir)) wp_mkdir_p($dir);

        $filename = 'affcd-analytics-'.date('Y-m-d-H-i-s').'.csv';
        $path = $dir.$filename;

        $fh = fopen($path, 'w');
        fputcsv($fh, ['Metric','Value']);
        foreach (['total_usage','total_conversions','total_revenue','conversion_rate','unique_sessions','active_domains'] as $k) {
            $label = ucwords(str_replace('_',' ',$k));
            fputcsv($fh, [$label, $snap['metrics'][$k] ?? 0]);
        }
        fputcsv($fh, []);
        fputcsv($fh, ['Top Codes']);
        fputcsv($fh, ['Code','Usage','Conversions','Revenue','Conversion Rate']);
        foreach ($snap['top_codes'] as $r) {
            fputcsv($fh, [
                $r['code'],
                $r['usage_count'],
                $r['conversions'],
                $r['revenue'],
                ($r['conversion_rate'] ?? 0).'%'
            ]);
        }
        fclose($fh);

        wp_send_json_success([
            'download_url' => trailingslashit($upload['baseurl']).'affcd-exports/'.$filename,
            'filename'     => $filename
        ]);
    }

    /* --------------------------
     * Data providers
     * -------------------------- */

    private function data_overview($from, $to) {
        global $wpdb;
        $usage = $wpdb->prefix.'affcd_usage_tracking';
        $codes = $wpdb->prefix.'affcd_vanity_codes';

        // Metrics
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) AS total_usage,
                COUNT(DISTINCT session_id) AS unique_sessions,
                SUM(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS total_conversions,
                COALESCE(SUM(CASE WHEN status='success' THEN conversion_value ELSE 0 END),0) AS total_revenue,
                COUNT(DISTINCT domain_from) AS active_domains
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
        ", $from, $to), ARRAY_A) ?: [];

        $metrics['conversion_rate'] = !empty($metrics['total_usage']) && (int)$metrics['total_usage'] > 0
            ? round(((int)$metrics['total_conversions'] / (int)$metrics['total_usage']) * 100, 2)
            : 0;

        // Traffic / conversions / revenue by day
        $traffic_trends = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE(created_at) AS date,
                COUNT(*) AS usage_count,
                SUM(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(CASE WHEN status='success' THEN conversion_value ELSE 0 END),0) AS revenue
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ", $from, $to), ARRAY_A) ?: [];

        // Top codes: using usage_tracking.affiliate_code (string) (not FK), but also try join with vanity_codes to ensure it exists
        $top_codes = $wpdb->get_results($wpdb->prepare("
            SELECT
                COALESCE(u.affiliate_code, v.code) AS code,
                COUNT(u.id) AS usage_count,
                SUM(CASE WHEN u.status='success' AND u.conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(CASE WHEN u.status='success' THEN u.conversion_value ELSE 0 END),0) AS revenue,
                ROUND(
                    (SUM(CASE WHEN u.status='success' AND u.conversion_value IS NOT NULL THEN 1 ELSE 0 END) / NULLIF(COUNT(u.id),0)) * 100,
                2) AS conversion_rate
            FROM {$usage} u
            LEFT JOIN {$codes} v ON v.code = u.affiliate_code
            WHERE u.created_at BETWEEN %s AND %s
            GROUP BY COALESCE(u.affiliate_code, v.code)
            HAVING usage_count > 0
            ORDER BY revenue DESC, conversions DESC
            LIMIT 10
        ", $from, $to), ARRAY_A) ?: [];

        // Recent activity
        $recent_activity = $wpdb->get_results($wpdb->prepare("
            SELECT
                created_at,
                domain_from,
                affiliate_code AS code,
                CASE WHEN status='success' AND conversion_value IS NOT NULL THEN conversion_value ELSE NULL END AS conversion_value
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
            ORDER BY created_at DESC
            LIMIT 25
        ", $from, $to), ARRAY_A) ?: [];

        return [
            'metrics'         => $metrics,
            'traffic_trends'  => $traffic_trends,
            'top_codes'       => $top_codes,
            'recent_activity' => $recent_activity,
        ];
    }

    private function data_performance($from, $to) {
        global $wpdb;
        $usage = $wpdb->prefix.'affcd_usage_tracking';

        // Device (JSON device_info -> $.type)
        $device_stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(device_info, '$.type')), 'Unknown') AS device_type,
                COUNT(*) AS count,
                SUM(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(CASE WHEN status='success' THEN conversion_value ELSE 0 END),0) AS revenue
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY device_type
            ORDER BY count DESC
        ", $from, $to), ARRAY_A) ?: [];

        // Browser (JSON device_info -> $.browser)
        $browser_stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(device_info, '$.browser')), 'Unknown') AS browser,
                COUNT(*) AS count,
                SUM(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY browser
            ORDER BY count DESC
            LIMIT 12
        ", $from, $to), ARRAY_A) ?: [];

        // OS (JSON device_info -> $.os)
        $os_stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(device_info, '$.os')), 'Unknown') AS os,
                COUNT(*) AS count
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY os
            ORDER BY count DESC
        ", $from, $to), ARRAY_A) ?: [];

        // Revenue trend line
        $revenue_trends = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE(created_at) AS date,
                COALESCE(SUM(CASE WHEN status='success' THEN conversion_value ELSE 0 END),0) AS revenue,
                SUM(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ", $from, $to), ARRAY_A) ?: [];

        return [
            'device_stats'   => $device_stats,
            'browser_stats'  => $browser_stats,
            'os_stats'       => $os_stats,
            'revenue_trends' => $revenue_trends,
        ];
    }

    private function data_affiliates($from, $to) {
        global $wpdb;
        $usage = $wpdb->prefix.'affcd_usage_tracking';
        $codes = $wpdb->prefix.'affcd_vanity_codes';

        // Roll up by affiliate_id via vanity_codes join when possible; fall back on affiliate_code string
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                v.affiliate_id,
                COUNT(DISTINCT v.id) AS total_codes,
                COUNT(u.id) AS total_usage,
                SUM(CASE WHEN u.status='success' AND u.conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(CASE WHEN u.status='success' THEN u.conversion_value ELSE 0 END),0) AS revenue,
                ROUND(AVG(CASE WHEN u.status='success' AND u.conversion_value IS NOT NULL THEN u.conversion_value END),2) AS avg_order_value,
                ROUND(
                    (SUM(CASE WHEN u.status='success' AND u.conversion_value IS NOT NULL THEN 1 ELSE 0 END)
                        / NULLIF(COUNT(u.id),0)) * 100, 2
                ) AS conversion_rate
            FROM {$codes} v
            LEFT JOIN {$usage} u
                ON u.affiliate_code = v.code
                AND u.created_at BETWEEN %s AND %s
            GROUP BY v.affiliate_id
            HAVING total_usage > 0
            ORDER BY revenue DESC
            LIMIT 100
        ", $from, $to), ARRAY_A) ?: [];

        // add affiliate name/email if AffiliateWP is present
        foreach ($rows as &$r) {
            $r['affiliate_name']  = 'Unknown';
            $r['affiliate_email'] = '';
            if (!empty($r['affiliate_id']) && function_exists('affwp_get_affiliate')) {
                $aff = affwp_get_affiliate((int)$r['affiliate_id']);
                if ($aff) {
                    $u = get_userdata($aff->user_id);
                    if ($u) {
                        $r['affiliate_name']  = $u->display_name;
                        $r['affiliate_email'] = $u->user_email;
                    }
                }
            }
        }

        return ['performance' => $rows];
    }

    private function data_domains($from, $to) {
        global $wpdb;
        $usage   = $wpdb->prefix.'affcd_usage_tracking';
        $domains = $wpdb->prefix.'affcd_authorized_domains';

        // Current performance by domain
        $performance = $wpdb->get_results($wpdb->prepare("
            SELECT
                u.domain_from AS domain,
                d.status,
                COUNT(u.id) AS total_requests,
                SUM(CASE WHEN u.status='success' AND u.conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(CASE WHEN u.status='success' THEN u.conversion_value ELSE 0 END),0) AS revenue,
                ROUND(
                    (SUM(CASE WHEN u.status='success' THEN 1 ELSE 0 END) / NULLIF(COUNT(u.id),0)) * 100, 2
                ) AS success_rate,
                SUM(CASE WHEN u.status='success' THEN 1 ELSE 0 END) AS successful_validations
            FROM {$usage} u
            LEFT JOIN {$domains} d
                ON d.domain_name = u.domain_from
            WHERE u.created_at BETWEEN %s AND %s
            GROUP BY u.domain_from, d.status
            ORDER BY total_requests DESC
        ", $from, $to), ARRAY_A) ?: [];

        // Activity over time (total requests by day)
        $activity = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE(created_at) AS date,
                COUNT(*) AS total_requests
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ", $from, $to), ARRAY_A) ?: [];

        return [
            'performance' => $performance,
            'activity'    => $activity,
        ];
    }

    private function data_geographic($from, $to) {
        global $wpdb;
        $usage = $wpdb->prefix.'affcd_usage_tracking';

        // geographic_info: expect keys like country / country_code / region / city (JSON)
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(geographic_info, '$.country')), '') AS country,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(geographic_info, '$.region')),  '') AS region,
                COUNT(*) AS sessions,
                SUM(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(CASE WHEN status='success' THEN conversion_value ELSE 0 END),0) AS revenue,
                ROUND(
                    (SUM(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN 1 ELSE 0 END)
                        / NULLIF(COUNT(*),0)) * 100, 2
                ) AS conversion_rate,
                ROUND(AVG(CASE WHEN status='success' AND conversion_value IS NOT NULL THEN conversion_value END), 2) AS avg_order_value
            FROM {$usage}
            WHERE created_at BETWEEN %s AND %s
              AND geographic_info IS NOT NULL
            GROUP BY country, region
            ORDER BY sessions DESC
            LIMIT 500
        ", $from, $to), ARRAY_A) ?: [];

        return ['performance' => $rows];
    }

    private function data_security($from, $to) {
        global $wpdb;
        $sec = $wpdb->prefix.'affcd_security_logs';

        // If the security_logs table exists (it does per your DB manager), provide metrics
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sec));
        if ($exists !== $sec) {
            return [
                'metrics' => [],
                'events_over_time' => [],
                'recent' => []
            ];
        }

        $metrics = [
            [
                'label' => __('Events (period)', 'affiliatewp-cross-domain-plugin-suite'),
                'value' => (int)$wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$sec}
                    WHERE created_at BETWEEN %s AND %s
                ", $from, $to))
            ],
            [
                'label' => __('Critical', 'affiliatewp-cross-domain-plugin-suite'),
                'value' => (int)$wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$sec}
                    WHERE severity='critical' AND created_at BETWEEN %s AND %s
                ", $from, $to))
            ],
            [
                'label' => __('High', 'affiliatewp-cross-domain-plugin-suite'),
                'value' => (int)$wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM {$sec}
                    WHERE severity='high' AND created_at BETWEEN %s AND %s
                ", $from, $to))
            ],
        ];

        $events_over_time = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) AS date, COUNT(*) AS total
            FROM {$sec}
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ", $from, $to), ARRAY_A) ?: [];

        $recent = $wpdb->get_results($wpdb->prepare("
            SELECT created_at, event_type, severity, ip_address, domain,
                   LEFT(COALESCE(event_data,''), 140) AS details
            FROM {$sec}
            WHERE created_at BETWEEN %s AND %s
            ORDER BY created_at DESC
            LIMIT 50
        ", $from, $to), ARRAY_A) ?: [];

        return [
            'metrics' => $metrics,
            'events_over_time' => $events_over_time,
            'recent'  => $recent
        ];
    }

    /* --------------------------
     * Helpers
     * -------------------------- */

    private function date_range($period, $start = '', $end = '') {
        if ($period === 'custom' && $start && $end) {
            return [$start.' 00:00:00', $end.' 23:59:59'];
        }
        $to = current_time('mysql');
        switch ($period) {
            case '24h': $from = date('Y-m-d H:i:s', strtotime('-24 hours')); break;
            case '30d': $from = date('Y-m-d H:i:s', strtotime('-30 days'));  break;
            case '90d': $from = date('Y-m-d H:i:s', strtotime('-90 days'));  break;
            case '7d':
            default:    $from = date('Y-m-d H:i:s', strtotime('-7 days'));   break;
        }
        return [$from, $to];
    }

    public function warm_cache() {
        foreach (['overview','performance','affiliates','domains','geographic','security'] as $tab) {
            foreach (['24h','7d','30d','90d'] as $period) {
                [$from, $to] = $this->date_range($period, '', '');
                $cache_key = md5($tab.$from.$to);
                if (wp_cache_get($cache_key, $this->cache_group) !== false) continue;

                switch ($tab) {
                    case 'performance': $payload = $this->data_performance($from, $to); break;
                    case 'affiliates':  $payload = $this->data_affiliates($from, $to); break;
                    case 'domains':     $payload = $this->data_domains($from, $to);    break;
                    case 'geographic':  $payload = $this->data_geographic($from, $to); break;
                    case 'security':    $payload = $this->data_security($from, $to);   break;
                    case 'overview':
                    default:            $payload = $this->data_overview($from, $to);   break;
                }

                $resp = ['data'=>$payload, 'date_range'=>['from'=>$from,'to'=>$to]];
                wp_cache_set($cache_key, $resp, $this->cache_group, $this->cache_ttl);
            }
        }
    }
}

// Boot it
new AFFCD_Analytics_Dashboard();
