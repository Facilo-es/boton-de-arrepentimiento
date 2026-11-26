<?php
/**
 * Revisión: one click looks at the home page through a loopback and lists what to fix.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * SiteCheck.
 */
class SiteCheck {

	const OPTION = 'bclaro_check';

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_post_bclaro_check', array( __CLASS__, 'handle_check' ) );
		add_action( 'admin_post_bclaro_test_mail', array( __CLASS__, 'handle_test_mail' ) );
		add_action( 'admin_post_bclaro_mail_ok', array( __CLASS__, 'handle_mail_ok' ) );
	}

	/**
	 * Check the user and the nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( Util::cap() ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'facilo-boton-arrepentimiento' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * A result line.
	 *
	 * @param string $key   Check key.
	 * @param string $state ok, warn or na.
	 * @param string $text  Words.
	 * @return array
	 */
	private static function line( $key, $state, $text ) {
		return array(
			'key'   => $key,
			'state' => $state,
			'text'  => $text,
		);
	}

	/**
	 * Run every check.
	 *
	 * @return array at, lines, loopback.
	 */
	public static function run() {
		$s     = Settings::get();
		$lines = array();
		$ok    = __( 'Se ve en tu página de inicio', 'facilo-boton-arrepentimiento' );
		$miss  = __( 'No lo encontramos en tu página de inicio', 'facilo-boton-arrepentimiento' );

		// Home page through a loopback.
		$url  = add_query_arg( 'bclaro_revision', (string) Util::now(), home_url( '/' ) );
		$res  = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => false, // Loopback to this same site; self-signed certificates are common on staging.
				'headers'   => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$body = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		$loop = $code > 0;
		$shut = 503 === $code || 401 === $code || false !== strpos( $body, 'id="loginform"' );
		if ( ! $loop || ( ! $shut && $code >= 400 ) ) {
			$loop = false;
		}

		if ( $loop && ! $shut ) {
			$lines[] = self::line( 'arrepentimiento', false !== strpos( $body, 'data-bclaro="arrepentimiento"' ) ? 'ok' : 'warn', false !== strpos( $body, 'data-bclaro="arrepentimiento"' ) ? $ok : $miss );
			if ( ! empty( $s['baja'] ) ) {
				$lines[] = self::line( 'baja', false !== strpos( $body, 'data-bclaro="baja"' ) ? 'ok' : 'warn', false !== strpos( $body, 'data-bclaro="baja"' ) ? $ok : $miss );
			}
		} else {
			$lines[] = self::line( 'arrepentimiento', 'na', __( 'No pudimos mirar tu página desde acá. Abrila en una ventana privada y fijate si ves los botones.', 'facilo-boton-arrepentimiento' ) );
		}

		$changed = Settings::label_changed( 'arrepentimiento' ) || ( ! empty( $s['baja'] ) && Settings::label_changed( 'baja' ) );
		$lines[] = self::line( 'nombres', $changed ? 'warn' : 'ok', $changed ? __( 'Cambiaste el nombre de un botón', 'facilo-boton-arrepentimiento' ) : __( 'Los nombres están completos', 'facilo-boton-arrepentimiento' ) );

		$pages   = Pages::missing();
		$lines[] = self::line( 'paginas', $pages ? 'warn' : 'ok', $pages ? __( 'Falta un formulario o no es público', 'facilo-boton-arrepentimiento' ) : __( 'Los formularios están publicados', 'facilo-boton-arrepentimiento' ) );

		$svc = Service::missing();
		/* translators: %s: list of missing fields. */
		$lines[] = self::line( 'atencion', $svc ? 'warn' : 'ok', $svc ? sprintf( __( 'Faltan datos de atención: %s', 'facilo-boton-arrepentimiento' ), implode( ', ', $svc ) ) : __( 'Tus datos de atención están completos', 'facilo-boton-arrepentimiento' ) );

		// The form's REST route: a post with no timing must come back as our own JSON refusal.
		// It stops at the bot check, so it neither stores anything nor counts toward the hourly limit.
		$r    = wp_remote_post(
			Rest::url(),
			array(
				'timeout'   => 15,
				'sslverify' => false, // Same site, as above.
				'body'      => array( 'tipo' => 'arrepentimiento' ),
			)
		);
		$json = is_wp_error( $r ) ? null : json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$form = is_array( $json ) && isset( $json['error'] ) && in_array( $json['error'], array( 'campos', 'limite' ), true );
		if ( is_wp_error( $r ) ) {
			$lines[] = self::line( 'formulario', 'na', __( 'No pudimos probar el formulario desde acá. Mandá un pedido de prueba desde tu sitio.', 'facilo-boton-arrepentimiento' ) );
		} else {
			$lines[] = self::line( 'formulario', $form ? 'ok' : 'warn', $form ? __( 'El formulario responde', 'facilo-boton-arrepentimiento' ) : __( 'Algo bloquea el formulario. Revisá tu plugin de seguridad', 'facilo-boton-arrepentimiento' ) );
		}

		$ran     = (int) get_option( 'bclaro_cron_ran_at', 0 );
		$cron    = $ran && ( Util::now() - $ran ) <= HOUR_IN_SECONDS + 300;
		$lines[] = self::line( 'tareas', $cron ? 'ok' : 'warn', $cron ? __( 'Las tareas programadas funcionan', 'facilo-boton-arrepentimiento' ) : __( 'Las tareas programadas no corren, y sin ellas no se reintentan los correos', 'facilo-boton-arrepentimiento' ) );

		$lines[] = self::line( 'correo', ! empty( $s['mail_ok'] ) ? 'ok' : 'warn', ! empty( $s['mail_ok'] ) ? __( 'Confirmaste que te llegó el correo de prueba', 'facilo-boton-arrepentimiento' ) : __( 'Mandate un correo de prueba', 'facilo-boton-arrepentimiento' ) );

		$lines[] = self::line( 'aviso', Notice::on() ? 'ok' : 'warn', Notice::on() ? __( 'El aviso está en tus correos y en el pago', 'facilo-boton-arrepentimiento' ) : __( 'El aviso está apagado', 'facilo-boton-arrepentimiento' ) );

		if ( $loop || $shut ) {
			$lines[] = self::line( 'abierto', $shut ? 'warn' : 'ok', $shut ? __( 'Tu sitio pide iniciar sesión o está en mantenimiento', 'facilo-boton-arrepentimiento' ) : __( 'Tu sitio está abierto al público', 'facilo-boton-arrepentimiento' ) );
		}

		$result = array(
			'at'       => Util::now(),
			'lines'    => $lines,
			'loopback' => $loop,
		);
		update_option( self::OPTION, $result, false );
		return $result;
	}

	/**
	 * "Revisar ahora".
	 */
	public static function handle_check() {
		self::guard( 'bclaro_check' );
		self::run();
		wp_safe_redirect( Util::admin_url( 'bclaro-revision' ) );
		exit;
	}

	/**
	 * "Enviar correo de prueba".
	 */
	public static function handle_test_mail() {
		self::guard( 'bclaro_test_mail' );
		$ok = Mailer::send_test();
		Settings::update( array( 'mail_ok' => 0 ) );
		wp_safe_redirect( Util::admin_url( 'bclaro-revision', array( 'msg' => $ok ? 'enviado' : 'fallo' ) ) );
		exit;
	}

	/**
	 * "Me llegó".
	 */
	public static function handle_mail_ok() {
		self::guard( 'bclaro_mail_ok' );
		Settings::update( array( 'mail_ok' => 1 ) );
		$last = get_option( self::OPTION );
		if ( is_array( $last ) && isset( $last['lines'] ) ) {
			self::run();
		}
		wp_safe_redirect( Util::admin_url( 'bclaro-revision', array( 'msg' => 'llego' ) ) );
		exit;
	}

	/**
	 * A one-button form.
	 *
	 * @param string $action Action and nonce.
	 * @param string $label  Words.
	 * @param string $css    Class.
	 */
	private static function button( $action, $label, $css = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
		echo '<button type="submit" class="bclaro-btn ' . esc_attr( $css ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * The screen.
	 */
	public static function page() {
		if ( ! current_user_can( Util::cap() ) ) {
			return;
		}
		$last = get_option( self::OPTION );
		echo '<div class="wrap bclaro-wrap bclaro-check">';
		Admin::title( __( 'Revisión del sitio', 'facilo-boton-arrepentimiento' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message flag only.
		$msg  = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		$msgs = array(
			'enviado' => array( 'success', sprintf( /* translators: %s: email. */ __( 'Te mandamos un correo de prueba a %s. Cuando te llegue, tocá “Me llegó”.', 'facilo-boton-arrepentimiento' ), Util::notify_email() ) ),
			'fallo'   => array( 'error', __( 'Tu sitio no pudo mandar el correo de prueba. Revisá la configuración de correo de tu hosting o usá un plugin de SMTP.', 'facilo-boton-arrepentimiento' ) ),
			'llego'   => array( 'success', __( 'Listo, anotamos que te llegó.', 'facilo-boton-arrepentimiento' ) ),
		);
		if ( isset( $msgs[ $msg ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $msgs[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $msgs[ $msg ][1] ) . '</p></div>';
		}
		echo '<div class="box"><div class="bclaro-run">';
		self::button( 'bclaro_check', __( 'Revisar ahora', 'facilo-boton-arrepentimiento' ), 'pri' );
		if ( is_array( $last ) && ! empty( $last['at'] ) ) {
			/* translators: %s: date and time. */
			echo '<span class="muted">' . esc_html( sprintf( __( 'Última revisión: %s', 'facilo-boton-arrepentimiento' ), Util::full( (int) $last['at'] ) ) ) . '</span>';
		}
		echo '</div>';
		if ( ! is_array( $last ) || empty( $last['lines'] ) ) {
			echo '<p>' . esc_html__( 'Tocá “Revisar ahora” y miramos tu sitio como lo ve un cliente.', 'facilo-boton-arrepentimiento' ) . '</p>';
		} else {
			$warn = 0;
			echo '<ul class="bclaro-lines">';
			foreach ( $last['lines'] as $l ) {
				if ( 'ok' !== $l['state'] ) {
					++$warn;
				}
				$icon = 'ok' === $l['state'] ? '✓' : ( 'warn' === $l['state'] ? '!' : '?' );
				echo '<li class="' . esc_attr( $l['state'] ) . '" data-k="' . esc_attr( $l['key'] ) . '"><span class="i" aria-hidden="true">' . esc_html( $icon ) . '</span> <span class="t">' . esc_html( $l['text'] ) . '</span>';
				if ( 'correo' === $l['key'] && 'ok' !== $l['state'] ) {
					echo '<span class="mini">';
					self::button( 'bclaro_test_mail', __( 'Enviar correo de prueba', 'facilo-boton-arrepentimiento' ) );
					self::button( 'bclaro_mail_ok', __( 'Me llegó', 'facilo-boton-arrepentimiento' ) );
					echo '</span>';
				}
				if ( 'paginas' === $l['key'] && 'ok' !== $l['state'] ) {
					echo ' <a class="bclaro-btn" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bclaro_recrear' ), 'bclaro_recrear' ) ) . '">' . esc_html__( 'Volver a crear la página', 'facilo-boton-arrepentimiento' ) . '</a>';
				}
				if ( 'atencion' === $l['key'] && 'ok' !== $l['state'] ) {
					echo ' <a href="' . esc_url( Util::admin_url( 'bclaro-ajustes', array( 'tab' => 'atencion' ) ) ) . '">' . esc_html__( 'Completar', 'facilo-boton-arrepentimiento' ) . '</a>';
				}
				echo '</li>';
			}
			echo '</ul>';
			if ( 0 === $warn ) {
				echo '<p class="bclaro-sum ok">' . esc_html__( 'No encontramos nada para corregir.', 'facilo-boton-arrepentimiento' ) . '</p>';
			} else {
				/* translators: %d: number of things to look at. */
				echo '<p class="bclaro-sum warn">' . esc_html( sprintf( _n( 'Hay %d cosa para mirar.', 'Hay %d cosas para mirar.', $warn, 'facilo-boton-arrepentimiento' ), $warn ) ) . '</p>';
			}
		}
		echo '<p class="bclaro-legal">' . esc_html__( 'Esta revisión mira solo lo que el plugin puede ver. No es asesoramiento legal.', 'facilo-boton-arrepentimiento' ) . '</p>';
		echo '</div></div>';
	}
}
