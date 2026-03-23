<?php
/**
 * Frontend Image Replace
 *
 * @package           FrontendImageReplace
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Frontend Image Replace
 * Plugin URI:        https://wp-frontend-image-replace.com
 * Description:       Upload new images to the WordPress media library directly from the frontend and swap them into your content. Perfect for replacing demo/placeholder images during development.
 * Version:           1.1.1
 * Requires at least: 5.4
 * Requires PHP:      7.4
 * Author:            Baumgärtner Marketing GmbH
 * Author URI:        https://bm1.de
 * Text Domain:       frontend-image-replace
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FIR_VERSION', '1.1.1' );
define( 'FIR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FIR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FIR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Freemius SDK integration.
 */
if ( ! function_exists( 'fir_fs' ) ) {
	function fir_fs() {
		global $fir_fs;

		if ( ! isset( $fir_fs ) ) {
			require_once FIR_PLUGIN_DIR . 'freemius/start.php';

			$fir_fs = fs_dynamic_init( array(
				'id'                  => '26225',
				'slug'                => 'fir',
				'type'                => 'plugin',
				'public_key'          => 'pk_de1c8475c019bc9d62d113503f273',
				'is_premium'          => false,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'menu'                => array(
					'slug'   => 'fir-settings',
					'parent' => array(
						'slug' => 'options-general.php',
					),
				),
			) );
		}

		return $fir_fs;
	}

	fir_fs();
	do_action( 'fir_fs_loaded' );

	fir_fs()->add_action( 'after_uninstall', 'fir_cleanup' );
}

function fir_cleanup() {
	delete_option( 'fir_enabled' );
	delete_option( 'fir_access_token' );
	delete_option( 'fir_token_expiry' );

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fir_daily_count_%' OR option_name LIKE '_transient_timeout_fir_daily_count_%'" );

	FIR_Logger::drop_table();
}

require_once FIR_PLUGIN_DIR . 'includes/class-logger.php';
require_once FIR_PLUGIN_DIR . 'includes/class-admin.php';
require_once FIR_PLUGIN_DIR . 'includes/class-frontend.php';
require_once FIR_PLUGIN_DIR . 'includes/class-replacer.php';

/**
 * Main plugin class.
 */
final class Frontend_Image_Replace {

	/**
	 * @var Frontend_Image_Replace|null
	 */
	private static $instance = null;

	/**
	 * @return Frontend_Image_Replace
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		new FIR_Admin();
		new FIR_Frontend();
		new FIR_Replacer();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'frontend-image-replace',
			false,
			dirname( FIR_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Check if the current site has a Pro license.
	 *
	 * @return bool
	 */
	public static function is_pro() {
		return fir_fs()->is_plan( 'pro' );
	}

	/**
	 * Check if the feature is enabled globally.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( 'fir_enabled', '0' ) === '1';
	}

	/**
	 * Check if the current request has access to use the image replace feature.
	 *
	 * @return bool
	 */
	public static function current_user_can_replace() {
		// 1. Valid access token in URL — works independently of fir_enabled (Pro only).
		if ( self::is_pro() ) {
			$token = isset( $_GET['fir_token'] ) ? sanitize_text_field( wp_unslash( $_GET['fir_token'] ) ) : '';
			if ( ! empty( $token ) ) {
				$stored_token = get_option( 'fir_access_token' );
				if ( ! empty( $stored_token ) && hash_equals( $stored_token, $token ) ) {
					$expiry = get_option( 'fir_token_expiry', 0 );
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

		$key   = 'fir_daily_count_' . gmdate( 'Y-m-d' );
		$count = (int) get_transient( $key );

		return max( 0, 3 - $count );
	}

	/**
	 * Increment the daily replacement counter.
	 */
	public static function increment_daily_count() {
		$key   = 'fir_daily_count_' . gmdate( 'Y-m-d' );
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
		update_option( 'fir_access_token', $token );
		update_option( 'fir_token_expiry', $days > 0 ? time() + ( $days * DAY_IN_SECONDS ) : 0 );
		return $token;
	}

	/**
	 * Revoke the current access token.
	 */
	public static function revoke_token() {
		delete_option( 'fir_access_token' );
		delete_option( 'fir_token_expiry' );
	}
}

register_activation_hook( __FILE__, function () {
	add_option( 'fir_enabled', '0' );
	FIR_Logger::create_table();
} );

add_action( 'plugins_loaded', array( 'Frontend_Image_Replace', 'instance' ) );
