<?php
/**
 * Frontend script and style loading.
 *
 * @package FrontendImageReplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FIR_Frontend {

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
		if ( isset( $_GET['lc_action_launch_editing'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['ct_builder'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['brizy-edit'] ) ) {
			return;
		}

		if ( ! Frontend_Image_Replace::current_user_can_replace() ) {
			return;
		}

		wp_enqueue_style(
			'fir-frontend',
			FIR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			FIR_VERSION
		);

		wp_enqueue_script(
			'fir-frontend',
			FIR_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			FIR_VERSION,
			true
		);

		// Pass data to JavaScript.
		$token     = isset( $_GET['fir_token'] ) ? sanitize_text_field( wp_unslash( $_GET['fir_token'] ) ) : '';
		$remaining = Frontend_Image_Replace::get_remaining_today();
		$is_pro    = Frontend_Image_Replace::is_pro();

		$logo_url = '';
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_url( $custom_logo_id );
		}

		wp_localize_script( 'fir-frontend', 'firData', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'fir_nonce' ),
			'token'      => $token,
			'postId'     => get_queried_object_id(),
			'remaining'  => $remaining,
			'isPro'      => $is_pro,
			'logoUrl'    => $logo_url,
			'upgradeUrl' => $is_pro ? '' : fir_fs()->get_upgrade_url(),
			'i18n'       => array(
				'replaceImage'  => __( 'Replace Image', 'frontend-image-replace' ),
				'uploading'     => __( 'Uploading...', 'frontend-image-replace' ),
				'success'       => __( 'Replaced!', 'frontend-image-replace' ),
				'error'         => __( 'Error', 'frontend-image-replace' ),
				'toolbarText'   => __( 'Image Replace Mode — hover any image to replace it', 'frontend-image-replace' ),
				'notMedia'      => __( 'This image is not from the media library', 'frontend-image-replace' ),
				'limitReached'  => __( 'Daily limit reached', 'frontend-image-replace' ),
				'remaining'     => __( 'replacements remaining today', 'frontend-image-replace' ),
				'upgradePro'    => __( 'Upgrade to Pro', 'frontend-image-replace' ),
				'unlimitedPro'  => __( 'Unlimited replacements with Pro', 'frontend-image-replace' ),
			),
		) );
	}
}
