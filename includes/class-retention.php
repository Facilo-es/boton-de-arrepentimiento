<?php
/**
 * Daily retention (F13): blanks old closed requests, deletes old spam and test requests.
 *
 * @package BotonClaro
 */

namespace BotonClaro;

defined( 'ABSPATH' ) || exit;

/**
 * Retention.
 */
class Retention {

	/**
	 * Rows per batch.
	 */
	const BATCH = 200;

	/**
	 * Hooks. Priority 5 so it runs before Pro's reminders.
	 */
	public static function boot() {
		add_action( 'bclaro_daily', array( __CLASS__, 'run' ), 5 );
	}

	/**
	 * Run one batch of each.
	 *
	 * @return array Counts: blanked, deleted.
	 */
	public static function run() {
		global $wpdb;
		$t     = Install::tables();
		$years = max( 1, (int) Settings::get( 'retention_years' ) );
		$old   = gmdate( 'Y-m-d H:i:s', Util::now() - $years * YEAR_IN_SECONDS );
		$month = gmdate( 'Y-m-d H:i:s', Util::now() - 30 * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own tables, daily batch.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE closed_at IS NOT NULL AND closed_at < %s AND erased_at IS NULL AND is_test = 0 AND status <> 'spam' ORDER BY id LIMIT %d", $t['requests'], $old, self::BATCH ) );
		foreach ( $ids as $id ) {
			Privacy::blank( (int) $id );
		}

		$gone = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE ( is_test = 1 OR status = 'spam' ) AND created_at < %s ORDER BY id LIMIT %d", $t['requests'], $month, self::BATCH ) );
		foreach ( $gone as $id ) {
			$req = Requests::get( (int) $id );
			if ( $req ) {
				Requests::delete( $req );
			}
		}
		// phpcs:enable

		return array(
			'blanked' => count( $ids ),
			'deleted' => count( $gone ),
		);
	}
}
