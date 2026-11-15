<?php
/**
 * Admin shell: menu, assets, notices, the activation redirect and the request actions.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class Admin {

	/**
	 * Our screens' page slugs.
	 */
	const PAGES = array( 'bclaro', 'bclaro-ajustes', 'bclaro-revision', 'bclaro-pro', 'bclaro-setup' );

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_setup' ) ); // After the access check, so the page still opens by URL.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( 'BotonClaro\\Pages', 'notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'mail_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BCLARO_FILE ), array( __CLASS__, 'links' ) );
		foreach ( array( 'status', 'replied', 'proof', 'retry', 'delete' ) as $a ) {
			add_action( 'admin_post_bclaro_' . $a, array( __CLASS__, 'act_' . $a ) );
		}
		add_action( 'wp_ajax_bclaro_css', array( __CLASS__, 'ajax_css' ) );
		AdminSettings::boot();
		Setup::boot();
		SiteCheck::boot();
	}

	/**
	 * The brand mark, as an SVG string.
	 *
	 * @param int $size Size in px.
	 * @return string
	 */
	public static function mark( $size = 28 ) {
		return '<svg class="bclaro-mark" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 28 28" aria-hidden="true" focusable="false"><rect width="28" height="28" rx="8" fill="#a8421f"/><path d="M7 14h13M15 8.5l6 5.5-6 5.5" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * Page title row with the mark.
	 *
	 * @param string $title Title.
	 * @param string $extra Extra HTML after the title, already escaped.
	 */
	public static function title( $title, $extra = '' ) {
		echo '<div class="bclaro-tit">' . self::mark() . '<h1>' . esc_html( $title ) . '</h1>' . $extra . '</div><hr class="wp-header-end">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- mark is static; $extra is escaped by the caller.
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		$cap   = Util::cap();
		$n     = Requests::open_count();
		$count = $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . number_format_i18n( $n ) . '</span></span>' : '';
		$icon  = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M4 1h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V4a3 3 0 0 1 3-3zm1 8v2h8.2l-3.1 2.9 1.4 1.4L17 10l-5.5-5.3-1.4 1.4L13.2 9z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI.
		add_menu_page( 'Botón Claro', 'Botón Claro' . $count, $cap, 'bclaro', array( 'BotonClaro\\AdminList', 'page' ), $icon, 56 );
		add_submenu_page( 'bclaro', __( 'Pedidos', 'boton-de-arrepentimiento' ), __( 'Pedidos', 'boton-de-arrepentimiento' ) . $count, $cap, 'bclaro', array( 'BotonClaro\\AdminList', 'page' ) );
		add_submenu_page( 'bclaro', __( 'Ajustes', 'boton-de-arrepentimiento' ), __( 'Ajustes', 'boton-de-arrepentimiento' ), $cap, 'bclaro-ajustes', array( 'BotonClaro\\AdminSettings', 'page' ) );
		add_submenu_page( 'bclaro', __( 'Revisión del sitio', 'boton-de-arrepentimiento' ), __( 'Revisión', 'boton-de-arrepentimiento' ), $cap, 'bclaro-revision', array( 'BotonClaro\\SiteCheck', 'page' ) );
		/**
		 * Pro adds its own screens here.
		 *
		 * @param string $parent Parent slug.
		 */
		do_action( 'bclaro_admin_menu', 'bclaro' );
		/**
		 * Whether to show the Pro menu item. Pro may keep it to show its account page.
		 *
		 * @param bool $show Show.
		 */
		if ( apply_filters( 'bclaro_pro_tab', true ) ) {
			add_submenu_page( 'bclaro', 'Botón Claro Pro', 'Pro', $cap, 'bclaro-pro', array( __CLASS__, 'pro_page' ) );
		}
		add_submenu_page( 'bclaro', __( 'Configurar Botón Claro', 'boton-de-arrepentimiento' ), __( 'Configurar', 'boton-de-arrepentimiento' ), $cap, 'bclaro-setup', array( 'BotonClaro\\Setup', 'page' ) );
	}

	/**
	 * Keep the setup reachable by URL but out of the menu.
	 */
	public static function hide_setup() {
		remove_submenu_page( 'bclaro', 'bclaro-setup' );
	}

	/**
	 * Whether the current screen is ours.
	 *
	 * @return string Page slug or ''.
	 */
	public static function is_own_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the screen slug only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return 0 === strpos( $page, 'bclaro' ) ? $page : '';
	}

	/**
	 * CSS and JS on our screens only.
	 */
	public static function assets() {
		$page = self::is_own_screen();
		if ( '' === $page ) {
			return;
		}
		wp_enqueue_style( 'bclaro-admin', BCLARO_URL . 'assets/admin.css', array(), BCLARO_VERSION );
		wp_enqueue_script( 'bclaro-admin', BCLARO_URL . 'assets/admin.js', array(), BCLARO_VERSION, true );
		wp_localize_script(
			'bclaro-admin',
			'bclaroAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'bclaro_css' ),
			)
		);
		/**
		 * Pro enqueues its own assets on our screens.
		 *
		 * @param string $page Page slug.
		 */
		do_action( 'bclaro_admin_assets', $page );
	}

	/**
	 * "Ajustes" next to Deactivate.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( Util::admin_url( 'bclaro-ajustes' ) ) . '">' . esc_html__( 'Ajustes', 'boton-de-arrepentimiento' ) . '</a>' );
		return $links;
	}

	/**
	 * Open the setup once, after the first activation.
	 */
	public static function redirect() {
		if ( ! get_transient( 'bclaro_setup_redirect' ) ) {
			return;
		}
		delete_transient( 'bclaro_setup_redirect' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress sets this on bulk activation; read only.
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( Util::cap() ) || get_option( 'bclaro_setup_done' ) ) {
			return;
		}
		wp_safe_redirect( Util::admin_url( 'bclaro-setup' ) );
		exit;
	}

	/**
	 * Notice when a code email gave up.
	 */
	public static function mail_notice() {
		if ( ! current_user_can( Util::cap() ) ) {
			return;
		}
		$s = Settings::get();
		foreach ( array_slice( array_map( 'intval', (array) $s['mail_alert'] ), 0, 3 ) as $id ) {
			$req = Requests::get( $id );
			if ( ! $req ) {
				Retry::clear_alert( $id );
				continue;
			}
			echo '<div class="notice notice-error bclaro-notice"><p><strong>' . esc_html( $req->code ) . ':</strong> ' . esc_html__( 'No pudimos enviar el código por correo.', 'boton-de-arrepentimiento' ) . ' <a href="' . esc_url( Util::admin_url( 'bclaro', array( 'id' => (int) $req->id ) ) ) . '">' . esc_html__( 'Ver el pedido', 'boton-de-arrepentimiento' ) . '</a></p></div>';
		}
	}

	/**
	 * Check the user and the nonce, and load the request.
	 *
	 * @return object
	 */
	private static function request_from_post() {
		if ( ! current_user_can( Util::cap() ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'boton-de-arrepentimiento' ), 403 );
		}
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line.
		check_admin_referer( 'bclaro_req_' . $id );
		$req = Requests::get( $id );
		if ( ! $req ) {
			wp_die( esc_html__( 'No encontramos ese pedido.', 'boton-de-arrepentimiento' ), 404 );
		}
		return $req;
	}

	/**
	 * Back to the request page with a message key.
	 *
	 * @param object $req Request.
	 * @param string $msg Message key.
	 */
	private static function back( $req, $msg ) {
		wp_safe_redirect(
			Util::admin_url(
				'bclaro',
				array(
					'id'  => (int) $req->id,
					'msg' => $msg,
				)
			)
		);
		exit;
	}

	/**
	 * "Cambiar estado".
	 */
	public static function act_status() {
		$req    = self::request_from_post();
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in request_from_post().
		if ( ! isset( Util::statuses()[ $status ] ) ) {
			self::back( $req, 'error' );
		}
		if ( $status !== $req->status ) {
			Requests::set_status( $req, $status, get_current_user_id() );
		}
		self::back( $req, 'estado' );
	}

	/**
	 * "Ya respondí".
	 */
	public static function act_replied() {
		$req = self::request_from_post();
		if ( ! $req->replied_at ) {
			Requests::replied( $req, get_current_user_id() );
		}
		self::back( $req, 'respondido' );
	}

	/**
	 * "Dar de baja y enviar constancia". Only after the owner's confirming click.
	 */
	public static function act_proof() {
		$req = self::request_from_post();
		if ( 'baja' !== $req->type || $req->proof_sent_at ) {
			self::back( $req, 'error' );
		}
		$now = Util::now();
		if ( ! Mailer::send_proof( $req, $now ) ) {
			self::back( $req, 'constancia_fallo' );
		}
		Requests::update( $req->id, array( 'proof_sent_at' => Util::mysql( $now ) ) );
		Requests::event( $req->id, 'constancia', '', get_current_user_id() );
		$req = Requests::get( $req->id );
		if ( ! $req->replied_at ) {
			Requests::replied( $req, get_current_user_id() );
			$req = Requests::get( $req->id );
		}
		if ( 'resuelto' !== $req->status ) {
			Requests::set_status( $req, 'resuelto', get_current_user_id() );
		}
		self::back( $req, 'constancia' );
	}

	/**
	 * "Reintentar envío".
	 */
	public static function act_retry() {
		$req = self::request_from_post();
		$ok  = Mailer::send_code( $req, true );
		self::back( $req, $ok ? 'reenviado' : 'reenvio_fallo' );
	}

	/**
	 * Delete a test or spam request. Real requests can't be deleted.
	 */
	public static function act_delete() {
		$req = self::request_from_post();
		if ( ! Requests::delete( $req ) ) {
			self::back( $req, 'error' );
		}
		wp_safe_redirect( Util::admin_url( 'bclaro', array( 'msg' => 'borrado' ) ) );
		exit;
	}

	/**
	 * Live preview: the CSS for a style and colour, plus the colour message.
	 */
	public static function ajax_css() {
		check_ajax_referer( 'bclaro_css' );
		if ( ! current_user_can( Util::cap() ) ) {
			wp_send_json_error( null, 403 );
		}
		$all   = Styles::all();
		$style = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : 'banda';
		$style = isset( $all[ $style ] ) ? $style : 'banda';
		$color = isset( $_POST['color'] ) ? Styles::clean_color( sanitize_text_field( wp_unslash( $_POST['color'] ) ) ) : '';
		$s     = array_merge( Settings::get(), array( 'style' => $style ) );
		/** This filter is documented in includes/class-adminsettings.php */
		$s   = (array) apply_filters( 'bclaro_sanitize_settings', $s, wp_unslash( $_POST ), 'preview' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Pro sanitizes its own keys in this filter.
		$out = array(
			'css' => Styles::css( $style, $color ? $color : '#a8421f', $s ),
			'msg' => self::color_message( $color ),
		);
		if ( ! empty( $_POST['tiles'] ) ) {
			$out['tiles'] = array();
			foreach ( array_keys( $all ) as $slug ) {
				$slug                  = sanitize_key( $slug );
				$out['tiles'][ $slug ] = Styles::scope( Styles::css( $slug, $color ? $color : '#a8421f', $s ), '#bclaro-tile-' . $slug );
			}
		}
		wp_send_json_success( $out );
	}

	/**
	 * The colour message under the swatches.
	 *
	 * @param string $color Colour.
	 * @return array text, bad.
	 */
	public static function color_message( $color ) {
		$color = Styles::clean_color( $color );
		if ( '' === $color || ! Styles::legible( $color ) ) {
			return array(
				'text' => __( 'Este color no se lee bien ni con texto blanco ni con texto negro. Probá uno más claro o más oscuro.', 'boton-de-arrepentimiento' ),
				'bad'  => true,
			);
		}
		if ( '#ffffff' !== Styles::text_on( $color ) ) {
			return array(
				'text' => __( 'Con este color ponemos el texto en negro, para que se lea bien.', 'boton-de-arrepentimiento' ),
				'bad'  => false,
			);
		}
		return array(
			'text' => __( 'Se lee bien, con texto blanco.', 'boton-de-arrepentimiento' ),
			'bad'  => false,
		);
	}

	/**
	 * The Pro page.
	 */
	public static function pro_page() {
		echo '<div class="wrap bclaro-wrap">';
		if ( has_action( 'bclaro_pro_tab_content' ) ) {
			/**
			 * Pro's own account page.
			 */
			do_action( 'bclaro_pro_tab_content' );
			echo '</div>';
			return;
		}
		$items = array(
			__( 'Sabé al instante si un pedido está dentro de los 10 días, con feriados incluidos', 'boton-de-arrepentimiento' ),
			__( 'Aceptá, devolvé la plata y reponé el stock en un clic', 'boton-de-arrepentimiento' ),
			__( 'Respondé con correos listos para cada caso', 'boton-de-arrepentimiento' ),
			__( 'Cancelá suscripciones y mandá la constancia en un solo paso', 'boton-de-arrepentimiento' ),
			__( 'Guardá un archivo de cada pedido y exportá todo a CSV', 'boton-de-arrepentimiento' ),
			__( 'Recibí avisos antes de que venza un plazo', 'boton-de-arrepentimiento' ),
		);
		self::title( 'Botón Claro Pro' );
		echo '<div class="bclaro-pro"><div class="bclaro-pro-in">';
		echo '<h2>' . esc_html__( 'Resolvé cada pedido en un clic', 'boton-de-arrepentimiento' ) . '</h2>';
		echo '<p class="bclaro-lead">' . esc_html__( 'Botón Claro gratis recibe los pedidos. Pro te ahorra el trabajo que viene después.', 'boton-de-arrepentimiento' ) . '</p><ul class="bclaro-checks">';
		foreach ( $items as $i ) {
			echo '<li>' . esc_html( $i ) . '</li>';
		}
		echo '</ul><p class="bclaro-price">' . esc_html__( 'USD 19 al año por tienda. USD 59 al año para agencias, tiendas sin límite.', 'boton-de-arrepentimiento' ) . '</p>';
		echo '<p class="bclaro-fine">' . esc_html__( '14 días para recuperar tu dinero. Se paga en dólares con tarjeta, y tu banco puede sumar impuestos.', 'boton-de-arrepentimiento' ) . '</p>';
		echo '<p><a class="bclaro-btn pri" href="' . esc_url( BCLARO_PRO_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Pasar a Pro', 'boton-de-arrepentimiento' ) . '</a></p>';
		echo '</div></div></div>';
	}
}
