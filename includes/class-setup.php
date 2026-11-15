<?php
/**
 * The guided setup: what you sell, customer service, buttons, and a done screen.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Setup.
 */
class Setup {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_post_bclaro_setup', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_bclaro_setup_skip', array( __CLASS__, 'skip' ) );
		add_action( 'admin_post_bclaro_test_request', array( __CLASS__, 'test_request' ) );
	}

	/**
	 * Current step, 1 to 4.
	 *
	 * @return int
	 */
	private static function step() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only step switch.
		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		return max( 1, min( 4, $step ) );
	}

	/**
	 * The step dots.
	 *
	 * @param int $step Step.
	 */
	private static function dots( $step ) {
		$names = array(
			1 => __( 'Qué vendés', 'boton-de-arrepentimiento' ),
			2 => __( 'Atención', 'boton-de-arrepentimiento' ),
			3 => __( 'Botones', 'boton-de-arrepentimiento' ),
			4 => __( 'Listo', 'boton-de-arrepentimiento' ),
		);
		echo '<ol class="bclaro-steps">';
		foreach ( $names as $n => $name ) {
			$cls = $n < $step ? 'done' : ( $n === $step ? 'on' : '' );
			echo '<li class="' . esc_attr( $cls ) . '"' . ( $n === $step ? ' aria-current="step"' : '' ) . '><span>' . (int) $n . '</span> ' . esc_html( $name ) . '</li>';
		}
		echo '</ol>';
	}

	/**
	 * Form opening for a step.
	 *
	 * @param int $step Step.
	 */
	private static function open( $step ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bclaro-form">';
		echo '<input type="hidden" name="action" value="bclaro_setup"><input type="hidden" name="step" value="' . (int) $step . '">';
		wp_nonce_field( 'bclaro_setup' );
	}

	/**
	 * Footer of a step form: back, skip and next.
	 *
	 * @param int    $step  Step.
	 * @param string $label Next button words.
	 */
	private static function close( $step, $label ) {
		echo '<div class="bclaro-nav">';
		if ( $step > 1 ) {
			echo '<a class="bclaro-btn" href="' . esc_url( Util::admin_url( 'bclaro-setup', array( 'step' => $step - 1 ) ) ) . '">' . esc_html__( 'Volver', 'boton-de-arrepentimiento' ) . '</a>';
		}
		echo '<a class="skip" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bclaro_setup_skip' ), 'bclaro_setup_skip' ) ) . '">' . esc_html__( 'Saltear por ahora', 'boton-de-arrepentimiento' ) . '</a>';
		echo '<button type="submit" class="bclaro-btn pri">' . esc_html( $label ) . '</button></div></form>';
	}

	/**
	 * The screen.
	 */
	public static function page() {
		if ( ! current_user_can( Util::cap() ) ) {
			return;
		}
		$s    = Settings::get();
		$step = self::step();
		echo '<div class="wrap bclaro-wrap bclaro-setup bclaro-step-' . (int) $step . '">';
		echo '<div class="bclaro-setup-head">' . Admin::mark( 32 ) . ' <span>Botón Claro</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		self::dots( $step );
		echo '<div class="bclaro-card">';
		call_user_func( array( __CLASS__, 'step' . $step ), $s );
		echo '</div></div>';
	}

	/**
	 * Step 1: what you sell.
	 *
	 * @param array $s Settings.
	 */
	private static function step1( $s ) {
		self::open( 1 );
		echo '<h1>' . esc_html__( 'Dejemos tus botones listos', 'boton-de-arrepentimiento' ) . '</h1>';
		echo '<p class="bclaro-lead">' . esc_html__( 'Son 3 pasos y te lleva unos 3 minutos.', 'boton-de-arrepentimiento' ) . '</p>';
		echo '<fieldset class="bclaro-choice"><legend>' . esc_html__( '¿Qué vendés?', 'boton-de-arrepentimiento' ) . '</legend>';
		$opts = array(
			'productos' => __( 'Productos', 'boton-de-arrepentimiento' ),
			'servicios' => __( 'Servicios o suscripciones', 'boton-de-arrepentimiento' ),
			'ambas'     => __( 'Las dos cosas', 'boton-de-arrepentimiento' ),
		);
		foreach ( $opts as $k => $label ) {
			echo '<label><input type="radio" name="sells" value="' . esc_attr( $k ) . '"' . checked( $s['sells'], $k, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</fieldset>';
		self::close( 1, __( 'Seguir', 'boton-de-arrepentimiento' ) );
	}

	/**
	 * Step 2: customer service.
	 *
	 * @param array $s Settings.
	 */
	private static function step2( $s ) {
		self::open( 2 );
		echo '<h1>' . esc_html__( '¿Cómo te contactan tus clientes?', 'boton-de-arrepentimiento' ) . '</h1>';
		echo '<p class="bclaro-lead">' . esc_html__( 'Estos datos se muestran junto a los botones.', 'boton-de-arrepentimiento' ) . '</p>';
		AdminSettings::service_fields( $s );
		self::close( 2, __( 'Seguir', 'boton-de-arrepentimiento' ) );
	}

	/**
	 * Step 3: the buttons, with a live preview.
	 *
	 * @param array $s Settings.
	 */
	private static function step3( $s ) {
		$rec = Styles::recommend();
		if ( ! get_option( 'bclaro_setup_done' ) && '' === (string) $s['rec_style'] ) {
			$s['style'] = $rec['style'];
			$s['color'] = $rec['color'];
		}
		self::open( 3 );
		echo '<h1>' . esc_html__( '¿Dónde van los botones?', 'boton-de-arrepentimiento' ) . '</h1>';
		echo '<div class="cols"><div>';
		echo '<div class="chk bclaro-where">';
		foreach (
			array(
				'strip' => array( __( 'Franja al pie de todas las páginas', 'boton-de-arrepentimiento' ), 'franja' ),
				'pill'  => array( __( 'Botón flotante, siempre a la vista', 'boton-de-arrepentimiento' ), 'pill' ),
				'baja'  => array( __( 'Mostrar también el botón de baja de servicio', 'boton-de-arrepentimiento' ), 'baja' ),
			) as $k => $o
		) {
			echo '<label class="bclaro-check"><input type="hidden" name="' . esc_attr( $k ) . '" value="0"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1" data-bclaro-place="' . esc_attr( $o[1] ) . '"' . checked( ! empty( $s[ $k ] ), true, false ) . '> ' . esc_html( $o[0] ) . '</label>';
		}
		echo '</div>';
		if ( 'productos' === $s['sells'] ) {
			echo '<p class="bclaro-note">' . esc_html__( 'Si solo vendés productos, quizá no lo necesites: la norma habla de bienes y servicios, pero este botón sirve para dar de baja un servicio contratado. Ante la duda, dejalo prendido, no molesta a nadie.', 'boton-de-arrepentimiento' ) . '</p>';
		}
		$all = Styles::all();
		$nm  = isset( $all[ $s['style'] ]['label'] ) ? $all[ $s['style'] ]['label'] : $s['style'];
		echo '<input type="hidden" name="style" id="bclaro-style" value="' . esc_attr( $s['style'] ) . '"><input type="hidden" name="color" id="bclaro-color" value="' . esc_attr( $s['color'] ) . '">';
		/* translators: %s: style name. */
		echo '<div class="elegido"><b>' . esc_html( sprintf( __( 'Elegimos %s para tu tienda', 'boton-de-arrepentimiento' ), $nm ) ) . '</b>';
		echo esc_html( $s['style'] === $rec['style'] ? $rec['why'] : '' ) . ' ' . esc_html__( 'Podés cambiarlo cuando quieras en Ajustes.', 'boton-de-arrepentimiento' ) . '</div>';
		echo '</div><div>';
		AdminSettings::preview_box();
		echo '</div></div>';
		self::close( 3, __( 'Crear mis botones', 'boton-de-arrepentimiento' ) );
	}

	/**
	 * Step 4: done.
	 *
	 * @param array $s Settings.
	 */
	private static function step4( $s ) {
		echo '<div class="bclaro-done">' . Admin::mark( 56 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		echo '<h1>' . esc_html__( 'Tus botones ya están en tu sitio', 'boton-de-arrepentimiento' ) . '</h1>';
		echo '<p class="bclaro-lead">' . esc_html__( 'Probalos una vez antes de irte: te va a llegar el mismo correo que recibe un cliente.', 'boton-de-arrepentimiento' ) . '</p>';
		echo '<div class="act"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bclaro_test_request">';
		wp_nonce_field( 'bclaro_test_request' );
		echo '<button type="submit" class="bclaro-btn pri">' . esc_html__( 'Hacer un pedido de prueba', 'boton-de-arrepentimiento' ) . '</button></form>';
		echo '<a class="bclaro-btn" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver mi sitio', 'boton-de-arrepentimiento' ) . '</a>';
		echo '<a class="bclaro-btn" href="' . esc_url( Util::admin_url( 'bclaro-revision' ) ) . '">' . esc_html__( 'Revisar mi sitio', 'boton-de-arrepentimiento' ) . '</a></div>';
		if ( '' !== (string) $s['url_arrepentimiento'] ) {
			echo '<p class="muted">' . esc_html__( 'Tu formulario:', 'boton-de-arrepentimiento' ) . ' <a href="' . esc_url( $s['url_arrepentimiento'] ) . '" target="_blank" rel="noopener">' . esc_html( $s['url_arrepentimiento'] ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Check the user and the nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( Util::cap() ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'boton-de-arrepentimiento' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Save a step and go to the next one.
	 */
	public static function save() {
		if ( ! current_user_can( Util::cap() ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'boton-de-arrepentimiento' ), 403 );
		}
		check_admin_referer( 'bclaro_setup' );
		$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
		$raw  = (array) wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized key by key below.
		if ( 1 === $step ) {
			$sells = isset( $raw['sells'] ) ? sanitize_key( $raw['sells'] ) : 'ambas';
			Settings::update( array( 'sells' => in_array( $sells, array( 'productos', 'servicios', 'ambas' ), true ) ? $sells : 'ambas' ) );
		} elseif ( 2 === $step ) {
			Settings::update( AdminSettings::sanitize( $raw, 'setup2' ) );
		} elseif ( 3 === $step ) {
			$rec   = Styles::recommend();
			$clean = AdminSettings::sanitize( $raw, 'setup3' );
			$clean = array_merge(
				$clean,
				array(
					'rec_style' => $rec['style'],
					'rec_color' => $rec['color'],
					'rec_why'   => $rec['why'],
				)
			);
			Settings::update( $clean );
			foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
				if ( Pages::needed( $type ) ) {
					Pages::ensure( $type );
				}
			}
			update_option( 'bclaro_setup_done', 1, false );
		}
		wp_safe_redirect( Util::admin_url( 'bclaro-setup', array( 'step' => min( 4, $step + 1 ) ) ) );
		exit;
	}

	/**
	 * "Saltear por ahora". No pages are made until step 3 is saved.
	 */
	public static function skip() {
		self::guard( 'bclaro_setup_skip' );
		update_option( 'bclaro_setup_done', 1, false );
		wp_safe_redirect( Util::admin_url( 'bclaro' ) );
		exit;
	}

	/**
	 * "Hacer un pedido de prueba": a Prueba request to the owner, with no clock.
	 */
	public static function test_request() {
		self::guard( 'bclaro_test_request' );
		$user = wp_get_current_user();
		$req  = Requests::create(
			array(
				'type'      => 'arrepentimiento',
				'name'      => $user->display_name ? $user->display_name : __( 'Pedido de prueba', 'boton-de-arrepentimiento' ),
				'email'     => Util::notify_email(),
				'phone'     => '',
				'order_ref' => '',
				'scope'     => 'todo',
				'service'   => '',
				'detail'    => '',
				'message'   => __( 'Pedido de prueba hecho desde la configuración.', 'boton-de-arrepentimiento' ),
				'is_test'   => 1,
			)
		);
		if ( ! $req || is_wp_error( $req ) ) {
			wp_safe_redirect( Util::admin_url( 'bclaro', array( 'msg' => 'error' ) ) );
			exit;
		}
		Mailer::send_code( $req );
		wp_safe_redirect(
			Util::admin_url(
				'bclaro',
				array(
					'id'  => (int) $req->id,
					'msg' => 'prueba',
				)
			)
		);
		exit;
	}
}
