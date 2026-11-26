<?php
/**
 * Tables, options and scheduled tasks.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Install and upgrade.
 */
class Install {

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 ); // After $wp_rewrite exists: Settings::ensure() builds page links.
		add_action( 'init', array( __CLASS__, 'maybe_migrate_content' ), 20 );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 15 minutes is the retry spacing the spec asks for.
	}

	/**
	 * The 15-minute schedule for mail retries.
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function schedules( $s ) {
		$s['bclaro_15min'] = array(
			'interval' => 900,
			'display'  => 'Facilo Botón de Arrepentimiento, cada 15 minutos',
		);
		return $s;
	}

	/**
	 * Table names.
	 *
	 * @return array
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'requests' => $wpdb->prefix . 'bclaro_requests',
			'events'   => $wpdb->prefix . 'bclaro_events',
		);
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		self::create_tables();
		Settings::ensure();
		self::schedule();
		if ( ! get_option( 'bclaro_setup_done' ) ) {
			set_transient( 'bclaro_setup_redirect', 1, 60 );
		}
	}

	/**
	 * Deactivation: clear scheduled tasks.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'bclaro_cron' );
		wp_clear_scheduled_hook( 'bclaro_daily' );
	}

	/**
	 * Schedule the two tasks.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'bclaro_cron' ) ) {
			wp_schedule_event( time() + 60, 'bclaro_15min', 'bclaro_cron' );
		}
		if ( ! wp_next_scheduled( 'bclaro_daily' ) ) {
			wp_schedule_event( time() + 300, 'daily', 'bclaro_daily' );
		}
	}

	/**
	 * Upgrade when the stored DB version is older.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'bclaro_db_version' ) !== BCLARO_DB_VERSION ) {
			self::create_tables();
			Settings::ensure();
		}
		if ( is_admin() ) {
			self::schedule();
		}
	}

	/**
	 * Once per plugin version: move the plugin's own pages to the 1.0.1 shortcode and block names.
	 */
	public static function maybe_migrate_content() {
		if ( get_option( 'bclaro_plugin_version' ) === BCLARO_VERSION ) {
			return;
		}
		$s = Settings::get();
		foreach ( array( 'page_arrepentimiento', 'page_baja' ) as $key ) {
			$id   = isset( $s[ $key ] ) ? (int) $s[ $key ] : 0;
			$post = $id ? get_post( $id ) : null;
			if ( ! $post || 'page' !== $post->post_type ) {
				continue;
			}
			$new = self::migrate_text( (string) $post->post_content );
			if ( $new !== $post->post_content ) {
				wp_update_post(
					array(
						'ID'           => $id,
						'post_content' => wp_slash( $new ),
					)
				);
			}
		}
		update_option( 'bclaro_plugin_version', BCLARO_VERSION, false );
	}

	/**
	 * Rewrite the 1.0.0 shortcode tags and block names to the 1.0.1 ones.
	 *
	 * @param string $text Post content.
	 * @return string
	 */
	public static function migrate_text( $text ) {
		$text = preg_replace( '#\[(/?)boton_claro_(aviso|atencion|formulario)(?=[\s\]/])#', '[$1facilo_arrepentimiento_$2', $text );
		$text = preg_replace( '#\[(/?)boton_(arrepentimiento|baja)(?=[\s\]/])#', '[$1facilo_boton_$2', $text );
		return preg_replace( '#<!-- (/?)wp:boton-claro/(formulario|botones|atencion)(?=[\s/])#', '<!-- $1wp:facilo-boton-arrepentimiento/$2', $text );
	}

	/**
	 * Create or update both tables.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::tables();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$t['requests']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(20) NOT NULL,
  type varchar(20) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'nuevo',
  is_test tinyint(1) NOT NULL DEFAULT 0,
  name varchar(120) NOT NULL DEFAULT '',
  email varchar(190) NOT NULL DEFAULT '',
  phone varchar(40) NOT NULL DEFAULT '',
  order_ref varchar(60) NOT NULL DEFAULT '',
  order_id bigint(20) unsigned NULL,
  scope varchar(10) NOT NULL DEFAULT '',
  service varchar(200) NOT NULL DEFAULT '',
  detail varchar(500) NOT NULL DEFAULT '',
  message text NULL,
  created_at datetime NOT NULL,
  reply_due_at datetime NULL,
  proof_due_at datetime NULL,
  replied_at datetime NULL,
  proof_sent_at datetime NULL,
  closed_at datetime NULL,
  erased_at datetime NULL,
  code_mail_status varchar(10) NOT NULL DEFAULT 'pendiente',
  code_mail_tries tinyint(3) unsigned NOT NULL DEFAULT 0,
  repeat_of bigint(20) unsigned NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code),
  KEY email (email),
  KEY order_id (order_id),
  KEY status (status),
  KEY created_at (created_at),
  KEY repeat_of (repeat_of)
) $charset;"
		);
		dbDelta(
			"CREATE TABLE {$t['events']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_id bigint(20) unsigned NOT NULL,
  at datetime NOT NULL,
  actor bigint(20) unsigned NOT NULL DEFAULT 0,
  kind varchar(30) NOT NULL,
  note text NULL,
  PRIMARY KEY  (id),
  KEY request_id (request_id)
) $charset;"
		);
		update_option( 'bclaro_db_version', BCLARO_DB_VERSION, false );
	}
}
