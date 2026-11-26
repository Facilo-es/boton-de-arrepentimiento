<?php
/**
 * Link a request to a WooCommerce order, and compare the emails. Never rules on anything.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Order link.
 */
class OrderLink {

	/**
	 * The order ID for what the customer typed, or null.
	 *
	 * @param string $ref Typed number.
	 * @return int|null
	 */
	public static function find_id( $ref ) {
		$ref = trim( str_replace( array( '#', ' ', 'Nº', 'nº', 'N°' ), '', (string) $ref ) );
		$id  = null;
		if ( '' === $ref ) {
			return null;
		}
		if ( Util::woo() && function_exists( 'wc_get_order' ) && ctype_digit( $ref ) ) {
			$order = wc_get_order( (int) $ref );
			if ( $order && ! is_a( $order, 'WC_Order_Refund' ) ) {
				$id = (int) $order->get_id();
			}
		}
		if ( ! $id ) {
			/**
			 * Find an order Free didn't, such as with a sequential numbers plugin.
			 *
			 * @param int|null $id  Order ID.
			 * @param string   $ref Typed number, without # and spaces.
			 */
			$id = apply_filters( 'bclaro_find_order', null, $ref );
		}
		return $id ? (int) $id : null;
	}

	/**
	 * The order of a request and whether the email matches.
	 *
	 * @param object $req Request.
	 * @return array state (confirmado, dudoso, sin_pedido, sin_numero, sin_woo), text, url, number.
	 */
	public static function state( $req ) {
		$out = array(
			'state'  => 'sin_numero',
			'text'   => '',
			'url'    => '',
			'number' => '',
		);
		if ( '' === (string) $req->order_ref || 'arrepentimiento' !== $req->type ) {
			return $out;
		}
		if ( ! Util::woo() || ! function_exists( 'wc_get_order' ) ) {
			$out['state'] = 'sin_woo';
			return $out;
		}
		$order = $req->order_id ? wc_get_order( (int) $req->order_id ) : false;
		if ( ! $order ) {
			$id    = self::find_id( $req->order_ref );
			$order = $id ? wc_get_order( $id ) : false;
		}
		if ( ! $order ) {
			$out['state'] = 'sin_pedido';
			$out['text']  = __( 'No encontramos ese pedido', 'facilo-boton-arrepentimiento' );
			return $out;
		}
		$out['number'] = (string) $order->get_order_number();
		$out['url']    = (string) $order->get_edit_order_url();
		if ( strtolower( trim( (string) $order->get_billing_email() ) ) === strtolower( trim( (string) $req->email ) ) ) {
			$out['state'] = 'confirmado';
			$out['text']  = __( 'El correo coincide', 'facilo-boton-arrepentimiento' );
		} else {
			$out['state'] = 'dudoso';
			$out['text']  = __( 'El correo no coincide', 'facilo-boton-arrepentimiento' );
		}
		return $out;
	}
}
