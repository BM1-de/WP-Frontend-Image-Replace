<?php
/**
 * Admin settings page.
 *
 * @package FrontendImageReplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FIR_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_filter( 'plugin_action_links_' . FIR_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Frontend Image Replace', 'frontend-image-replace' ),
			__( 'Frontend Image Replace', 'frontend-image-replace' ),
			'manage_options',
			'fir-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'fir_settings', 'fir_enabled', array(
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

		// Generate token (Pro only).
		if ( isset( $_POST['fir_generate_token'] ) && check_admin_referer( 'fir_token_action' ) ) {
			if ( ! Frontend_Image_Replace::is_pro() ) {
				wp_safe_redirect( admin_url( 'options-general.php?page=fir-settings' ) );
				exit;
			}
			$days = isset( $_POST['fir_token_days'] ) ? absint( $_POST['fir_token_days'] ) : 7;
			$token = Frontend_Image_Replace::generate_token( $days );
			set_transient( 'fir_new_token', $token, 60 );
			wp_safe_redirect( admin_url( 'options-general.php?page=fir-settings&token_generated=1' ) );
			exit;
		}

		// Revoke token.
		if ( isset( $_POST['fir_revoke_token'] ) && check_admin_referer( 'fir_token_action' ) ) {
			Frontend_Image_Replace::revoke_token();
			wp_safe_redirect( admin_url( 'options-general.php?page=fir-settings&token_revoked=1' ) );
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
			admin_url( 'options-general.php?page=fir-settings' ),
			__( 'Settings', 'frontend-image-replace' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		$is_enabled   = Frontend_Image_Replace::is_enabled();
		$is_pro       = Frontend_Image_Replace::is_pro();
		$stored_token = get_option( 'fir_access_token' );
		$token_expiry = get_option( 'fir_token_expiry', 0 );
		$new_token    = get_transient( 'fir_new_token' );

		if ( $new_token ) {
			delete_transient( 'fir_new_token' );
		}

		$token_expired = ! empty( $token_expiry ) && time() >= (int) $token_expiry;

		// Collect installation metadata for Zammad form.
		global $wp_version;
		$meta_lines = array(
			'Site URL: ' . home_url(),
			'WordPress: ' . $wp_version,
			'PHP: ' . phpversion(),
			'Plugin: Frontend Image Replace ' . FIR_VERSION,
			'Plan: ' . ( $is_pro ? 'Pro' : 'Free' ),
			'Multisite: ' . ( is_multisite() ? 'Ja' : 'Nein' ),
			'Theme: ' . wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'Locale: ' . get_locale(),
		);
		$meta_text = implode( "\n", $meta_lines );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Frontend Image Replace', 'frontend-image-replace' ); ?></h1>

			<?php if ( isset( $_GET['token_generated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Access link generated successfully.', 'frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['token_revoked'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Access link revoked.', 'frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'frontend-image-replace' ); ?></p>
				</div>
			<?php endif; ?>

			<div style="display: flex; gap: 24px; align-items: flex-start;">

			<!-- Main Content -->
			<div style="flex: 1; min-width: 0;">

			<!-- Enable/Disable -->
			<form method="post" action="options.php">
				<?php settings_fields( 'fir_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Enable Image Replace', 'frontend-image-replace' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="fir_enabled" value="1" <?php checked( $is_enabled ); ?>>
								<?php esc_html_e( 'Show the image replace overlay on the frontend', 'frontend-image-replace' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, all visitors will see a replace button when hovering over images.', 'frontend-image-replace' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php if ( ! $is_pro ) : ?>
				<div class="notice notice-info inline" style="margin: 10px 0 20px;">
					<p>
						<strong><?php esc_html_e( 'Free Plan:', 'frontend-image-replace' ); ?></strong>
						<?php
						printf(
							/* translators: %d: daily limit, %s: upgrade link */
							esc_html__( 'Limited to %d image replacements per day. %s for unlimited replacements.', 'frontend-image-replace' ),
							3,
							'<a href="' . esc_url( fir_fs()->get_upgrade_url() ) . '">' . esc_html__( 'Upgrade to Pro', 'frontend-image-replace' ) . '</a>'
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
				<?php esc_html_e( 'Guest Access Link', 'frontend-image-replace' ); ?>
				<?php if ( ! $is_pro ) : ?>
					<span style="background: #dba617; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; vertical-align: middle; margin-left: 8px;">PRO</span>
				<?php endif; ?>
			</h2>

			<?php if ( $is_pro ) : ?>
				<p class="description">
					<?php esc_html_e( 'Generate a temporary link that allows anyone to replace images without logging in. Useful for sharing with clients or team members during development.', 'frontend-image-replace' ); ?>
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
									esc_html__( 'Access link active. Expires: %s', 'frontend-image-replace' ),
									esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $token_expiry ) )
								);
							} else {
								esc_html_e( 'Access link active (no expiry).', 'frontend-image-replace' );
							}
							?>
							</strong>
						</p>
						<p>
							<input type="text" readonly
								value="<?php echo esc_url( home_url( '?fir_token=' . $stored_token ) ); ?>"
								style="width: 100%; max-width: 600px; font-family: monospace; font-size: 13px;"
								id="fir-access-link"
								onclick="this.select();">
						</p>
						<p>
							<button type="button" class="button" onclick="
								var input = document.getElementById('fir-access-link');
								input.select();
								document.execCommand('copy');
								this.textContent = '<?php echo esc_js( __( 'Copied!', 'frontend-image-replace' ) ); ?>';
							">
								<?php esc_html_e( 'Copy Link', 'frontend-image-replace' ); ?>
							</button>
						</p>
						<p class="description">
							<?php esc_html_e( 'Append ?fir_token=... to any page URL on this site to enable image replace mode.', 'frontend-image-replace' ); ?>
						</p>
					</div>
				<?php elseif ( $stored_token && $token_expired ) : ?>
					<p>
						<span class="dashicons dashicons-warning" style="color: #d63638;"></span>
						<?php esc_html_e( 'The access link has expired.', 'frontend-image-replace' ); ?>
					</p>
				<?php else : ?>
					<p>
						<?php esc_html_e( 'No access link generated yet.', 'frontend-image-replace' ); ?>
					</p>
				<?php endif; ?>

				<form method="post" style="margin-top: 10px;">
					<?php wp_nonce_field( 'fir_token_action' ); ?>

					<p>
						<label for="fir_token_days">
							<?php esc_html_e( 'Expires after:', 'frontend-image-replace' ); ?>
						</label>
						<select name="fir_token_days" id="fir_token_days">
							<option value="1"><?php esc_html_e( '1 day', 'frontend-image-replace' ); ?></option>
							<option value="7" selected><?php esc_html_e( '7 days', 'frontend-image-replace' ); ?></option>
							<option value="30"><?php esc_html_e( '30 days', 'frontend-image-replace' ); ?></option>
							<option value="0"><?php esc_html_e( 'Never', 'frontend-image-replace' ); ?></option>
						</select>
					</p>

					<p>
						<button type="submit" name="fir_generate_token" class="button button-primary">
							<?php
							echo $stored_token
								? esc_html__( 'Regenerate Access Link', 'frontend-image-replace' )
								: esc_html__( 'Generate Access Link', 'frontend-image-replace' );
							?>
						</button>

						<?php if ( $stored_token ) : ?>
							<button type="submit" name="fir_revoke_token" class="button" style="color: #d63638;">
								<?php esc_html_e( 'Revoke', 'frontend-image-replace' ); ?>
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
							esc_html__( 'Guest access links are a Pro feature. %s to share temporary image replace links with clients and team members.', 'frontend-image-replace' ),
							'<a href="' . esc_url( fir_fs()->get_upgrade_url() ) . '"><strong>' . esc_html__( 'Upgrade to Pro', 'frontend-image-replace' ) . '</strong></a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<hr>

			<!-- Info -->
			<h2><?php esc_html_e( 'How It Works', 'frontend-image-replace' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Enable the feature above and visit any page on your site.', 'frontend-image-replace' ); ?></li>
				<li><?php esc_html_e( 'Hover over any image to see the replace overlay.', 'frontend-image-replace' ); ?></li>
				<li><?php esc_html_e( 'Click to select a new image from your computer.', 'frontend-image-replace' ); ?></li>
				<li><?php esc_html_e( 'The new image is uploaded to the media library and all references in your content are updated.', 'frontend-image-replace' ); ?></li>
			</ol>
			<p class="description">
				<?php esc_html_e( 'The original image remains in the media library and is not deleted.', 'frontend-image-replace' ); ?>
			</p>

			</div><!-- /.main-content -->

			<!-- Sidebar -->
			<div style="width: 320px; flex-shrink: 0; position: sticky; top: 52px;">

				<!-- BM1 Support Info -->
				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #0073aa; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 415.57 500.57" width="32" height="38" style="flex-shrink: 0;"><defs><style>.bm1-logo-fill { fill: #005a96; }</style></defs><path class="bm1-logo-fill" d="m66.6,205.21H0v-124.49h66.6c45.75,0,72.65,22.88,72.65,61.9s-26.9,62.58-72.65,62.58Z"/><path class="bm1-logo-fill" d="m74.68,419.85H0v-137.93h72.65c51.12,0,80.09,24.23,80.09,68.63s-28.96,69.3-78.06,69.3Z"/><path class="bm1-logo-fill" d="m415.57,250.29c0,138.23-112.05,250.29-250.29,250.29-18.67,0-36.89-2.03-54.39-5.93,80.2-11.76,130.65-63.44,130.65-139.4,0-57.85-29.6-99.58-81.4-117.05,41.02-19.53,64.57-56.53,64.57-106.3,0-67.8-44.55-112.31-118.55-124.82C125.12,2.44,144.92,0,165.28,0c138.23,0,250.29,112.05,250.29,250.29Z"/></svg>
						<div>
							<strong style="font-size: 14px;">Baumg&auml;rtner Marketing</strong><br>
							<span style="color: #646970; font-size: 12px;">Plugin Development &amp; Support</span>
						</div>
					</div>
					<hr style="border: none; border-top: 1px solid #e0e0e0; margin: 12px 0;" />
					<p style="margin: 8px 0; font-size: 13px;">
						<span class="dashicons dashicons-admin-site" style="font-size: 14px; width: 14px; margin-right: 4px; color: #646970;"></span>
						<a href="https://bm1.de" target="_blank" rel="noopener noreferrer">bm1.de</a>
					</p>
					<hr style="border: none; border-top: 1px solid #e0e0e0; margin: 12px 0;" />
					<p style="margin: 0 0 12px; font-size: 12px; color: #646970;">
						<?php esc_html_e( 'For questions or issues with the plugin, we are happy to help.', 'frontend-image-replace' ); ?>
					</p>
					<button id="zammad-feedback-form" class="button button-primary" style="width: 100%; text-align: center;">
						<span class="dashicons dashicons-sos" style="font-size: 14px; width: 14px; margin-right: 4px; line-height: 1.8;"></span>
						<?php esc_html_e( 'Send Support Request', 'frontend-image-replace' ); ?>
					</button>
				</div>

			</div><!-- /.sidebar -->

			</div><!-- /.flex-wrapper -->
		</div>

		<!-- Zammad Feedback Form -->
		<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
		<script id="zammad_form_script" src="https://mail.bm1.de/assets/form/form.js"></script>
		<script>
		jQuery(function($) {
			$('#zammad-feedback-form').ZammadForm({
				messageTitle: 'Support-Anfrage: Frontend Image Replace',
				messageSubmit: '&Uuml;bermitteln',
				messageThankYou: 'Vielen Dank f&uuml;r Ihre Anfrage (#%s). Wir melden uns umgehend.',
				modal: true,
				attachmentSupport: true,
				attributes: [
					{
						display: 'Ihr Name',
						name: 'name',
						tag: 'input',
						type: 'text',
						placeholder: 'Ihr Name',
						required: true
					},
					{
						display: 'E-Mail',
						name: 'email',
						tag: 'input',
						type: 'email',
						placeholder: 'Ihre E-Mail-Adresse',
						required: true
					},
					{
						display: 'Nachricht',
						name: 'body',
						tag: 'textarea',
						placeholder: 'Beschreiben Sie Ihr Anliegen...',
						required: true,
						rows: 6
					}
				]
			});

			var firMeta = <?php echo wp_json_encode( $meta_text ); ?>;
			var metaAppended = false;

			// Intercept click on submit button to append metadata before ZammadForm processes it
			$(document).on('click', '.zammad-form [type="submit"], .zammad-form .btn', function() {
				if (metaAppended) return;
				var $form = $(this).closest('.zammad-form');
				var $body = $form.find('textarea[name="body"]');
				var $email = $form.find('input[name="email"]');
				if ($body.length && $body.val().trim()) {
					var extra = '\n\n---\nE-Mail: ' + ($email.val() || '') + '\n' + firMeta;
					$body.val($body.val() + extra);
					metaAppended = true;
					// Reset flag when modal closes
					setTimeout(function() { metaAppended = false; }, 5000);
				}
			});

			// Also try via native submit event with capture phase
			document.addEventListener('submit', function(e) {
				var form = e.target;
				if (!form.classList.contains('zammad-form')) return;
				if (metaAppended) return;
				var body = form.querySelector('textarea[name="body"]');
				var email = form.querySelector('input[name="email"]');
				if (body && body.value.trim()) {
					body.value += '\n\n---\nE-Mail: ' + (email ? email.value : '') + '\n' + firMeta;
					metaAppended = true;
					setTimeout(function() { metaAppended = false; }, 5000);
				}
			}, true);
		});
		</script>
		<?php
	}
}
