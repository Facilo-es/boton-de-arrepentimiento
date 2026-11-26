<?php
/**
 * The public route the form posts to: POST /wp-json/facilo-boton-arrepentimiento/v1/solicitudes.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * REST.
 */
class Rest {

	const NS    = 'facilo-boton-arrepentimiento/v1';
	const ROUTE = '/solicitudes';

	/**
	 * Field lengths.
	 *
	 * @var array
	 */
	private static $max = array(
		'nombre'   => 120,
		'correo'   => 190,
		'telefono' => 40,
		'pedido'   => 60,
		'servicio' => 200,
		'detalle'  => 1000,
		'mensaje'  => 3000,
	);

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * The route.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create' ),
				// Public on purpose: the form must work for visitors who aren't logged in and on cached
				// pages, where a stored nonce would have expired. The law bars any login or extra step.
				// Abuse is handled in the callback: honeypot, time check and a rate limit per hashed IP.
				'permission_callback' => '__return_true',
				'args'                => array(
					'tipo' => array(
						'type'     => 'string',
						'enum'     => array( 'arrepentimiento', 'baja' ),
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * The URL of the route.
	 *
	 * @return string
	 */
	public static function url() {
		return rest_url( self::NS . self::ROUTE );
	}

	/**
	 * The "write to us" line with the contact email.
	 *
	 * @param string $kind limite or servidor.
	 * @return string
	 */
	public static function message( $kind ) {
		$mail = Util::contact_email();
		if ( 'limite' === $kind ) {
			/* translators: %s: contact email. */
			return sprintf( __( 'No pudimos tomar tu pedido en este momento. Esperá unos minutos o escribinos a %s.', 'facilo-boton-arrepentimiento' ), $mail );
		}
		/* translators: %s: contact email. */
		return sprintf( __( 'No pudimos enviar tu pedido. Probá de nuevo en un momento. Si sigue sin funcionar, escribinos a %s.', 'facilo-boton-arrepentimiento' ), $mail );
	}

	/**
	 * Clean one text field.
	 *
	 * @param array  $p     Params.
	 * @param string $key   Key.
	 * @param bool   $multi Multi-line.
	 * @return string
	 */
	private static function field( $p, $key, $multi = false ) {
		$v = isset( $p[ $key ] ) && is_scalar( $p[ $key ] ) ? (string) $p[ $key ] : '';
		$v = $multi ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
		return trim( mb_substr( $v, 0, self::$max[ $key ] ) );
	}

	/**
	 * Validate the posted fields.
	 *
	 * @param array $p Params.
	 * @return array [ clean data, errors field => text ].
	 */
	public static function validate( $p ) {
		$type  = isset( $p['tipo'] ) && 'baja' === $p['tipo'] ? 'baja' : 'arrepentimiento';
		$scope = isset( $p['alcance'] ) && 'parte' === $p['alcance'] ? 'parte' : 'todo';
		$d     = array(
			'type'      => $type,
			'name'      => self::field( $p, 'nombre' ),
			'email'     => sanitize_email( self::field( $p, 'correo' ) ),
			'phone'     => self::field( $p, 'telefono' ),
			'order_ref' => self::field( $p, 'pedido' ),
			'scope'     => 'arrepentimiento' === $type ? $scope : '',
			'service'   => 'baja' === $type ? self::field( $p, 'servicio' ) : '',
			'detail'    => ( 'arrepentimiento' === $type && 'parte' === $scope ) ? self::field( $p, 'detalle', true ) : '',
			'message'   => self::field( $p, 'mensaje', true ),
			'is_test'   => 0,
		);
		$e     = array();
		$need  = __( 'Completá este dato.', 'facilo-boton-arrepentimiento' );
		if ( '' === $d['name'] ) {
			$e['nombre'] = $need;
		}
		$raw = isset( $p['correo'] ) && is_scalar( $p['correo'] ) ? trim( (string) $p['correo'] ) : '';
		if ( '' === $raw ) {
			$e['correo'] = $need;
		} elseif ( ! is_email( $d['email'] ) ) {
			$e['correo'] = __( 'Revisá el correo: parece que le falta algo.', 'facilo-boton-arrepentimiento' );
		}
		if ( 'baja' === $type && '' === $d['service'] ) {
			$e['servicio'] = $need;
		}
		if ( 'parte' === $d['scope'] && '' === $d['detail'] ) {
			$e['detalle'] = $need;
		}
		return array( $d, $e );
	}

	/**
	 * Create a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function create( $request ) {
		nocache_headers();
		$p = (array) $request->get_params();
		if ( SpamGuard::is_bot( $p ) || SpamGuard::too_many_links( $p ) || SpamGuard::over_limit() ) {
			return self::reply( 429, 'limite' );
		}
		list( $d, $e ) = self::validate( $p );
		if ( $e ) {
			return new \WP_REST_Response(
				array(
					'ok'     => false,
					'error'  => 'campos',
					'fields' => $e,
				),
				400
			);
		}
		// A repeat of a recent request (same type, email and order within 15 minutes) is stored as
		// its own request with its own new code, linked to the earlier one. The reply is built the
		// same way for both, so it never tells a visitor whether an earlier request exists, and it
		// never carries anything from that earlier request.
		$dup = Requests::duplicate( $d['type'], $d['email'], $d['order_ref'] );
		if ( $dup ) {
			$d['repeat_of'] = (int) $dup->id;
		}
		$req = Requests::create( $d );
		if ( is_wp_error( $req ) || ! $req ) {
			return self::reply( 500, 'servidor' );
		}
		Mailer::send_code( $req );
		if ( ! $dup ) {
			// The owner already has the earlier request; a repeat only shows in its timeline.
			Mailer::send_owner( Requests::get( $req->id ) );
		}
		return self::received( $req->code, $req->email );
	}

	/**
	 * The success reply, the same for new and repeated requests.
	 *
	 * @param string $code  Code of the request just stored.
	 * @param string $email Customer's email, as they typed it.
	 * @return \WP_REST_Response
	 */
	private static function received( $code, $email ) {
		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'code'    => (string) $code,
				'email'   => (string) $email,
				/* translators: %s: customer's email. */
				'message' => sprintf( __( 'Recibimos tu pedido. Te mandamos el código por correo a %s.', 'facilo-boton-arrepentimiento' ), $email ),
			),
			201
		);
	}

	/**
	 * An error reply with the contact email.
	 *
	 * @param int    $status HTTP status.
	 * @param string $kind   limite or servidor.
	 * @return \WP_REST_Response
	 */
	private static function reply( $status, $kind ) {
		return new \WP_REST_Response(
			array(
				'ok'      => false,
				'error'   => $kind,
				'message' => self::message( $kind ),
			),
			$status
		);
	}
}
