<?php
/**
 * Ajustes: six tabs in one option, plus the style gallery and live preview shared with the setup.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * AdminSettings.
 */
class AdminSettings {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'admin_post_bclaro_save', array( __CLASS__, 'save' ) );
	}

	/**
	 * Tabs, slug => label.
	 *
	 * @return array
	 */
	public static function tabs() {
		$tabs = array(
			'botones'     => __( 'Botones', 'facilo-boton-arrepentimiento' ),
			'formularios' => __( 'Formularios', 'facilo-boton-arrepentimiento' ),
			'atencion'    => __( 'Atención al cliente', 'facilo-boton-arrepentimiento' ),
			'correos'     => __( 'Correos', 'facilo-boton-arrepentimiento' ),
			'aviso'       => __( 'Aviso de derechos', 'facilo-boton-arrepentimiento' ),
			'privacidad'  => __( 'Privacidad', 'facilo-boton-arrepentimiento' ),
		);
		/**
		 * Ajustes tabs. Pro adds its own and renders them through bclaro_settings_tab_{slug}.
		 *
		 * @param array $tabs slug => label.
		 */
		return (array) apply_filters( 'bclaro_settings_tabs', $tabs );
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current() {
		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'botones';
		return isset( $tabs[ $tab ] ) ? $tab : 'botones';
	}

	/**
	 * A text field row.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 * @param array  $s     Settings.
	 * @param array  $o     type, placeholder, help, rows.
	 */
	private static function field( $key, $label, $s, $o = array() ) {
		$o   = array_merge(
			array(
				'type'        => 'text',
				'placeholder' => '',
				'help'        => '',
				'rows'        => 0,
			),
			$o
		);
		$id  = 'bclaro-' . $key;
		$val = isset( $s[ $key ] ) ? (string) $s[ $key ] : '';
		echo '<div class="bclaro-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( $o['rows'] ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" rows="' . (int) $o['rows'] . '" class="large-text">' . esc_textarea( $val ) . '</textarea>';
		} else {
			echo '<input type="' . esc_attr( $o['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $o['placeholder'] ) . '" class="regular-text">';
		}
		if ( '' !== $o['help'] ) {
			echo '<p class="description">' . esc_html( $o['help'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * A checkbox row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param array  $s     Settings.
	 * @param string $place Placement name for the live preview, or ''.
	 */
	private static function check( $key, $label, $s, $place = '' ) {
		echo '<label class="bclaro-check"><input type="hidden" name="' . esc_attr( $key ) . '" value="0"><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( ! empty( $s[ $key ] ), true, false ) . ( '' !== $place ? ' data-bclaro-place="' . esc_attr( $place ) . '"' : '' ) . '> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * The screen.
	 */
	public static function page() {
		if ( ! current_user_can( Util::cap() ) ) {
			return;
		}
		$s    = Settings::get();
		$tabs = self::tabs();
		$tab  = self::current();
		echo '<div class="wrap bclaro-wrap">';
		Admin::title( __( 'Ajustes', 'facilo-boton-arrepentimiento' ), '<a class="page-title-action" href="' . esc_url( Util::admin_url( 'bclaro-setup' ) ) . '">' . esc_html__( 'Abrir la configuración guiada', 'facilo-boton-arrepentimiento' ) . '</a>' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message flag only.
		if ( isset( $_GET['msg'] ) && 'guardado' === sanitize_key( wp_unslash( $_GET['msg'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Guardamos los cambios.', 'facilo-boton-arrepentimiento' ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper bclaro-navtabs">';
		foreach ( $tabs as $slug => $label ) {
			echo '<a class="nav-tab' . ( $slug === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( Util::admin_url( 'bclaro-ajustes', array( 'tab' => $slug ) ) ) . '"' . ( $slug === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		$own = array( 'botones', 'formularios', 'atencion', 'correos', 'aviso', 'privacidad' );
		if ( ! in_array( $tab, $own, true ) ) {
			/**
			 * Render a tab Free doesn't know.
			 *
			 * @param array $s Settings.
			 */
			do_action( 'bclaro_settings_tab_' . $tab, $s );
			echo '</div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="bclaro-form bclaro-tab-' . esc_attr( $tab ) . '">';
		echo '<input type="hidden" name="action" value="bclaro_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'bclaro_save' );
		call_user_func( array( __CLASS__, 'tab_' . $tab ), $s );
		echo '<p class="save"><button type="submit" class="bclaro-btn pri">' . esc_html__( 'Guardar cambios', 'facilo-boton-arrepentimiento' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * The gallery tiles' CSS, one inline style per tile (admin.js swaps each one when the colour changes).
	 * Hooked from Admin::assets on the settings screen, so nothing prints a style tag itself.
	 */
	public static function enqueue_tile_styles() {
		$s   = Settings::get();
		$col = Styles::clean_color( $s['color'] );
		$col = $col ? $col : Styles::recommend()['color'];
		foreach ( array_keys( Styles::all() ) as $slug ) {
			$slug   = sanitize_key( $slug );
			$handle = 'bclaro-tile-css-' . $slug;
			wp_register_style( $handle, false, array( 'bclaro-admin' ), BCLARO_VERSION );
			wp_enqueue_style( $handle );
			wp_add_inline_style( $handle, wp_strip_all_tags( Styles::scope( Styles::css( $slug, $col, $s ), '#bclaro-tile-' . $slug ) ) );
		}
	}

	/**
	 * Style gallery and details.
	 *
	 * @param array $s    Settings.
	 * @param bool  $full Show the reason line and the "back to recommended" link.
	 */
	public static function style_controls( $s, $full = true ) {
		$rec = Styles::recommend();
		$all = Styles::all();
		$col = Styles::clean_color( $s['color'] );
		$col = $col ? $col : $rec['color'];
		echo '<input type="hidden" name="style" id="bclaro-style" value="' . esc_attr( $s['style'] ) . '">';
		echo '<input type="hidden" name="color" id="bclaro-color" value="' . esc_attr( $col ) . '">';
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Estilo', 'facilo-boton-arrepentimiento' ) . '</h2>';
		if ( $full ) {
			echo '<div class="rec" data-style="' . esc_attr( $rec['style'] ) . '" data-color="' . esc_attr( $rec['color'] ) . '"><p>' . esc_html( $rec['why'] ) . '</p><button type="button" class="button-link" id="bclaro-volver">' . esc_html__( 'Volver al recomendado', 'facilo-boton-arrepentimiento' ) . '</button></div>';
		}
		echo '<div class="gal" role="radiogroup" aria-label="' . esc_attr__( 'Estilo', 'facilo-boton-arrepentimiento' ) . '">';
		$label = Settings::label( 'arrepentimiento' );
		foreach ( $all as $slug => $st ) {
			$slug = sanitize_key( $slug );
			$on   = $slug === $s['style'];
			echo '<button type="button" class="st8' . ( $on ? ' on' : '' ) . '" data-e="' . esc_attr( $slug ) . '" role="radio" aria-checked="' . ( $on ? 'true' : 'false' ) . '">';
			echo '<span class="mu" id="bclaro-tile-' . esc_attr( $slug ) . '"><span class="bclaro-franja"><span class="bclaro-b">' . esc_html( $label ) . '</span></span></span>';
			echo '<b>' . esc_html( isset( $st['label'] ) ? $st['label'] : $slug ) . '</b>';
			if ( $slug === $rec['style'] ) {
				echo '<span class="badge">' . esc_html__( 'Recomendado para tu tienda', 'facilo-boton-arrepentimiento' ) . '</span>';
			}
			echo '</button>';
		}
		echo '</div></div>';

		$pal = Styles::palette();
		if ( ! in_array( $col, $pal, true ) ) {
			array_unshift( $pal, $col );
			$pal = array_slice( $pal, 0, 6 );
		}
		$msg = Admin::color_message( $col );
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Detalles', 'facilo-boton-arrepentimiento' ) . '</h2>';
		echo '<div class="fila"><span>' . esc_html__( 'Color', 'facilo-boton-arrepentimiento' ) . '</span><div class="sws">';
		foreach ( $pal as $c ) {
			echo '<button type="button" class="sw' . ( $c === $col ? ' on' : '' ) . '" data-c="' . esc_attr( $c ) . '" style="background:' . esc_attr( $c ) . '" title="' . esc_attr( $c ) . '"><span class="screen-reader-text">' . esc_html( $c ) . '</span></button>';
		}
		echo '<label class="otro"><input type="color" id="bclaro-otro" value="' . esc_attr( $col ) . '"> ' . esc_html__( 'Otro color', 'facilo-boton-arrepentimiento' ) . '</label></div></div>';
		echo '<p class="lee' . ( $msg['bad'] ? ' mal' : '' ) . '" id="bclaro-lee" aria-live="polite">' . esc_html( $msg['text'] ) . '</p>';
	}

	/**
	 * The live preview box: the shop's home page in an iframe with the settings applied.
	 */
	public static function preview_box() {
		$url = add_query_arg( 'bclaro_vista', '1', home_url( '/' ) );
		echo '<div class="bclaro-box pv"><h2>' . esc_html__( 'Así se ve en tu tienda', 'facilo-boton-arrepentimiento' ) . ' <span class="seg" data-k="ver"><button type="button" class="on" data-v="pc">' . esc_html__( 'Computadora', 'facilo-boton-arrepentimiento' ) . '</button><button type="button" data-v="cel">' . esc_html__( 'Celular', 'facilo-boton-arrepentimiento' ) . '</button></span></h2>';
		echo '<div class="vp" id="bclaro-vp"><iframe id="bclaro-frame" src="' . esc_url( $url ) . '" title="' . esc_attr__( 'Vista previa', 'facilo-boton-arrepentimiento' ) . '" tabindex="-1" loading="lazy"></iframe></div></div>';
	}

	/**
	 * Botones.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_botones( $s ) {
		echo '<div class="cols"><div>';
		self::style_controls( $s, true );
		echo '<div class="fila"><span>' . esc_html__( 'Dónde', 'facilo-boton-arrepentimiento' ) . '</span><div class="chk">';
		self::check( 'strip', __( 'Franja al pie', 'facilo-boton-arrepentimiento' ), $s, 'franja' );
		self::check( 'pill', __( 'Botón flotante', 'facilo-boton-arrepentimiento' ), $s, 'pill' );
		echo '</div></div>';
		if ( empty( $s['strip'] ) && empty( $s['pill'] ) ) {
			echo '<p class="bclaro-warn">' . esc_html__( 'Apagaste las dos posiciones. Si no pusiste los botones con un bloque, tus clientes no los van a ver.', 'facilo-boton-arrepentimiento' ) . '</p>';
		}
		/**
		 * Extra controls at the end of the Botones box (Pro's Detalles).
		 *
		 * @param array $s Settings.
		 */
		do_action( 'bclaro_settings_after_botones', $s );
		echo '</div>';

		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Nombres', 'facilo-boton-arrepentimiento' ) . '</h2>';
		self::check( 'baja', __( 'Mostrar también el botón de baja de servicio', 'facilo-boton-arrepentimiento' ), $s, 'baja' );
		$r = Util::rules();
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			self::field( 'label_' . $type, Util::type_label( $type ), $s, array( 'placeholder' => $r['labels'][ $type ] ) );
			if ( Settings::label_changed( $type ) ) {
				/* translators: %s: exact legal name. */
				echo '<p class="bclaro-warn">' . esc_html( sprintf( __( 'Cambiaste el nombre. La norma lo llama “%s”: te recomendamos dejarlo así.', 'facilo-boton-arrepentimiento' ), $r['labels'][ $type ] ) ) . '</p>';
			}
		}
		echo '<p class="description">' . esc_html__( 'También podés ponerlos en cualquier página con el bloque “Botones de arrepentimiento y baja” o con [facilo_boton_arrepentimiento] y [facilo_boton_baja].', 'facilo-boton-arrepentimiento' ) . '</p>';
		echo '</div></div><div>';
		self::preview_box();
		echo '</div></div>';
	}

	/**
	 * Formularios.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_formularios( $s ) {
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Páginas de los formularios', 'facilo-boton-arrepentimiento' ) . '</h2><ul class="bclaro-pages">';
		$missing = Pages::missing();
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			if ( ! Pages::needed( $type ) ) {
				continue;
			}
			$id = (int) $s[ 'page_' . $type ];
			echo '<li><b>' . esc_html( Util::type_label( $type ) ) . ':</b> ';
			if ( in_array( $type, $missing, true ) ) {
				echo '<span class="bclaro-warn-inline">' . esc_html__( 'Falta la página del formulario. Hasta que vuelva, el botón no se muestra.', 'facilo-boton-arrepentimiento' ) . '</span>';
			} else {
				echo '<a href="' . esc_url( $s[ 'url_' . $type ] ) . '" target="_blank" rel="noopener">' . esc_html( $s[ 'url_' . $type ] ) . '</a> · <a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html__( 'Editar', 'facilo-boton-arrepentimiento' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';
		if ( $missing ) {
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bclaro_recrear' ), 'bclaro_recrear' ) ) . '">' . esc_html__( 'Volver a crear la página', 'facilo-boton-arrepentimiento' ) . '</a></p>';
		}
		echo '<p class="description">' . esc_html__( 'Si querés el formulario en otra página, usá el bloque “Formulario de arrepentimiento o baja” o [facilo_arrepentimiento_formulario tipo="arrepentimiento"].', 'facilo-boton-arrepentimiento' ) . '</p></div>';
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Texto bajo el formulario de arrepentimiento', 'facilo-boton-arrepentimiento' ) . '</h2>';
		self::field(
			'rights_text',
			__( 'Tus derechos, en palabras simples', 'facilo-boton-arrepentimiento' ),
			$s,
			array(
				'rows' => 7,
				'help' => __( 'Usá **así** para negrita. Dejá una línea en blanco entre párrafos.', 'facilo-boton-arrepentimiento' ),
			)
		);
		do_action( 'bclaro_settings_after_formularios', $s ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in the hooks contract.
		echo '</div>';
	}

	/**
	 * Atención al cliente.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_atencion( $s ) {
		echo '<div class="bclaro-box"><h2>' . esc_html__( '¿Cómo te contactan tus clientes?', 'facilo-boton-arrepentimiento' ) . '</h2><p>' . esc_html__( 'Estos datos se muestran junto a los botones.', 'facilo-boton-arrepentimiento' ) . '</p>';
		self::service_fields( $s );
		do_action( 'bclaro_settings_after_atencion', $s ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in the hooks contract.
		echo '</div>';
	}

	/**
	 * The customer service fields. Shared with the setup.
	 *
	 * @param array $s Settings.
	 */
	public static function service_fields( $s ) {
		self::field(
			'hours',
			__( 'Horario de atención', 'facilo-boton-arrepentimiento' ),
			$s,
			array(
				'placeholder' => __( 'Lunes a viernes de 9 a 17', 'facilo-boton-arrepentimiento' ),
				'help'        => __( 'Si atendés solo por teléfono o por internet, la Disposición 954/2025 pide al menos 8 horas en días hábiles, de lunes a viernes.', 'facilo-boton-arrepentimiento' ),
			)
		);
		self::field( 'phone', __( 'Teléfono', 'facilo-boton-arrepentimiento' ), $s, array( 'type' => 'tel' ) );
		self::field( 'email', __( 'Correo de atención', 'facilo-boton-arrepentimiento' ), $s, array( 'type' => 'email' ) );
		self::field( 'claims', __( 'Otro canal para reclamos', 'facilo-boton-arrepentimiento' ), $s, array( 'placeholder' => __( 'WhatsApp, o un enlace', 'facilo-boton-arrepentimiento' ) ) );
		self::field( 'area', __( 'Área responsable', 'facilo-boton-arrepentimiento' ), $s, array( 'placeholder' => __( 'Atención al cliente', 'facilo-boton-arrepentimiento' ) ) );
		$n = $s;
		if ( '' === (string) $n['notify'] ) {
			$n['notify'] = (string) get_option( 'admin_email' );
		}
		self::field( 'notify', __( 'Correo donde recibís los avisos', 'facilo-boton-arrepentimiento' ), $n, array( 'type' => 'email' ) );
	}

	/**
	 * Correos.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_correos( $s ) {
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Correos', 'facilo-boton-arrepentimiento' ) . '</h2>';
		self::field(
			'notify',
			__( 'Correo donde recibís los avisos', 'facilo-boton-arrepentimiento' ),
			$s,
			array(
				'type' => 'email',
				'help' => __( 'Te avisamos acá cuando llega un pedido, y si no pudimos mandarle el código a un cliente.', 'facilo-boton-arrepentimiento' ),
			)
		);
		echo '<p class="description">' . esc_html__( 'Los clientes reciben su código al instante, con tu correo de atención para responder. Si un envío falla, lo reintentamos solos durante el día.', 'facilo-boton-arrepentimiento' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( Util::admin_url( 'bclaro-revision' ) ) . '">' . esc_html__( 'Enviar correo de prueba', 'facilo-boton-arrepentimiento' ) . '</a></p>';
		do_action( 'bclaro_settings_after_correos', $s ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in the hooks contract.
		echo '</div>';
	}

	/**
	 * Aviso de derechos.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_aviso( $s ) {
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Aviso de derechos', 'facilo-boton-arrepentimiento' ) . '</h2>';
		self::check( 'notice_on', __( 'Mostrar el aviso en los correos de pedido y en el pago', 'facilo-boton-arrepentimiento' ), $s );
		self::field(
			'notice_text',
			__( 'Texto del aviso', 'facilo-boton-arrepentimiento' ),
			$s,
			array(
				'rows' => 4,
				'help' => __( '{enlace} se cambia por el enlace al formulario. También podés ponerlo en facturas o contratos con [facilo_arrepentimiento_aviso].', 'facilo-boton-arrepentimiento' ),
			)
		);
		if ( ! Util::woo() ) {
			echo '<p class="description">' . esc_html__( 'Sin WooCommerce, el aviso se muestra solo donde pongas [facilo_arrepentimiento_aviso].', 'facilo-boton-arrepentimiento' ) . '</p>';
		}
		do_action( 'bclaro_settings_after_aviso', $s ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in the hooks contract.
		echo '</div>';
	}

	/**
	 * Privacidad.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_privacidad( $s ) {
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Privacidad', 'facilo-boton-arrepentimiento' ) . '</h2>';
		self::field(
			'retention_years',
			__( 'Años que guardamos los datos del cliente después de cerrar un pedido', 'facilo-boton-arrepentimiento' ),
			$s,
			array(
				'type' => 'number',
				'help' => __( 'Tres años es el plazo en que prescriben las sanciones de la Ley 24.240. Después se borran los datos personales, y quedan el código y las fechas.', 'facilo-boton-arrepentimiento' ),
			)
		);
		self::check( 'delete_on_uninstall', __( 'Borrar todos los datos al desinstalar', 'facilo-boton-arrepentimiento' ), $s );
		echo '<p class="description">' . esc_html__( 'Las páginas de los formularios quedan siempre.', 'facilo-boton-arrepentimiento' ) . '</p>';
		do_action( 'bclaro_settings_after_privacidad', $s ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in the hooks contract.
		echo '</div>';
	}

	/**
	 * Every field the settings, setup and preview forms may post, with how it is cleaned when read.
	 * Anything else in the request is ignored.
	 *
	 * @return array Field name => 'text', 'textarea', 'key', 'int', 'flag', 'email' or 'color'.
	 */
	public static function input_fields() {
		$fields = array(
			'sells'                 => 'key',
			'style'                 => 'key',
			'color'                 => 'color',
			'strip'                 => 'flag',
			'pill'                  => 'flag',
			'baja'                  => 'flag',
			'label_arrepentimiento' => 'text',
			'label_baja'            => 'text',
			'rights_text'           => 'textarea',
			'hours'                 => 'text',
			'phone'                 => 'text',
			'claims'                => 'text',
			'area'                  => 'text',
			'email'                 => 'email',
			'notify'                => 'email',
			'notice_on'             => 'flag',
			'notice_text'           => 'textarea',
			'retention_years'       => 'int',
			'delete_on_uninstall'   => 'flag',
		);
		/**
		 * Fields the settings forms may post. Pro adds its own here.
		 *
		 * @param array $fields Field name => cleaning type.
		 */
		return (array) apply_filters( 'bclaro_settings_input_fields', $fields );
	}

	/**
	 * The allowed fields of the current POST, each cleaned as it is read. Callers check the nonce and capability first.
	 *
	 * @return array
	 */
	public static function posted() {
		$out = array();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- every caller checks its nonce before calling this.
		foreach ( self::input_fields() as $key => $type ) {
			$key = (string) $key;
			if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) ) {
				continue;
			}
			switch ( $type ) {
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
					break;
				case 'key':
					$out[ $key ] = sanitize_key( wp_unslash( $_POST[ $key ] ) );
					break;
				case 'int':
					$out[ $key ] = absint( wp_unslash( $_POST[ $key ] ) );
					break;
				case 'flag':
					$out[ $key ] = empty( $_POST[ $key ] ) ? 0 : 1;
					break;
				case 'email':
					$out[ $key ] = sanitize_email( wp_unslash( $_POST[ $key ] ) );
					break;
				case 'color':
					$out[ $key ] = (string) sanitize_hex_color( wp_unslash( $_POST[ $key ] ) );
					break;
				default:
					$out[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $out;
	}

	/**
	 * Clean the posted fields of one tab.
	 *
	 * @param array  $raw Fields from posted(), already cleaned when read.
	 * @param string $tab Tab.
	 * @return array
	 */
	public static function sanitize( $raw, $tab ) {
		$text  = static function ( $k ) use ( $raw ) {
			return isset( $raw[ $k ] ) ? sanitize_text_field( (string) $raw[ $k ] ) : '';
		};
		$flag  = static function ( $k ) use ( $raw ) {
			return ! empty( $raw[ $k ] ) ? 1 : 0;
		};
		$clean = array();
		switch ( $tab ) {
			case 'botones':
			case 'setup3':
				$all   = Styles::all();
				$style = sanitize_key( $text( 'style' ) );
				if ( isset( $all[ $style ] ) ) {
					$clean['style'] = $style;
				}
				$color = Styles::clean_color( $text( 'color' ) );
				if ( $color && Styles::legible( $color ) ) {
					$clean['color'] = $color;
				}
				$clean['strip'] = $flag( 'strip' );
				$clean['pill']  = $flag( 'pill' );
				$clean['baja']  = $flag( 'baja' );
				if ( 'botones' === $tab ) {
					$r = Util::rules();
					foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
						$l                         = mb_substr( $text( 'label_' . $type ), 0, 80 );
						$clean[ 'label_' . $type ] = '' !== $l ? $l : $r['labels'][ $type ];
					}
				}
				break;
			case 'formularios':
				$clean['rights_text'] = isset( $raw['rights_text'] ) ? sanitize_textarea_field( (string) $raw['rights_text'] ) : '';
				break;
			case 'atencion':
			case 'setup2':
				foreach ( array( 'hours', 'phone', 'claims', 'area' ) as $k ) {
					$clean[ $k ] = mb_substr( $text( $k ), 0, 200 );
				}
				$clean['email']  = sanitize_email( $text( 'email' ) );
				$notify          = sanitize_email( $text( 'notify' ) );
				$clean['notify'] = is_email( $notify ) ? $notify : (string) get_option( 'admin_email' );
				break;
			case 'correos':
				$notify          = sanitize_email( $text( 'notify' ) );
				$clean['notify'] = is_email( $notify ) ? $notify : (string) get_option( 'admin_email' );
				break;
			case 'aviso':
				$clean['notice_on']   = $flag( 'notice_on' );
				$clean['notice_text'] = isset( $raw['notice_text'] ) ? sanitize_textarea_field( (string) $raw['notice_text'] ) : '';
				if ( '' === trim( $clean['notice_text'] ) ) {
					$clean['notice_text'] = Settings::default_notice();
				}
				break;
			case 'privacidad':
				$clean['retention_years']     = max( 1, min( 20, absint( isset( $raw['retention_years'] ) ? $raw['retention_years'] : 3 ) ) );
				$clean['delete_on_uninstall'] = $flag( 'delete_on_uninstall' );
				break;
		}
		/**
		 * Clean the settings of a tab. Pro adds its own keys from the posted fields.
		 *
		 * @param array  $clean Clean values.
		 * @param array  $raw   Allowed posted fields from posted(), each already cleaned.
		 * @param string $tab   Tab slug.
		 */
		return (array) apply_filters( 'bclaro_sanitize_settings', $clean, $raw, $tab );
	}

	/**
	 * Save one tab.
	 */
	public static function save() {
		if ( ! current_user_can( Util::cap() ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'facilo-boton-arrepentimiento' ), 403 );
		}
		check_admin_referer( 'bclaro_save' );
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		if ( ! isset( self::tabs()[ $tab ] ) ) {
			wp_die( esc_html__( 'No pudimos hacer ese cambio.', 'facilo-boton-arrepentimiento' ), 400 );
		}
		$clean = self::sanitize( self::posted(), $tab );
		Settings::update( $clean );
		if ( 'botones' === $tab && ! empty( $clean['baja'] ) && get_option( 'bclaro_setup_done' ) && in_array( 'baja', Pages::missing(), true ) ) {
			Pages::ensure( 'baja' );
		}
		wp_safe_redirect(
			Util::admin_url(
				'bclaro-ajustes',
				array(
					'tab' => $tab,
					'msg' => 'guardado',
				)
			)
		);
		exit;
	}
}
