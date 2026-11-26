<?php
/**
 * Personal data exporter and eraser by email, and the policy text (F13).
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy.
 */
class Privacy {

	/**
	 * Fields blanked when data is erased.
	 */
	const FIELDS = array( 'name', 'email', 'phone', 'message', 'detail' );

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $e Exporters.
	 * @return array
	 */
	public static function exporters( $e ) {
		$e['facilo-boton-arrepentimiento'] = array(
			'exporter_friendly_name' => __( 'Facilo Botón de Arrepentimiento: pedidos', 'facilo-boton-arrepentimiento' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $e;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $e Erasers.
	 * @return array
	 */
	public static function erasers( $e ) {
		$e['facilo-boton-arrepentimiento'] = array(
			'eraser_friendly_name' => __( 'Facilo Botón de Arrepentimiento: pedidos', 'facilo-boton-arrepentimiento' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $e;
	}

	/**
	 * Requests for one email, 50 per page.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	private static function rows( $email, $page ) {
		global $wpdb;
		$t = Install::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, privacy tools.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE email = %s ORDER BY id LIMIT %d OFFSET %d', $t['requests'], $email, 50, ( max( 1, (int) $page ) - 1 ) * 50 ) );
	}

	/**
	 * Exporter.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$rows = self::rows( $email, $page );
		$data = array();
		foreach ( $rows as $r ) {
			$items = array(
				__( 'Código', 'facilo-boton-arrepentimiento' ) => $r->code,
				__( 'Tipo', 'facilo-boton-arrepentimiento' )   => Util::type_label( $r->type ),
				__( 'Recibido', 'facilo-boton-arrepentimiento' ) => Util::full( Util::ts( $r->created_at ) ),
				__( 'Nombre', 'facilo-boton-arrepentimiento' ) => $r->name,
				__( 'Correo', 'facilo-boton-arrepentimiento' ) => $r->email,
				__( 'Teléfono', 'facilo-boton-arrepentimiento' ) => $r->phone,
				__( 'Pedido', 'facilo-boton-arrepentimiento' ) => $r->order_ref,
				__( 'Servicio', 'facilo-boton-arrepentimiento' ) => $r->service,
				__( 'Detalle', 'facilo-boton-arrepentimiento' ) => $r->detail,
				__( 'Mensaje', 'facilo-boton-arrepentimiento' ) => $r->message,
			);
			$list  = array();
			foreach ( $items as $name => $value ) {
				if ( '' !== (string) $value ) {
					$list[] = array(
						'name'  => $name,
						'value' => (string) $value,
					);
				}
			}
			$data[] = array(
				'group_id'    => 'facilo-boton-arrepentimiento',
				'group_label' => __( 'Pedidos de arrepentimiento y baja', 'facilo-boton-arrepentimiento' ),
				'item_id'     => 'bclaro-' . $r->id,
				'data'        => $list,
			);
		}
		return array(
			'data' => $data,
			'done' => count( $rows ) < 50,
		);
	}

	/**
	 * Eraser: keeps open requests and says why; blanks closed ones.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$rows     = self::rows( $email, 1 );
		$removed  = false;
		$retained = false;
		$messages = array();
		foreach ( $rows as $r ) {
			if ( Requests::is_open( $r ) ) {
				$retained = true;
				/* translators: %s: request code. */
				$messages[] = sprintf( __( 'El pedido %s sigue abierto: la tienda todavía tiene que responderlo, así que guardamos sus datos hasta cerrarlo.', 'facilo-boton-arrepentimiento' ), $r->code );
				continue;
			}
			self::blank( (int) $r->id );
			$removed = true;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Blank the personal fields of one request and log it.
	 *
	 * @param int $id Request ID.
	 */
	public static function blank( $id ) {
		$cols              = array_fill_keys( self::FIELDS, '' );
		$cols['erased_at'] = Util::mysql();
		Requests::update( $id, $cols );
		Requests::event( $id, 'datos_borrados' );
	}

	/**
	 * Suggested policy text.
	 */
	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$years = (int) Settings::get( 'retention_years' );
		$text  = '<p>' . esc_html__( 'Cuando usás el botón de arrepentimiento o el botón de baja, guardamos tu nombre, tu correo, tu teléfono si lo dejás, el número de pedido y tu mensaje, para responder tu pedido.', 'facilo-boton-arrepentimiento' ) . '</p>'
			. '<p>' . esc_html(
				sprintf(
					/* translators: %d: years. */
					_n( 'Estos datos quedan en esta tienda y no se envían a nadie más. Los borramos %d año después de cerrar tu pedido. Guardamos solo el código y las fechas.', 'Estos datos quedan en esta tienda y no se envían a nadie más. Los borramos %d años después de cerrar tu pedido. Guardamos solo el código y las fechas.', $years, 'facilo-boton-arrepentimiento' ),
					$years
				)
			) . '</p>';
		wp_add_privacy_policy_content( 'Facilo Botón de Arrepentimiento', wp_kses_post( $text ) );
	}
}
