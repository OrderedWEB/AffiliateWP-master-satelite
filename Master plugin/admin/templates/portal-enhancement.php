<?php
/**
 * Portal Enhancement Settings
 * File: admin/templates/portal-enhancement.php
 */

if (!defined('ABSPATH')) exit;

$settings = $args['settings'] ?? [];
?>

<div class="wrap">
    <h1><?php _e('Affiliate Portal Enhancement', 'affiliatewp-cross-domain-plugin-suite'); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('affcd_portal_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php _e('Enhanced Portal Enabled', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="affcd_portal_settings[enabled]" value="1" 
                            <?php checked(!empty($settings['enabled'])); ?>>
                        <?php _e('Enable enhanced affiliate portal features', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Marketing Materials', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="affcd_portal_settings[marketing_materials]" value="1" 
                            <?php checked(!empty($settings['marketing_materials'])); ?>>
                        <?php _e('Enable marketing materials generation', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e('Performance Dashboard', 'affiliatewp-cross-domain-plugin-suite'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="affcd_portal_settings[performance_dashboard]" value="1" 
                            <?php checked(!empty($settings['performance_dashboard'])); ?>>
                        <?php _e('Show enhanced performance metrics', 'affiliatewp-cross-domain-plugin-suite'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php _e('Portal Features', 'affiliatewp-cross-domain-plugin-suite'); ?></h2>
    <p><?php _e('The enhanced portal provides affiliates with advanced tools and insights.', 'affiliatewp-cross-domain-plugin-suite'); ?></p>
</div>