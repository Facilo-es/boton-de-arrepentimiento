<?php
/**
 * One request: fields, clocks, order link, actions and the add-only timeline.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * AdminRequest.
 */
class AdminRequest {

	/**
	 * A small form posting to one of Free's admin_post handlers.
	 *
	 * @param object $req     Request.
	 * @param string $action  Handler suffix.
	 * @param string $label   Button words.
	 * @param string $class   Button class.
	 * @param string $confirm Confirm text, or ''.
	 * @param string $extra   Extra fields, already escaped.
	 * @return string
	 */
	private static function form( $req, $action, $label, $css = '', $confirm = '', $extra = '' ) {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( '' !== $confirm ? ' data-bclaro-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
		$out .= '<input type="hidden" name="action" value="bclaro_' . esc_attr( $action ) . '"><input type="hidden" name="id" value="' . (int) $req->id . '">';
		$out .= wp_nonce_field( 'bclaro_req_' . (int) $req->id, '_wpnonce', true, false );
		$out .= $extra . '<button type="submit" class="bclaro-btn ' . esc_attr( $css ) . '">' . esc_html( $label ) . '</button></form>';
		return $out;
	}

	/**
	 * The screen.
	 *
	 * @param int $id Request ID.
	 */
	public static function page( $id ) {
		$req = Requests::get( $id );
		echo '<div class="wrap bclaro-wrap">';
		if ( ! $req ) {
			Admin::title( __( 'Pedidos', 'boton-de-arrepentimiento' ) );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No encontramos ese pedido.', 'boton-de-arrepentimiento' ) . '</p></div>';
			echo '<p><a href="' . esc_url( Util::admin_url( 'bclaro' ) ) . '">' . esc_html__( 'Volver a los pedidos', 'boton-de-arrepentimiento' ) . '</a></p></div>';
			return;
		}
		$back  = '<a class="page-title-action" href="' . esc_url( Util::admin_url( 'bclaro' ) ) . '">' . esc_html__( 'Volver a los pedidos', 'boton-de-arrepentimiento' ) . '</a>';
		$title = sprintf( /* translators: %s: request code. */ __( 'Pedido %s', 'boton-de-arrepentimiento' ), $req->code );
		Admin::title( $title, $back );
		AdminList::message();

		$c = AdminList::clocks( $req );
		echo '<div class="bclaro-req"><div class="bclaro-main">';

		// Head: type, code, status.
		echo '<div class="box prop"><div class="pcode"><span class="code">' . esc_html( $req->code ) . '</span> ' . AdminList::pill( $req->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill().
		if ( $req->is_test ) {
			echo ' <span class="tag-test">' . esc_html__( 'Prueba', 'boton-de-arrepentimiento' ) . '</span>';
		}
		echo '</div><h2>' . esc_html( Util::type_label( $req->type ) ) . '</h2>';
		echo '<p class="ord">' . esc_html( sprintf( /* translators: %s: date and time. */ __( 'Recibido el %s', 'boton-de-arrepentimiento' ), Util::full( Util::ts( $req->created_at ) ) ) ) . '</p>';

		// Clocks.
		if ( $c['reply'] ) {
			$rc  = $c['reply'];
			$cls = 'done' === $rc['state'] ? 'done' : ( 'late' === $rc['state'] ? 'late' : ( 'soon' === $rc['state'] ? 'warn' : '' ) );
			echo '<div class="count"><div class="big ' . esc_attr( $cls ) . '">';
			if ( 'done' === $rc['state'] ) {
				echo '<span>' . esc_html( $rc['text'] ) . '</span>';
			} else {
				echo AdminList::big_number( $rc['text'], $rc['hours'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in big_number().
			}
			echo '</div><p class="how">' . esc_html__( 'Tenés 24 horas para la primera respuesta.', 'boton-de-arrepentimiento' ) . '</p>';
			if ( $c['proof'] ) {
				$pc = $c['proof'];
				if ( 'done' === $pc['state'] ) {
					$pt = __( 'Constancia enviada', 'boton-de-arrepentimiento' );
				} elseif ( 'late' === $pc['state'] ) {
					/* translators: %s: "1 hora" or "n horas". */
					$pt = sprintf( __( 'Constancia: vencida hace %s', 'boton-de-arrepentimiento' ), Util::hours( $pc['hours'] ) );
				} else {
					/* translators: %s: "1 hora" or "n horas". */
					$pt = sprintf( __( 'Constancia: quedan %s', 'boton-de-arrepentimiento' ), Util::hours( $pc['hours'] ) );
				}
				echo '<p class="tp ' . esc_attr( 'late' === $pc['state'] ? 'late' : ( 'soon' === $pc['state'] ? 'warn' : '' ) ) . '">' . esc_html( $pt ) . '</p>';
			}
			echo '</div>';
		}

		// Mail failed.
		if ( 'fallido' === $req->code_mail_status ) {
			echo '<div class="bclaro-alert"><p><strong>' . esc_html__( 'No pudimos enviar el código por correo.', 'boton-de-arrepentimiento' ) . '</strong> ' . esc_html__( 'El cliente vio su código en pantalla. Revisá que tu sitio pueda enviar correos.', 'boton-de-arrepentimiento' ) . '</p>';
			echo self::form( $req, 'retry', __( 'Reintentar envío', 'boton-de-arrepentimiento' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			echo '</div>';
		}

		// Actions.
		$mailto = 'mailto:' . rawurlencode( $req->email ) . '?subject=' . rawurlencode( sprintf( /* translators: %s: code. */ __( 'Tu pedido %s', 'boton-de-arrepentimiento' ), $req->code ) );
		echo '<div class="act">';
		if ( ! $req->replied_at && ! $req->is_test ) {
			echo self::form( $req, 'replied', __( 'Ya respondí', 'boton-de-arrepentimiento' ), 'pri' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		if ( '' !== (string) $req->email ) {
			echo '<a class="bclaro-btn" href="' . esc_url( $mailto, array( 'mailto' ) ) . '">' . esc_html__( 'Escribir al cliente', 'boton-de-arrepentimiento' ) . '</a>';
		}
		if ( 'baja' === $req->type && ! $req->proof_sent_at && '' !== (string) $req->email ) {
			/* translators: %s: customer email. */
			$confirm = sprintf( __( 'Vamos a mandarle la constancia a %s. Si cobrás con débito automático, cancelalo en tu medio de pago: este botón no lo hace.', 'boton-de-arrepentimiento' ), $req->email );
			echo self::form( $req, 'proof', __( 'Dar de baja y enviar constancia', 'boton-de-arrepentimiento' ), 'pri', $confirm ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		$sel = '<label class="screen-reader-text" for="bclaro-status">' . esc_html__( 'Estado', 'boton-de-arrepentimiento' ) . '</label><select id="bclaro-status" name="status">';
		foreach ( Util::statuses() as $k => $label ) {
			$sel .= '<option value="' . esc_attr( $k ) . '"' . selected( $req->status, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$sel .= '</select>';
		echo self::form( $req, 'status', __( 'Cambiar estado', 'boton-de-arrepentimiento' ), '', '', $sel ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		/**
		 * Pro's buttons on the request page.
		 *
		 * @param object $req Request.
		 */
		do_action( 'bclaro_request_actions', $req );
		if ( $req->is_test || 'spam' === $req->status ) {
			echo self::form( $req, 'delete', __( 'Borrar', 'boton-de-arrepentimiento' ), 'del', __( '¿Borramos este pedido? No se puede deshacer.', 'boton-de-arrepentimiento' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '</div></div>';

		// Fields.
		$rows = array(
			__( 'Nombre', 'boton-de-arrepentimiento' )   => $req->name,
			__( 'Correo', 'boton-de-arrepentimiento' )   => $req->email,
			__( 'Teléfono', 'boton-de-arrepentimiento' ) => $req->phone,
		);
		if ( 'baja' === $req->type ) {
			$rows[ __( 'Servicio', 'boton-de-arrepentimiento' ) ]                             = $req->service;
			$rows[ __( 'Número de cliente, pedido o contrato', 'boton-de-arrepentimiento' ) ] = $req->order_ref;
		} else {
			$rows[ __( 'Número de pedido', 'boton-de-arrepentimiento' ) ] = '' !== (string) $req->order_ref ? '#' . ltrim( $req->order_ref, '#' ) : '';
			$rows[ __( 'Qué devuelve', 'boton-de-arrepentimiento' ) ]     = 'parte' === $req->scope ? __( 'Una parte', 'boton-de-arrepentimiento' ) . ( '' !== (string) $req->detail ? ': ' . $req->detail : '' ) : __( 'Toda la compra', 'boton-de-arrepentimiento' );
		}
		$rows[ __( 'Mensaje', 'boton-de-arrepentimiento' ) ] = $req->message;
		echo '<div class="box"><h2>' . esc_html__( 'Datos del pedido', 'boton-de-arrepentimiento' ) . '</h2><dl class="bclaro-dl">';
		foreach ( $rows as $label => $value ) {
			$value = trim( (string) $value );
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . ( '' !== $value ? nl2br( esc_html( $value ) ) : '<span class="muted">—</span>' ) . '</dd>';
		}
		if ( $req->erased_at ) {
			echo '<dt>' . esc_html__( 'Datos personales', 'boton-de-arrepentimiento' ) . '</dt><dd>' . esc_html__( 'Borrados', 'boton-de-arrepentimiento' ) . '</dd>';
		}
		echo '</dl>';
		$o = OrderLink::state( $req );
		if ( '' !== $o['text'] ) {
			echo '<p class="bclaro-order ' . esc_attr( $o['state'] ) . '">';
			if ( '' !== $o['url'] ) {
				echo '<a href="' . esc_url( $o['url'] ) . '">' . esc_html( sprintf( /* translators: %s: order number. */ __( 'Pedido #%s', 'boton-de-arrepentimiento' ), $o['number'] ) ) . '</a> · ';
			}
			echo esc_html( $o['text'] ) . '</p>';
		}
		echo '</div>';

		// Timeline.
		echo '<div class="box"><h2>' . esc_html__( 'Historial', 'boton-de-arrepentimiento' ) . '</h2><ol class="bclaro-tl">';
		foreach ( Requests::events( $req->id ) as $e ) {
			$note = trim( (string) $e->note );
			echo '<li><time>' . esc_html( Util::short( Util::ts( $e->at ) ) ) . '</time> <span>' . esc_html( Requests::event_label( $e ) ) . '</span>' . ( '' !== $note && 'estado' !== $e->kind && 'respondido' !== $e->kind ? ' <small>' . esc_html( $note ) . '</small>' : '' ) . '</li>';
		}
		echo '</ol></div>';
		echo '</div><div class="bclaro-side">';
		/**
		 * Side panels, such as Pro's day counter.
		 *
		 * @param object $req Request.
		 */
		do_action( 'bclaro_request_panels', $req );
		echo '</div></div></div>';
	}
}
