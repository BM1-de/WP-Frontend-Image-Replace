<?php
/**
 * Admin settings page.
 *
 * @package BM1FrontendImageReplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BM1FIR_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_menu', array( $this, 'add_log_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_log_actions' ) );
		add_filter( 'plugin_action_links_' . BM1FIR_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'BM1 Frontend Image Replace', 'bm1-frontend-image-replace' ),
			__( 'BM1 Frontend Image Replace', 'bm1-frontend-image-replace' ),
			'manage_options',
			'bm1fir-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'bm1fir_settings', 'bm1fir_enabled', array(
			'type'              => 'string',
			'sanitize_callback' => function ( $value ) {
				return $value === '1' ? '1' : '0';
			},
			'default'           => '0',
		) );
	}

	/**
	 * Handle token generation and revocation.
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Deactivate license key.
		if ( isset( $_POST['bm1fir_deactivate_license'] ) && check_admin_referer( 'bm1fir_license_action' ) ) {
			if ( function_exists( 'bm1_fs' ) ) {
				bm1_fs()->delete_account_event();
			}
			wp_safe_redirect( admin_url( 'options-general.php?page=bm1fir-settings&license_deactivated=1' ) );
			exit;
		}

		// Activate license key.
		if ( isset( $_POST['bm1fir_activate_license'] ) && check_admin_referer( 'bm1fir_license_action' ) ) {
			$license_key = isset( $_POST['bm1fir_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bm1fir_license_key'] ) ) : '';
			if ( ! empty( $license_key ) && function_exists( 'bm1_fs' ) ) {
				try {
					$result = bm1_fs()->activate_migrated_license( $license_key );
					if ( is_object( $result ) && isset( $result->error ) ) {
						set_transient( 'bm1fir_license_error', $result->error, 60 );
					} else {
						set_transient( 'bm1fir_license_success', true, 60 );
					}
				} catch ( Exception $e ) {
					set_transient( 'bm1fir_license_error', $e->getMessage(), 60 );
				}
			}
			wp_safe_redirect( admin_url( 'options-general.php?page=bm1fir-settings' ) );
			exit;
		}

		// Generate token (Pro only).
		if ( isset( $_POST['bm1fir_generate_token'] ) && check_admin_referer( 'bm1fir_token_action' ) ) {
			if ( ! BM1_Frontend_Image_Replace::is_pro() ) {
				wp_safe_redirect( admin_url( 'options-general.php?page=bm1fir-settings' ) );
				exit;
			}
			$days = isset( $_POST['bm1fir_token_days'] ) ? absint( $_POST['bm1fir_token_days'] ) : 7;
			$token = BM1_Frontend_Image_Replace::generate_token( $days );
			set_transient( 'bm1fir_new_token', $token, 60 );
			wp_safe_redirect( admin_url( 'options-general.php?page=bm1fir-settings&token_generated=1' ) );
			exit;
		}

		// Revoke token.
		if ( isset( $_POST['bm1fir_revoke_token'] ) && check_admin_referer( 'bm1fir_token_action' ) ) {
			BM1_Frontend_Image_Replace::revoke_token();
			wp_safe_redirect( admin_url( 'options-general.php?page=bm1fir-settings&token_revoked=1' ) );
			exit;
		}
	}

	/**
	 * Add Settings link in plugin list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=bm1fir-settings' ),
			__( 'Settings', 'bm1-frontend-image-replace' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		$is_enabled   = BM1_Frontend_Image_Replace::is_enabled();
		$is_pro       = BM1_Frontend_Image_Replace::is_pro();
		$stored_token = get_option( 'bm1fir_access_token' );
		$token_expiry = get_option( 'bm1fir_token_expiry', 0 );
		$new_token    = get_transient( 'bm1fir_new_token' );

		if ( $new_token ) {
			delete_transient( 'bm1fir_new_token' );
		}

		$token_expired = ! empty( $token_expiry ) && time() >= (int) $token_expiry;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BM1 Frontend Image Replace', 'bm1-frontend-image-replace' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param for admin notice display. ?>
			<?php if ( isset( $_GET['token_generated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Access link generated successfully.', 'bm1-frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param for admin notice display. ?>
			<?php if ( isset( $_GET['token_revoked'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Access link revoked.', 'bm1-frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param for admin notice display. ?>
			<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'bm1-frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Enable/Disable -->
			<form method="post" action="options.php">
				<?php settings_fields( 'bm1fir_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Enable Image Replace', 'bm1-frontend-image-replace' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="bm1fir_enabled" value="1" <?php checked( $is_enabled ); ?>>
								<?php esc_html_e( 'Show the image replace overlay on the frontend', 'bm1-frontend-image-replace' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, all visitors will see a replace button when hovering over images.', 'bm1-frontend-image-replace' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php if ( ! $is_pro ) : ?>
				<div class="notice notice-info inline" style="margin: 10px 0 20px;">
					<p>
						<?php
						printf(
							/* translators: %s: upgrade link */
							esc_html__( 'Upgrade to Pro for guest access links and activity log. %s', 'bm1-frontend-image-replace' ),
							'<a href="' . esc_url( bm1_fs()->get_upgrade_url() ) . '">' . esc_html__( 'Upgrade to Pro', 'bm1-frontend-image-replace' ) . '</a>'
						);
						?>
					</p>
				</div>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>

			<hr>

			<!-- Access Link -->
			<h2>
				<?php esc_html_e( 'Guest Access Link', 'bm1-frontend-image-replace' ); ?>
				<?php if ( ! $is_pro ) : ?>
					<span style="background: #dba617; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; vertical-align: middle; margin-left: 8px;">PRO</span>
				<?php endif; ?>
			</h2>

			<?php if ( $is_pro ) : ?>
				<p class="description">
					<?php esc_html_e( 'Generate a temporary link that allows anyone to replace images without logging in. Useful for sharing with clients or team members during development.', 'bm1-frontend-image-replace' ); ?>
				</p>

				<?php if ( $stored_token && ! $token_expired ) : ?>
					<div class="notice notice-success inline" style="margin: 15px 0;">
						<p>
							<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
							<strong>
							<?php
							if ( ! empty( $token_expiry ) ) {
								printf(
									/* translators: %s: expiry date */
									esc_html__( 'Access link active. Expires: %s', 'bm1-frontend-image-replace' ),
									esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $token_expiry ) )
								);
							} else {
								esc_html_e( 'Access link active (no expiry).', 'bm1-frontend-image-replace' );
							}
							?>
							</strong>
						</p>
						<p>
							<input type="text" readonly
								value="<?php echo esc_url( home_url( '?bm1fir_token=' . $stored_token ) ); ?>"
								style="width: 100%; max-width: 600px; font-family: monospace; font-size: 13px;"
								id="bm1fir-access-link"
								onclick="this.select();">
						</p>
						<p>
							<button type="button" class="button" onclick="
								var input = document.getElementById('bm1fir-access-link');
								input.select();
								document.execCommand('copy');
								this.textContent = '<?php echo esc_js( __( 'Copied!', 'bm1-frontend-image-replace' ) ); ?>';
							">
								<?php esc_html_e( 'Copy Link', 'bm1-frontend-image-replace' ); ?>
							</button>
						</p>
						<p class="description">
							<?php esc_html_e( 'Append ?bm1fir_token=... to any page URL on this site to enable image replace mode.', 'bm1-frontend-image-replace' ); ?>
						</p>
					</div>
				<?php elseif ( $stored_token && $token_expired ) : ?>
					<p>
						<span class="dashicons dashicons-warning" style="color: #d63638;"></span>
						<?php esc_html_e( 'The access link has expired.', 'bm1-frontend-image-replace' ); ?>
					</p>
				<?php else : ?>
					<p>
						<?php esc_html_e( 'No access link generated yet.', 'bm1-frontend-image-replace' ); ?>
					</p>
				<?php endif; ?>

				<form method="post" style="margin-top: 10px;">
					<?php wp_nonce_field( 'bm1fir_token_action' ); ?>

					<p>
						<label for="bm1fir_token_days">
							<?php esc_html_e( 'Expires after:', 'bm1-frontend-image-replace' ); ?>
						</label>
						<select name="bm1fir_token_days" id="bm1fir_token_days">
							<option value="1"><?php esc_html_e( '1 day', 'bm1-frontend-image-replace' ); ?></option>
							<option value="7" selected><?php esc_html_e( '7 days', 'bm1-frontend-image-replace' ); ?></option>
							<option value="30"><?php esc_html_e( '30 days', 'bm1-frontend-image-replace' ); ?></option>
							<option value="0"><?php esc_html_e( 'Never', 'bm1-frontend-image-replace' ); ?></option>
						</select>
					</p>

					<p>
						<button type="submit" name="bm1fir_generate_token" class="button button-primary">
							<?php
							echo $stored_token
								? esc_html__( 'Regenerate Access Link', 'bm1-frontend-image-replace' )
								: esc_html__( 'Generate Access Link', 'bm1-frontend-image-replace' );
							?>
						</button>

						<?php if ( $stored_token ) : ?>
							<button type="submit" name="bm1fir_revoke_token" class="button" style="color: #d63638;">
								<?php esc_html_e( 'Revoke', 'bm1-frontend-image-replace' ); ?>
							</button>
						<?php endif; ?>
					</p>
				</form>

			<?php else : ?>
				<div class="notice notice-warning inline" style="margin: 15px 0;">
					<p>
						<?php
						printf(
							/* translators: %s: upgrade link */
							esc_html__( 'Guest access links are a Pro feature. %s to share temporary image replace links with clients and team members.', 'bm1-frontend-image-replace' ),
							'<a href="' . esc_url( bm1_fs()->get_upgrade_url() ) . '"><strong>' . esc_html__( 'Upgrade to Pro', 'bm1-frontend-image-replace' ) . '</strong></a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<!-- License -->
			<hr>
			<?php if ( ! $is_pro ) : ?>
			<h2><?php esc_html_e( 'Activate Pro License', 'bm1-frontend-image-replace' ); ?></h2>

			<?php if ( get_transient( 'bm1fir_license_success' ) ) : ?>
				<?php delete_transient( 'bm1fir_license_success' ); ?>
				<div class="notice notice-success inline" style="margin: 10px 0;">
					<p><?php esc_html_e( 'License activated successfully! Please reload the page.', 'bm1-frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			$license_error = get_transient( 'bm1fir_license_error' );
			if ( $license_error ) :
				delete_transient( 'bm1fir_license_error' );
			?>
				<div class="notice notice-error inline" style="margin: 10px 0;">
					<p><?php echo esc_html( $license_error ); ?></p>
				</div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Already have a license key? Enter it below to activate Pro features.', 'bm1-frontend-image-replace' ); ?>
			</p>
			<form method="post" style="margin-top: 12px;">
				<?php wp_nonce_field( 'bm1fir_license_action' ); ?>
				<div style="display: flex; gap: 8px; align-items: center;">
					<input type="text" name="bm1fir_license_key" placeholder="sk_..." style="width: 350px;" required>
					<button type="submit" name="bm1fir_activate_license" value="1" class="button button-primary">
						<?php esc_html_e( 'Activate License', 'bm1-frontend-image-replace' ); ?>
					</button>
				</div>
			</form>

			<?php else : ?>
			<h2><?php esc_html_e( 'License', 'bm1-frontend-image-replace' ); ?></h2>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param for admin notice display. ?>
			<?php if ( isset( $_GET['license_deactivated'] ) ) : ?>
				<div class="notice notice-success inline" style="margin: 10px 0;">
					<p><?php esc_html_e( 'License deactivated.', 'bm1-frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
				<?php esc_html_e( 'Pro license active.', 'bm1-frontend-image-replace' ); ?>
			</p>
			<form method="post" style="margin-top: 8px;">
				<?php wp_nonce_field( 'bm1fir_license_action' ); ?>
				<button type="submit" name="bm1fir_deactivate_license" value="1" class="button button-link" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to deactivate the license?', 'bm1-frontend-image-replace' ); ?>');">
					<?php esc_html_e( 'Deactivate License', 'bm1-frontend-image-replace' ); ?>
				</button>
			</form>
			<?php endif; ?>

			<hr>

			<!-- Info -->
			<h2><?php esc_html_e( 'How It Works', 'bm1-frontend-image-replace' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Enable the feature above and visit any page on your site.', 'bm1-frontend-image-replace' ); ?></li>
				<li><?php esc_html_e( 'Hover over any image to see the replace overlay.', 'bm1-frontend-image-replace' ); ?></li>
				<li><?php esc_html_e( 'Click to select a new image from your computer.', 'bm1-frontend-image-replace' ); ?></li>
				<li><?php esc_html_e( 'The new image is uploaded to the media library and all references in your content are updated.', 'bm1-frontend-image-replace' ); ?></li>
			</ol>
			<p class="description">
				<?php esc_html_e( 'The original image remains in the media library and is not deleted.', 'bm1-frontend-image-replace' ); ?>
			</p>
			<p class="description" style="margin-top: 12px;">
				<?php
				printf(
					/* translators: %s: support website URL */
					esc_html__( 'Need help? Visit %s', 'bm1-frontend-image-replace' ),
					'<a href="https://wp-frontend-image-replace.com" target="_blank" rel="noopener noreferrer">wp-frontend-image-replace.com</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register the log page under Tools.
	 */
	public function add_log_page() {
		if ( ! BM1_Frontend_Image_Replace::is_pro() ) {
			return;
		}

		add_management_page(
			__( 'BM1 Frontend Image Replace Log', 'bm1-frontend-image-replace' ),
			__( 'Image Replace Log', 'bm1-frontend-image-replace' ),
			'manage_options',
			'bm1fir-log',
			array( $this, 'render_log_page' )
		);
	}

	/**
	 * Handle log page actions (clear all, bulk remove).
	 */
	public function handle_log_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Clear entire log.
		if ( isset( $_POST['bm1fir_clear_log'] ) && check_admin_referer( 'bulk-bm1fir_log_entries' ) ) {
			BM1FIR_Logger::clear_log();
			wp_safe_redirect( admin_url( 'tools.php?page=bm1fir-log&cleared=1' ) );
			exit;
		}

		// Bulk remove selected entries.
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( 'remove' === $action && ! empty( $_POST['log_ids'] ) && check_admin_referer( 'bulk-bm1fir_log_entries' ) ) {
			$ids = array_map( 'absint', $_POST['log_ids'] );
			BM1FIR_Logger::delete_entries( $ids );
			wp_safe_redirect( admin_url( 'tools.php?page=bm1fir-log&removed=' . count( $ids ) ) );
			exit;
		}
	}

	/**
	 * Render the log page.
	 */
	public function render_log_page() {
		if ( ! file_exists( BM1FIR_PLUGIN_DIR . 'includes/class-log-list-table__premium_only.php' ) ) {
			return;
		}
		require_once BM1FIR_PLUGIN_DIR . 'includes/class-log-list-table__premium_only.php';

		$table = new BM1FIR_Log_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BM1 Frontend Image Replace Log', 'bm1-frontend-image-replace' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param for admin notice display. ?>
			<?php if ( isset( $_GET['cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Log cleared.', 'bm1-frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param for admin notice display. ?>
			<?php if ( isset( $_GET['removed'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of removed entries */
							esc_html( _n( '%d entry removed.', '%d entries removed.', absint( $_GET['removed'] ), 'bm1-frontend-image-replace' ) ),
							absint( $_GET['removed'] )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
