<?php
/**
 * Request codes: ARR-2026-4F7K9Q.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Codes.
 */
class Codes {

	/**
	 * Crockford base 32, without I, L, O and U.
	 */
	const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

	/**
	 * A new code for a type, using the Argentine year.
	 *
	 * @param string   $type Type.
	 * @param int|null $ts   Timestamp, now if null.
	 * @return string
	 */
	public static function make( $type, $ts = null ) {
		$prefix = 'baja' === $type ? 'BAJ' : 'ARR';
		$year   = Util::local( null === $ts ? Util::now() : $ts )->format( 'Y' );
		$bytes  = random_bytes( 6 );
		$out    = '';
		for ( $i = 0; $i < 6; $i++ ) {
			$out .= self::ALPHABET[ ord( $bytes[ $i ] ) & 31 ];
		}
		return $prefix . '-' . $year . '-' . $out;
	}

	/**
	 * Whether a string looks like a code.
	 *
	 * @param string $code Code.
	 * @return bool
	 */
	public static function valid( $code ) {
		return (bool) preg_match( '/^(ARR|BAJ)-\d{4}-[0-9ABCDEFGHJKMNPQRSTVWXYZ]{6}$/', (string) $code );
	}
}
