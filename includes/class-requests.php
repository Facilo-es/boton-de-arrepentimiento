<?php
/**
 * Requests and their add-only timeline, in the plugin's own tables.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Requests.
 */
class Requests {

	/**
	 * Create a request. Returns the row, or a WP_Error when the database write fails.
	 *
	 * A repeat (repeat_of set to the earlier request's ID) is stored as its own request with its own
	 * code, already closed with the status repetido, so it never adds a second deadline.
	 *
	 * @param array $data Clean fields: type, name, email, phone, order_ref, scope, service, detail, message, is_test, and optionally repeat_of.
	 * @return object|\WP_Error
	 */
	public static function create( $data ) {
		global $wpdb;
		$t     = Install::tables();
		$r     = Util::rules();
		$now   = Util::now();
		$test  = ! empty( $data['is_test'] );
		$type  = 'baja' === $data['type'] ? 'baja' : 'arrepentimiento';
		$order = null;
		if ( 'arrepentimiento' === $type && '' !== $data['order_ref'] ) {
			$order = OrderLink::find_id( $data['order_ref'] );
		}
		$row = array(
			'type'             => $type,
			'status'           => 'nuevo',
			'is_test'          => $test ? 1 : 0,
			'name'             => $data['name'],
			'email'            => $data['email'],
			'phone'            => $data['phone'],
			'order_ref'        => $data['order_ref'],
			'scope'            => $data['scope'],
			'service'          => $data['service'],
			'detail'           => $data['detail'],
			'message'          => $data['message'],
			'created_at'       => Util::mysql( $now ),
			'reply_due_at'     => $test ? null : Util::mysql( $now + HOUR_IN_SECONDS * (int) $r['reply_hours'] ),
			'proof_due_at'     => ( $test || 'baja' !== $type ) ? null : Util::mysql( $now + HOUR_IN_SECONDS * (int) $r['proof_hours'] ),
			'code_mail_status' => 'pendiente',
			'code_mail_tries'  => 0,
		);
		if ( $order ) {
			$row['order_id'] = (int) $order;
		}
		$repeat = ! $test && ! empty( $data['repeat_of'] ) ? (int) $data['repeat_of'] : 0;
		if ( $repeat ) {
			$row['repeat_of'] = $repeat;
			$row['status']    = 'repetido';
			$row['closed_at'] = $row['created_at'];
		}
		$suppress = $wpdb->suppress_errors( true );
		$id       = 0;
		for ( $try = 0; $try < 3 && ! $id; $try++ ) {
			$row['code'] = Codes::make( $type, $now );
			$ok          = $wpdb->insert( $t['requests'], $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
			if ( $ok ) {
				$id = (int) $wpdb->insert_id;
			} elseif ( false === stripos( (string) $wpdb->last_error, 'Duplicate' ) ) {
				break;
			}
		}
		$wpdb->suppress_errors( $suppress );
		if ( ! $id ) {
			return new \WP_Error( 'bclaro_db', 'db' );
		}
		self::event( $id, 'recibido' );
		$req = self::get( $id );
		if ( $repeat ) {
			$first = self::get( $repeat );
			self::event( $id, 'repite', $first ? (string) $first->code : '' );
			self::event( $repeat, 'repetido', (string) $req->code );
		}
		/**
		 * A request was stored, with its first event.
		 *
		 * @param int    $id  Request ID.
		 * @param object $req Row.
		 */
		do_action( 'bclaro_request_created', $id, $req );
		return $req;
	}

	/**
	 * A request by ID.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$t = Install::tables();
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t['requests'], (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * A request by code.
	 *
	 * @param string $code Code.
	 * @return object|null
	 */
	public static function by_code( $code ) {
		global $wpdb;
		$t = Install::tables();
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE code = %s', $t['requests'], (string) $code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * The same type, email and order number in the last 15 minutes.
	 *
	 * @param string $type      Type.
	 * @param string $email     Email.
	 * @param string $order_ref Order or service reference.
	 * @return object|null
	 */
	public static function duplicate( $type, $email, $order_ref ) {
		global $wpdb;
		$t     = Install::tables();
		$since = Util::mysql( Util::now() - 15 * MINUTE_IN_SECONDS );
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE type = %s AND email = %s AND order_ref = %s AND created_at >= %s AND is_test = 0 AND repeat_of IS NULL AND status <> %s ORDER BY id ASC LIMIT 1',
				$t['requests'],
				$type,
				$email,
				$order_ref,
				$since,
				'spam'
			)
		);
	}

