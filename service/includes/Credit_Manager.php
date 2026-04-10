<?php
/**
 * Credit accounting. Two buckets per user:
 *
 *  - FREE: a rolling-window allowance from settings
 *          ("N credits per {day|week|month|year}"). No rows are inserted
 *          for free generations other than the plugins-table entry itself;
 *          availability is computed from plugins.tier='free' rows within
 *          the rolling window.
 *
 *  - PAID: grants from EDD purchases/renewals stored in the credits table
 *          (no used/expires_at). Balance is
 *          SUM(credits.amount where tier='paid') - COUNT(plugins where tier='paid').
 *
 * Total balance = free_available + paid_available. If either is > 0 the
 * request is allowed; we prefer FREE first, then PAID.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Credit_Manager {

	const TIER_FREE = 'free';
	const TIER_PAID = 'paid';

	/**
	 * Record a paid credit grant.
	 */
	public function grant_paid( $user_id, $amount, array $meta = array() ) {
		if ( $amount <= 0 ) {
			return false;
		}

		global $wpdb;
		return (bool) $wpdb->insert(
			Installer::credits_table(),
			array(
				'user_id'        => (int) $user_id,
				'tier'           => self::TIER_PAID,
				'amount'         => (int) $amount,
				'source'         => isset( $meta['source'] ) ? (string) $meta['source'] : 'manual',
				'edd_payment_id' => isset( $meta['edd_payment_id'] ) ? (int) $meta['edd_payment_id'] : 0,
				'edd_price_id'   => isset( $meta['edd_price_id'] ) ? (int) $meta['edd_price_id'] : 0,
				'note'           => isset( $meta['note'] ) ? substr( (string) $meta['note'], 0, 255 ) : '',
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Number of free credits still available in the rolling window.
	 */
	public function free_available( $user_id ) {
		$allowance = $this->free_allowance();
		$used      = $this->free_used_in_window( $user_id );
		return max( 0, $allowance - $used );
	}

	/**
	 * Number of paid credits still available.
	 */
	public function paid_available( $user_id ) {
		global $wpdb;

		$granted = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount), 0) FROM ' . Installer::credits_table() . ' WHERE user_id = %d AND tier = %s',
				(int) $user_id,
				self::TIER_PAID
			)
		);

		$spent = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Installer::plugins_table() . ' WHERE user_id = %d AND tier = %s',
				(int) $user_id,
				self::TIER_PAID
			)
		);

		return max( 0, $granted - $spent );
	}

	/**
	 * Decide which tier to charge this request to, or return null if none.
	 */
	public function select_tier_to_charge( $user_id ) {
		if ( $this->free_available( $user_id ) > 0 ) {
			return self::TIER_FREE;
		}
		if ( $this->paid_available( $user_id ) > 0 ) {
			return self::TIER_PAID;
		}
		return null;
	}

	/**
	 * Free allowance from settings (credits per window).
	 */
	public function free_allowance() {
		$settings = get_option( 'plugindaddy_service_settings', array() );
		$value    = isset( $settings['free_allowance'] ) ? (int) $settings['free_allowance'] : PLUGINDADDY_FREE_ALLOWANCE_DEFAULT;
		return max( 0, $value );
	}

	/**
	 * Free window period: 'day' | 'week' | 'month' | 'year'.
	 */
	public function free_period() {
		$settings = get_option( 'plugindaddy_service_settings', array() );
		$period   = isset( $settings['free_period'] ) ? (string) $settings['free_period'] : PLUGINDADDY_FREE_PERIOD_DEFAULT;
		return in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ? $period : 'month';
	}

	/**
	 * Free allowance window start as a UTC "Y-m-d H:i:s" string.
	 */
	public function window_start() {
		return gmdate( 'Y-m-d H:i:s', strtotime( '-1 ' . $this->free_period() ) );
	}

	private function free_used_in_window( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Installer::plugins_table() . ' WHERE user_id = %d AND tier = %s AND created_at > %s',
				(int) $user_id,
				self::TIER_FREE,
				$this->window_start()
			)
		);
	}
}
