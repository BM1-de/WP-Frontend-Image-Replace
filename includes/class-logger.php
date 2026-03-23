<?php
/**
 * Logger for activity tracking and debug logging.
 *
 * @package FrontendImageReplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FIR_Logger {

	/**
	 * Log an image replacement to the database.
	 *
	 * @param array $data {
	 *     @type int    $post_id
	 *     @type string $post_title
	 *     @type int    $old_attachment_id
	 *     @type int    $new_attachment_id
	 *     @type string $old_url
	 *     @type string $new_url
	 *     @type string $user_info
	 * }
	 */
	public static function log_replacement( $data ) {
		if ( ! Frontend_Image_Replace::is_pro() ) {
			return;
		}

		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'fir_log',
			array(
				'post_id'           => $data['post_id'],
				'post_title'        => $data['post_title'],
				'old_attachment_id' => $data['old_attachment_id'],
				'new_attachment_id' => $data['new_attachment_id'],
				'old_url'           => $data['old_url'],
				'new_url'           => $data['new_url'],
				'user_info'         => $data['user_info'],
			),
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Create the log table. Called on plugin activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'fir_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			post_id BIGINT UNSIGNED NOT NULL,
			post_title VARCHAR(255) NOT NULL,
			old_attachment_id BIGINT UNSIGNED NOT NULL,
			new_attachment_id BIGINT UNSIGNED NOT NULL,
			old_url TEXT NOT NULL,
			new_url TEXT NOT NULL,
			user_info VARCHAR(255) NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the log table. Called on plugin uninstall.
	 */
	public static function drop_table() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fir_log" );
	}

	/**
	 * Delete all log entries.
	 */
	public static function clear_log() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}fir_log" );
	}

	/**
	 * Delete specific log entries by ID.
	 *
	 * @param array $ids Array of log entry IDs.
	 */
	public static function delete_entries( $ids ) {
		global $wpdb;

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fir_log WHERE id IN ({$placeholders})", $ids ) );
	}

	/**
	 * Log a debug message to the WordPress debug log.
	 * Only active when WP_DEBUG is true.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 */
	public static function debug( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$entry = '[FIR] [DEBUG] ' . $message;

		if ( ! empty( $context ) ) {
			$entry .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $entry );
	}

	/**
	 * Log an error message to the WordPress debug log.
	 * Only active when WP_DEBUG is true.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 */
	public static function error( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$entry = '[FIR] [ERROR] ' . $message;

		if ( ! empty( $context ) ) {
			$entry .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $entry );
	}
}
