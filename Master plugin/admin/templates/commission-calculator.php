<div class="affcd-form-row" id="custom-rate-row" style="display: none;">
                                <label for="custom-rate"><?php _e('Custom Rate (% or $):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <input type="number" id="custom-rate" name="custom_rate" step="0.01" min="0">
                            </div>

                            <div class="affcd-form-actions">
                                <button type="submit" class="button button-primary button-large">
                                    <?php _e('Calculate Commission', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                                <button type="button" id="save-calculation" class="button" style="display: none;">
                                    <?php _e('Save Calculation', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Calculation Results -->
                <div class="affcd-calculation-results">
                    <div class="affcd-results-card" id="results-card" style="display: none;">
                        <h3><?php _e('Calculation Results', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        
                        <div class="affcd-result-summary">
                            <div class="affcd-result-item affcd-primary-result">
                                <span class="affcd-result-label"><?php _e('Commission Amount:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                <span class="affcd-result-value" id="commission-amount">$0.00</span>
                            </div>
                            
                            <div class="affcd-result-breakdown">
                                <div class="affcd-result-item">
                                    <span class="affcd-result-label"><?php _e('Base Amount:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-result-value" id="result-base-amount">$0.00</span>
                                </div>
                                <div class="affcd-result-item">
                                    <span class="affcd-result-label"><?php _e('Commission Rate:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-result-value" id="commission-rate">0%</span>
                                </div>
                                <div class="affcd-result-item">
                                    <span class="affcd-result-label"><?php _e('Tier Level:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-result-value" id="tier-level">1</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="affcd-calculation-details" id="calculation-details">
                            <!-- Detailed breakdown will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Affiliate Info Card -->
                    <div class="affcd-affiliate-info" id="affiliate-info" style="display: none;">
                        <h4><?php _e('Affiliate Information', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <div id="affiliate-details">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier Management Tab -->
        <div id="tier-management" class="tab-content">
            <div class="affcd-section">
                <div class="affcd-section-header">
                    <h3><?php _e('Commission Tier Configuration', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <button id="add-tier" class="button button-primary">
                        <?php _e('Add New Tier', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </button>
                </div>
                
                <div class="affcd-tiers-container">
                    <?php if (!empty($tier_config)): ?>
                        <?php foreach ($tier_config as $tier_id => $tier): ?>
                        <div class="affcd-tier-card" data-tier-id="<?php echo esc_attr($tier_id); ?>">
                            <div class="affcd-tier-header">
                                <h4><?php echo esc_html($tier['name']); ?></h4>
                                <div class="affcd-tier-actions">
                                    <button class="button button-small edit-tier"><?php _e('Edit', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                                    <button class="button button-small button-link-delete delete-tier"><?php _e('Delete', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                                </div>
                            </div>
                            <div class="affcd-tier-details">
                                <div class="affcd-tier-info">
                                    <span class="affcd-tier-label"><?php _e('Commission Rate:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-tier-value"><?php echo esc_html($tier['rate']); ?>%</span>
                                </div>
                                <div class="affcd-tier-info">
                                    <span class="affcd-tier-label"><?php _e('Min. Sales:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-tier-value">$<?php echo esc_html(number_format($tier['min_sales'])); ?></span>
                                </div>
                                <div class="affcd-tier-info">
                                    <span class="affcd-tier-label"><?php _e('Active Users:', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                                    <span class="affcd-tier-value"><?php echo esc_html($tier['user_count'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="affcd-no-tiers">
                            <p><?php _e('No commission tiers configured. Create your first tier to get started!', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Calculation History Tab -->
        <div id="calculation-history" class="tab-content">
            <div class="affcd-section">
                <div class="affcd-section-header">
                    <h3><?php _e('Recent Calculations', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <div class="affcd-history-filters">
                        <select id="history-period">
                            <option value="7_days"><?php _e('Last 7 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="30_days" selected><?php _e('Last 30 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="90_days"><?php _e('Last 90 Days', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        </select>
                    </div>
                </div>
                
                <?php if (!empty($recent_calculations)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Affiliate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Type', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Base Amount', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Commission', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Tier', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Date', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                            <th><?php _e('Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_calculations as $calc): ?>
                        <tr>
                            <td><?php echo esc_html($calc->affiliate_name ?? 'Unknown'); ?></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $calc->calculation_type))); ?></td>
                            <td>$<?php echo esc_html(number_format($calc->base_amount, 2)); ?></td>
                            <td class="affcd-commission-amount">$<?php echo esc_html(number_format($calc->calculated_amount, 2)); ?></td>
                            <td><?php echo esc_html($calc->tier_level); ?></td>
                            <td><?php echo esc_html(date('M j, Y H:i', strtotime($calc->created_at))); ?></td>
                            <td>
                                <button class="button button-small view-calculation" data-calc-id="<?php echo esc_attr($calc->id); ?>">
                                    <?php _e('View', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                                <button class="button button-small recalculate" 
                                        data-affiliate="<?php echo esc_attr($calc->affiliate_id); ?>"
                                        data-amount="<?php echo esc_attr($calc->base_amount); ?>"
                                        data-type="<?php echo esc_attr($calc->calculation_type); ?>">
                                    <?php _e('Recalculate', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="affcd-no-calculations">
                    <p><?php _e('No calculations found. Start by calculating your first commission!', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Simulation Tool Tab -->
        <div id="simulation-tool" class="tab-content">
            <div class="affcd-section">
                <h3><?php _e('Commission Simulation Tool', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <p class="affcd-section-desc"><?php _e('Test different scenarios and see how commission changes would affect your affiliates.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                
                <div class="affcd-simulation-form">
                    <form id="simulation-form">
                        <div class="affcd-simulation-grid">
                            <div class="affcd-simulation-inputs">
                                <h4><?php _e('Simulation Parameters', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                                
                                <div class="affcd-form-row">
                                    <label for="sim-sales-range"><?php _e('Sales Range ($):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <div class="affcd-range-inputs">
                                        <input type="number" id="sim-min-sales" name="min_sales" placeholder="Min" value="100" step="100">
                                        <span>to</span>
                                        <input type="number" id="sim-max-sales" name="max_sales" placeholder="Max" value="10000" step="100">
                                    </div>
                                </div>
                                
                                <div class="affcd-form-row">
                                    <label for="sim-rate-scenarios"><?php _e('Rate Scenarios (%):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <input type="text" id="sim-rate-scenarios" name="rate_scenarios" 
                                           placeholder="5,10,15,20" value="5,10,15,20">
                                    <small><?php _e('Comma-separated values', 'affiliatewp-cross-domain-plugin-suite'); ?></small>
                                </div>
                                
                                <div class="affcd-form-row">
                                    <label for="sim-intervals"><?php _e('Data Points:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <select id="sim-intervals" name="intervals">
                                        <option value="10">10 points</option>
                                        <option value="20" selected>20 points</option>
                                        <option value="50">50 points</option>
                                        <option value="100">100 points</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="button button-primary">
                                    <?php _e('Run Simulation', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </div>
                            
                            <div class="affcd-simulation-results">
                                <h4><?php _e('Simulation Results', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                                <div id="simulation-chart" class="affcd-chart-container">
                                    <p class="affcd-chart-placeholder"><?php _e('Run a simulation to see results here', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="affcd-simulation-summary" id="simulation-summary" style="display: none;">
                    <h4><?php _e('Summary', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                    <div class="affcd-summary-grid">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div id="reports" class="tab-content">
            <div class="affcd-section">
                <div class="affcd-section-header">
                    <h3><?php _e('Commission Reports', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    <div class="affcd-report-actions">
                        <button id="generate-report" class="button button-primary">
                            <?php _e('Generate Report', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                        <button id="schedule-report" class="button">
                            <?php _e('Schedule Reports', 'affiliatewp-cross-domain-plugin-suite'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="affcd-report-filters">
                    <div class="affcd-filter-row">
                        <label for="report-period"><?php _e('Time Period:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <select id="report-period">
                            <option value="last_month"><?php _e('Last Month', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="last_quarter"><?php _e('Last Quarter', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="last_year"><?php _e('Last Year', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="custom"><?php _e('Custom Range', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        </select>
                    </div>
                    
                    <div class="affcd-filter-row" id="custom-date-range" style="display: none;">
                        <label for="report-start-date"><?php _e('From:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <input type="date" id="report-start-date">
                        <label for="report-end-date"><?php _e('To:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <input type="date" id="report-end-date">
                    </div>
                    
                    <div class="affcd-filter-row">
                        <label for="report-format"><?php _e('Format:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <select id="report-format">
                            <option value="csv"><?php _e('CSV', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="excel"><?php _e('Excel', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            <option value="pdf"><?php _e('PDF', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="affcd-report-preview" id="report-preview">
                    <h4><?php _e('Report Preview', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                    <p class="affcd-preview-placeholder"><?php _e('Generate a report to see preview here', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tier Management Modal -->
    <div id="tier-modal" class="affcd-modal" style="display: none;">
        <div class="affcd-modal-content">
            <div class="affcd-modal-header">
                <h3 id="tier-modal-title"><?php _e('Add New Tier', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                <span class="affcd-modal-close">&times;</span>
            </div>
            <div class="affcd-modal-body">
                <form id="tier-form">
                    <input type="hidden" id="tier-id" name="tier_id">
                    
                    <div class="affcd-form-row">
                        <label for="tier-name"><?php _e('Tier Name:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <input type="text" id="tier-name" name="tier_name" required>
                    </div>
                    
                    <div class="affcd-form-row">
                        <label for="tier-rate"><?php _e('Commission Rate (%):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <input type="number" id="tier-rate" name="tier_rate" step="0.01" min="0" max="100" required>
                    </div>
                    
                    <div class="affcd-form-row">
                        <label for="tier-min-sales"><?php _e('Minimum Sales ($):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <input type="number" id="tier-min-sales" name="tier_min_sales" step="0.01" min="0" required>
                    </div>
                    
                    <div class="affcd-form-row">
                        <label for="tier-description"><?php _e('Description:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                        <textarea id="tier-description" name="tier_description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="affcd-modal-footer">
                <button class="button button-primary" id="save-tier"><?php _e('Save Tier', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                <button class="button" id="cancel-tier"><?php _e('Cancel', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.affcd-calculator-tabs {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    margin-top: 20px;
}

.tab-content {
    display: none;
    padding: 30px;
}

.tab-content.active {
    display: block;
}

.affcd-calculator-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.affcd-form-card,
.affcd-results-card {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
}

.affcd-form-card h3,
.affcd-results-card h3 {
    margin: 0 0 20px 0;
    color: #333;
}

.affcd-form-row {
    margin-bottom: 20px;
}

.affcd-form-row label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}

.affcd-form-row input,
.affcd-form-row select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.affcd-form-actions {
    margin-top: 25px;
}

.affcd-result-summary {
    margin-bottom: 20px;
}

.affcd-primary-result {
    background: #0073aa;
    color: white;
    padding: 20px;
    border-radius: 6px;
    text-align: center;
    margin-bottom: 20px;
}

.affcd-primary-result .affcd-result-value {
    display: block;
    font-size: 32px;
    font-weight: bold;
    margin-top: 5px;
}

.affcd-result-breakdown {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}

.affcd-result-item {
    display: flex;
    flex-direction: column;
    text-align: center;
    padding: 15px;
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
}

.affcd-result-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.affcd-result-value {
    font-size: 18px;
    font-weight: bold;
    color: #0073aa;
}

.affcd-affiliate-info {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.affcd-tiers-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.affcd-tier-card {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.affcd-tier-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.affcd-tier-header h4 {
    margin: 0;
    color: #333;
}

.affcd-tier-actions {
    display: flex;
    gap: 5px;
}

.affcd-tier-details {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 15px;
}

.affcd-tier-info {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.affcd-tier-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.affcd-tier-value {
    display: block;
    font-size: 16px;
    font-weight: bold;
    color: #0073aa;
}

.affcd-no-tiers,
.affcd-no-calculations {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px dashed #e1e1e1;
}

.affcd-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.affcd-history-filters {
    display: flex;
    align-items: center;
    gap: 10px;
}

.affcd-commission-amount {
    font-weight: bold;
    color: #0073aa;
}

.affcd-simulation-form {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.affcd-simulation-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 30px;
}

.affcd-simulation-inputs h4,
.affcd-simulation-results h4 {
    margin: 0 0 20px 0;
    color: #333;
}

.affcd-range-inputs {
    display: flex;
    align-items: center;
    gap: 10px;
}

.affcd-range-inputs input {
    width: auto;
    flex: 1;
}

.affcd-chart-container {
    min-height: 300px;
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.affcd-chart-placeholder {
    color: #666;
    font-style: italic;
}

.affcd-simulation-summary {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
}

.affcd-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.affcd-report-actions {
    display: flex;
    gap: 10px;
}

.affcd-report-filters {
    background: #f8f9fa;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.affcd-filter-row {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.affcd-filter-row:last-child {
    margin-bottom: 0;
}

.affcd-filter-row label {
    font-weight: bold;
    min-width: 80px;
}

.affcd-filter-row input,
.affcd-filter-row select {
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.affcd-report-preview {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 25px;
    min-height: 300px;
}

.affcd-preview-placeholder {
    color: #666;
    font-style: italic;
    text-align: center;
    padding: 60px 20px;
}

.affcd-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
}

.affcd-modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.affcd-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #e1e1e1;
    background: #f8f9fa;
}

.affcd-modal-header h3 {
    margin: 0;
}

.affcd-modal-close {
    font-size: 28px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.affcd-modal-close:hover {
    color: #333;
}

.affcd-modal-body {
    padding: 25px;
}

.affcd-modal-body textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
}

.affcd-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px 25px;
    border-top: 1px solid #e1e1e1;
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .affcd-calculator-grid {
        grid-template-columns: 1fr;
    }
    
    .affcd-result-breakdown {
        grid-template-columns: 1fr;
    }
    
    .affcd-tiers-container {
        grid-template-columns: 1fr;
    }
    
    .affcd-tier-details<?php
/**
 * Commission Calculator Template
 * File: admin/templates/commission-calculator.php
 * Plugin: AffiliateWP Cross Domain Plugin Suite Master
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = affcd();
$calculator = $plugin->commission_calculator;

if (!$calculator) {
    wp_die(__('Commission Calculator component not available.', 'affiliatewp-cross-domain-plugin-suite'));
}

// Get tier configuration and recent calculations
$tier_config = $calculator->get_tier_configuration();
$recent_calculations = $calculator->get_recent_calculations(20);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Commission Calculator', 'affiliatewp-cross-domain-plugin-suite'); ?>
    </h1>
    
    <div class="page-title-action">
        <button id="bulk-calculate" class="button">
            <?php _e('Bulk Calculate', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </button>
        <button id="export-report" class="button">
            <?php _e('Export Report', 'affiliatewp-cross-domain-plugin-suite'); ?>
        </button>
    </div>

    <hr class="wp-header-end">

    <!-- Calculator Tabs -->
    <div class="affcd-calculator-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#single-calculation" class="nav-tab nav-tab-active"><?php _e('Single Calculation', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#tier-management" class="nav-tab"><?php _e('Tier Management', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#calculation-history" class="nav-tab"><?php _e('Calculation History', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#simulation-tool" class="nav-tab"><?php _e('Simulation Tool', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
            <a href="#reports" class="nav-tab"><?php _e('Reports', 'affiliatewp-cross-domain-plugin-suite'); ?></a>
        </nav>

        <!-- Single Calculation Tab -->
        <div id="single-calculation" class="tab-content active">
            <div class="affcd-calculator-grid">
                <!-- Calculator Form -->
                <div class="affcd-calculator-form">
                    <div class="affcd-form-card">
                        <h3><?php _e('Calculate Commission', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        <form id="commission-form">
                            <div class="affcd-form-row">
                                <label for="affiliate-select"><?php _e('Affiliate:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <select id="affiliate-select" name="affiliate_id" required>
                                    <option value=""><?php _e('Select affiliate...', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <?php
                                    $affiliates = $calculator->get_all_affiliates();
                                    foreach ($affiliates as $affiliate):
                                    ?>
                                    <option value="<?php echo esc_attr($affiliate->ID); ?>">
                                        <?php echo esc_html($affiliate->display_name . ' (' . $affiliate->user_email . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="affcd-form-row">
                                <label for="base-amount"><?php _e('Base Amount ($):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <input type="number" id="base-amount" name="base_amount" step="0.01" min="0" required>
                            </div>

                            <div class="affcd-form-row">
                                <label for="calculation-type"><?php _e('Calculation Type:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <select id="calculation-type" name="calculation_type" required>
                                    <option value=""><?php _e('Select type...', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="percentage"><?php _e('Percentage', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="flat_rate"><?php _e('Flat Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="tiered"><?php _e('Tiered Commission', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                    <option value="performance_based"><?php _e('Performance Based', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                </select>
                            </div>

                            <div class="affcd-form-row" id="custom-rate-row" style="display: none;">
                                <label for="custom-rate"><?php _e('Custom Rate (% or $):', 'affiliatewp-cross-domain-plugin-suite'); ?>):</label>
                                <input type="number" id="custom-rate" name="custom_rate" step="0.01" min="0">
                                <div class="affcd-field-note">
                                    <?php _e('Leave blank to use affiliate\'s default rate', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </div>
                            </div>

                            <div class="affcd-form-row" id="performance-metrics-row" style="display: none;">
                                <label for="performance-metrics"><?php _e('Performance Metrics:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <div class="affcd-metrics-grid">
                                    <div class="metric-input">
                                        <label for="total-sales"><?php _e('Total Sales ($):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                        <input type="number" id="total-sales" name="total_sales" step="0.01" min="0">
                                    </div>
                                    <div class="metric-input">
                                        <label for="referral-count"><?php _e('Total Referrals:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                        <input type="number" id="referral-count" name="referral_count" min="0">
                                    </div>
                                    <div class="metric-input">
                                        <label for="conversion-rate"><?php _e('Conversion Rate (%):', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                        <input type="number" id="conversion-rate" name="conversion_rate" step="0.01" min="0" max="100">
                                    </div>
                                </div>
                            </div>

                            <div class="affcd-form-row" id="tier-config-row" style="display: none;">
                                <label><?php _e('Tier Configuration:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <div class="affcd-tier-builder">
                                    <div class="tier-template" style="display: none;">
                                        <div class="tier-row">
                                            <input type="number" class="tier-min" placeholder="Min Amount" step="0.01" min="0">
                                            <input type="number" class="tier-max" placeholder="Max Amount" step="0.01" min="0">
                                            <input type="number" class="tier-rate" placeholder="Rate (%)" step="0.01" min="0" max="100">
                                            <button type="button" class="remove-tier"><?php _e('Remove', 'affiliatewp-cross-domain-plugin-suite'); ?></button>
                                        </div>
                                    </div>
                                    <div id="tier-rows"></div>
                                    <button type="button" id="add-tier" class="affcd-btn-secondary">
                                        <?php _e('Add Tier', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="affcd-form-actions">
                                <button type="submit" class="affcd-btn-primary">
                                    <?php _e('Calculate Commission', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                                <button type="button" id="clear-form" class="affcd-btn-secondary">
                                    <?php _e('Clear Form', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </div>

                            <div class="affcd-loading" id="calculation-loading" style="display: none;">
                                <div class="affcd-spinner"></div>
                                <span><?php _e('Calculating...', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Panel -->
                <div class="affcd-calculator-results">
                    <div class="affcd-results-card" id="calculation-results" style="display: none;">
                        <h3><?php _e('Commission Calculation Results', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        <div class="affcd-results-content">
                            <div class="result-summary">
                                <div class="result-item primary">
                                    <label><?php _e('Commission Amount:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <span id="commission-amount" class="amount">$0.00</span>
                                </div>
                                <div class="result-item">
                                    <label><?php _e('Commission Rate:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <span id="commission-rate">0%</span>
                                </div>
                                <div class="result-item">
                                    <label><?php _e('Base Amount:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <span id="base-amount-display">$0.00</span>
                                </div>
                            </div>

                            <div class="result-breakdown" id="breakdown-section" style="display: none;">
                                <h4><?php _e('Calculation Breakdown', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                                <div id="breakdown-content"></div>
                            </div>

                            <div class="result-metadata">
                                <div class="metadata-item">
                                    <label><?php _e('Affiliate:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <span id="selected-affiliate"></span>
                                </div>
                                <div class="metadata-item">
                                    <label><?php _e('Calculation Type:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <span id="calculation-type-display"></span>
                                </div>
                                <div class="metadata-item">
                                    <label><?php _e('Calculated:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                    <span id="calculation-timestamp"></span>
                                </div>
                            </div>

                            <div class="result-actions">
                                <button type="button" id="export-result" class="affcd-btn-secondary">
                                    <?php _e('Export Results', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                                <button type="button" id="save-calculation" class="affcd-btn-secondary">
                                    <?php _e('Save Calculation', 'affiliatewp-cross-domain-plugin-suite'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="affcd-help-card">
                        <h4><?php _e('How It Works', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <ul>
                            <li><?php _e('Select an affiliate to view their current commission rates', 'affiliatewp-cross-domain-plugin-suite'); ?></li>
                            <li><?php _e('Enter the base amount for calculation', 'affiliatewp-cross-domain-plugin-suite'); ?></li>
                            <li><?php _e('Choose calculation type or use custom rates', 'affiliatewp-cross-domain-plugin-suite'); ?></li>
                            <li><?php _e('Results include breakdown and exportable data', 'affiliatewp-cross-domain-plugin-suite'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Calculation Tab -->
        <div id="batch-calculation" class="tab-content">
            <div class="affcd-batch-calculator">
                <div class="affcd-form-card">
                    <h3><?php _e('Batch Commission Calculator', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                    
                    <div class="affcd-batch-options">
                        <div class="batch-option-row">
                            <label>
                                <input type="radio" name="batch-method" value="manual" checked>
                                <?php _e('Manual Entry', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </label>
                            <label>
                                <input type="radio" name="batch-method" value="csv-upload">
                                <?php _e('CSV Upload', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </label>
                        </div>
                    </div>

                    <!-- Manual Entry Section -->
                    <div id="manual-batch-entry" class="batch-entry-section">
                        <div class="batch-table-container">
                            <table id="batch-calculation-table" class="affcd-batch-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Affiliate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Base Amount', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Calculation Type', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Custom Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Commission', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="batch-table-body">
                                    <!-- Dynamic rows will be added here -->
                                </tbody>
                            </table>
                        </div>

                        <div class="batch-table-controls">
                            <button type="button" id="add-batch-row" class="affcd-btn-secondary">
                                <?php _e('Add Row', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                            <button type="button" id="calculate-batch" class="affcd-btn-primary">
                                <?php _e('Calculate All', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- CSV Upload Section -->
                    <div id="csv-batch-upload" class="batch-entry-section" style="display: none;">
                        <div class="csv-upload-area">
                            <div class="upload-dropzone" id="csv-dropzone">
                                <div class="dropzone-content">
                                    <div class="dropzone-icon">📄</div>
                                    <p><?php _e('Drop CSV file here or click to browse', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                                    <small><?php _e('Required columns: affiliate_id, base_amount, calculation_type', 'affiliatewp-cross-domain-plugin-suite'); ?></small>
                                </div>
                                <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                            </div>
                        </div>

                        <div class="csv-template-section">
                            <h4><?php _e('CSV Template', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                            <p><?php _e('Download a template file with the required column structure:', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
                            <button type="button" id="download-csv-template" class="affcd-btn-secondary">
                                <?php _e('Download Template', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Batch Results Section -->
                    <div id="batch-results-section" class="batch-results" style="display: none;">
                        <h4><?php _e('Batch Calculation Results', 'affiliatewp-cross-domain-plugin-suite'); ?></h4>
                        <div class="batch-summary">
                            <div class="summary-stat">
                                <label><?php _e('Total Calculations:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <span id="total-calculations">0</span>
                            </div>
                            <div class="summary-stat">
                                <label><?php _e('Total Commission:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <span id="total-commission">$0.00</span>
                            </div>
                            <div class="summary-stat">
                                <label><?php _e('Average Rate:', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                                <span id="average-rate">0%</span>
                            </div>
                        </div>

                        <div class="batch-results-table-container">
                            <table id="batch-results-table" class="affcd-results-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Affiliate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Base Amount', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Rate', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Commission', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                        <th><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="batch-results-body">
                                    <!-- Results will be populated here -->
                                </tbody>
                            </table>
                        </div>

                        <div class="batch-results-actions">
                            <button type="button" id="export-batch-results" class="affcd-btn-primary">
                                <?php _e('Export All Results', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                            <button type="button" id="save-batch-results" class="affcd-btn-secondary">
                                <?php _e('Save to History', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div id="calculation-history" class="tab-content">
            <div class="affcd-history-section">
                <div class="affcd-form-card">
                    <div class="history-header">
                        <h3><?php _e('Calculation History', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
                        <div class="history-controls">
                            <select id="history-filter" class="affcd-select">
                                <option value="all"><?php _e('All Calculations', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="single"><?php _e('Single Calculations', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                                <option value="batch"><?php _e('Batch Calculations', 'affiliatewp-cross-domain-plugin-suite'); ?></option>
                            </select>
                            <input type="date" id="history-date-from" class="affcd-input">
                            <input type="date" id="history-date-to" class="affcd-input">
                            <button type="button" id="filter-history" class="affcd-btn-secondary">
                                <?php _e('Filter', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="history-table-container">
                        <table id="calculation-history-table" class="affcd-history-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Type', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Affiliate(s)', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Total Commission', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Status', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                    <th><?php _e('Actions', 'affiliatewp-cross-domain-plugin-suite'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="history-table-body">
                                <!-- History entries will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <div class="history-pagination">
                        <div class="pagination-info">
                            <span id="history-showing-info"><?php _e('Showing 0 - 0 of 0 entries', 'affiliatewp-cross-domain-plugin-suite'); ?></span>
                        </div>
                        <div class="pagination-controls">
                            <button type="button" id="history-prev-page" class="affcd-btn-secondary" disabled>
                                <?php _e('Previous', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                            <span id="history-page-info">Page 1 of 1</span>
                            <button type="button" id="history-next-page" class="affcd-btn-secondary" disabled>
                                <?php _e('Next', 'affiliatewp-cross-domain-plugin-suite'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for calculation details -->
<div id="calculation-detail-modal" class="affcd-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Calculation Details', 'affiliatewp-cross-domain-plugin-suite'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="calculation-detail-content">
            <!-- Detail content will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="affcd-btn-secondary modal-close">
                <?php _e('Close', 'affiliatewp-cross-domain-plugin-suite'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize commission calculator
    if (typeof AffiliateWPCrossDomain !== 'undefined' && AffiliateWPCrossDomain.CommissionCalculator) {
        AffiliateWPCrossDomain.CommissionCalculator.init();
    }
});
</script>