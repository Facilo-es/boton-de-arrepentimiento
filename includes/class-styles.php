<?php
/**
 * Button styles, colour rules and the style picked for the shop.
 *
 * The colour rules are a port of the design's estilo.js, so the admin preview and the site agree.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Styles.
 */
class Styles {

	const INK = '#101208';

	/**
	 * Registered styles: id => label, desc, css path. Pro adds more through bclaro_styles.
	 *
	 * @return array
	 */
	public static function all() {
		$dir    = BCLARO_DIR . 'assets/styles/';
		$styles = array(
			'banda'    => array(
				'label' => 'Banda',
				'desc'  => __( 'Una franja ancha del color de tu tienda', 'facilo-boton-arrepentimiento' ),
				'css'   => $dir . 'banda.css',
			),
			'capsula'  => array(
				'label' => 'Cápsula',
				'desc'  => __( 'Botones redondeados, flotan con sombra', 'facilo-boton-arrepentimiento' ),
				'css'   => $dir . 'capsula.css',
			),
			'contorno' => array(
				'label' => 'Contorno',
				'desc'  => __( 'Blanco con borde, sobrio y prolijo', 'facilo-boton-arrepentimiento' ),
				'css'   => $dir . 'contorno.css',
			),
		);
		$styles = apply_filters( 'bclaro_styles', $styles );
		return is_array( $styles ) && isset( $styles['banda'] ) ? $styles : array( 'banda' => $styles['banda'] ?? array() );
	}

	/**
	 * Read a CSS file and squeeze its whitespace.
	 *
	 * @param string $path File.
	 * @return string
	 */
	public static function read( $path ) {
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		return self::minify( $css );
	}

