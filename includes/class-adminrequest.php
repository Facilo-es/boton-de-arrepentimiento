<?php
/**
 * One request: fields, clocks, order link, actions and the add-only timeline.
 *
 * @package FaciloBotonArrepentimiento
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
			Admin::title( __( 'Pedidos', 'facilo-boton-arrepentimiento' ) );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No encontramos ese pedido.', 'facilo-boton-arrepentimiento' ) . '</p></div>';
			echo '<p><a href="' . esc_url( Util::admin_url( 'bclaro' ) ) . '">' . esc_html__( 'Volver a los pedidos', 'facilo-boton-arrepentimiento' ) . '</a></p></div>';
			return;
		}
		$back  = '<a class="page-title-action" href="' . esc_url( Util::admin_url( 'bclaro' ) ) . '">' . esc_html__( 'Volver a los pedidos', 'facilo-boton-arrepentimiento' ) . '</a>';
		$title = sprintf( /* translators: %s: request code. */ __( 'Pedido %s', 'facilo-boton-arrepentimiento' ), $req->code );
		Admin::title( $title, $back );
		AdminList::message();

		$c = AdminList::clocks( $req );
		echo '<div class="bclaro-req"><div class="bclaro-main">';

		// Head: type, code, status.
		echo '<div class="box prop"><div class="pcode"><span class="code">' . esc_html( $req->code ) . '</span> ' . wp_kses( AdminList::pill( $req->status ), Kses::allowed() );
		if ( $req->is_test ) {
			echo ' <span class="tag-test">' . esc_html__( 'Prueba', 'facilo-boton-arrepentimiento' ) . '</span>';
		}
		echo '</div><h2>' . esc_html( Util::type_label( $req->type ) ) . '</h2>';
		echo '<p class="ord">' . esc_html( sprintf( /* translators: %s: date and time. */ __( 'Recibido el %s', 'facilo-boton-arrepentimiento' ), Util::full( Util::ts( $req->created_at ) ) ) ) . '</p>';
		$first = ! empty( $req->repeat_of ) ? Requests::get( (int) $req->repeat_of ) : null;
		if ( $first ) {
			echo '<p class="ord bclaro-repeat">' . esc_html__( 'El cliente volvió a enviar un pedido igual. Lo atendés en el primero:', 'facilo-boton-arrepentimiento' ) . ' <a class="code" href="' . esc_url( Util::admin_url( 'bclaro', array( 'id' => (int) $first->id ) ) ) . '">' . esc_html( $first->code ) . '</a></p>';
		}

		// Clocks.
		if ( $c['reply'] ) {
			$rc  = $c['reply'];
			$cls = 'done' === $rc['state'] ? 'done' : ( 'late' === $rc['state'] ? 'late' : ( 'soon' === $rc['state'] ? 'warn' : '' ) );
			echo '<div class="count"><div class="big ' . esc_attr( $cls ) . '">';
			if ( 'done' === $rc['state'] ) {
				echo '<span>' . esc_html( $rc['text'] ) . '</span>';
			} else {
				echo wp_kses( AdminList::big_number( $rc['text'], $rc['hours'] ), Kses::allowed() );
			}
			echo '</div><p class="how">' . esc_html__( 'Tenés 24 horas para la primera respuesta.', 'facilo-boton-arrepentimiento' ) . '</p>';
			if ( $c['proof'] ) {
				$pc = $c['proof'];
				if ( 'done' === $pc['state'] ) {
					$pt = __( 'Constancia enviada', 'facilo-boton-arrepentimiento' );
				} elseif ( 'late' === $pc['state'] ) {
					/* translators: %s: "1 hora" or "n horas". */
					$pt = sprintf( __( 'Constancia: vencida hace %s', 'facilo-boton-arrepentimiento' ), Util::hours( $pc['hours'] ) );
				} else {
					/* translators: %s: "1 hora" or "n horas". */
					$pt = sprintf( __( 'Constancia: quedan %s', 'facilo-boton-arrepentimiento' ), Util::hours( $pc['hours'] ) );
				}
				echo '<p class="tp ' . esc_attr( 'late' === $pc['state'] ? 'late' : ( 'soon' === $pc['state'] ? 'warn' : '' ) ) . '">' . esc_html( $pt ) . '</p>';
			}
			echo '</div>';
		}

		// Mail failed.
		if ( 'fallido' === $req->code_mail_status ) {
			echo '<div class="bclaro-alert"><p><strong>' . esc_html__( 'No pudimos enviar el código por correo.', 'facilo-boton-arrepentimiento' ) . '</strong> ' . esc_html__( 'El cliente vio su código en pantalla. Revisá que tu sitio pueda enviar correos.', 'facilo-boton-arrepentimiento' ) . '</p>';
			echo wp_kses( self::form( $req, 'retry', __( 'Reintentar envío', 'facilo-boton-arrepentimiento' ) ), Kses::allowed() );
			echo '</div>';
		}

		// Actions.
		$mailto = 'mailto:' . rawurlencode( $req->email ) . '?subject=' . rawurlencode( sprintf( /* translators: %s: code. */ __( 'Tu pedido %s', 'facilo-boton-arrepentimiento' ), $req->code ) );
		echo '<div class="act">';
		if ( ! $req->replied_at && ! $req->is_test ) {
			echo wp_kses( self::form( $req, 'replied', __( 'Ya respondí', 'facilo-boton-arrepentimiento' ), 'pri' ), Kses::allowed() );
		}
		if ( '' !== (string) $req->email ) {
			echo '<a class="bclaro-btn" href="' . esc_url( $mailto, array( 'mailto' ) ) . '">' . esc_html__( 'Escribir al cliente', 'facilo-boton-arrepentimiento' ) . '</a>';
		}
		if ( 'baja' === $req->type && ! $req->proof_sent_at && '' !== (string) $req->email ) {
			/* translators: %s: customer email. */
			$confirm = sprintf( __( 'Vamos a mandarle la constancia a %s. Si cobrás con débito automático, cancelalo en tu medio de pago: este botón no lo hace.', 'facilo-boton-arrepentimiento' ), $req->email );
			echo wp_kses( self::form( $req, 'proof', __( 'Dar de baja y enviar constancia', 'facilo-boton-arrepentimiento' ), 'pri', $confirm ), Kses::allowed() );
		}
		$sel = '<label class="screen-reader-text" for="bclaro-status">' . esc_html__( 'Estado', 'facilo-boton-arrepentimiento' ) . '</label><select id="bclaro-status" name="status">';
		foreach ( Util::statuses() as $k => $label ) {
			$sel .= '<option value="' . esc_attr( $k ) . '"' . selected( $req->status, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$sel .= '</select>';
		echo wp_kses( self::form( $req, 'status', __( 'Cambiar estado', 'facilo-boton-arrepentimiento' ), '', '', $sel ), Kses::allowed() );
		/**
		 * Pro's buttons on the request page.
		 *
		 * @param object $req Request.
		 */
		do_action( 'bclaro_request_actions', $req );
		if ( $req->is_test || 'spam' === $req->status ) {
			echo wp_kses( self::form( $req, 'delete', __( 'Borrar', 'facilo-boton-arrepentimiento' ), 'del', __( '¿Borramos este pedido? No se puede deshacer.', 'facilo-boton-arrepentimiento' ) ), Kses::allowed() );
		}
		echo '</div></div>';

		// Fields.
		$rows = array(
			__( 'Nombre', 'facilo-boton-arrepentimiento' ) => $req->name,
			__( 'Correo', 'facilo-boton-arrepentimiento' ) => $req->email,
			__( 'Teléfono', 'facilo-boton-arrepentimiento' ) => $req->phone,
		);
		if ( 'baja' === $req->type ) {
			$rows[ __( 'Servicio', 'facilo-boton-arrepentimiento' ) ]                             = $req->service;
			$rows[ __( 'Número de cliente, pedido o contrato', 'facilo-boton-arrepentimiento' ) ] = $req->order_ref;
		} else {
			$rows[ __( 'Número de pedido', 'facilo-boton-arrepentimiento' ) ] = '' !== (string) $req->order_ref ? '#' . ltrim( $req->order_ref, '#' ) : '';
			$rows[ __( 'Qué devuelve', 'facilo-boton-arrepentimiento' ) ]     = 'parte' === $req->scope ? __( 'Una parte', 'facilo-boton-arrepentimiento' ) . ( '' !== (string) $req->detail ? ': ' . $req->detail : '' ) : __( 'Toda la compra', 'facilo-boton-arrepentimiento' );
		}
		$rows[ __( 'Mensaje', 'facilo-boton-arrepentimiento' ) ] = $req->message;
		echo '<div class="box"><h2>' . esc_html__( 'Datos del pedido', 'facilo-boton-arrepentimiento' ) . '</h2><dl class="bclaro-dl">';
		foreach ( $rows as $label => $value ) {
			$value = trim( (string) $value );
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . ( '' !== $value ? nl2br( esc_html( $value ) ) : '<span class="muted">—</span>' ) . '</dd>';
		}
		if ( $req->erased_at ) {
			echo '<dt>' . esc_html__( 'Datos personales', 'facilo-boton-arrepentimiento' ) . '</dt><dd>' . esc_html__( 'Borrados', 'facilo-boton-arrepentimiento' ) . '</dd>';
		}
		echo '</dl>';
		$o = OrderLink::state( $req );
		if ( '' !== $o['text'] ) {
			echo '<p class="bclaro-order ' . esc_attr( $o['state'] ) . '">';
			if ( '' !== $o['url'] ) {
				echo '<a href="' . esc_url( $o['url'] ) . '">' . esc_html( sprintf( /* translators: %s: order number. */ __( 'Pedido #%s', 'facilo-boton-arrepentimiento' ), $o['number'] ) ) . '</a> · ';
			}
			echo esc_html( $o['text'] ) . '</p>';
		}
		echo '</div>';

		// Timeline.
		echo '<div class="box"><h2>' . esc_html__( 'Historial', 'facilo-boton-arrepentimiento' ) . '</h2><ol class="bclaro-tl">';
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
