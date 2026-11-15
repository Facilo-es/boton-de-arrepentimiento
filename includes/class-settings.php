<?php
/**
 * The single autoloaded settings option.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
class Settings {

	const OPTION = 'bclaro_settings';

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default rights notice.
	 *
	 * @return string
	 */
	public static function default_notice() {
		return 'Tenés 10 días corridos para arrepentirte de esta compra desde que la recibís o la contratás, lo que pase último, sin dar motivos y sin costo. Podés hacerlo desde el {enlace}.';
	}

	/**
	 * Default rights text under the arrepentimiento form.
	 *
	 * @return string
	 */
	public static function default_rights() {
		return "**Tus 10 días.** Tenés 10 días corridos desde que recibiste tu compra o la contrataste, lo que haya pasado último. Si el último día no es hábil, el plazo sigue hasta el primer día hábil. No tenés que explicar por qué, y arrepentirte no te cuesta nada: los gastos de devolución corren por nuestra cuenta.\n\nAlgunos casos pueden no estar incluidos, como productos hechos a tu medida, perecederos o ya usados. Igual podés mandar tu pedido: lo revisamos y te respondemos.";
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		$r = Util::rules();
		$d = array(
			'style'                 => 'banda',
			'color'                 => '#a8421f',
			'rec_style'             => '',
			'rec_color'             => '',
			'rec_why'               => '',
			'strip'                 => 1,
			'pill'                  => 1,
			'baja'                  => 1,
			'label_arrepentimiento' => $r['labels']['arrepentimiento'],
			'label_baja'            => $r['labels']['baja'],
			'page_arrepentimiento'  => 0,
			'page_baja'             => 0,
			'url_arrepentimiento'   => '',
			'url_baja'              => '',
			'sells'                 => 'ambas',
			'hours'                 => '',
			'phone'                 => '',
			'email'                 => '',
			'claims'                => '',
			'area'                  => '',
			'notify'                => '',
			'notice_on'             => 1,
			'notice_text'           => self::default_notice(),
			'rights_text'           => self::default_rights(),
			'retention_years'       => (int) $r['retention_years'],
			'delete_on_uninstall'   => 0,
			'holidays'              => '',
			'mail_ok'               => 0,
			'mail_alert'            => array(),
			'css'                   => '',
			'css_key'               => '',
		);
		/**
		 * Default settings. Pro adds its own keys.
		 *
		 * @param array $d Defaults.
		 */
		return (array) apply_filters( 'bclaro_settings_defaults', $d );
	}

	/**
	 * Make sure the option exists, autoloaded, with every key.
	 */
	public static function ensure() {
		$stored = get_option( self::OPTION );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$s = array_merge( self::defaults(), $stored );
		if ( '' === $s['notify'] ) {
			$s['notify'] = (string) get_option( 'admin_email' );
		}
		$s = self::compile( $s );
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, $s, '', true );
		} else {
			update_option( self::OPTION, $s, true );
		}
		self::$cache = $s;
	}

	/**
	 * All settings, or one of them.
	 *
	 * @param string|null $key Key, or null for all.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		if ( null !== $key ) {
			$all = self::get();
			return isset( $all[ $key ] ) ? $all[ $key ] : null;
		}
		if ( null === self::$cache ) {
			$s = get_option( self::OPTION );
			if ( ! is_array( $s ) ) {
				$s = array();
			}
			self::$cache = array_merge( self::defaults(), $s );
		}
		return self::$cache;
	}

	/**
	 * Merge and store new values. Values must already be sanitized.
	 *
	 * @param array $values New values.
	 * @return array
	 */
	public static function update( $values ) {
		$s = array_merge( self::get(), $values );
		$s = self::compile( $s );
		update_option( self::OPTION, $s, true );
		self::$cache = $s;
		return $s;
	}

	/**
	 * Forget the cache, after another process changed the option.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Compute the stored values the front end reads: the button CSS and the page links.
	 *
	 * @param array $s Settings.
	 * @return array
	 */
	public static function compile( $s ) {
		$styles = Styles::all();
		if ( ! isset( $styles[ $s['style'] ] ) ) {
			$s['style'] = 'banda';
		}
		$s['css']     = Styles::css( $s['style'], $s['color'], $s );
		$s['css_key'] = BCLARO_VERSION;
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			$id                  = (int) $s[ 'page_' . $type ];
			$s[ 'url_' . $type ] = '';
			if ( $id && 'publish' === get_post_status( $id ) && 'page' === get_post_type( $id ) ) {
				$s[ 'url_' . $type ] = (string) get_permalink( $id );
			}
		}
		return $s;
	}

	/**
	 * Labels currently in use.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function label( $type ) {
		$s = self::get();
		$r = Util::rules();
		$l = isset( $s[ 'label_' . $type ] ) ? trim( (string) $s[ 'label_' . $type ] ) : '';
		return '' !== $l ? $l : $r['labels'][ $type ];
	}

	/**
	 * Whether a label was changed from the exact name.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function label_changed( $type ) {
		$r = Util::rules();
		return self::label( $type ) !== $r['labels'][ $type ];
	}
}