	/**
	 * Remove comments and line breaks.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function minify( $css ) {
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = preg_replace( '/\s*\n\s*/', '', $css );
		return trim( $css );
	}

	/**
	 * Hex to RGB.
	 *
	 * @param string $h Colour.
	 * @return int[]
	 */
	public static function rgb( $h ) {
		$h = ltrim( $h, '#' );
		return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) );
	}

	/**
	 * Relative luminance.
	 *
	 * @param string $h Colour.
	 * @return float
	 */
	public static function lum( $h ) {
		$c = array();
		foreach ( self::rgb( $h ) as $x ) {
			$x   = $x / 255;
			$c[] = $x <= .03928 ? $x / 12.92 : pow( ( $x + .055 ) / 1.055, 2.4 );
		}
		return .2126 * $c[0] + .7152 * $c[1] + .0722 * $c[2];
	}

	/**
	 * Contrast ratio.
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 * @return float
	 */
	public static function ratio( $a, $b ) {
		$x = self::lum( $a );
		$y = self::lum( $b );
		return ( max( $x, $y ) + .05 ) / ( min( $x, $y ) + .05 );
	}

	/**
	 * Text colour on a fill.
	 *
	 * @param string $c Fill.
	 * @return string
	 */
	public static function text_on( $c ) {
		return self::ratio( $c, '#ffffff' ) >= self::ratio( $c, self::INK ) ? '#ffffff' : self::INK;
	}

	/**
	 * Whether white or ink text reaches 4.5:1.
	 *
	 * @param string $c Fill.
	 * @return bool
	 */
	public static function legible( $c ) {
		return max( self::ratio( $c, '#ffffff' ), self::ratio( $c, self::INK ) ) >= 4.5;
	}

	/**
	 * HSL of a colour, as estilo.js.
	 *
	 * @param string $h Colour.
	 * @return float[]
	 */
	public static function hsl( $h ) {
		list( $r, $g, $b ) = array_map(
			static function ( $x ) {
				return $x / 255;
			},
			self::rgb( $h )
		);
		$mx                = max( $r, $g, $b );
		$mn                = min( $r, $g, $b );
		$l                 = ( $mx + $mn ) / 2;
		$d                 = $mx - $mn;
		$hh                = 0;
		$s                 = 0;
		if ( $d > 0 ) {
			$s = $d / ( 1 - abs( 2 * $l - 1 ) );
			if ( $mx === $r ) {
				$hh = fmod( ( $g - $b ) / $d, 6 );
			} elseif ( $mx === $g ) {
				$hh = ( $b - $r ) / $d + 2;
			} else {
				$hh = ( $r - $g ) / $d + 4;
			}
			$hh *= 60;
		}
		return array( fmod( $hh + 360, 360 ), $s, $l );
	}

	/**
	 * HSL to hex, as estilo.js.
	 *
	 * @param float $h Hue.
	 * @param float $s Saturation.
	 * @param float $l Lightness.
	 * @return string
	 */
	public static function hex( $h, $s, $l ) {
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;
		if ( $h < 60 ) {
			$v = array( $c, $x, 0 );
		} elseif ( $h < 120 ) {
			$v = array( $x, $c, 0 );
		} elseif ( $h < 180 ) {
			$v = array( 0, $c, $x );
		} elseif ( $h < 240 ) {
			$v = array( 0, $x, $c );
		} elseif ( $h < 300 ) {
			$v = array( $x, 0, $c );
		} else {
			$v = array( $c, 0, $x );
		}
		$out = '#';
		foreach ( $v as $ch ) {
			$n    = (int) self::js_round( ( $ch + $m ) * 255 );
			$out .= str_pad( dechex( max( 0, min( 255, $n ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/**
	 * JavaScript's Math.round: halves go up.
	 *
	 * @param float $x Number.
	 * @return float
	 */
	private static function js_round( $x ) {
		return floor( $x + 0.5 );
	}

	/**
	 * Aurora's second colour, as estilo.js.
	 *
	 * @param string $c Colour.
	 * @return string
	 */
	public static function second( $c ) {
		list( $h, $s, $l ) = self::hsl( $c );
		$t                 = self::text_on( $c );
		for ( $k = 0; $k <= 1; $k += .05 ) {
			$x = self::hex( fmod( $h + 48 * ( 1 - $k ), 360 ), max( $s, .45 ), $l + ( self::INK === $t ? .04 : -.04 ) * ( 1 - $k ) );
			if ( self::ratio( $x, $t ) >= 4.5 ) {
				return $x;
			}
		}
		return $c;
	}

	/**
	 * CSS variables for a colour.
	 *
	 * @param string $c Colour.
	 * @return array
	 */
	public static function vars( $c ) {
		return array(
			'--bc' => $c,
			'--bt' => self::text_on( $c ),
			'--bx' => self::ratio( $c, '#ffffff' ) >= 4.5 ? $c : self::INK,
			'--b2' => self::second( $c ),
		);
	}

	/**
	 * Normalise a colour to #rrggbb, or '' if invalid.
	 *
	 * @param string $c Colour.
	 * @return string
	 */
	public static function clean_color( $c ) {
		$c = strtolower( trim( (string) $c ) );
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $c, $m ) ) {
			$c = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $c ) ? $c : '';
	}

	/**
	 * Short hex where possible, to keep the inline CSS small.
	 *
	 * @param string $c Colour.
	 * @return string
	 */
	private static function short_hex( $c ) {
		if ( preg_match( '/^#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3$/', $c, $m ) ) {
			return '#' . $m[1] . $m[2] . $m[3];
		}
		return $c;
	}

	/**
	 * The inline CSS for the site: base, the chosen style and one variables line.
	 *
	 * @param string $style Style id.
	 * @param string $color Colour.
	 * @param array  $s     Settings, for details Pro may add.
	 * @return string
	 */
	public static function css( $style, $color, $s = array() ) {
		$all   = self::all();
		$style = isset( $all[ $style ] ) ? $style : 'banda';
		$color = self::clean_color( $color );
		if ( '' === $color || ! self::legible( $color ) ) {
			$color = '#a8421f';
		}
		$vars = self::vars( $color );
		if ( 'aurora' !== $style ) {
			unset( $vars['--b2'] );
		}
		/**
		 * Extra details for the buttons, such as corners or size, added by Pro.
		 *
		 * @param array  $details Array with 'vars' (name => value) and 'css' (string).
		 * @param string $style   Style id.
		 * @param array  $s       Settings.
		 */
		$details = apply_filters(
			'bclaro_button_details',
			array(
				'vars' => array(),
				'css'  => '',
			),
			$style,
			$s
		);
		if ( ! empty( $details['vars'] ) && is_array( $details['vars'] ) ) {
			$vars = array_merge( $vars, $details['vars'] );
		}
		$line = array();
		foreach ( $vars as $k => $v ) {
			$line[] = $k . ':' . self::short_hex( (string) $v );
		}
		$css  = self::read( BCLARO_DIR . 'assets/styles/base.css' );
		$css .= self::read( $all[ $style ]['css'] );
		$css .= '.bclaro-franja,.bclaro-pill,.bc{' . implode( ';', $line ) . '}';
		if ( ! empty( $details['css'] ) ) {
			$css .= self::minify( (string) $details['css'] );
		}
		return $css;
	}

	/**
	 * Scope a style's CSS under a selector, for the gallery tiles in admin.
	 *
	 * @param string $css   CSS.
	 * @param string $scope Scope selector.
	 * @return string
	 */
	public static function scope( $css, $scope ) {
		$out = '';
		$i   = 0;
		$n   = strlen( $css );
		while ( $i < $n ) {
			$open = strpos( $css, '{', $i );
			if ( false === $open ) {
				break;
			}
			$head = trim( substr( $css, $i, $open - $i ) );
			if ( 0 === strpos( $head, '@media' ) || 0 === strpos( $head, '@supports' ) ) {
				$depth = 1;
				$j     = $open + 1;
				while ( $j < $n && $depth > 0 ) {
					if ( '{' === $css[ $j ] ) {
						++$depth;
					} elseif ( '}' === $css[ $j ] ) {
						--$depth;
					}
					++$j;
				}
				$inner = substr( $css, $open + 1, $j - $open - 2 );
				if ( 0 === strpos( $head, '@media' ) ) {
					$out .= $head . '{' . self::scope( $inner, $scope ) . '}';
				}
				$i = $j;
				continue;
			}
			$close = strpos( $css, '}', $open );
			if ( false === $close ) {
				break;
			}
			$body = substr( $css, $open + 1, $close - $open - 1 );
			if ( 0 === strpos( $head, '@keyframes' ) ) {
				$depth = 1;
				$j     = $open + 1;
				while ( $j < $n && $depth > 0 ) {
					if ( '{' === $css[ $j ] ) {
						++$depth;
					} elseif ( '}' === $css[ $j ] ) {
						--$depth;
					}
					++$j;
				}
				$i = $j;
				continue;
			}
			$sels = array();
			foreach ( explode( ',', $head ) as $sel ) {
				$sels[] = $scope . ' ' . trim( $sel );
			}
			$out .= implode( ',', $sels ) . '{' . $body . '}';
			$i    = $close + 1;
		}
		return $out;
	}

	/**
	 * Theme colours to offer as swatches.
	 *
	 * @return string[]
	 */
	public static function palette() {
		$out = array();
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$pal = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
			if ( is_array( $pal ) ) {
				foreach ( $pal as $p ) {
					$c = isset( $p['color'] ) ? self::clean_color( $p['color'] ) : '';
					if ( $c && self::legible( $c ) && self::ratio( $c, '#ffffff' ) > 1.6 && ! in_array( $c, $out, true ) ) {
						$out[] = $c;
					}
				}
			}
		}
		$btn = self::theme_button();
		if ( $btn['bg'] && self::legible( $btn['bg'] ) && ! in_array( $btn['bg'], $out, true ) ) {
			array_unshift( $out, $btn['bg'] );
		}
		foreach ( array( '#a8421f', '#3a2c22', '#16263a', '#0b4ea2', '#6b7b52' ) as $c ) {
			if ( count( $out ) >= 5 ) {
				break;
			}
			if ( ! in_array( $c, $out, true ) ) {
				$out[] = $c;
			}
		}
		return array_slice( $out, 0, 6 );
	}

	/**
	 * Read what the theme says about its buttons.
	 *
	 * @return array bg, border, radius (px or -1), gradient, page background.
	 */
	public static function theme_button() {
		$b = array(
			'bg'       => '',
			'border'   => false,
			'radius'   => -1,
			'gradient' => false,
			'page'     => '',
			'source'   => '',
		);
		if ( function_exists( 'wp_get_global_styles' ) && wp_theme_has_theme_json() ) {
			$st = wp_get_global_styles( array(), array( 'transforms' => array( 'resolve-variables' ) ) );
			$el = isset( $st['elements']['button'] ) ? $st['elements']['button'] : array();
			if ( ! empty( $el['color']['gradient'] ) ) {
				$b['gradient'] = true;
			}
			if ( ! empty( $el['color']['background'] ) ) {
				$b['bg'] = self::clean_color( $el['color']['background'] );
				if ( 'transparent' === $el['color']['background'] ) {
					$b['bg'] = '';
				}
			}
			if ( ! empty( $el['border']['width'] ) ) {
				$b['border'] = true;
			}
			if ( isset( $el['border']['radius'] ) && is_string( $el['border']['radius'] ) ) {
				$b['radius'] = self::px( $el['border']['radius'] );
			}
			if ( ! empty( $st['color']['background'] ) ) {
				$b['page'] = self::clean_color( $st['color']['background'] );
			}
			if ( $b['bg'] ) {
				$b['source'] = 'button';
			}
		}
		if ( ! $b['bg'] ) {
			foreach ( array( 'storefront_button_background_color', 'button_background_color', 'button-bg-color', 'theme-color', 'accent_color' ) as $mod ) {
				$c = self::clean_color( (string) get_theme_mod( $mod, '' ) );
				if ( $c ) {
					$b['bg']     = $c;
					$b['source'] = 'button';
					break;
				}
			}
		}
		if ( ! $b['page'] ) {
			$bg = (string) get_background_color();
			if ( $bg ) {
				$b['page'] = self::clean_color( '#' . ltrim( $bg, '#' ) );
			}
		}
		return $b;
	}

	/**
	 * A CSS length in px, roughly. Rem and em count as 16px.
	 *
	 * @param string $v Value.
	 * @return int
	 */
	private static function px( $v ) {
		if ( preg_match( '/^([\d.]+)\s*(px|rem|em|%)?$/', trim( $v ), $m ) ) {
			$n = (float) $m[1];
			if ( isset( $m[2] ) && ( 'rem' === $m[2] || 'em' === $m[2] ) ) {
				$n *= 16;
			}
			if ( isset( $m[2] ) && '%' === $m[2] && $n >= 50 ) {
				$n = 999;
			}
			return (int) round( $n );
		}
		if ( preg_match( '/9999|999/', $v ) ) {
			return 999;
		}
		return -1;
	}

	/**
	 * Pick a style and colour for this shop, with the reason line.
	 *
	 * Rules from the design: dark page > Neón, grey buttons > Reloj, gradient > Aurora, transparent
	 * with border > Contorno, radius 20 or more > Cápsula, 4 or less > Bloque, else Banda. Free maps
	 * the Pro styles to Banda.
	 *
	 * @return array style, color, why.
	 */
	public static function recommend() {
		$b    = self::theme_button();
		$pick = 'banda';
		$why  = '';
		if ( $b['page'] && self::lum( $b['page'] ) < .05 ) {
			$pick = 'neon';
			$why  = 'tu tienda tiene fondo oscuro y una franja de color se destaca sobre él';
		} elseif ( $b['bg'] && self::hsl( $b['bg'] )[1] < .12 ) {
			$pick = 'reloj';
			$why  = 'tus botones son grises o negros, y una franja de color los distingue';
		} elseif ( $b['gradient'] ) {
			$pick = 'aurora';
			$why  = 'tus botones usan degradé, y una franja del mismo tono acompaña ese estilo';
		} elseif ( ! $b['bg'] && $b['border'] ) {
			$pick = 'contorno';
			$why  = 'tus botones tienen borde y fondo transparente';
		} elseif ( $b['radius'] >= 20 ) {
			$pick = 'capsula';
			$why  = 'tus botones son redondeados';
		} elseif ( $b['radius'] >= 0 && $b['radius'] <= 4 ) {
			$pick = 'bloque';
			$why  = 'tus botones tienen esquinas rectas, y una franja ancha se ve sin competir con ellos';
		} else {
			$why = 'se ve bien en cualquier tienda';
		}
		$all = self::all();
		/**
		 * Styles the automatic pick may choose. Free: its own three.
		 *
		 * @param string[] $allowed Style ids.
		 */
		$allowed = (array) apply_filters( 'bclaro_recommend_styles', array( 'banda', 'capsula', 'contorno' ) );
		if ( ! isset( $all[ $pick ] ) || ! in_array( $pick, $allowed, true ) ) {
			$pick = 'banda';
			$why  = 'se ve bien en cualquier tienda';
		}
		$color = '';
		$from  = '';
		if ( $b['bg'] && self::legible( $b['bg'] ) ) {
			$color = $b['bg'];
			$from  = 'el de tus botones';
		} else {
			$pal = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) : array();
			if ( is_array( $pal ) ) {
				foreach ( $pal as $p ) {
					$c = isset( $p['color'] ) ? self::clean_color( $p['color'] ) : '';
					if ( $c && self::legible( $c ) && self::ratio( $c, '#ffffff' ) >= 3 ) {
						$color = $c;
						$from  = 'el de tu tema';
						break;
					}
				}
			}
		}
		if ( ! $color ) {
			$color = '#a8421f';
			$from  = 'uno cálido que se lee bien, porque tu tema no define uno';
		}
		$label = $all[ $pick ]['label'];
		return array(
			'style' => $pick,
			'color' => $color,
			/* translators: 1: style name, 2: reason, 3: where the colour comes from. */
			'why'   => sprintf( __( 'Elegimos %1$s porque %2$s, y el color es %3$s.', 'facilo-boton-arrepentimiento' ), $label, $why, $from ),
		);
	}
}
