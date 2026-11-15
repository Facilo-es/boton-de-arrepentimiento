<?php
/**
 * Pedidos: the request list. A plain table, no WP_List_Table, so it matches the design.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * AdminList.
 */
class AdminList {

	const PER_PAGE = 20;

	/**
	 * Requests per status, real ones only (no test).
	 *
	 * @return array status => n, plus 'todos'.
	 */
	public static function counts() {
		global $wpdb;
		$t    = Install::tables();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i GROUP BY status', $t['requests'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array_fill_keys( array_keys( Util::statuses() ), 0 );
		foreach ( (array) $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		$out['todos'] = array_sum( $out ) - $out['spam'];
		return $out;
	}

	/**
	 * Clocks shown for a request: reply and, for baja, proof.
	 *
	 * @param object $r Row.
	 * @return array reply => clock|null, proof => clock|null, row => r-ok|r-warn|r-late|''.
	 */
	public static function clocks( $r ) {
		$out = array(
			'reply' => null,
			'proof' => null,
			'row'   => '',
		);
		if ( $r->is_test ) {
			return $out;
		}
		$open = Requests::is_open( $r );
		if ( $open || $r->replied_at ) {
			$out['reply'] = Requests::clock( $r->reply_due_at, $r->replied_at );
		}
		if ( 'baja' === $r->type && ( $open || $r->proof_sent_at ) ) {
			$out['proof'] = Requests::clock( $r->proof_due_at, $r->proof_sent_at );
		}
		if ( ! $open ) {
			return $out;
		}
		$rank  = array(
			'done' => 0,
			'ok'   => 1,
			'soon' => 2,
			'late' => 3,
		);
		$worst = -1;
		foreach ( array( $out['reply'], $out['proof'] ) as $c ) {
			if ( $c && $rank[ $c['state'] ] > $worst ) {
				$worst = $rank[ $c['state'] ];
			}
		}
		$map        = array( 'r-ok', 'r-ok', 'r-warn', 'r-late' );
		$out['row'] = $worst >= 0 ? $map[ $worst ] : '';
		return $out;
	}

	/**
	 * The time cell: big number, small words.
	 *
	 * @param object $r Row.
	 * @param array  $c Clocks from clocks().
	 * @return string HTML.
	 */
	public static function time_cell( $r, $c ) {
		if ( $r->is_test ) {
			return '<span class="t done">—</span>';
		}
		$html = '';
		$rc   = $c['reply'];
		if ( ! $rc ) {
			$html .= '<span class="t done">' . esc_html__( 'Cerrado', 'boton-de-arrepentimiento' ) . '</span>';
		} elseif ( 'done' === $rc['state'] ) {
			$html .= '<span class="t done">' . esc_html( $rc['text'] ) . '</span>';
		} else {
			$cls   = 'late' === $rc['state'] ? 'late' : ( 'soon' === $rc['state'] ? 'warn' : '' );
			$html .= '<span class="t ' . esc_attr( $cls ) . '">' . self::big_number( $rc['text'], $rc['hours'] ) . '</span>';
		}
		$pc = $c['proof'];
		if ( $pc ) {
			if ( 'done' === $pc['state'] ) {
				$text = __( 'Constancia enviada', 'boton-de-arrepentimiento' );
				$cls  = 'done';
			} elseif ( 'late' === $pc['state'] ) {
				/* translators: %s: "1 hora" or "n horas". */
				$text = sprintf( __( 'Constancia: vencida hace %s', 'boton-de-arrepentimiento' ), Util::hours( $pc['hours'] ) );
				$cls  = 'late';
			} else {
				/* translators: %s: "1 hora" or "n horas". */
				$text = 1 === (int) $pc['hours'] ? __( 'Constancia: queda 1 hora', 'boton-de-arrepentimiento' ) : sprintf( __( 'Constancia: quedan %s', 'boton-de-arrepentimiento' ), Util::hours( $pc['hours'] ) );
				$cls  = 'soon' === $pc['state'] ? 'warn' : '';
			}
			$html .= '<small class="tp ' . esc_attr( $cls ) . '">' . esc_html( $text ) . '</small>';
		}
		return $html;
	}

	/**
	 * Wrap the number of hours in <b>, keep the words around it.
	 *
	 * @param string $text  Words, with the number.
	 * @param int    $hours Number.
	 * @return string
	 */
	public static function big_number( $text, $hours ) {
		$n   = (string) (int) $hours;
		$pos = strpos( $text, $n );
		if ( false === $pos ) {
			return esc_html( $text );
		}
		return esc_html( substr( $text, 0, $pos ) ) . '<b>' . esc_html( $n ) . '</b>' . esc_html( substr( $text, $pos + strlen( $n ) ) );
	}

	/**
	 * Status pill.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function pill( $status ) {
		$all = Util::statuses();
		$cls = array(
			'nuevo'          => 'nuevo',
			'en_curso'       => 'curso',
			'resuelto'       => 'hecho',
			'no_corresponde' => 'no',
			'spam'           => 'spam',
		);
		return '<span class="st ' . esc_attr( isset( $cls[ $status ] ) ? $cls[ $status ] : '' ) . '">' . esc_html( isset( $all[ $status ] ) ? $all[ $status ] : $status ) . '</span>';
	}

	/**
	 * Read the filters from the URL.
	 *
	 * @return array
	 */
	private static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$f = array(
			'estado' => isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : '',
			'type'   => isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( $_GET['tipo'] ) ) : '',
			's'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'paged'  => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
		// phpcs:enable
		if ( ! in_array( $f['type'], array( 'arrepentimiento', 'baja' ), true ) ) {
			$f['type'] = '';
		}
		if ( '' !== $f['estado'] && ! isset( Util::statuses()[ $f['estado'] ] ) ) {
			$f['estado'] = '';
		}
		return $f;
	}

