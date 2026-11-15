<?php
/**
 * Spam checks for the public form: honeypot, time to fill in, and a rate limit per hashed IP.
 *
 * The IP hash lives only in a transient and is never stored with a request.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Spam guard.
 */
class SpamGuard {

	const LIMIT    = 10;
	const MIN_SECS = 2;

	/**
	 * Most links a real request carries in its text fields.
	 */
	const MAX_LINKS = 3;

	/**
	 * The visitor's IP. Filter bclaro_client_ip for proxies.
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'bclaro_client_ip', $ip );
	}

	/**
	 * Transient key for an IP: a salted hash, so the IP itself is never kept.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	public static function key( $ip ) {
		return 'bclaro_rl_' . substr( hash_hmac( 'sha256', (string) $ip, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Whether the submission looks like a bot: honeypot filled or sent too fast.
	 *
	 * @param array $p Posted fields.
	 * @return bool
	 */
	public static function is_bot( $p ) {
		if ( isset( $p['web'] ) && '' !== trim( (string) $p['web'] ) ) {
			return true;
		}
		$ms = isset( $p['t'] ) ? (int) $p['t'] : 0;
		return $ms < self::MIN_SECS * 1000;
	}

	/**
	 * Whether the text fields carry more links than a real request would.
	 *
	 * @param array $p Posted fields.
	 * @return bool
	 */
	public static function too_many_links( $p ) {
		$text = '';
		foreach ( array( 'nombre', 'servicio', 'pedido', 'detalle', 'mensaje' ) as $k ) {
			if ( isset( $p[ $k ] ) && is_scalar( $p[ $k ] ) ) {
				$text .= ' ' . $p[ $k ];
			}
		}
		$n = preg_match_all( '#(https?://|www\.)#i', $text );
		return $n > (int) apply_filters( 'bclaro_max_links', self::MAX_LINKS );
	}

	/**
	 * Count one more request from this IP and say whether it went over the limit.
	 *
	 * @return bool True when over the limit.
	 */
	public static function over_limit() {
		$key  = self::key( self::ip() );
		$data = get_transient( $key );
		$now  = Util::now();
		if ( ! is_array( $data ) || $data['until'] <= $now ) {
			$data = array(
				'n'     => 0,
				'until' => $now + HOUR_IN_SECONDS,
			);
		}
		++$data['n'];
		set_transient( $key, $data, max( 60, $data['until'] - $now ) );
		return $data['n'] > self::LIMIT;
	}
}
