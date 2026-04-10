<?php
/**
 * EDD + EDD Recurring integration.
 *
 * - Adds a "Credits" column to the variable price metabox on the designated
 *   EDD download (configured in Settings → PluginDaddy). The value per
 *   price_id is stored inside the standard _edd_variable_prices meta as
 *   `plugindaddy_credits`, so EDD's built-in save handler preserves it.
 * - On purchase completion (edd_complete_purchase), grants the purchased
 *   credits to the buyer's user record. Creates the WP user if one doesn't
 *   exist for that email — but does NOT issue or email a new API key, since
 *   purchases never create keys.
 * - On subscription renewal (edd_subscription_post_renew), grants the same
 *   amount again using the renewal payment.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class EDD_Integration {

	public function __construct() {
		add_action( 'edd_complete_purchase', array( $this, 'on_purchase_complete' ), 20, 1 );
		add_action( 'edd_subscription_post_renew', array( $this, 'on_subscription_renew' ), 10, 4 );

		add_filter( 'edd_download_price_table_head', array( $this, 'price_table_head' ), 10, 1 );
		add_action( 'edd_download_price_table_row', array( $this, 'price_table_row' ), 10, 3 );
	}

	/**
	 * Header cell in the variable price metabox.
	 */
	public function price_table_head( $post_id ) {
		if ( ! $this->is_configured_download( $post_id ) ) {
			return;
		}
		echo '<th>' . esc_html__( 'Credits', 'plugindaddy-service' ) . '</th>';
	}

	/**
	 * Body cell per variable price row.
	 */
	public function price_table_row( $post_id, $key, $args ) {
		if ( ! $this->is_configured_download( $post_id ) ) {
			return;
		}
		$value = isset( $args['plugindaddy_credits'] ) ? (int) $args['plugindaddy_credits'] : 0;
		$name  = 'edd_variable_prices[' . esc_attr( $key ) . '][plugindaddy_credits]';
		echo '<td><input type="number" min="0" step="1" class="small-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" /></td>';
	}

	/**
	 * Grant credits on a completed one-off or initial subscription purchase.
	 */
	public function on_purchase_complete( $payment_id ) {
		if ( ! function_exists( 'edd_get_payment_user_email' ) ) {
			return;
		}

		$email = edd_get_payment_user_email( $payment_id );
		if ( ! is_email( $email ) ) {
			return;
		}

		$product_id = $this->configured_product_id();
		if ( ! $product_id ) {
			return;
		}

		$cart_items = edd_get_payment_meta_cart_details( $payment_id, true );
		if ( ! is_array( $cart_items ) ) {
			return;
		}

		$user_id = ( new User_Manager() )->find_or_create_user( $email );
		if ( is_wp_error( $user_id ) ) {
			return;
		}

		$credit_manager = new Credit_Manager();

		foreach ( $cart_items as $item ) {
			if ( empty( $item['id'] ) || (int) $item['id'] !== (int) $product_id ) {
				continue;
			}
			$price_id = isset( $item['item_number']['options']['price_id'] ) ? (int) $item['item_number']['options']['price_id'] : 0;
			$amount   = $this->credits_for_price( $product_id, $price_id );
			if ( $amount <= 0 ) {
				continue;
			}
			$credit_manager->grant_paid(
				$user_id,
				$amount,
				array(
					'source'         => 'edd_purchase',
					'edd_payment_id' => $payment_id,
					'edd_price_id'   => $price_id,
					'note'           => sprintf( 'Payment #%d, price_id %d', (int) $payment_id, $price_id ),
				)
			);
		}
	}

	/**
	 * Grant credits on each recurring renewal.
	 *
	 * @param mixed  $subscription   The EDD_Subscription object.
	 * @param int    $subscription_id
	 * @param object $payment        The renewal EDD_Payment.
	 * @param array  $args
	 */
	public function on_subscription_renew( $subscription, $subscription_id, $payment, $args = array() ) {
		if ( ! is_object( $subscription ) || empty( $subscription->customer_id ) ) {
			return;
		}

		$product_id = $this->configured_product_id();
		if ( ! $product_id || (int) $subscription->product_id !== (int) $product_id ) {
			return;
		}

		$customer = function_exists( 'edd_get_customer' ) ? edd_get_customer( $subscription->customer_id ) : null;
		$email    = $customer && ! empty( $customer->email ) ? $customer->email : '';
		if ( ! is_email( $email ) ) {
			return;
		}

		$user_id = ( new User_Manager() )->find_or_create_user( $email );
		if ( is_wp_error( $user_id ) ) {
			return;
		}

		$price_id = isset( $subscription->price_id ) ? (int) $subscription->price_id : 0;
		$amount   = $this->credits_for_price( $product_id, $price_id );
		if ( $amount <= 0 ) {
			return;
		}

		( new Credit_Manager() )->grant_paid(
			$user_id,
			$amount,
			array(
				'source'         => 'edd_renewal',
				'edd_payment_id' => is_object( $payment ) && ! empty( $payment->ID ) ? (int) $payment->ID : 0,
				'edd_price_id'   => $price_id,
				'note'           => sprintf( 'Renewal of subscription #%d', (int) $subscription_id ),
			)
		);
	}

	/**
	 * Look up the Credits value stored on a specific variable price.
	 */
	private function credits_for_price( $product_id, $price_id ) {
		$prices = get_post_meta( (int) $product_id, 'edd_variable_prices', true );
		if ( ! is_array( $prices ) || empty( $prices[ $price_id ] ) ) {
			return 0;
		}
		return isset( $prices[ $price_id ]['plugindaddy_credits'] ) ? (int) $prices[ $price_id ]['plugindaddy_credits'] : 0;
	}

	private function configured_product_id() {
		$settings = get_option( 'plugindaddy_service_settings', array() );
		return isset( $settings['edd_product_id'] ) ? (int) $settings['edd_product_id'] : 0;
	}

	private function is_configured_download( $post_id ) {
		return (int) $post_id === $this->configured_product_id();
	}
}
