<?php
/**
 * Settings Page Template
 *
 * @package WCFDR
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wcfdr_settings');
        do_settings_sections('wcfdr_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wcfdr_max_per_page"><?php esc_html_e('Maximum Results Per Page', 'wcf-data-replacer'); ?></label>
                </th>
                <td>
                    <input type="number" id="wcfdr_max_per_page" name="wcfdr_max_per_page" 
                           value="<?php echo esc_attr(get_option('wcfdr_max_per_page', 200)); ?>" 
                           min="10" max="500" class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Maximum number of search results to display per page.', 'wcf-data-replacer'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wcfdr_max_bulk_rows"><?php esc_html_e('Maximum Bulk Operation Rows', 'wcf-data-replacer'); ?></label>
                </th>
                <td>
                    <input type="number" id="wcfdr_max_bulk_rows" name="wcfdr_max_bulk_rows" 
                           value="<?php echo esc_attr(get_option('wcfdr_max_bulk_rows', 5000)); ?>" 
                           min="100" max="10000" class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Maximum number of rows that can be processed in a single bulk operation.', 'wcf-data-replacer'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wcfdr_regex_timeout"><?php esc_html_e('Regex Timeout (ms)', 'wcf-data-replacer'); ?></label>
                </th>
                <td>
                    <input type="number" id="wcfdr_max_bulk_rows" name="wcfdr_regex_timeout" 
                           value="<?php echo esc_attr(get_option('wcfdr_regex_timeout', 5000)); ?>" 
                           min="1000" max="30000" class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Maximum time in milliseconds to allow regex operations to run.', 'wcf-data-replacer'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wcfdr_backup_retention"><?php esc_html_e('Backup Retention Count', 'wcf-data-replacer'); ?></label>
                </th>
                <td>
                    <input type="number" id="wcfdr_backup_retention" name="wcfdr_backup_retention" 
                           value="<?php echo esc_attr(get_option('wcfdr_backup_retention', 10)); ?>" 
                           min="1" max="100" class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Number of backup revisions to keep per meta key/post combination.', 'wcf-datareplacer'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>
