<?php
/**
 * The form block and shortcode, and the logged-in prefill.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Form block.
 */
class FormBlock {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_ajax_bclaro_yo', array( __CLASS__, 'me' ) );
	}

	/**
	 * Block, shortcode and assets.
	 */
	public static function register() {
		wp_register_style( 'bclaro-form', BCLARO_URL . 'assets/form.css', array(), BCLARO_VERSION );
		wp_register_script(
			'bclaro-form',
			BCLARO_URL . 'assets/form.js',
			array(),
			BCLARO_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_register_script(
			'bclaro-editor',
			BCLARO_URL . 'assets/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			BCLARO_VERSION,
			true
		);
		wp_set_script_translations( 'bclaro-editor', 'facilo-boton-arrepentimiento' );
		register_block_type(
			BCLARO_DIR . 'blocks/formulario',
			array( 'render_callback' => array( __CLASS__, 'render_block' ) )
		);
		add_shortcode( 'facilo_arrepentimiento_formulario', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Block render.
	 *
	 * @param array $attrs Attributes.
	 * @return string
	 */
	public static function render_block( $attrs ) {
		return self::render( isset( $attrs['tipo'] ) ? $attrs['tipo'] : 'arrepentimiento' );
	}

	/**
	 * [facilo_arrepentimiento_formulario tipo="baja"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'tipo' => 'arrepentimiento' ), $atts, 'facilo_arrepentimiento_formulario' );
		return self::render( $atts['tipo'] );
	}

	/**
	 * Rights text: paragraphs, with **bold**, escaped.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function rich( $text ) {
		$out = '';
		foreach ( preg_split( "/\n\s*\n/", trim( (string) $text ) ) as $p ) {
			if ( '' === trim( $p ) ) {
				continue;
			}
			$p    = esc_html( trim( $p ) );
			$p    = preg_replace( '/\*\*(.+?)\*\*/', '<b>$1</b>', $p );
			$out .= '<p>' . nl2br( $p ) . '</p>';
		}
		return $out;
	}

	/**
	 * The form.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function render( $type ) {
		$type = 'baja' === $type ? 'baja' : 'arrepentimiento';
		wp_enqueue_style( 'bclaro-form' );
		wp_enqueue_script( 'bclaro-form' );
		$s       = Settings::get();
		$contact = Util::contact_email();
		$privacy = get_privacy_policy_url();
		ob_start();
		include BCLARO_DIR . 'templates/form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Logged-in customer's name and email, for the prefill. Read-only, own data.
	 */
	public static function me() {
		$u = wp_get_current_user();
		if ( ! $u->exists() ) {
			wp_send_json_error();
		}
		$name = trim( get_user_meta( $u->ID, 'billing_first_name', true ) . ' ' . get_user_meta( $u->ID, 'billing_last_name', true ) );
		if ( '' === $name ) {
			$name = trim( $u->first_name . ' ' . $u->last_name );
		}
		if ( '' === $name ) {
			$name = $u->display_name;
		}
		nocache_headers();
		wp_send_json_success(
			array(
				'nombre' => $name,
				'correo' => $u->user_email,
			)
		);
	}
}
