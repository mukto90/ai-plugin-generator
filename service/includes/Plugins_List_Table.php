<?php
/**
 * WP_List_Table displaying rows from the plugindaddy_plugins table.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Plugins_List_Table extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'plugindaddy_plugin',
				'plural'   => 'plugindaddy_plugins',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'user'        => __( 'User', 'plugindaddy-service' ),
			'plugin_name' => __( 'Plugin', 'plugindaddy-service' ),
			'description' => __( 'Description', 'plugindaddy-service' ),
			'tier'        => __( 'Tier', 'plugindaddy-service' ),
			'provider'    => __( 'Provider', 'plugindaddy-service' ),
			'created_at'  => __( 'Created', 'plugindaddy-service' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'plugin_name' => array( 'plugin_name', false ),
			'created_at'  => array( 'created_at', true ),
			'tier'        => array( 'tier', false ),
		);
	}

	public function prepare_items() {
		global $wpdb;

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order   = ! empty( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		$allowed_orderby = array( 'created_at', 'plugin_name', 'tier' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}

		$table = Installer::plugins_table();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$this->items = $rows ? $rows : array();

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	public function column_user( $item ) {
		$user = get_user_by( 'id', (int) $item['user_id'] );
		if ( ! $user ) {
			return '<em>' . esc_html__( '(deleted)', 'plugindaddy-service' ) . '</em>';
		}
		$edit = get_edit_user_link( $user->ID );
		$name = $user->display_name ? $user->display_name : $user->user_login;
		return sprintf(
			'<a href="%s">%s</a><br><small>%s</small>',
			esc_url( $edit ),
			esc_html( $name ),
			esc_html( $user->user_email )
		);
	}

	public function column_plugin_name( $item ) {
		$name = $item['plugin_name'] ?: '(untitled)';
		$slug = $item['plugin_slug'];
		return sprintf(
			'<strong>%s</strong><br><code>%s</code>',
			esc_html( $name ),
			esc_html( $slug )
		);
	}

	public function column_description( $item ) {
		$raw = (string) $item['description'];
		$raw = wp_strip_all_tags( $raw );
		if ( strlen( $raw ) > 240 ) {
			$raw = substr( $raw, 0, 240 ) . '…';
		}
		return esc_html( $raw );
	}

	public function column_tier( $item ) {
		$tier = $item['tier'];
		$label = 'paid' === $tier ? __( 'Paid', 'plugindaddy-service' ) : __( 'Free', 'plugindaddy-service' );
		$color = 'paid' === $tier ? '#1e8e3e' : '#5a6573';
		return sprintf( '<span style="color:%s;font-weight:600;">%s</span>', esc_attr( $color ), esc_html( $label ) );
	}

	public function column_created_at( $item ) {
		$ts = strtotime( $item['created_at'] . ' UTC' );
		if ( ! $ts ) {
			return esc_html( $item['created_at'] );
		}
		return sprintf(
			'%s<br><small>%s</small>',
			esc_html( wp_date( get_option( 'date_format' ), $ts ) ),
			esc_html( wp_date( get_option( 'time_format' ), $ts ) )
		);
	}

	public function no_items() {
		esc_html_e( 'No plugins have been generated yet.', 'plugindaddy-service' );
	}
}
