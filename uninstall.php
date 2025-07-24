<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Stock_Notifier_Pro
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Delete the custom database table.
$table_name = $wpdb->prefix . 'snpfwc_subscribers';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Direct query is necessary for uninstalling custom tables. Table name is safely derived from $wpdb->prefix.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// 2. Delete the plugin's settings from the options table.
delete_option( 'snpfwc_settings' );

// 3. Delete the version number from the options table.
delete_option( 'snpfwc_version' );

// 4. Clear any scheduled cron events.
wp_clear_scheduled_hook( 'snpfwc_send_notifications_cron' );
wp_clear_scheduled_hook( 'snpfwc_run_single_notification_event' );