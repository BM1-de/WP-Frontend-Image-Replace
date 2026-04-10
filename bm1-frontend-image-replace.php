<?php
/**
 * BM1 Frontend Image Replace
 *
 * @package           BM1FrontendImageReplace
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       BM1 Frontend Image Replace
 * Plugin URI:        https://wp-frontend-image-replace.com
 * Description:       Upload new images to the WordPress media library directly from the frontend and swap them into your content. Perfect for replacing demo/placeholder images during development.
 * Version:           1.2.0
 * Requires at least: 5.4
 * Requires PHP:      7.4
 * Author:            Baumgärtner Marketing GmbH
 * Author URI:        https://bm1.de
 * Text Domain:       bm1-frontend-image-replace
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BM1FIR_VERSION', '1.2.0' );
define( 'BM1FIR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BM1FIR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BM1FIR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Freemius SDK integration.
 */
if ( ! function_exists( 'bm1_fs' ) ) {
	function bm1_fs() {
		global $bm1_fs;

		if ( ! isset( $bm1_fs ) ) {
			require_once BM1FIR_PLUGIN_DIR . 'freemius/start.php';

			$bm1_fs = fs_dynamic_init( array(
				'id'                  => '26225',
				'slug'                => 'bm1fir',
				'premium_slug'        => 'bm1fir-premium',
				'type'                => 'plugin',
				'public_key'          => 'pk_de1c8475c019bc9d62d113503f273',
				'is_premium'          => true,
				'premium_suffix'      => 'Pro',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => true,
				'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'menu'                => array(
					'slug'    => 'bm1fir-settings',
					'parent'  => array(
						'slug' => 'options-general.php',
					),
					'support' => false,
				),
			) );
		}

		return $bm1_fs;
	}

	bm1_fs();
	do_action( 'bm1_fs_loaded' );

	bm1_fs()->add_action( 'after_uninstall', 'bm1fir_cleanup' );
}

function bm1fir_cleanup() {
	delete_option( 'bm1fir_enabled' );
	delete_option( 'bm1fir_access_token' );
	delete_option( 'bm1fir_token_expiry' );

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_bm1fir_daily_count_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_bm1fir_daily_count_' ) . '%'
	) );

	BM1FIR_Logger::drop_table();
}

if ( file_exists( BM1FIR_PLUGIN_DIR . 'includes/class-logger__premium_only.php' ) ) {
	require_once BM1FIR_PLUGIN_DIR . 'includes/class-logger__premium_only.php';
} elseif ( ! class_exists( 'BM1FIR_Logger' ) ) {
	// Stub for free version — logger is premium-only.
	class BM1FIR_Logger {
		public static function log_replacement( $data ) {}
		public static function create_table() {}
		public static function drop_table() {}
		public static function clear_log() {}
		public static function delete_entries( $ids ) {}
		public static function debug( $message, $context = array() ) {}
		public static function error( $message, $context = array() ) {}
	}
}
require_once BM1FIR_PLUGIN_DIR . 'includes/class-admin.php';
require_once BM1FIR_PLUGIN_DIR . 'includes/class-frontend.php';
require_once BM1FIR_PLUGIN_DIR . 'includes/class-replacer.php';

/**
 * Main plugin class.
 */
final class BM1_Frontend_Image_Replace {

	/**
	 * @var BM1_Frontend_Image_Replace|null
	 */
	private static $instance = null;

	/**
	 * @return BM1_Frontend_Image_Replace
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new BM1FIR_Admin();
		new BM1FIR_Frontend();
		new BM1FIR_Replacer();
	}

	/**
	 * Check if the current site has a Pro license.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return bm1_fs()->is_plan( 'pro' );
	}

	/**
	 * Check if the feature is enabled globally.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( 'bm1fir_enabled', '0' ) === '1';
	}

	/**
	 * Check if the current request has access to use the image replace feature.
	 *
	 * @return bool
	 */
	public static function current_user_can_replace() {
		// 1. Valid access token in URL — works independently of bm1fir_enabled (Pro only).
		if ( self::is_pro() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token-based auth, no state change.
			$token = isset( $_GET['bm1fir_token'] ) ? sanitize_text_field( wp_unslash( $_GET['bm1fir_token'] ) ) : '';
			if ( ! empty( $token ) ) {
				$stored_token = get_option( 'bm1fir_access_token' );
				if ( ! empty( $stored_token ) && hash_equals( $stored_token, $token ) ) {
					$expiry = get_option( 'bm1fir_token_expiry', 0 );
					if ( empty( $expiry ) || time() < (int) $expiry ) {
						return true;
					}
				}
			}
		}

		// 2. When globally enabled: overlay for everyone.
		if ( self::is_enabled() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the number of replacements remaining today.
	 *
	 * @return int -1 means unlimited (Pro).
	 */
	public static function get_remaining_today() {
		if ( self::is_pro() ) {
			return -1;
		}

		$key   = 'bm1fir_daily_count_' . gmdate( 'Y-m-d' );
		$count = (int) get_transient( $key );

		return max( 0, 3 - $count );
	}

	/**
	 * Increment the daily replacement counter.
	 */
	public static function increment_daily_count() {
		$key   = 'bm1fir_daily_count_' . gmdate( 'Y-m-d' );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );
	}

	/**
	 * Generate a new access token (Pro only).
	 *
	 * @param int $days Days until expiry. 0 = no expiry.
	 * @return string
	 */
	public static function generate_token( $days = 7 ) {
		$token = wp_generate_password( 32, false );
		update_option( 'bm1fir_access_token', $token );
		update_option( 'bm1fir_token_expiry', $days > 0 ? time() + ( $days * DAY_IN_SECONDS ) : 0 );
		return $token;
	}

	/**
	 * Revoke the current access token.
	 */
	public static function revoke_token() {
		delete_option( 'bm1fir_access_token' );
		delete_option( 'bm1fir_token_expiry' );
	}
}

register_activation_hook( __FILE__, function () {
	add_option( 'bm1fir_enabled', '0' );
	BM1FIR_Logger::create_table();
} );

add_action( 'plugins_loaded', array( 'BM1_Frontend_Image_Replace', 'instance' ) );
