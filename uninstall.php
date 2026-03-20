<?php
/**
 * Uninstall handler.
 *
 * Removes all plugin options from the database.
 *
 * @package FrontendImageReplace
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'fir_enabled' );
delete_option( 'fir_access_token' );
delete_option( 'fir_token_expiry' );

// Clean up daily count transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fir_daily_count_%' OR option_name LIKE '_transient_timeout_fir_daily_count_%'" );
