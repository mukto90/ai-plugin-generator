<?php
/**
 * Authentication + account provisioning for PluginDaddy users.
 *
 * Flow:
 * 1. /keys/request     — generates a key, stores it in a transient keyed by
 *                        email, emails it, and error_logs it for testing.
 *                        NO WP user is created at this stage.
 * 2. /keys/verify      — looks up the transient, creates a WP user if one
 *                        doesn't exist for that email, stores the hashed key
 *                        in user meta, clears the transient. Returns user info.
 * 3. /plugin/generate  — authenticate(email, api_key) → user_id by comparing
 *                        the hashed key in user meta.
 *
 * @package PluginDaddy_Service
 */

namespace PluginDaddy_Service;

defined( 'ABSPATH' ) || exit;

class User_Manager {

	const TRANSIENT_TTL    = 10 * MINUTE_IN_SECONDS;
	const TRANSIENT_PREFIX = 'plugindaddy_pending_key_';
	const USER_META_KEY    = '_plugindaddy_api_key_hash';
	const USER_META_ISSUED = '_plugindaddy_api_key_issued_at';
	const USER_ROLE        = 'subscriber';

	/**
	 * Create a key, email it, store it in a 10-minute transient. Returns the
	 * plain key or a WP_Error if the email fails.
	 */
	public function issue_and_email( $email ) {
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'plugindaddy_invalid_email', __( 'Invalid email address.', 'plugindaddy-service' ), array( 'status' => 400 ) );
		}

		$key = $this->generate_key();
		set_transient(
			$this->transient_key_for_email( $email ),
			array(
				'api_key'    => $key,
				'email'      => $email,
				'created_at' => time(),
			),
			self::TRANSIENT_TTL
		);

		// Testing hook — log issued keys so we can grab them without inbox access.
		error_log( sprintf( '[PluginDaddy] Issued pending key to %s: %s', $email, $key ) );

		$minutes = (int) ( self::TRANSIENT_TTL / MINUTE_IN_SECONDS );
		$subject = __( 'Your PluginDaddy API key', 'plugindaddy-service' );
		$body    = sprintf(
			/* translators: 1: API key, 2: minutes until expiry */
			__( "Welcome to PluginDaddy!\n\nYour API key: %1\$s\n\nPaste it into the AI Plugin Generator settings page along with this email address. The key is valid for %2\$d minutes — if it expires, request a new one from the settings page.", 'plugindaddy-service' ),
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
	 * Verify a pending key from the transient store and provision a WP user.
	 *
	 * @return array|\WP_Error { user_id, email, api_key }
	 */
	public function verify_and_provision( $email, $api_key ) {
		if ( ! is_email( $email ) || empty( $api_key ) ) {
			return new \WP_Error( 'plugindaddy_invalid', __( 'Invalid credentials.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		$transient_id = $this->transient_key_for_email( $email );
		$pending      = get_transient( $transient_id );
		if ( ! is_array( $pending ) || empty( $pending['api_key'] ) || ! hash_equals( (string) $pending['api_key'], (string) $api_key ) ) {
			return new \WP_Error( 'plugindaddy_unknown_key', __( 'This API key is unknown or has expired. Please request a new one.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		$user_id = $this->find_or_create_user( $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, self::USER_META_KEY, $this->hash_key( $api_key ) );
		update_user_meta( $user_id, self::USER_META_ISSUED, time() );

		delete_transient( $transient_id );

		return array(
			'user_id' => (int) $user_id,
			'email'   => $email,
		);
	}

	/**
	 * Authenticate a request. Returns user_id or WP_Error.
	 */
	public function authenticate( $email, $api_key ) {
		if ( ! is_email( $email ) || empty( $api_key ) ) {
			return new \WP_Error( 'plugindaddy_invalid', __( 'Invalid credentials.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new \WP_Error( 'plugindaddy_unknown_user', __( 'Unknown API key.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		$stored = get_user_meta( $user->ID, self::USER_META_KEY, true );
		if ( empty( $stored ) || ! hash_equals( (string) $stored, $this->hash_key( $api_key ) ) ) {
			return new \WP_Error( 'plugindaddy_unknown_key', __( 'Unknown or incorrect API key.', 'plugindaddy-service' ), array( 'status' => 401 ) );
		}

		return (int) $user->ID;
	}

	/**
	 * Find an existing WP user by email or create one with a random password.
	 */
	public function find_or_create_user( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return (int) $user->ID;
		}

		$username = $this->unique_username_from_email( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'role'       => self::USER_ROLE,
				'display_name' => $this->display_name_from_email( $email ),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		return (int) $user_id;
	}

	private function unique_username_from_email( $email ) {
		$base     = sanitize_user( substr( $email, 0, strpos( $email, '@' ) ), true );
		$base     = $base ?: 'plugindaddy_user';
		$username = $base;
		$i        = 1;
		while ( username_exists( $username ) ) {
			$username = $base . '_' . $i;
			$i++;
		}
		return $username;
	}

	private function display_name_from_email( $email ) {
		$local = substr( $email, 0, strpos( $email, '@' ) );
		return ucwords( str_replace( array( '.', '_', '-' ), ' ', $local ) );
	}

	private function generate_key() {
		return 'pd_' . bin2hex( random_bytes( 20 ) );
	}

	private function hash_key( $api_key ) {
		return hash( 'sha256', $api_key );
	}

	private function transient_key_for_email( $email ) {
		return self::TRANSIENT_PREFIX . hash( 'sha256', strtolower( $email ) );
	}
}
