<?php
/**
 * Rights notice (F9): order emails, classic and block checkout, and [boton_claro_aviso].
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Notice.
 */
class Notice {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email' ), 20, 4 );
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'classic' ) );
		add_filter( 'render_block_woocommerce/checkout-actions-block', array( __CLASS__, 'block' ), 10, 1 );
	}

	/**
	 * Shortcode.
	 */
	public static function register() {
		add_shortcode( 'boton_claro_aviso', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Whether the notice is on and has text.
	 *
	 * @return bool
	 */
	public static function on() {
		$s = Settings::get();
		return ! empty( $s['notice_on'] ) && '' !== trim( (string) $s['notice_text'] );
	}

	/**
	 * The notice as HTML, with {enlace} as the form link.
	 *
	 * @param string $tag Wrapper tag.
	 * @param string $css Inline style for the wrapper, for emails.
	 * @return string
	 */
	public static function html( $tag = 'p', $css = '' ) {
		$s    = Settings::get();
		$url  = $s['url_arrepentimiento'];
		$text = esc_html( trim( (string) $s['notice_text'] ) );
		$name = esc_html( Util::rules()['labels']['arrepentimiento'] );
		$link = $url ? '<a href="' . esc_url( $url ) . '">' . $name . '</a>' : $name;
		$text = str_replace( '{enlace}', $link, $text );
		$attr = $css ? ' style="' . esc_attr( $css ) . '"' : '';
		return '<' . $tag . ' class="bclaro-aviso"' . $attr . '>' . $text . '</' . $tag . '>';
	}

	/**
	 * Plain-text version, for plain emails.
	 *
	 * @return string
	 */
	public static function plain() {
		$s    = Settings::get();
		$link = Util::rules()['labels']['arrepentimiento'];
		if ( $s['url_arrepentimiento'] ) {
			$link .= ' (' . $s['url_arrepentimiento'] . ')';
		}
		return str_replace( '{enlace}', $link, trim( (string) $s['notice_text'] ) );
	}

	/**
	 * Customer order emails.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin To admin.
	 * @param bool      $plain_text    Plain.
	 * @param object    $email         Email.
	 */
	public static function email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		unset( $order, $email );
		if ( $sent_to_admin || ! self::on() ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n" . esc_html( wp_strip_all_tags( self::plain() ) ) . "\n\n";
			return;
		}
		echo wp_kses_post( self::html( 'p', 'margin:16px 0 24px;font-size:14px;line-height:1.5' ) );
	}

	/**
	 * Classic checkout, above the place-order button.
	 */
	public static function classic() {
		if ( self::on() ) {
			echo wp_kses_post( self::html( 'p', 'font-size:.9em;margin:0 0 1em' ) );
		}
	}

	/**
	 * Block checkout, above the place-order button.
	 *
	 * @param string $html Block HTML.
	 * @return string
	 */
	public static function block( $html ) {
		if ( ! self::on() ) {
			return $html;
		}
		return '<div class="wc-block-components-checkout-step bclaro-aviso-wrap" style="font-size:.9em;margin:0 0 1em">' . wp_kses_post( self::html( 'p' ) ) . '</div>' . $html;
	}

	/**
	 * [boton_claro_aviso].
	 *
	 * @return string
	 */
	public static function shortcode() {
		$s = Settings::get();
		if ( '' === trim( (string) $s['notice_text'] ) ) {
			return '';
		}
		return self::html( 'p' );
	}
}
