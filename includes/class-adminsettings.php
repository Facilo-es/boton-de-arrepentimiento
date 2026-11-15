<?php
/**
 * Ajustes: six tabs in one option, plus the style gallery and live preview shared with the setup.
 *
 * @package BotonClaro
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
			'botones'     => __( 'Botones', 'boton-de-arrepentimiento' ),
			'formularios' => __( 'Formularios', 'boton-de-arrepentimiento' ),
			'atencion'    => __( 'Atención al cliente', 'boton-de-arrepentimiento' ),
			'correos'     => __( 'Correos', 'boton-de-arrepentimiento' ),
			'aviso'       => __( 'Aviso de derechos', 'boton-de-arrepentimiento' ),
			'privacidad'  => __( 'Privacidad', 'boton-de-arrepentimiento' ),
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
	 * @param string $attr  Extra attributes, already escaped.
	 */
	private static function check( $key, $label, $s, $attr = '' ) {
		echo '<label class="bclaro-check"><input type="hidden" name="' . esc_attr( $key ) . '" value="0"><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( ! empty( $s[ $key ] ), true, false ) . $attr . '> ' . esc_html( $label ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attr is static.
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
		Admin::title( __( 'Ajustes', 'boton-de-arrepentimiento' ), '<a class="page-title-action" href="' . esc_url( Util::admin_url( 'bclaro-setup' ) ) . '">' . esc_html__( 'Abrir la configuración guiada', 'boton-de-arrepentimiento' ) . '</a>' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message flag only.
		if ( isset( $_GET['msg'] ) && 'guardado' === sanitize_key( wp_unslash( $_GET['msg'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Guardamos los cambios.', 'boton-de-arrepentimiento' ) . '</p></div>';
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
		echo '<p class="save"><button type="submit" class="bclaro-btn pri">' . esc_html__( 'Guardar cambios', 'boton-de-arrepentimiento' ) . '</button></p>';
		echo '</form></div>';
	}

	/**
	 * Gallery of styles with the recommended one, and the colour swatches. Shared with the setup.
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
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Estilo', 'boton-de-arrepentimiento' ) . '</h2>';
		if ( $full ) {
			echo '<div class="rec" data-style="' . esc_attr( $rec['style'] ) . '" data-color="' . esc_attr( $rec['color'] ) . '"><p>' . esc_html( $rec['why'] ) . '</p><button type="button" class="button-link" id="bclaro-volver">' . esc_html__( 'Volver al recomendado', 'boton-de-arrepentimiento' ) . '</button></div>';
		}
		echo '<div class="gal" role="radiogroup" aria-label="' . esc_attr__( 'Estilo', 'boton-de-arrepentimiento' ) . '">';
		$label = Settings::label( 'arrepentimiento' );
		foreach ( $all as $slug => $st ) {
			$slug = sanitize_key( $slug );
			$on   = $slug === $s['style'];
			echo '<button type="button" class="st8' . ( $on ? ' on' : '' ) . '" data-e="' . esc_attr( $slug ) . '" role="radio" aria-checked="' . ( $on ? 'true' : 'false' ) . '">';
			echo '<style id="bclaro-tile-css-' . esc_attr( $slug ) . '">' . wp_strip_all_tags( Styles::scope( Styles::css( $slug, $col, $s ), '#bclaro-tile-' . $slug ) ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS built by the plugin from its own files and a cleaned hex colour.
			echo '<span class="mu" id="bclaro-tile-' . esc_attr( $slug ) . '"><span class="bclaro-franja"><span class="bclaro-b">' . esc_html( $label ) . '</span></span></span>';
			echo '<b>' . esc_html( isset( $st['label'] ) ? $st['label'] : $slug ) . '</b>';
			if ( $slug === $rec['style'] ) {
				echo '<span class="badge">' . esc_html__( 'Recomendado para tu tienda', 'boton-de-arrepentimiento' ) . '</span>';
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
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Detalles', 'boton-de-arrepentimiento' ) . '</h2>';
		echo '<div class="fila"><span>' . esc_html__( 'Color', 'boton-de-arrepentimiento' ) . '</span><div class="sws">';
		foreach ( $pal as $c ) {
			echo '<button type="button" class="sw' . ( $c === $col ? ' on' : '' ) . '" data-c="' . esc_attr( $c ) . '" style="background:' . esc_attr( $c ) . '" title="' . esc_attr( $c ) . '"><span class="screen-reader-text">' . esc_html( $c ) . '</span></button>';
		}
		echo '<label class="otro"><input type="color" id="bclaro-otro" value="' . esc_attr( $col ) . '"> ' . esc_html__( 'Otro color', 'boton-de-arrepentimiento' ) . '</label></div></div>';
		echo '<p class="lee' . ( $msg['bad'] ? ' mal' : '' ) . '" id="bclaro-lee" aria-live="polite">' . esc_html( $msg['text'] ) . '</p>';
	}

	/**
	 * The live preview box: the shop's home page in an iframe with the settings applied.
	 */
	public static function preview_box() {
		$url = add_query_arg( 'bclaro_vista', '1', home_url( '/' ) );
		echo '<div class="bclaro-box pv"><h2>' . esc_html__( 'Así se ve en tu tienda', 'boton-de-arrepentimiento' ) . ' <span class="seg" data-k="ver"><button type="button" class="on" data-v="pc">' . esc_html__( 'Computadora', 'boton-de-arrepentimiento' ) . '</button><button type="button" data-v="cel">' . esc_html__( 'Celular', 'boton-de-arrepentimiento' ) . '</button></span></h2>';
		echo '<div class="vp" id="bclaro-vp"><iframe id="bclaro-frame" src="' . esc_url( $url ) . '" title="' . esc_attr__( 'Vista previa', 'boton-de-arrepentimiento' ) . '" tabindex="-1" loading="lazy"></iframe></div></div>';
	}

	/**
	 * Botones.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_botones( $s ) {
		echo '<div class="cols"><div>';
		self::style_controls( $s, true );
		echo '<div class="fila"><span>' . esc_html__( 'Dónde', 'boton-de-arrepentimiento' ) . '</span><div class="chk">';
		self::check( 'strip', __( 'Franja al pie', 'boton-de-arrepentimiento' ), $s, ' data-bclaro-place="franja"' );
		self::check( 'pill', __( 'Botón flotante', 'boton-de-arrepentimiento' ), $s, ' data-bclaro-place="pill"' );
		echo '</div></div>';
		if ( empty( $s['strip'] ) && empty( $s['pill'] ) ) {
			echo '<p class="bclaro-warn">' . esc_html__( 'Apagaste las dos posiciones. Si no pusiste los botones con un bloque, tus clientes no los van a ver.', 'boton-de-arrepentimiento' ) . '</p>';
		}
		/**
		 * Extra controls at the end of the Botones box (Pro's Detalles).
		 *
		 * @param array $s Settings.
		 */
		do_action( 'bclaro_settings_after_botones', $s );
		echo '</div>';

		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Nombres', 'boton-de-arrepentimiento' ) . '</h2>';
		self::check( 'baja', __( 'Mostrar también el botón de baja de servicio', 'boton-de-arrepentimiento' ), $s, ' data-bclaro-place="baja"' );
		$r = Util::rules();
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			self::field( 'label_' . $type, Util::type_label( $type ), $s, array( 'placeholder' => $r['labels'][ $type ] ) );
			if ( Settings::label_changed( $type ) ) {
				/* translators: %s: exact legal name. */
				echo '<p class="bclaro-warn">' . esc_html( sprintf( __( 'Cambiaste el nombre. La norma lo llama “%s”: te recomendamos dejarlo así.', 'boton-de-arrepentimiento' ), $r['labels'][ $type ] ) ) . '</p>';
			}
		}
		echo '<p class="description">' . esc_html__( 'También podés ponerlos en cualquier página con el bloque “Botones de Botón Claro” o con [boton_arrepentimiento] y [boton_baja].', 'boton-de-arrepentimiento' ) . '</p>';
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
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Páginas de los formularios', 'boton-de-arrepentimiento' ) . '</h2><ul class="bclaro-pages">';
		$missing = Pages::missing();
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			if ( ! Pages::needed( $type ) ) {
				continue;
			}
			$id = (int) $s[ 'page_' . $type ];
			echo '<li><b>' . esc_html( Util::type_label( $type ) ) . ':</b> ';
			if ( in_array( $type, $missing, true ) ) {
				echo '<span class="bclaro-warn-inline">' . esc_html__( 'Falta la página del formulario. Hasta que vuelva, el botón no se muestra.', 'boton-de-arrepentimiento' ) . '</span>';
			} else {
				echo '<a href="' . esc_url( $s[ 'url_' . $type ] ) . '" target="_blank" rel="noopener">' . esc_html( $s[ 'url_' . $type ] ) . '</a> · <a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html__( 'Editar', 'boton-de-arrepentimiento' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';
		if ( $missing ) {
			echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bclaro_recrear' ), 'bclaro_recrear' ) ) . '">' . esc_html__( 'Volver a crear la página', 'boton-de-arrepentimiento' ) . '</a></p>';
		}
		echo '<p class="description">' . esc_html__( 'Si querés el formulario en otra página, usá el bloque “Formulario de Botón Claro” o [boton_claro_formulario tipo="arrepentimiento"].', 'boton-de-arrepentimiento' ) . '</p></div>';
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Texto bajo el formulario de arrepentimiento', 'boton-de-arrepentimiento' ) . '</h2>';
		self::field(
			'rights_text',
			__( 'Tus derechos, en palabras simples', 'boton-de-arrepentimiento' ),
			$s,
			array(
				'rows' => 7,
				'help' => __( 'Usá **así** para negrita. Dejá una línea en blanco entre párrafos.', 'boton-de-arrepentimiento' ),
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
		echo '<div class="bclaro-box"><h2>' . esc_html__( '¿Cómo te contactan tus clientes?', 'boton-de-arrepentimiento' ) . '</h2><p>' . esc_html__( 'Estos datos se muestran junto a los botones.', 'boton-de-arrepentimiento' ) . '</p>';
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
			__( 'Horario de atención', 'boton-de-arrepentimiento' ),
			$s,
			array(
				'placeholder' => __( 'Lunes a viernes de 9 a 17', 'boton-de-arrepentimiento' ),
				'help'        => __( 'Si atendés solo por teléfono o por internet, la Disposición 954/2025 pide al menos 8 horas en días hábiles, de lunes a viernes.', 'boton-de-arrepentimiento' ),
			)
		);
		self::field( 'phone', __( 'Teléfono', 'boton-de-arrepentimiento' ), $s, array( 'type' => 'tel' ) );
		self::field( 'email', __( 'Correo de atención', 'boton-de-arrepentimiento' ), $s, array( 'type' => 'email' ) );
		self::field( 'claims', __( 'Otro canal para reclamos', 'boton-de-arrepentimiento' ), $s, array( 'placeholder' => __( 'WhatsApp, o un enlace', 'boton-de-arrepentimiento' ) ) );
		self::field( 'area', __( 'Área responsable', 'boton-de-arrepentimiento' ), $s, array( 'placeholder' => __( 'Atención al cliente', 'boton-de-arrepentimiento' ) ) );
		$n = $s;
		if ( '' === (string) $n['notify'] ) {
			$n['notify'] = (string) get_option( 'admin_email' );
		}
		self::field( 'notify', __( 'Correo donde recibís los avisos', 'boton-de-arrepentimiento' ), $n, array( 'type' => 'email' ) );
	}

	/**
	 * Correos.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_correos( $s ) {
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Correos', 'boton-de-arrepentimiento' ) . '</h2>';
		self::field(
			'notify',
			__( 'Correo donde recibís los avisos', 'boton-de-arrepentimiento' ),
			$s,
			array(
				'type' => 'email',
				'help' => __( 'Te avisamos acá cuando llega un pedido, y si no pudimos mandarle el código a un cliente.', 'boton-de-arrepentimiento' ),
			)
		);
		echo '<p class="description">' . esc_html__( 'Los clientes reciben su código al instante, con tu correo de atención para responder. Si un envío falla, lo reintentamos solos durante el día.', 'boton-de-arrepentimiento' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( Util::admin_url( 'bclaro-revision' ) ) . '">' . esc_html__( 'Enviar correo de prueba', 'boton-de-arrepentimiento' ) . '</a></p>';
		do_action( 'bclaro_settings_after_correos', $s ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in the hooks contract.
		echo '</div>';
	}

	/**
	 * Aviso de derechos.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_aviso( $s ) {
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Aviso de derechos', 'boton-de-arrepentimiento' ) . '</h2>';
		self::check( 'notice_on', __( 'Mostrar el aviso en los correos de pedido y en el pago', 'boton-de-arrepentimiento' ), $s );
		self::field(
			'notice_text',
			__( 'Texto del aviso', 'boton-de-arrepentimiento' ),
			$s,
			array(
				'rows' => 4,
				'help' => __( '{enlace} se cambia por el enlace al formulario. También podés ponerlo en facturas o contratos con [boton_claro_aviso].', 'boton-de-arrepentimiento' ),
			)
		);
		if ( ! Util::woo() ) {
			echo '<p class="description">' . esc_html__( 'Sin WooCommerce, el aviso se muestra solo donde pongas [boton_claro_aviso].', 'boton-de-arrepentimiento' ) . '</p>';
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
		echo '<div class="bclaro-box"><h2>' . esc_html__( 'Privacidad', 'boton-de-arrepentimiento' ) . '</h2>';
		self::field(
			'retention_years',
			__( 'Años que guardamos los datos del cliente después de cerrar un pedido', 'boton-de-arrepentimiento' ),
			$s,
			array(
				'type' => 'number',
				'help' => __( 'Tres años es el plazo en que prescriben las sanciones de la Ley 24.240. Después se borran los datos personales, y quedan el código y las fechas.', 'boton-de-arrepentimiento' ),
			)
		);
		self::check( 'delete_on_uninstall', __( 'Borrar todos los datos al desinstalar', 'boton-de-arrepentimiento' ), $s );
		echo '<p class="description">' . esc_html__( 'Las páginas de los formularios quedan siempre.', 'boton-de-arrepentimiento' ) . '</p>';
		do_action( 'bclaro_settings_after_privacidad', $s ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in the hooks contract.
		echo '</div>';
	}

	/**
	 * Clean the posted fields of one tab.
	 *
	 * @param array  $raw Raw (unslashed) post.
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
		 * Clean the settings of a tab. Pro adds its own keys from the raw post.
		 *
		 * @param array  $clean Clean values.
		 * @param array  $raw   Raw post, unslashed.
		 * @param string $tab   Tab slug.
		 */
		return (array) apply_filters( 'bclaro_sanitize_settings', $clean, $raw, $tab );
	}

	/**
	 * Save one tab.
	 */
	public static function save() {
		if ( ! current_user_can( Util::cap() ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'boton-de-arrepentimiento' ), 403 );
		}
		check_admin_referer( 'bclaro_save' );
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		if ( ! isset( self::tabs()[ $tab ] ) ) {
			wp_die( esc_html__( 'No pudimos hacer ese cambio.', 'boton-de-arrepentimiento' ), 400 );
		}
		$raw   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every key is sanitized in sanitize().
		$clean = self::sanitize( (array) $raw, $tab );
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