	/**
	 * The screen. With ?id=N it shows one request instead.
	 */
	public static function page() {
		if ( ! current_user_can( Util::cap() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		if ( ! empty( $_GET['id'] ) ) {
			AdminRequest::page( absint( $_GET['id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
			return;
		}
		$f      = self::filters();
		$counts = self::counts();
		$kpis   = Requests::kpis();
		$query  = array(
			'tab'    => 'spam' === $f['estado'] ? 'spam' : 'todos',
			'status' => 'spam' === $f['estado'] ? '' : $f['estado'],
			'type'   => $f['type'],
			's'      => $f['s'],
		);
		$res    = Requests::query( $query, $f['paged'], self::PER_PAGE );
		$cols   = array(
			'code'    => __( 'Código', 'boton-de-arrepentimiento' ),
			'type'    => __( 'Tipo', 'boton-de-arrepentimiento' ),
			'client'  => __( 'Cliente', 'boton-de-arrepentimiento' ),
			'order'   => __( 'Pedido', 'boton-de-arrepentimiento' ),
			'created' => __( 'Recibido', 'boton-de-arrepentimiento' ),
			'time'    => __( 'Tiempo', 'boton-de-arrepentimiento' ),
			'status'  => __( 'Estado', 'boton-de-arrepentimiento' ),
		);
		if ( ! Util::woo() ) {
			// No store, no order column (T30). Service sites still see which service a baja is about.
			if ( Pages::needed( 'baja' ) ) {
				$cols['order'] = __( 'Servicio', 'boton-de-arrepentimiento' );
			} else {
				unset( $cols['order'] );
			}
		}
		/**
		 * Columns of the list. Pro adds its own, rendered through bclaro_list_column_{key}.
		 *
		 * @param array $cols key => label.
		 */
		$cols = (array) apply_filters( 'bclaro_list_columns', $cols );

		echo '<div class="wrap bclaro-wrap">';
		self::title( __( 'Pedidos', 'boton-de-arrepentimiento' ) );
		self::message();

		echo '<div class="kpis">';
		$tiles = array(
			array( $counts['nuevo'], __( 'Nuevo', 'boton-de-arrepentimiento' ), '' ),
			array( $counts['en_curso'], __( 'En curso', 'boton-de-arrepentimiento' ), '' ),
			array( $kpis['late'], __( 'Vencido', 'boton-de-arrepentimiento' ), $kpis['late'] ? 'late' : 'calm' ),
			array( $counts['resuelto'], __( 'Resuelto', 'boton-de-arrepentimiento' ), '' ),
		);
		foreach ( $tiles as $tile ) {
			echo '<div class="kpi ' . esc_attr( $tile[2] ) . '"><b>' . esc_html( number_format_i18n( $tile[0] ) ) . '</b><span>' . esc_html( $tile[1] ) . '</span></div>';
		}
		echo '</div>';

		echo '<form class="bclaro-tabs" method="get"><input type="hidden" name="page" value="bclaro"><ul class="subsubsub">';
		$tabs = array_merge( array( '' => __( 'Todos', 'boton-de-arrepentimiento' ) ), Util::statuses() );
		$i    = 0;
		foreach ( $tabs as $key => $label ) {
			$n   = '' === $key ? $counts['todos'] : $counts[ $key ];
			$url = Util::admin_url(
				'bclaro',
				array_filter(
					array(
						'estado' => $key,
						'tipo'   => $f['type'],
					)
				)
			);
			echo '<li>' . ( $i++ ? ' | ' : '' ) . '<a href="' . esc_url( $url ) . '"' . ( $key === $f['estado'] ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $n ) ) . ')</span></a></li>';
		}
		echo '</ul><div class="bclaro-tools">';
		if ( '' !== $f['estado'] ) {
			echo '<input type="hidden" name="estado" value="' . esc_attr( $f['estado'] ) . '">';
		}
		echo '<label class="screen-reader-text" for="bclaro-tipo">' . esc_html__( 'Tipo', 'boton-de-arrepentimiento' ) . '</label><select id="bclaro-tipo" name="tipo" onchange="this.form.submit()"><option value="">' . esc_html__( 'Todos los tipos', 'boton-de-arrepentimiento' ) . '</option>';
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			echo '<option value="' . esc_attr( $type ) . '"' . selected( $f['type'], $type, false ) . '>' . esc_html( Util::type_label( $type ) ) . '</option>';
		}
		echo '</select><label class="screen-reader-text" for="bclaro-s">' . esc_html__( 'Buscar', 'boton-de-arrepentimiento' ) . '</label><input type="search" id="bclaro-s" name="s" value="' . esc_attr( $f['s'] ) . '" placeholder="' . esc_attr__( 'Código, nombre, correo o pedido', 'boton-de-arrepentimiento' ) . '"><button class="button">' . esc_html__( 'Buscar', 'boton-de-arrepentimiento' ) . '</button>';
		/**
		 * Extra tools over the list, such as Pro's CSV export.
		 *
		 * @param array $f Filters.
		 */
		do_action( 'bclaro_list_actions', $f );
		echo '</div></form>';

		if ( ! $res['rows'] ) {
			$empty = ( '' === $f['estado'] && '' === $f['type'] && '' === $f['s'] && ! $counts['todos'] )
				? __( 'Todavía no llegó ningún pedido. Cuando llegue uno, te avisamos por correo y lo ves acá.', 'boton-de-arrepentimiento' )
				: __( 'No hay pedidos con estos filtros.', 'boton-de-arrepentimiento' );
			echo '<div class="bclaro-empty">' . self::mark( 40 ) . '<p>' . esc_html( $empty ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			return;
		}

		echo '<table class="bclaro-table"><thead><tr>';
		foreach ( $cols as $key => $label ) {
			echo '<th scope="col" class="c-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $res['rows'] as $r ) {
			$c    = self::clocks( $r );
			$link = Util::admin_url( 'bclaro', array( 'id' => (int) $r->id ) );
			echo '<tr class="' . esc_attr( $c['row'] ) . '">';
			foreach ( $cols as $key => $label ) {
				echo '<td class="c-' . esc_attr( $key ) . '" data-label="' . esc_attr( $label ) . '">';
				switch ( $key ) {
					case 'code':
						echo '<a class="code" href="' . esc_url( $link ) . '">' . esc_html( $r->code ) . '</a>';
						if ( $r->is_test ) {
							echo ' <span class="tag-test">' . esc_html__( 'Prueba', 'boton-de-arrepentimiento' ) . '</span>';
						}
						break;
					case 'type':
						echo esc_html( Util::type_label( $r->type ) );
						break;
					case 'client':
						echo '<a class="cli" href="' . esc_url( $link ) . '"><b>' . esc_html( '' !== $r->name ? $r->name : '—' ) . '</b></a><small>' . esc_html( $r->email ) . '</small>';
						break;
					case 'order':
						if ( 'baja' === $r->type ) {
							echo esc_html( $r->service ) . ( '' !== $r->order_ref ? '<small>' . esc_html( $r->order_ref ) . '</small>' : '' );
						} else {
							echo '' !== $r->order_ref ? esc_html( '#' . ltrim( $r->order_ref, '#' ) ) : '<span class="muted">—</span>';
						}
						break;
					case 'created':
						echo esc_html( Util::short( Util::ts( $r->created_at ) ) );
						break;
					case 'time':
						echo self::time_cell( $r, $c ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in time_cell().
						break;
					case 'status':
						echo self::pill( $r->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill().
						break;
					default:
						/**
						 * Render a column added through bclaro_list_columns.
						 *
						 * @param object $r Row.
						 */
						do_action( 'bclaro_list_column_' . $key, $r );
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $res['total'] / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $f['paged'],
						'total'   => $pages,
					)
				)
			) . '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Title row. Shared by our screens.
	 *
	 * @param string $title Title.
	 */
	private static function title( $title ) {
		Admin::title( $title );
	}

	/**
	 * The mark.
	 *
	 * @param int $size Size.
	 * @return string
	 */
	private static function mark( $size ) {
		return Admin::mark( $size );
	}

	/**
	 * One-line result after an action.
	 */
	public static function message() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message key only.
		$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		$all = array(
			'estado'           => array( 'success', __( 'Cambiamos el estado.', 'boton-de-arrepentimiento' ) ),
			'respondido'       => array( 'success', __( 'Listo: marcamos el pedido como respondido.', 'boton-de-arrepentimiento' ) ),
			'constancia'       => array( 'success', __( 'Mandamos la constancia y cerramos el pedido.', 'boton-de-arrepentimiento' ) ),
			'constancia_fallo' => array( 'error', __( 'No pudimos mandar la constancia. Revisá que tu sitio pueda enviar correos y probá de nuevo.', 'boton-de-arrepentimiento' ) ),
			'reenviado'        => array( 'success', __( 'Le mandamos el código al cliente.', 'boton-de-arrepentimiento' ) ),
			'reenvio_fallo'    => array( 'error', __( 'No pudimos enviar el código por correo.', 'boton-de-arrepentimiento' ) ),
			'borrado'          => array( 'success', __( 'Borramos el pedido.', 'boton-de-arrepentimiento' ) ),
			'prueba'           => array( 'success', __( 'Hicimos un pedido de prueba. Te mandamos el mismo correo que recibe un cliente.', 'boton-de-arrepentimiento' ) ),
			'error'            => array( 'error', __( 'No pudimos hacer ese cambio.', 'boton-de-arrepentimiento' ) ),
		);
		if ( isset( $all[ $msg ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $all[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $all[ $msg ][1] ) . '</p></div>';
		}
	}
}
