<?php
/**
 * Handles database operations for the plugin.
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNPFWC_Database {

    /**
     * Create or update the custom database tables.
     */
    public static function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) NOT NULL DEFAULT 0,
            customer_email varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'subscribed',
            subscription_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            notified_date datetime NULL,
            
            -- NEW: Columns for Analytics and Coupons --
            conversion_status varchar(20) NOT NULL DEFAULT 'pending',
            order_id bigint(20) NULL,
            generated_coupon varchar(255) NULL,
            
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Store the current version after creating/updating the table.
        update_option('snpfwc_version', SNPFWC_VERSION);
    }

    /**
     * Checks if the database is up to date, and creates/updates it if not.
     */
    public static function update_check() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query is necessary to check table existence for upgrade routine.
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
        
        // If the table doesn't exist or version is outdated, run the installer.
        if ( ! $table_exists || get_option('snpfwc_version') != SNPFWC_VERSION ) {
            self::create_table();
        }
    }
}