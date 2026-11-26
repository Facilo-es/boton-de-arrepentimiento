<?php
/**
 * Small shared helpers: rules, capability, dates in Argentine time.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers.
 */
class Util {

	/**
	 * Cached rules.
	 *
	 * @var array|null
	 */
	private static $rules = null;

	/**
	 * The country rules from rules/ar.php.
	 *
	 * @return array
	 */
	public static function rules() {
		if ( null === self::$rules ) {
			self::$rules = require BCLARO_DIR . 'rules/ar.php';
		}
		return self::$rules;
	}

	/**
	 * Capability that manages the plugin.
	 *
	 * @return string
	 */
	public static function cap() {
		$cap = self::woo() ? 'manage_woocommerce' : 'manage_options';
		return (string) apply_filters( 'bclaro_capability', $cap );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function woo() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Argentine time zone.
	 *
	 * @return \DateTimeZone
	 */
	public static function tz() {
		$r = self::rules();
		return new \DateTimeZone( $r['timezone'] );
	}

	/**
	 * Current time as a Unix timestamp. Filterable so tests can fix the clock.
	 *
	 * @return int
	 */
	public static function now() {
		return (int) apply_filters( 'bclaro_now', time() );
	}

	/**
	 * UTC MySQL datetime for a timestamp.
	 *
	 * @param int|null $ts Timestamp, now if null.
	 * @return string
	 */
	public static function mysql( $ts = null ) {
		return gmdate( 'Y-m-d H:i:s', null === $ts ? self::now() : (int) $ts );
	}

	/**
	 * Timestamp of a UTC MySQL datetime.
	 *
	 * @param string|null $mysql Datetime.
	 * @return int
	 */
	public static function ts( $mysql ) {
		if ( empty( $mysql ) ) {
			return 0;
		}
		return (int) strtotime( $mysql . ' UTC' );
	}

	/**
	 * Spanish month names.
	 *
	 * @return string[]
	 */
	private static function months() {
		return array( '', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
	}

	/**
	 * Spanish weekday names, 0 = Sunday.
	 *
	 * @return string[]
	 */
	private static function days() {
		return array( 'domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado' );
	}

	/**
	 * Local DateTime in Argentina for a timestamp.
	 *
	 * @param int $ts Timestamp.
	 * @return \DateTimeImmutable
	 */
	public static function local( $ts ) {
		return ( new \DateTimeImmutable( '@' . (int) $ts ) )->setTimezone( self::tz() );
	}

	/**
	 * Date as "domingo 8 de noviembre", always Spanish and Argentine time.
	 *
	 * @param int  $ts       Timestamp.
	 * @param bool $with_day Include the weekday.
	 * @return string
	 */
	public static function date( $ts, $with_day = true ) {
		$d = self::local( $ts );
		$m = self::months();
		$w = self::days();
		$s = (int) $d->format( 'j' ) . ' de ' . $m[ (int) $d->format( 'n' ) ];
		return $with_day ? $w[ (int) $d->format( 'w' ) ] . ' ' . $s : $s;
	}

	/**
	 * Time as "14:30", Argentine time.
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function time( $ts ) {
		return self::local( $ts )->format( 'H:i' );
	}

	/**
	 * Short "8 nov 14:30" for lists.
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function short( $ts ) {
		$d = self::local( $ts );
		$m = self::months();
		return (int) $d->format( 'j' ) . ' ' . substr( $m[ (int) $d->format( 'n' ) ], 0, 3 ) . ' ' . $d->format( 'H:i' );
	}

	/**
	 * "8 de noviembre de 2026 a las 14:30".
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function full( $ts ) {
		return self::date( $ts, false ) . ' de ' . self::local( $ts )->format( 'Y' ) . ' a las ' . self::time( $ts );
	}

	/**
	 * "1 hora" or "n horas".
	 *
	 * @param int $n Hours.
	 * @return string
	 */
	public static function hours( $n ) {
		return 1 === (int) $n ? '1 hora' : (int) $n . ' horas';
	}

	/**
	 * Human labels for the request types.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function type_label( $type ) {
		return 'baja' === $type ? __( 'Baja', 'facilo-boton-arrepentimiento' ) : __( 'Arrepentimiento', 'facilo-boton-arrepentimiento' );
	}

	/**
	 * Status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'nuevo'          => __( 'Nuevo', 'facilo-boton-arrepentimiento' ),
			'en_curso'       => __( 'En curso', 'facilo-boton-arrepentimiento' ),
			'resuelto'       => __( 'Resuelto', 'facilo-boton-arrepentimiento' ),
			'no_corresponde' => __( 'No corresponde', 'facilo-boton-arrepentimiento' ),
			'repetido'       => __( 'Repetido', 'facilo-boton-arrepentimiento' ),
			'spam'           => __( 'Spam', 'facilo-boton-arrepentimiento' ),
		);
	}

	/**
	 * Admin URL of a plugin screen.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function admin_url( $page, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * The customer service email, falling back to the notification address.
	 *
	 * @return string
	 */
	public static function contact_email() {
		$s = Settings::get();
		if ( ! empty( $s['email'] ) ) {
			return $s['email'];
		}
		return self::notify_email();
	}

	/**
	 * The owner's notification address.
	 *
	 * @return string
	 */
	public static function notify_email() {
		$s = Settings::get();
		return ! empty( $s['notify'] ) ? $s['notify'] : (string) get_option( 'admin_email' );
	}
}
