<?php
/**
 * The two form pages: create, bring back, and keep the button links in step.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Pages.
 */
class Pages {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'post_gone' ) );
		add_action( 'post_updated', array( __CLASS__, 'post_updated' ), 10, 3 );
		add_action( 'update_option_permalink_structure', array( __CLASS__, 'recompile' ) );
		add_action( 'update_option_page_on_front', array( __CLASS__, 'recompile' ) );
		add_action( 'admin_post_bclaro_recrear', array( __CLASS__, 'handle_recreate' ) );
	}

	/**
	 * Title and slug of each page.
	 *
	 * @param string $type Type.
	 * @return array
	 */
	public static function meta( $type ) {
		if ( 'baja' === $type ) {
			return array(
				'title' => __( 'Botón de baja de servicio', 'boton-de-arrepentimiento' ),
				'slug'  => 'boton-de-baja-de-servicio',
			);
		}
		return array(
			'title' => __( 'Botón de arrepentimiento', 'boton-de-arrepentimiento' ),
			'slug'  => 'boton-de-arrepentimiento',
		);
	}

	/**
	 * Whether a page ID is one of ours.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	private static function ours( $id ) {
		$s = Settings::get();
		return (int) $id && ( (int) $s['page_arrepentimiento'] === (int) $id || (int) $s['page_baja'] === (int) $id );
	}

	/**
	 * Recompute the links.
	 */
	public static function recompile() {
		Settings::flush();
		Settings::update( array() );
	}

	/**
	 * A page of ours changed status.
	 *
	 * @param string   $now  New status.
	 * @param string   $old  Old status.
	 * @param \WP_Post $post Post.
	 */
	public static function status_changed( $now, $old, $post ) {
		if ( $now !== $old && 'page' === $post->post_type && self::ours( $post->ID ) ) {
			self::recompile();
		}
	}

	/**
	 * A page of ours was deleted for good.
	 *
	 * @param int $id Post ID.
	 */
	public static function post_gone( $id ) {
		if ( self::ours( $id ) ) {
			self::recompile();
		}
	}

	/**
	 * A page of ours changed slug or parent.
	 *
	 * @param int      $id     Post ID.
	 * @param \WP_Post $after  After.
	 * @param \WP_Post $before Before.
	 */
	public static function post_updated( $id, $after, $before ) {
		if ( self::ours( $id ) && ( $after->post_name !== $before->post_name || $after->post_parent !== $before->post_parent ) ) {
			self::recompile();
		}
	}

	/**
	 * Whether a type needs its page.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function needed( $type ) {
		$s = Settings::get();
		return 'arrepentimiento' === $type || ! empty( $s['baja'] );
	}

	/**
	 * Types whose page is missing or not public.
	 *
	 * @return string[]
	 */
	public static function missing() {
		$s   = Settings::get();
		$out = array();
		foreach ( array( 'arrepentimiento', 'baja' ) as $type ) {
			if ( self::needed( $type ) && '' === $s[ 'url_' . $type ] ) {
				$out[] = $type;
			}
		}
		return $out;
	}

	/**
	 * Create, bring back or republish a page. Returns its ID.
	 *
	 * @param string $type Type.
	 * @return int
	 */
	public static function ensure( $type ) {
		$s  = Settings::get();
		$id = (int) $s[ 'page_' . $type ];
		$m  = self::meta( $type );
		if ( ! $id || ! get_post( $id ) ) {
			// After a reinstall, reuse the page we made before instead of adding a "-2" copy.
			$old = get_page_by_path( $m['slug'], OBJECT, 'page' );
			if ( $old && false !== strpos( (string) $old->post_content, '"tipo":"' . $type . '"' ) ) {
				$id = (int) $old->ID;
			}
		}
		if ( $id && get_post( $id ) && 'page' === get_post_type( $id ) ) {
			if ( 'trash' === get_post_status( $id ) ) {
				wp_untrash_post( $id );
			}
			if ( 'publish' !== get_post_status( $id ) || '' !== get_post_field( 'post_password', $id ) ) {
				wp_update_post(
					array(
						'ID'            => $id,
						'post_status'   => 'publish',
						'post_password' => '',
					)
				);
			}
		} else {
			$id = (int) wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $m['title'],
					'post_name'      => $m['slug'],
					'post_content'   => '<!-- wp:boton-claro/formulario {"tipo":"' . $type . '"} /-->',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);
		}
		Settings::update( array( 'page_' . $type => $id ) );
		return $id;
	}

	/**
	 * "Volver a crear la página".
	 */
	public static function handle_recreate() {
		if ( ! current_user_can( Util::cap() ) ) {
			wp_die( esc_html__( 'No tenés permiso para hacer esto.', 'boton-de-arrepentimiento' ), 403 );
		}
		check_admin_referer( 'bclaro_recrear' );
		foreach ( self::missing() as $type ) {
			self::ensure( $type );
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : Util::admin_url( 'bclaro' ) );
		exit;
	}

	/**
	 * The notice when a needed page is missing. Shown on every admin screen, as the buttons are hidden meanwhile.
	 */
	public static function notice() {
		if ( ! get_option( 'bclaro_setup_done' ) || ! current_user_can( Util::cap() ) || ! self::missing() ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=bclaro_recrear' ), 'bclaro_recrear' );
		echo '<div class="notice notice-warning bclaro-notice"><p><strong>Botón Claro:</strong> ' . esc_html__( 'Falta la página del formulario. Hasta que vuelva, el botón no se muestra.', 'boton-de-arrepentimiento' ) . '</p><p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Volver a crear la página', 'boton-de-arrepentimiento' ) . '</a></p></div>';
	}
}
