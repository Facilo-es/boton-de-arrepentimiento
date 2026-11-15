<?php
/**
 * Tables, options and scheduled tasks.
 *
 * @package BotonClaro
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
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
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
			'display'  => 'Botón Claro, cada 15 minutos',
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
  PRIMARY KEY  (id),
  UNIQUE KEY code (code),
  KEY email (email),
  KEY order_id (order_id),
  KEY status (status),
  KEY created_at (created_at)
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
