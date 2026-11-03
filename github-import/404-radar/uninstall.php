<?php
/**
 * Uninstall handler for 404 Radar.
 *
 * Runs only when the plugin is deleted from wp-admin (not on deactivation).
 * Removes the log table, options and scheduled events created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'r404_logs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'r404_opts' );
delete_option( 'r404_daily' );

wp_clear_scheduled_hook( 'r404_clean' );
wp_clear_scheduled_hook( 'r404_mail' );
