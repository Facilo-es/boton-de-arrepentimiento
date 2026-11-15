<?php
/**
 * Customer service block (F10): on both forms, as a block and as a shortcode.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Service.
 */
class Service {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Block and shortcode.
	 */
	public static function register() {
		register_block_type(
			BCLARO_DIR . 'blocks/atencion',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
		add_shortcode( 'boton_claro_atencion', array( __CLASS__, 'render' ) );
	}

	/**
	 * The lines, label => value, with empty ones left out.
	 *
	 * @return array
	 */
	public static function lines() {
		$s     = Settings::get();
		$lines = array(
			__( 'Horario:', 'boton-de-arrepentimiento' )  => $s['hours'],
			__( 'Teléfono:', 'boton-de-arrepentimiento' ) => $s['phone'],
			__( 'Correo:', 'boton-de-arrepentimiento' )   => $s['email'],
			__( 'Reclamos:', 'boton-de-arrepentimiento' ) => $s['claims'],
			__( 'Área responsable:', 'boton-de-arrepentimiento' ) => $s['area'],
		);
		return array_filter(
			array_map( 'trim', array_map( 'strval', $lines ) ),
			'strlen'
		);
	}

	/**
	 * Fields still empty, by their names in the setup.
	 *
	 * @return array
	 */
	public static function missing() {
		$s    = Settings::get();
		$need = array(
			'hours' => __( 'horario', 'boton-de-arrepentimiento' ),
			'phone' => __( 'teléfono', 'boton-de-arrepentimiento' ),
			'area'  => __( 'área responsable', 'boton-de-arrepentimiento' ),
		);
		$out  = array();
		foreach ( $need as $k => $label ) {
			if ( '' === trim( (string) $s[ $k ] ) ) {
				$out[] = $label;
			}
		}
		if ( '' === trim( (string) $s['email'] ) && '' === trim( (string) $s['claims'] ) ) {
			$out[] = __( 'otro canal para reclamos', 'boton-de-arrepentimiento' );
		}
		return $out;
	}

	/**
	 * Render, escaped.
	 *
	 * @return string
	 */
	public static function render() {
		$lines = self::lines();
		if ( ! $lines ) {
			return '';
		}
		$out = '<section class="bc-att"><h3>' . esc_html__( 'Atención al cliente', 'boton-de-arrepentimiento' ) . '</h3><dl>';
		foreach ( $lines as $label => $value ) {
			if ( is_email( $value ) ) {
				$value = '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
			} elseif ( preg_match( '#^https?://\S+$#', $value ) ) {
				$value = '<a href="' . esc_url( $value ) . '" rel="noopener">' . esc_html( $value ) . '</a>';
			} else {
				$value = esc_html( $value );
			}
			$out .= '<dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd>';
		}
		$out .= '</dl></section>';
		if ( ! wp_style_is( 'bclaro-form', 'enqueued' ) ) {
			wp_enqueue_style( 'bclaro-form' );
		}
		return $out;
	}
}
