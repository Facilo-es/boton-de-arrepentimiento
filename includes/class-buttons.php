<?php
/**
 * The two buttons: footer strip, floating pill, block and shortcodes.
 *
 * Plain HTML in wp_footer, no JS, and CSS precomputed in the autoloaded settings.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Buttons.
 */
class Buttons {

	const HANDLE = 'bclaro-botones';

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 20 );
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ), 999 );
	}

	/**
	 * Whether this request is the admin preview inside the settings screens.
	 *
	 * @return bool
	 */
	public static function preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview flag, checked against the capability.
		return isset( $_GET['bclaro_vista'] ) && is_user_logged_in() && current_user_can( Util::cap() );
	}

	/**
	 * Hide the admin bar in the preview.
	 *
	 * @param bool $show Show.
	 * @return bool
	 */
	public static function admin_bar( $show ) {
		return self::preview() ? false : $show;
	}

	/**
	 * Shortcodes and the block.
	 */
	public static function register() {
		add_shortcode( 'boton_arrepentimiento', array( __CLASS__, 'shortcode_arr' ) );
		add_shortcode( 'boton_baja', array( __CLASS__, 'shortcode_baja' ) );
		register_block_type(
			BCLARO_DIR . 'blocks/botones',
			array( 'render_callback' => array( __CLASS__, 'render_block' ) )
		);
	}

	/**
	 * The inline CSS, on a registered empty handle.
	 */
	public static function styles() {
		$s = Settings::get();
		if ( '' === $s['css'] ) {
			return;
		}
		wp_register_style( self::HANDLE, false, array(), BCLARO_VERSION );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, $s['css'] );
	}

	/**
	 * Types to show, with their links. A type whose page is missing is left out.
	 *
	 * @param bool $all Include types without a page, linking to '#' (preview).
	 * @return array type => url
	 */
	public static function links( $all = false ) {
		$s   = Settings::get();
		$out = array();
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			if ( 'baja' === $type && empty( $s['baja'] ) && ! $all ) {
				continue;
			}
			if ( '' !== $s[ 'url_' . $type ] ) {
				$out[ $type ] = $s[ 'url_' . $type ];
			} elseif ( $all ) {
				$out[ $type ] = '#';
			}
		}
		return $out;
	}

	/**
	 * One link.
	 *
	 * @param string $type Type.
	 * @param string $url  Link.
	 * @return string
	 */
	public static function link( $type, $url ) {
		$label = Settings::label( $type );
		return '<a class="bclaro-b" href="' . esc_url( $url ) . '" data-bclaro="' . esc_attr( $type ) . '" aria-label="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Strip and pill, at the end of every public page.
	 */
	public static function footer() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return;
		}
		$s       = Settings::get();
		$preview = self::preview();
		$links   = self::links( $preview );
		if ( ! $links ) {
			return;
		}
		$html    = '';
		$on_form = false;
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			if ( (int) $s[ 'page_' . $type ] && is_page( (int) $s[ 'page_' . $type ] ) ) {
				$on_form = true;
			}
		}
		if ( ! empty( $s['strip'] ) || $preview ) {
			$inner = self::inner( $links, 'franja', $preview );
			if ( '' !== $inner ) {
				$html .= '<div class="bclaro-franja"' . ( empty( $s['strip'] ) ? ' style="display:none"' : '' ) . '>' . $inner . '</div>';
			}
		}
		if ( ( ! empty( $s['pill'] ) && ! $on_form ) || $preview ) {
			$inner = self::inner( $links, 'pill', $preview );
			$class = trim( 'bclaro-pill ' . (string) apply_filters( 'bclaro_pill_class', '' ) );
			if ( '' !== $inner ) {
				$html .= '<div class="' . esc_attr( $class ) . '"' . ( empty( $s['pill'] ) ? ' style="display:none"' : '' ) . '>' . $inner . '</div>';
			}
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
	}

	/**
	 * Whether a button shows in a placement. Pro hides it for excluded products or pages.
	 *
	 * @param string $type      Type.
	 * @param string $placement franja, pill, block or shortcode.
	 * @return bool
	 */
	public static function visible( $type, $placement ) {
		/**
		 * Show or hide one button in one placement.
		 *
		 * @param bool   $show      Show.
		 * @param string $type      arrepentimiento or baja.
		 * @param string $placement franja, pill, block or shortcode.
		 */
		return (bool) apply_filters( 'bclaro_button_visible', true, $type, $placement );
	}

	/**
	 * The links of one placement.
	 *
	 * @param array  $links     type => url.
	 * @param string $placement Placement.
	 * @param bool   $preview   Settings preview.
	 * @return string
	 */
	private static function inner( $links, $placement, $preview ) {
		$s     = Settings::get();
		$inner = '';
		foreach ( $links as $type => $url ) {
			if ( ! self::visible( $type, $placement ) ) {
				continue;
			}
			$attr   = ( $preview && 'baja' === $type && empty( $s['baja'] ) ) ? ' style="display:none"' : '';
			$inner .= str_replace( '<a ', '<a' . $attr . ' ', self::link( $type, $url ) );
		}
		return $inner;
	}

	/**
	 * Inline strip used by the block and shortcodes.
	 *
	 * @param string[] $types Types.
	 * @return string
	 */
	public static function inline( $types, $placement = 'block' ) {
		$links = self::links();
		$inner = '';
		foreach ( $types as $type ) {
			if ( isset( $links[ $type ] ) && self::visible( $type, $placement ) ) {
				$inner .= self::link( $type, $links[ $type ] );
			}
		}
		if ( '' === $inner ) {
			return '';
		}
		self::styles_late();
		return '<div class="bclaro-franja bclaro-bloque">' . $inner . '</div>';
	}

	/**
	 * Make sure the CSS is there when a block renders after the head (it normally is already).
	 */
	private static function styles_late() {
		if ( ! wp_style_is( self::HANDLE, 'enqueued' ) ) {
			self::styles();
		}
	}

	/**
	 * Block render.
	 *
	 * @param array $attrs Attributes.
	 * @return string
	 */
	public static function render_block( $attrs ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- block render callback signature; the block has no attributes.
		return self::inline( array( 'arrepentimiento', 'baja' ) );
	}

	/**
	 * [boton_arrepentimiento].
	 *
	 * @return string
	 */
	public static function shortcode_arr() {
		return self::inline( array( 'arrepentimiento' ), 'shortcode' );
	}

	/**
	 * [boton_baja].
	 *
	 * @return string
	 */
	public static function shortcode_baja() {
		return self::inline( array( 'baja' ), 'shortcode' );
	}
}
