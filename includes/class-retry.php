<?php
/**
 * Mail retries through WP-Cron: every 15 minutes, up to 8 retries, then E4 and a notice.
 *
 * @package FaciloBotonArrepentimiento
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Retry.
 */
class Retry {

	/**
	 * First try plus 8 retries.
	 */
	const MAX_TRIES = 9;

	/**
	 * Hooks.
	 */
	public static function boot() {
		add_action( 'bclaro_cron', array( __CLASS__, 'run' ) );
	}

	/**
	 * One cron run.
	 */
	public static function run() {
		global $wpdb;
		update_option( 'bclaro_cron_ran_at', Util::now(), false );
		$t   = Install::tables();
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT id FROM %i WHERE ( code_mail_status = %s OR ( code_mail_status = %s AND created_at < %s ) ) AND code_mail_tries < %d AND erased_at IS NULL ORDER BY id ASC LIMIT 50',
				$t['requests'],
				'fallido',
				'pendiente',
				Util::mysql( Util::now() - 5 * MINUTE_IN_SECONDS ),
				self::MAX_TRIES
			)
		);
		foreach ( $ids as $id ) {
			$req = Requests::get( (int) $id );
			if ( ! $req ) {
				continue;
			}
			$ok = Mailer::send_code( $req );
			if ( ! $ok && (int) $req->code_mail_tries + 1 >= self::MAX_TRIES ) {
				self::give_up( Requests::get( (int) $id ) );
			}
		}
	}

	/**
	 * The last try failed: tell the owner by E4 and by a notice on the plugin's screens.
	 *
	 * @param object $req Request.
	 */
	public static function give_up( $req ) {
		Requests::event( $req->id, 'codigo_rendido' );
		Mailer::send_fail( $req );
		$s     = Settings::get();
		$alert = array_map( 'intval', (array) $s['mail_alert'] );
		if ( ! in_array( (int) $req->id, $alert, true ) ) {
			$alert[] = (int) $req->id;
			Settings::update( array( 'mail_alert' => array_values( $alert ) ) );
		}
	}

	/**
	 * The code went out after all: drop the notice for this request.
	 *
	 * @param int $id Request ID.
	 */
	public static function clear_alert( $id ) {
		$s     = Settings::get();
		$alert = array_map( 'intval', (array) $s['mail_alert'] );
		if ( in_array( (int) $id, $alert, true ) ) {
			Settings::update( array( 'mail_alert' => array_values( array_diff( $alert, array( (int) $id ) ) ) ) );
		}
	}

	/**
	 * Whether a request gave up on its code email.
	 *
	 * @param object $req Request.
	 * @return bool
	 */
	public static function gave_up( $req ) {
		return 'fallido' === $req->code_mail_status && (int) $req->code_mail_tries >= self::MAX_TRIES;
	}
}
