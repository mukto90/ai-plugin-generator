<?php
/**
 * EDD + EDD Recurring integration.
 * Issues API keys on purchase, revokes them on subscription cancel/expiry.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class EDD_Integration {

	public function __construct() {
		add_action( 'edd_complete_purchase', array( $this, 'on_purchase_complete' ), 20, 1 );
		add_action( 'edd_subscription_cancelled', array( $this, 'on_subscription_cancelled' ), 10, 2 );
		add_action( 'edd_subscription_expired', array( $this, 'on_subscription_expired' ), 10, 2 );
	}

	public function on_purchase_complete( $payment_id ) {
		if ( ! function_exists( 'edd_get_payment_meta' ) ) {
			return;
		}
		$email = edd_get_payment_user_email( $payment_id );
		if ( ! is_email( $email ) ) {
			return;
		}
		$plan = $this->resolve_plan_from_payment( $payment_id );
		( new Key_Manager() )->issue_persistent( $email, $plan, $this->plan_expiry( $plan ) );
	}

	public function on_subscription_cancelled( $sub_id, $subscription ) {
		( new Key_Manager() )->revoke_by_subscription( $sub_id );
	}

	public function on_subscription_expired( $sub_id, $subscription ) {
		( new Key_Manager() )->revoke_by_subscription( $sub_id );
	}

	private function resolve_plan_from_payment( $payment_id ) {
		// TODO: map the purchased download ID to a plan slug (trial/pro/studio).
		return 'pro';
	}

	private function plan_expiry( $plan ) {
		return strtotime( '+1 month' );
	}
}
