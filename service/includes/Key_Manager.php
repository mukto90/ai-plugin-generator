<?php
/**
 * Issues, stores, emails, and verifies API keys.
 *
 * - Trial keys issued via /keys/request live in a short-lived transient
 *   (10 minutes), keyed by email hash. The transient value holds the key.
 * - Persistent keys (EDD purchases) live in the plugindaddy_keys table,
 *   which also tracks quota_limit / quota_used.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class Key_Manager {

	const TRANSIENT_TTL    = 10 * MINUTE_IN_SECONDS;
	const TRANSIENT_PREFIX = 'plugindaddy_pending_key_';

	/**
	 * Generate a trial key, store it in a transient, and email it.
	 *
	 * @param string $email
	 * @return string|\WP_Error
	 */
	public function issue_and_email( $email ) {
		$key = $this->generate_key();
		$ctx = array(
			'api_key'    => $key,
			'email'      => $email,
			'plan'       => 'trial',
			'quota_left' => $this->plan_quota( 'trial' ),
			'created_at' => time(),
		);

		set_transient( $this->transient_key_for_email( $email ), $ctx, self::TRANSIENT_TTL );

		// Testing hook — log issued keys so we can grab them without inbox access.
		error_log( sprintf( '[PluginDaddy] Issued trial key to %s: %s', $email, $key ) );

		$minutes = (int) ( self::TRANSIENT_TTL / MINUTE_IN_SECONDS );
		$subject = __( 'Your PluginDaddy API key', 'plugindaddy-service' );
		$body    = sprintf(
			/* translators: 1: API key, 2: minutes until expiry */
			__( "Welcome to PluginDaddy!\n\nYour trial API key: %1\$s\n\nPaste this into the AI Plugin Generator settings page along with this email address. The key is valid for %2\$d minutes — if it expires, request a new one from the settings page.", 'plugindaddy-service' ),
			$key,
			$minutes
		);

		$sent = wp_mail(
			$email,
			$subject,
			$body,
			array( 'From: PluginDaddy <no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>' )
		);

		if ( ! $sent ) {
			delete_transient( $this->transient_key_for_email( $email ) );
			return new \WP_Error( 'plugindaddy_mail_failed', __( 'Could not send the API key email. Please try again.', 'plugindaddy-service' ), array( 'status' => 500 ) );
		}

		return $key;
	}

	/**
	 * Issue a persistent key tied to an EDD customer/subscription.
	 *
	 * @param string $email
	 * @param string $plan
	 * @param int    $expires_at Unix timestamp. 0 for never.
	 * @param array  $edd Optional { customer_id, subscription_id }.
	 * @return string|\WP_Error
	 */
	public function issue_persistent( $email, $plan, $expires_at = 0, array $edd = array() ) {
		global $wpdb;

		$key  = $this->generate_key();
		$hash = $this->key_hash( $key );

		$inserted = $wpdb->insert(
			Installer::keys_table(),
			array(
				'email'               => $email,
				'key_hash'            => $hash,
				'plan'                => $plan,
				'quota_limit'         => $this->plan_quota( $plan ),
				'quota_used'          => 0,
				'edd_customer_id'     => isset( $edd['customer_id'] ) ? (int) $edd['customer_id'] : 0,
				'edd_subscription_id' => isset( $edd['subscription_id'] ) ? (int) $edd['subscription_id'] : 0,
				'expires_at'          => $expires_at ? gmdate( 'Y-m-d H:i:s', $expires_at ) : null,
				'created_at'          => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'plugindaddy_db_error', __( 'Could not store the issued key.', 'plugindaddy-service' ), array( 'status' => 500 ) );
		}

		error_log( sprintf( '[PluginDaddy] Issued %s key to %s: %s', $plan, $email, $key ) );

		$subject = __( 'Your PluginDaddy API key', 'plugindaddy-service' );
		$body    = sprintf(
			/* translators: 1: plan name, 2: API key */
			__( "Thanks for subscribing to PluginDaddy %1\$s!\n\nYour API key: %2\$s\n\nPaste this into the AI Plugin Generator settings page along with this email address.", 'plugindaddy-service' ),
			ucfirst( $plan ),
			$key
		);
		wp_mail(
			$email,
			$subject,
			$body,
			array( 'From: PluginDaddy <no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>' )
		);

		return $key;
	}

	/**
	 * Verify an email + API key pair. Checks transient store first, then table.
	 *
	 * @param string $email
	 * @param string $api_key
	 * @return array|\WP_Error
	 */
	public function verify( $email, $api_key ) {
		if ( ! is_email( $email ) || empty( $api_key ) ) {
			return new \WP_Error( 'plugindaddy_invalid', __( 'Invalid credentials.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		$pending_id = $this->transient_key_for_email( $email );
		$pending    = get_transient( $pending_id );
		if ( is_array( $pending ) && ! empty( $pending['api_key'] ) && hash_equals( (string) $pending['api_key'], (string) $api_key ) ) {
			$pending['id']    = $pending_id;
			$pending['store'] = 'transient';
			return $pending;
		}

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Installer::keys_table() . ' WHERE key_hash = %s AND email = %s LIMIT 1',
				$this->key_hash( $api_key ),
				$email
			),
			ARRAY_A
		);

		if ( empty( $row ) ) {
			return new \WP_Error( 'plugindaddy_unknown_key', __( 'Unknown or expired API key.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
			return new \WP_Error( 'plugindaddy_expired', __( 'API key has expired.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		return array(
			'id'         => (int) $row['id'],
			'email'      => $row['email'],
			'plan'       => $row['plan'],
			'quota_left' => max( 0, (int) $row['quota_limit'] - (int) $row['quota_used'] ),
			'expires_at' => $row['expires_at'],
			'store'      => 'persistent',
		);
	}

	/**
	 * Consume one unit of quota.
	 *
	 * @param array $ctx
	 * @return bool
	 */
	public function consume_quota( array $ctx ) {
		if ( ! isset( $ctx['quota_left'] ) || $ctx['quota_left'] <= 0 ) {
			return false;
		}

		if ( isset( $ctx['store'] ) && 'transient' === $ctx['store'] ) {
			$ctx['quota_left']--;
			set_transient( $ctx['id'], $ctx, self::TRANSIENT_TTL );
			return true;
		}

		global $wpdb;
		$affected = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Installer::keys_table() . ' SET quota_used = quota_used + 1 WHERE id = %d AND quota_used < quota_limit',
				(int) $ctx['id']
			)
		);
		return (bool) $affected;
	}

	public function revoke( $email, $api_key ) {
		delete_transient( $this->transient_key_for_email( $email ) );
		global $wpdb;
		$wpdb->delete(
			Installer::keys_table(),
			array( 'key_hash' => $this->key_hash( $api_key ) ),
			array( '%s' )
		);
	}

	public function revoke_by_subscription( $subscription_id ) {
		global $wpdb;
		$wpdb->delete(
			Installer::keys_table(),
			array( 'edd_subscription_id' => (int) $subscription_id ),
			array( '%d' )
		);
	}

	private function generate_key() {
		return 'pd_' . bin2hex( random_bytes( 20 ) );
	}

	private function key_hash( $api_key ) {
		return hash( 'sha256', $api_key );
	}

	private function transient_key_for_email( $email ) {
		return self::TRANSIENT_PREFIX . hash( 'sha256', strtolower( $email ) );
	}

	private function plan_quota( $plan ) {
		switch ( $plan ) {
			case 'pro':
				return 100;
			case 'studio':
				return 10000;
			case 'trial':
			default:
				return 5;
		}
	}
}
