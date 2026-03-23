<?php
/**
 * WP_List_Table for the image replacement log.
 *
 * @package FrontendImageReplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class FIR_Log_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'fir_log_entry',
			'plural'   => 'fir_log_entries',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'                => '<input type="checkbox" />',
			'created_at'        => __( 'Date', 'frontend-image-replace' ),
			'post_title'        => __( 'Page', 'frontend-image-replace' ),
			'old_attachment_id' => __( 'Old Image', 'frontend-image-replace' ),
			'new_attachment_id' => __( 'New Image', 'frontend-image-replace' ),
			'user_info'         => __( 'User', 'frontend-image-replace' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
		);
	}

	public function prepare_items() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fir_log';
		$per_page   = 20;
		$paged      = $this->get_pagenum();
		$offset     = ( $paged - 1 ) * $per_page;

		// Sanitize sorting parameters — only allow whitelisted values.
		$orderby = 'created_at';
		$order   = 'DESC';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_input = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : '';
		if ( 'asc' === strtolower( $order_input ) ) {
			$order = 'ASC';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY created_at {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d" />', $item->id );
	}

	public function column_created_at( $item ) {
		return esc_html(
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $item->created_at )
			)
		);
	}

	public function column_post_title( $item ) {
		$edit_link = get_edit_post_link( $item->post_id );
		$view_link = get_permalink( $item->post_id );
		$title     = $item->post_title ?: __( '(no title)', 'frontend-image-replace' );

		if ( $edit_link ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html( $title ) );
		}

		if ( $view_link ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $view_link ), esc_html( $title ) );
		}

		return esc_html( $title );
	}

	public function column_old_attachment_id( $item ) {
		return $this->render_attachment_column( $item->old_attachment_id, $item->old_url );
	}

	public function column_new_attachment_id( $item ) {
		return $this->render_attachment_column( $item->new_attachment_id, $item->new_url );
	}

	private function render_attachment_column( $attachment_id, $url ) {
		$thumb = wp_get_attachment_image( $attachment_id, array( 60, 60 ) );
		$edit  = get_edit_post_link( $attachment_id );

		$output = '';
		if ( $thumb ) {
			$output .= $thumb . '<br>';
		}
		if ( $edit ) {
			$output .= sprintf( '<a href="%s">#%d</a>', esc_url( $edit ), $attachment_id );
		} else {
			$output .= sprintf( '#%d', $attachment_id );
		}

		return $output;
	}

	public function column_user_info( $item ) {
		return esc_html( $item->user_info );
	}

	public function get_bulk_actions() {
		return array(
			'remove' => __( 'Remove selected', 'frontend-image-replace' ),
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			submit_button(
				__( 'Clear log', 'frontend-image-replace' ),
				'',
				'fir_clear_log',
				false,
				array( 'onclick' => 'return confirm("' . esc_js( __( 'Remove all log entries?', 'frontend-image-replace' ) ) . '");' )
			);
			?>
		</div>
		<?php
	}
}
