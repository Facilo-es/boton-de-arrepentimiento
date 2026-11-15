<?php
/**
 * Uninstall. Data goes only when the owner ticked "Borrar todos los datos al desinstalar".
 * The pages stay either way, since the owner may have edited them.
 *
 * @package BotonClaro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Clean one site.
 */
function bclaro_uninstall_site() {
	global $wpdb;
	wp_clear_scheduled_hook( 'bclaro_cron' );
	wp_clear_scheduled_hook( 'bclaro_daily' );
	delete_transient( 'bclaro_setup_redirect' );

	$s = get_option( 'bclaro_settings', array() );
	if ( ! is_array( $s ) || empty( $s['delete_on_uninstall'] ) ) {
		return;
	}
	foreach ( array( 'bclaro_requests', 'bclaro_events' ) as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- our own tables, on uninstall.
	}
	foreach ( array( 'bclaro_settings', 'bclaro_setup_done', 'bclaro_db_version', 'bclaro_cron_ran_at', 'bclaro_check' ) as $option ) {
		delete_option( $option );
	}
	// Rate-limit counters.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_bclaro_' ) . '%', $wpdb->esc_like( '_transient_timeout_bclaro_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $bclaro_site ) {
		switch_to_blog( $bclaro_site );
		bclaro_uninstall_site();
		restore_current_blog();
	}
} else {
	bclaro_uninstall_site();
}
