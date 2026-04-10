<?php
/**
 * AJAX handlers and image replacement logic.
 *
 * @package BM1FrontendImageReplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BM1FIR_Replacer {

	public function __construct() {
		add_action( 'wp_ajax_bm1fir_replace_image', array( $this, 'handle_replace' ) );
		add_action( 'wp_ajax_nopriv_bm1fir_replace_image', array( $this, 'handle_replace' ) );
		add_action( 'wp_ajax_bm1fir_resolve_images', array( $this, 'handle_resolve' ) );
		add_action( 'wp_ajax_nopriv_bm1fir_resolve_images', array( $this, 'handle_resolve' ) );
	}

	/**
	 * Verify that the current AJAX request is authorized.
	 *
	 * @return bool
	 */
	private function verify_access() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'bm1fir_nonce', 'nonce', false ) ) {
			return false;
		}

		// Check logged-in user capability.
		if ( current_user_can( 'upload_files' ) ) {
			return true;
		}

		// Allow access when globally enabled.
		if ( BM1_Frontend_Image_Replace::is_enabled() ) {
			return true;
		}

		// Check token from POST data (Pro only).
		if ( ! BM1_Frontend_Image_Replace::is_pro() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above.
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( empty( $token ) ) {
			return false;
		}

		$stored_token = get_option( 'bm1fir_access_token' );
		if ( empty( $stored_token ) || ! hash_equals( $stored_token, $token ) ) {
			return false;
		}

		$expiry = get_option( 'bm1fir_token_expiry', 0 );
		if ( ! empty( $expiry ) && time() >= (int) $expiry ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle the image replacement AJAX request.
	 */
	public function handle_replace() {
		if ( ! $this->verify_access() ) {
			BM1FIR_Logger::debug( 'Replace rejected: unauthorized' );
			wp_send_json_error( __( 'Unauthorized.', 'bm1-frontend-image-replace' ), 403 );
		}

		// Check daily limit (Free: 3/day).
		$remaining = BM1_Frontend_Image_Replace::get_remaining_today();
		if ( $remaining === 0 ) {
			BM1FIR_Logger::debug( 'Replace rejected: daily limit reached' );
			wp_send_json_error( array(
				'message'     => __( 'Daily limit reached. Upgrade to Pro for unlimited replacements.', 'bm1-frontend-image-replace' ),
				'upgrade_url' => bm1_fs()->get_upgrade_url(),
				'limit'       => true,
			) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		$old_attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		$post_id           = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		$image_src         = isset( $_POST['image_src'] ) ? esc_url_raw( wp_unslash( $_POST['image_src'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		$occurrence_index  = isset( $_POST['occurrence_index'] ) ? absint( $_POST['occurrence_index'] ) : 0;

		if ( ! $old_attachment_id ) {
			wp_send_json_error( __( 'Invalid attachment ID.', 'bm1-frontend-image-replace' ) );
		}

		$old_attachment = get_post( $old_attachment_id );
		if ( ! $old_attachment || 'attachment' !== $old_attachment->post_type ) {
			wp_send_json_error( __( 'Attachment not found.', 'bm1-frontend-image-replace' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		if ( ! isset( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'bm1-frontend-image-replace' ) );
		}

		// Rate limiting: max 20 uploads per minute per IP.
		$remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$ip_key      = 'bm1fir_rate_' . md5( $remote_addr );
		$count       = (int) get_transient( $ip_key );
		if ( $count >= 20 ) {
			BM1FIR_Logger::debug( 'Replace rejected: rate limit exceeded' );
			wp_send_json_error( __( 'Too many uploads. Please wait a moment.', 'bm1-frontend-image-replace' ), 429 );
		}
		set_transient( $ip_key, $count + 1, MINUTE_IN_SECONDS );

		// File size limit: max 10 MB.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		$file_size = isset( $_FILES['file']['size'] ) ? absint( $_FILES['file']['size'] ) : 0;
		$max_size  = 10 * MB_IN_BYTES;
		if ( $file_size > $max_size ) {
			wp_send_json_error( __( 'File too large. Maximum size is 10 MB.', 'bm1-frontend-image-replace' ) );
		}

		// Validate file type: only images allowed.
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		$file_name     = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
		$file_info     = wp_check_filetype( $file_name );
		if ( empty( $file_info['type'] ) || ! in_array( $file_info['type'], $allowed_types, true ) ) {
			wp_send_json_error( __( 'Only image files (JPG, PNG, GIF, WebP, AVIF) are allowed.', 'bm1-frontend-image-replace' ) );
		}

		// Include required WordPress files.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Upload new file as a new attachment.
		$upload = wp_handle_upload( $_FILES['file'], array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			BM1FIR_Logger::error( 'Upload failed', array( 'error' => $upload['error'] ) );
			wp_send_json_error( __( 'Upload failed. Please try again.', 'bm1-frontend-image-replace' ) );
		}

		// Create new attachment post.
		$new_attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$new_attachment_id = wp_insert_attachment( $new_attachment, $upload['file'] );

		if ( is_wp_error( $new_attachment_id ) ) {
			BM1FIR_Logger::error( 'Attachment creation failed', array( 'error' => $new_attachment_id->get_error_message() ) );
			wp_delete_file( $upload['file'] );
			wp_send_json_error( $new_attachment_id->get_error_message() );
		}

		// Generate attachment metadata (thumbnails).
		$metadata = wp_generate_attachment_metadata( $new_attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $new_attachment_id, $metadata );

		// Get new full-size URL.
		$new_url = wp_get_attachment_url( $new_attachment_id );

		// Replace only the ONE clicked image in the specific post.
		$this->replace_single_occurrence( $post_id, $old_attachment_id, $new_attachment_id, $image_src, $new_url, $occurrence_index );

		// Log the replacement.
		BM1FIR_Logger::log_replacement( array(
			'post_id'           => $post_id,
			'post_title'        => get_the_title( $post_id ),
			'old_attachment_id' => $old_attachment_id,
			'new_attachment_id' => $new_attachment_id,
			'old_url'           => $image_src,
			'new_url'           => $new_url,
			'user_info'         => is_user_logged_in()
				? wp_get_current_user()->user_login
				: 'Token (' . substr( md5( $remote_addr ), 0, 8 ) . ')',
		) );
		BM1FIR_Logger::debug( 'Image replaced', array( 'post_id' => $post_id, 'old_id' => $old_attachment_id, 'new_id' => $new_attachment_id ) );

		// Increment daily counter for free users.
		BM1_Frontend_Image_Replace::increment_daily_count();

		wp_send_json_success( array(
			'old_attachment_id' => $old_attachment_id,
			'new_attachment_id' => $new_attachment_id,
			'url'               => $upload['url'],
		) );
	}

	/**
	 * Handle batch URL resolution AJAX request.
	 */
	public function handle_resolve() {
		if ( ! $this->verify_access() ) {
			wp_send_json_error( __( 'Unauthorized.', 'bm1-frontend-image-replace' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_access().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded, then each URL sanitized individually below via esc_url_raw().
		$urls = isset( $_POST['urls'] ) ? json_decode( wp_unslash( $_POST['urls'] ), true ) : array();
		if ( ! is_array( $urls ) || empty( $urls ) ) {
			wp_send_json_success( array() );
		}

		// Limit to 100 URLs per request.
		$urls    = array_slice( $urls, 0, 100 );
		$results = array();

		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url );
			if ( empty( $url ) ) {
				continue;
			}

			$id = $this->url_to_attachment_id( $url );
			if ( $id ) {
				$results[ $url ] = $id;
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * Resolve an image URL to its attachment ID.
	 *
	 * @param string $url Image URL.
	 * @return int Attachment ID or 0.
	 */
	private function url_to_attachment_id( $url ) {
		// Try direct match.
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			return $id;
		}

		// Try stripping size suffix (e.g., image-300x200.jpg -> image.jpg).
		$stripped = preg_replace( '/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $url );
		if ( $stripped !== $url ) {
			$id = attachment_url_to_postid( $stripped );
			if ( $id ) {
				return $id;
			}
		}

		// Try stripping scaled suffix (e.g., image-scaled.jpg -> image.jpg).
		$stripped = preg_replace( '/-scaled(\.[a-zA-Z0-9]+)$/', '$1', $url );
		if ( $stripped !== $url ) {
			$id = attachment_url_to_postid( $stripped );
			if ( $id ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * Get all URLs for an attachment (full size + all thumbnail sizes).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Associative array of size => URL.
	 */
	private function get_attachment_urls( $attachment_id ) {
		$urls     = array();
		$base_url = wp_get_attachment_url( $attachment_id );

		if ( ! $base_url ) {
			return $urls;
		}

		$urls['full'] = $base_url;

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) ) {
			$base_dir = dirname( $base_url );
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				$urls[ $size_name ] = $base_dir . '/' . $size_data['file'];
			}
		}

		return $urls;
	}

	/**
	 * Replace a single image occurrence in a specific post's content.
	 *
	 * @param int    $post_id           The post to update.
	 * @param int    $old_attachment_id  Old attachment ID.
	 * @param int    $new_attachment_id  New attachment ID.
	 * @param string $image_src         The specific src URL that was clicked.
	 * @param string $new_url           The new full-size image URL.
	 * @param int    $occurrence_index  Which occurrence to replace (0-based).
	 */
	private function replace_single_occurrence( $post_id, $old_attachment_id, $new_attachment_id, $image_src, $new_url, $occurrence_index = 0 ) {
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$content = $post->post_content;
		$updated = false;

		// Build a map of old thumbnail URLs to new thumbnail URLs for srcset replacement.
		$old_urls = $this->get_attachment_urls( $old_attachment_id );
		$new_urls = $this->get_attachment_urls( $new_attachment_id );
		$url_map  = $this->build_url_map( $old_urls, $new_urls );

		// Determine which size the old src corresponds to, so we can get correct dimensions.
		$old_size_name = 'full';
		foreach ( $old_urls as $size => $url ) {
			if ( $url === $image_src ) {
				$old_size_name = $size;
				break;
			}
		}

		// Get the new image dimensions for the matched size.
		$new_metadata  = wp_get_attachment_metadata( $new_attachment_id );
		$new_dimensions = $this->get_dimensions_for_size( $new_metadata, $old_size_name );

		// Strategy 1: Try to find Gutenberg image blocks containing the clicked src.
		$block_pattern = '/<!-- wp:(?:image|cover) \{[^}]*\} -->.+?<!-- \/wp:(?:image|cover) -->/s';
		if ( preg_match_all( $block_pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			$found_index = 0;
			foreach ( $matches[0] as $match ) {
				$block  = $match[0];
				$offset = $match[1];

				if ( strpos( $block, $image_src ) === false ) {
					continue;
				}

				if ( $found_index < $occurrence_index ) {
					$found_index++;
					continue;
				}

				$new_block = $block;

				// Replace all old URLs with new URLs (handles src and srcset).
				foreach ( $url_map as $old => $new ) {
					$new_block = str_replace( $old, $new, $new_block );
				}

				// Replace attachment ID in block comment JSON.
				$new_block = preg_replace(
					'/"id"\s*:\s*' . $old_attachment_id . '\b/',
					'"id":' . $new_attachment_id,
					$new_block
				);

				// Replace CSS class.
				$new_block = preg_replace(
					'/wp-image-' . $old_attachment_id . '\b/',
					'wp-image-' . $new_attachment_id,
					$new_block
				);

				// Update width/height in block comment JSON and img tag.
				$new_block = $this->update_dimensions( $new_block, $new_dimensions );

				$content = substr_replace( $content, $new_block, $offset, strlen( $block ) );
				$updated = true;
				break;
			}
		}

		// Strategy 2: If no Gutenberg block found, try <img> tags.
		if ( ! $updated ) {
			$img_pattern = '/<img\s[^>]*' . preg_quote( $image_src, '/' ) . '[^>]*\/?>/i';
			if ( preg_match_all( $img_pattern, $content, $img_matches, PREG_OFFSET_CAPTURE ) ) {
				$target = isset( $img_matches[0][ $occurrence_index ] ) ? $img_matches[0][ $occurrence_index ] : null;

				if ( $target ) {
					$img_tag = $target[0];
					$offset  = $target[1];

					$new_img = $img_tag;

					// Replace URLs.
					foreach ( $url_map as $old => $new ) {
						$new_img = str_replace( $old, $new, $new_img );
					}

					// Replace CSS class.
					$new_img = preg_replace(
						'/wp-image-' . $old_attachment_id . '\b/',
						'wp-image-' . $new_attachment_id,
						$new_img
					);

					// Update width/height attributes.
					$new_img = $this->update_dimensions( $new_img, $new_dimensions );

					$content = substr_replace( $content, $new_img, $offset, strlen( $img_tag ) );
					$updated = true;
				}
			}
		}

		if ( $updated ) {
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $content,
			) );
			clean_post_cache( $post_id );
		}
	}

	/**
	 * Build a mapping of old URLs to new URLs, matching by size name.
	 *
	 * @param array $old_urls Old URLs by size name.
	 * @param array $new_urls New URLs by size name.
	 * @return array Old URL => New URL pairs.
	 */
	private function build_url_map( $old_urls, $new_urls ) {
		$map = array();

		foreach ( $old_urls as $size => $old_url ) {
			if ( isset( $new_urls[ $size ] ) ) {
				$map[ $old_url ] = $new_urls[ $size ];
			} elseif ( isset( $new_urls['full'] ) ) {
				$map[ $old_url ] = $new_urls['full'];
			}
		}

		return $map;
	}

	/**
	 * Get the dimensions for a specific image size from attachment metadata.
	 *
	 * @param array  $metadata  Attachment metadata.
	 * @param string $size_name Size name (e.g., 'full', 'large', 'medium').
	 * @return array { width: int, height: int } or empty array.
	 */
	private function get_dimensions_for_size( $metadata, $size_name ) {
		if ( empty( $metadata ) ) {
			return array();
		}

		if ( 'full' === $size_name ) {
			return array(
				'width'  => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
				'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
			);
		}

		if ( ! empty( $metadata['sizes'][ $size_name ] ) ) {
			return array(
				'width'  => (int) $metadata['sizes'][ $size_name ]['width'],
				'height' => (int) $metadata['sizes'][ $size_name ]['height'],
			);
		}

		// Fallback to full size.
		return array(
			'width'  => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
			'height' => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
		);
	}

	/**
	 * Update width and height attributes in an HTML string.
	 *
	 * @param string $html       The HTML string to update.
	 * @param array  $dimensions { width: int, height: int }.
	 * @return string Updated HTML.
	 */
	private function update_dimensions( $html, $dimensions ) {
		if ( empty( $dimensions['width'] ) || empty( $dimensions['height'] ) ) {
			return $html;
		}

		$w = $dimensions['width'];
		$h = $dimensions['height'];

		// Update width="..." and height="..." attributes in <img> tags.
		$html = preg_replace( '/\bwidth="[^"]*"/', 'width="' . $w . '"', $html );
		$html = preg_replace( '/\bheight="[^"]*"/', 'height="' . $h . '"', $html );

		// Update "width":... and "height":... in Gutenberg block comment JSON.
		$html = preg_replace( '/"width"\s*:\s*\d+/', '"width":' . $w, $html );
		$html = preg_replace( '/"height"\s*:\s*\d+/', '"height":' . $h, $html );

		return $html;
	}
}