	/**
	 * Update columns.
	 *
	 * @param int   $id   ID.
	 * @param array $cols Columns.
	 * @return bool
	 */
	public static function update( $id, $cols ) {
		global $wpdb;
		$t = Install::tables();
		return false !== $wpdb->update( $t['requests'], $cols, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Add a timeline entry. Entries are never edited.
	 *
	 * @param int    $id    Request ID.
	 * @param string $kind  Kind.
	 * @param string $note  Note.
	 * @param int    $actor User ID, 0 for the system.
	 */
	public static function event( $id, $kind, $note = '', $actor = 0 ) {
		global $wpdb;
		$t = Install::tables();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$t['events'],
			array(
				'request_id' => (int) $id,
				'at'         => Util::mysql(),
				'actor'      => (int) $actor,
				'kind'       => $kind,
				'note'       => $note,
			)
		);
		/**
		 * A timeline entry was added.
		 *
		 * @param int    $id    Request ID.
		 * @param string $kind  Kind.
		 * @param string $note  Note.
		 * @param int    $actor User ID.
		 */
		do_action( 'bclaro_event_added', (int) $id, $kind, $note, (int) $actor );
	}

	/**
	 * Timeline of a request, oldest first.
	 *
	 * @param int $id Request ID.
	 * @return array
	 */
	public static function events( $id ) {
		global $wpdb;
		$t = Install::tables();
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE request_id = %d ORDER BY id ASC', $t['events'], (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Words for a timeline entry.
	 *
	 * @param object $e Event.
	 * @return string
	 */
	public static function event_label( $e ) {
		switch ( $e->kind ) {
			case 'recibido':
				return __( 'Pedido recibido', 'facilo-boton-arrepentimiento' );
			case 'codigo_enviado':
				return __( 'Código enviado', 'facilo-boton-arrepentimiento' );
			case 'codigo_fallido':
				return __( 'No pudimos enviar el código por correo.', 'facilo-boton-arrepentimiento' );
			case 'codigo_rendido':
				return __( 'No pudimos enviar el código', 'facilo-boton-arrepentimiento' );
			case 'aviso_fallido':
				return __( 'No pudimos mandarte el aviso por correo', 'facilo-boton-arrepentimiento' );
			case 'aviso_enviado':
				return __( 'Aviso al dueño enviado', 'facilo-boton-arrepentimiento' );
			case 'estado':
				$st = Util::statuses();
				/* translators: %s: status. */
				return sprintf( __( 'Estado: %s', 'facilo-boton-arrepentimiento' ), isset( $st[ $e->note ] ) ? $st[ $e->note ] : $e->note );
			case 'respondido':
				$u = $e->actor ? get_userdata( (int) $e->actor ) : false;
				/* translators: %s: user name. */
				return sprintf( __( 'Respondido por %s', 'facilo-boton-arrepentimiento' ), $u ? $u->display_name : '—' );
			case 'constancia':
				return __( 'Constancia enviada', 'facilo-boton-arrepentimiento' );
			case 'reintento':
				return __( 'Reintento de envío', 'facilo-boton-arrepentimiento' );
			case 'datos_borrados':
				return __( 'Datos personales borrados', 'facilo-boton-arrepentimiento' );
			case 'repite':
				/* translators: %s: code of the earlier request. */
				return sprintf( __( 'Repite el pedido %s: lo atendés ahí', 'facilo-boton-arrepentimiento' ), $e->note );
			case 'repetido':
				/* translators: %s: code of the repeated request. */
				return sprintf( __( 'El cliente lo volvió a enviar: %s', 'facilo-boton-arrepentimiento' ), $e->note );
			case 'prueba':
				return __( 'Pedido de prueba: solo te llegó a vos', 'facilo-boton-arrepentimiento' );
		}
		/**
		 * Words for an event kind Free doesn't know (Pro's own kinds).
		 *
		 * @param string $label Words.
		 * @param object $e     Event.
		 */
		return (string) apply_filters( 'bclaro_event_label', (string) $e->note, $e );
	}

	/**
	 * Change the status. Closing statuses set closed_at; reopening clears it.
	 *
	 * @param object $req    Request.
	 * @param string $status New status.
	 * @param int    $actor  User ID.
	 * @return bool
	 */
	public static function set_status( $req, $status, $actor = 0 ) {
		$all = Util::statuses();
		if ( ! isset( $all[ $status ] ) || $req->status === $status ) {
			return false;
		}
		$cols = array( 'status' => $status );
		if ( in_array( $status, array( 'resuelto', 'no_corresponde', 'repetido', 'spam' ), true ) ) {
			$cols['closed_at'] = $req->closed_at ? $req->closed_at : Util::mysql();
		} else {
			$cols['closed_at'] = null;
		}
		self::update( $req->id, $cols );
		self::event( $req->id, 'estado', $status, $actor );
		/**
		 * Status changed.
		 *
		 * @param object $req   Request before the change.
		 * @param string $old   Old status.
		 * @param string $new   New status.
		 * @param int    $actor User ID.
		 */
		do_action( 'bclaro_status_changed', $req, $req->status, $status, (int) $actor );
		return true;
	}

	/**
	 * Mark as answered: stops the 24-hour clock.
	 *
	 * @param object $req   Request.
	 * @param int    $actor User ID.
	 */
	public static function replied( $req, $actor ) {
		if ( $req->replied_at ) {
			return;
		}
		$cols = array( 'replied_at' => Util::mysql() );
		if ( 'nuevo' === $req->status ) {
			$cols['status'] = 'en_curso';
		}
		self::update( $req->id, $cols );
		self::event( $req->id, 'respondido', '', $actor );
		if ( isset( $cols['status'] ) ) {
			self::event( $req->id, 'estado', 'en_curso', $actor );
			/** This action is documented in includes/class-requests.php */
			do_action( 'bclaro_status_changed', $req, $req->status, 'en_curso', (int) $actor );
		}
	}

	/**
	 * Delete a request and its timeline. Only test and spam requests may go.
	 *
	 * @param object $req Request.
	 * @return bool
	 */
	public static function delete( $req ) {
		global $wpdb;
		if ( ! $req->is_test && 'spam' !== $req->status ) {
			return false;
		}
		$t = Install::tables();
		$wpdb->delete( $t['events'], array( 'request_id' => (int) $req->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $t['requests'], array( 'id' => (int) $req->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return true;
	}

	/**
	 * Whether a request is open: needs the owner.
	 *
	 * @param object $req Request.
	 * @return bool
	 */
	public static function is_open( $req ) {
		return in_array( $req->status, array( 'nuevo', 'en_curso' ), true );
	}

	/**
	 * Open requests, for the menu count. Test requests don't count.
	 *
	 * @return int
	 */
	public static function open_count() {
		global $wpdb;
		$t = Install::tables();
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status IN (%s, %s) AND is_test = 0', $t['requests'], 'nuevo', 'en_curso' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Build the WHERE part for the list.
	 *
	 * @param array $f Filters: tab, type, status, s.
	 * @return array SQL and args.
	 */
	private static function where( $f ) {
		global $wpdb;
		$sql  = array( '1=1' );
		$args = array();
		$tab  = isset( $f['tab'] ) ? $f['tab'] : 'abiertos';
		if ( 'abiertos' === $tab ) {
			$sql[] = "status IN ('nuevo','en_curso')";
		} elseif ( 'vencidos' === $tab ) {
			$sql[]  = "status IN ('nuevo','en_curso') AND is_test = 0 AND ( ( replied_at IS NULL AND reply_due_at < %s ) OR ( type = 'baja' AND proof_sent_at IS NULL AND proof_due_at < %s ) )";
			$args[] = Util::mysql();
			$args[] = Util::mysql();
		} elseif ( 'cerrados' === $tab ) {
			$sql[] = "status IN ('resuelto','no_corresponde')";
		} elseif ( 'spam' === $tab ) {
			$sql[] = "status = 'spam'";
		} else {
			$sql[] = "status <> 'spam'";
		}
		if ( ! empty( $f['type'] ) ) {
			$sql[]  = 'type = %s';
			$args[] = $f['type'];
		}
		if ( ! empty( $f['status'] ) ) {
			$sql[]  = 'status = %s';
			$args[] = $f['status'];
		}
		if ( isset( $f['s'] ) && '' !== $f['s'] ) {
			$like   = '%' . $wpdb->esc_like( $f['s'] ) . '%';
			$sql[]  = '( code LIKE %s OR name LIKE %s OR email LIKE %s OR order_ref LIKE %s OR service LIKE %s )';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}
		return array( implode( ' AND ', $sql ), $args );
	}

	/**
	 * A page of requests, newest first.
	 *
	 * @param array $f        Filters.
	 * @param int   $page     Page, from 1.
	 * @param int   $per_page Rows per page.
	 * @return array rows, total.
	 */
	public static function query( $f, $page = 1, $per_page = 20 ) {
		global $wpdb;
		$t                    = Install::tables();
		list( $where, $args ) = self::where( $f );
		$count_args           = array_merge( array( $t['requests'] ), $args );
		$total                = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE $where", $count_args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where holds only placeholders, filled from $count_args.
		$row_args             = array_merge( array( $t['requests'] ), $args, array( (int) $per_page, max( 0, ( (int) $page - 1 ) * (int) $per_page ) ) );
		$rows                 = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $row_args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where holds only placeholders, filled from $row_args.
		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Numbers for the tiles over the list.
	 *
	 * @return array open, late, soon, week.
	 */
	public static function kpis() {
		global $wpdb;
		$t    = Install::tables();
		$now  = Util::mysql();
		$soon = Util::mysql( Util::now() + 6 * HOUR_IN_SECONDS );
		$week = Util::mysql( Util::now() - 7 * DAY_IN_SECONDS );
		$row  = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT
					SUM( status IN ('nuevo','en_curso') AND is_test = 0 ) AS open_n,
					SUM( status IN ('nuevo','en_curso') AND is_test = 0 AND ( ( replied_at IS NULL AND reply_due_at < %s ) OR ( type = 'baja' AND proof_sent_at IS NULL AND proof_due_at < %s ) ) ) AS late_n,
					SUM( status IN ('nuevo','en_curso') AND is_test = 0 AND replied_at IS NULL AND reply_due_at >= %s AND reply_due_at < %s ) AS soon_n,
					SUM( created_at >= %s AND is_test = 0 AND status NOT IN ('spam','repetido') ) AS week_n
				FROM %i",
				$now,
				$now,
				$now,
				$soon,
				$week,
				$t['requests']
			)
		);
		return array(
			'open' => (int) ( $row ? $row->open_n : 0 ),
			'late' => (int) ( $row ? $row->late_n : 0 ),
			'soon' => (int) ( $row ? $row->soon_n : 0 ),
			'week' => (int) ( $row ? $row->week_n : 0 ),
		);
	}

	/**
	 * Time left on a clock: state (ok, soon, late, done) and words.
	 *
	 * @param string|null $due  Due datetime.
	 * @param string|null $done Done datetime.
	 * @return array|null
	 */
	public static function clock( $due, $done ) {
		if ( $done ) {
			return array(
				'state' => 'done',
				'text'  => __( 'Respondido', 'facilo-boton-arrepentimiento' ),
			);
		}
		if ( ! $due ) {
			return null;
		}
		$diff = Util::ts( $due ) - Util::now();
		if ( $diff >= 0 ) {
			$h = (int) ceil( $diff / HOUR_IN_SECONDS );
			return array(
				'state' => $diff < 6 * HOUR_IN_SECONDS ? 'soon' : 'ok',
				'text'  => 1 === $h ? __( 'Queda 1 hora', 'facilo-boton-arrepentimiento' ) : sprintf( /* translators: %d: hours. */ __( 'Quedan %d horas', 'facilo-boton-arrepentimiento' ), $h ),
				'hours' => $h,
			);
		}
		$h = max( 1, (int) floor( -$diff / HOUR_IN_SECONDS ) );
		return array(
			'state' => 'late',
			'text'  => 1 === $h ? __( 'Vencido hace 1 hora', 'facilo-boton-arrepentimiento' ) : sprintf( /* translators: %d: hours. */ __( 'Vencido hace %d horas', 'facilo-boton-arrepentimiento' ), $h ),
			'hours' => $h,
		);
	}
}
