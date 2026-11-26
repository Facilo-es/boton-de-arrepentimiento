<?php
/**
 * Allowed HTML for the plugin's own markup, used to escape it on output.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Allow-lists for wp_kses(), covering every tag and attribute the plugin prints.
 */
class Kses {

	/**
	 * Cached allow-list.
	 *
	 * @var array|null
	 */
	private static $allowed = null;

	/**
	 * Tags and attributes the plugin's admin, front and email markup uses.
	 *
	 * @return array
	 */
	public static function allowed() {
		if ( null !== self::$allowed ) {
			return self::$allowed;
		}
		$common = array(
			'class'            => true,
			'id'               => true,
			'style'            => true,
			'title'            => true,
			'role'             => true,
			'lang'             => true,
			'dir'              => true,
			'hidden'           => true,
			'tabindex'         => true,
			'aria-label'       => true,
			'aria-hidden'      => true,
			'aria-live'        => true,
			'aria-current'     => true,
			'aria-describedby' => true,
			'aria-expanded'    => true,
			'aria-controls'    => true,
			'data-*'           => true,
		);
		$tags   = array( 'a', 'b', 'br', 'button', 'code', 'dd', 'div', 'dl', 'dt', 'em', 'fieldset', 'form', 'h1', 'h2', 'h3', 'h4', 'hr', 'i', 'img', 'input', 'label', 'legend', 'li', 'nav', 'ol', 'option', 'p', 'section', 'select', 'small', 'span', 'strong', 'table', 'tbody', 'td', 'textarea', 'th', 'thead', 'time', 'tr', 'ul' );
		$out    = array();
		foreach ( $tags as $tag ) {
			$out[ $tag ] = $common;
		}
		$out['a']        += array(
			'href'   => true,
			'rel'    => true,
			'target' => true,
		);
		$out['button']   += array(
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'disabled' => true,
		);
		$out['form']     += array(
			'action' => true,
			'method' => true,
		);
		$out['img']      += array(
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
		);
		$out['input']    += array(
			'type'        => true,
			'name'        => true,
			'value'       => true,
			'checked'     => true,
			'disabled'    => true,
			'placeholder' => true,
			'required'    => true,
			'min'         => true,
			'max'         => true,
			'step'        => true,
		);
		$out['label']    += array( 'for' => true );
		$out['option']   += array(
			'value'    => true,
			'selected' => true,
		);
		$out['select']   += array(
			'name'     => true,
			'disabled' => true,
		);
		$out['textarea'] += array(
			'name' => true,
			'rows' => true,
		);
		$out['time']     += array( 'datetime' => true );
		foreach ( array( 'table', 'td', 'th' ) as $tag ) {
			$out[ $tag ] += array(
				'width'       => true,
				'align'       => true,
				'valign'      => true,
				'bgcolor'     => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'border'      => true,
			);
		}
		// The plugin's mark is an inline SVG.
		$svg           = array(
			'class'           => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		);
		$out['svg']    = $svg + array(
			'width'       => true,
			'height'      => true,
			'viewbox'     => true,
			'xmlns'       => true,
			'aria-hidden' => true,
			'focusable'   => true,
			'role'        => true,
		);
		$out['g']      = $svg;
		$out['path']   = $svg + array( 'd' => true );
		$out['rect']   = $svg + array(
			'x'      => true,
			'y'      => true,
			'width'  => true,
			'height' => true,
			'rx'     => true,
		);
		$out['circle'] = $svg + array(
			'cx' => true,
			'cy' => true,
			'r'  => true,
		);
		self::$allowed = $out;
		return $out;
	}
}
