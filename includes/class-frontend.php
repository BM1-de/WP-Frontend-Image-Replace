<?php
/**
 * Frontend script and style loading.
 *
 * @package BM1FrontendImageReplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BM1FIR_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Enqueue frontend assets if the user has access.
	 */
	public function maybe_enqueue() {
		if ( is_admin() ) {
			return;
		}

		// Skip frontend page builders / editors.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only checks for page builder context, no state change.
		if ( isset( $_GET['lc_action_launch_editing'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['ct_builder'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['brizy-edit'] ) ) {
			return;
		}

		if ( ! BM1FIR_Plugin::current_user_can_replace() ) {
			return;
		}

		wp_enqueue_style(
			'bm1fir-frontend',
			BM1FIR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			BM1FIR_VERSION
		);

		wp_enqueue_script(
			'bm1fir-frontend',
			BM1FIR_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			BM1FIR_VERSION,
			true
		);

		// Pass data to JavaScript.
		//#! pro
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only token retrieval, validated elsewhere.
		$token = isset( $_GET['bm1fir_token'] ) ? sanitize_text_field( wp_unslash( $_GET['bm1fir_token'] ) ) : '';
		//#! endpro

		$logo_url = '';
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_url( $custom_logo_id );
		}

		$script_data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bm1fir_nonce' ),
			'postId'  => get_queried_object_id(),
			'logoUrl' => $logo_url,
			'i18n'    => array(
				'replaceImage' => __( 'Replace Image', 'bm1-frontend-image-replace' ),
				'uploading'    => __( 'Uploading...', 'bm1-frontend-image-replace' ),
				'success'      => __( 'Replaced!', 'bm1-frontend-image-replace' ),
				'error'        => __( 'Error', 'bm1-frontend-image-replace' ),
				'toolbarText'  => __( 'Image Replace Mode — hover any image to replace it', 'bm1-frontend-image-replace' ),
				'notMedia'     => __( 'This image is not from the media library', 'bm1-frontend-image-replace' ),
			),
		);

		//#! pro
		$script_data['token'] = isset( $token ) ? $token : '';
		//#! endpro

		wp_localize_script( 'bm1fir-frontend', 'bm1firData', $script_data );
	}
}
