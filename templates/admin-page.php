<?php
/**
 * Admin Page Template for WCF Data Replacer
 *
 * @package WCFDR
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-search" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
        WCF Data Replacer
    </h1>
    
    <hr class="wp-header-end">
    
    <div id="wcfdr-admin-app">
        <!-- React application will be mounted here -->
        <div style="text-align: center; padding: 40px;">
            <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
            <p>Loading Data Replacer application...</p>
        </div>
    </div>
    
    <!-- Fallback content if JavaScript is disabled -->
    <noscript>
        <div class="notice notice-error">
            <p><strong>JavaScript Required:</strong> The WCF Data Replacer requires JavaScript to function properly. Please enable JavaScript in your browser.</p>
        </div>
    </noscript>
</div>

<style>
.wp-heading-inline .dashicons {
    vertical-align: middle;
    color: #0073aa;
}

#wcfdr-admin-app {
    margin-top: 20px;
}

.spinner.is-active {
    visibility: visible;
}
</style>

