<?php
/**
 * Uninstall handler for SEO Intelligence & Search Console Analyzer.
 *
 * Runs only when the user deletes the plugin from wp-admin (not on
 * simple deactivation). Removes options, transients, custom tables,
 * and stored OAuth tokens. No network requests are made here.
 *
 * @package SEO_Intelligence
 */

// Guard against direct access / execution outside the uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ---------------------------------------------------------------------
// Remove options (site + network, in case of multisite uninstall).
// ---------------------------------------------------------------------
$option_keys = [
	'seoi_settings',
	'seoi_oauth_tokens',
	'seoi_selected_property',
	'seoi_last_sync',
	'seoi_db_version',
	'seoi_ai_settings',
];

function seoi_uninstall_cleanup_site(): void {
	global $wpdb;

	$option_keys = [
		'seoi_settings',
		'seoi_oauth_tokens',
		'seoi_selected_property',
		'seoi_last_sync',
		'seoi_db_version',
		'seoi_ai_settings',
		'seoi_indexnow_key',
	];

	foreach ( $option_keys as $key ) {
		delete_option( $key );
		delete_site_option( $key );
	}

	// Remove any transients created by the plugin (prefix: seoi_).
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_seoi\_%' OR option_name LIKE '\_transient\_timeout\_seoi\_%'"
	);

	// Drop custom tables.
	$tables = [
		$wpdb->prefix . 'seoi_query_data',
		$wpdb->prefix . 'seoi_page_data',
		$wpdb->prefix . 'seoi_opportunities',
		$wpdb->prefix . 'seoi_cannibalization',
		$wpdb->prefix . 'seoi_index_queue',
		$wpdb->prefix . 'seoi_indexnow_log',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are hardcoded, not user input.
	}

	// Clear scheduled cron events.
	foreach ( [ 'seoi_sync_search_console_data', 'seoi_process_index_queue', 'seoi_indexnow_submit_single' ] as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
		wp_clear_scheduled_hook( $hook );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites( [ 'fields' => 'ids' ] );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		seoi_uninstall_cleanup_site();
		restore_current_blog();
	}
} else {
	seoi_uninstall_cleanup_site();
}
