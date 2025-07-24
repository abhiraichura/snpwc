<?php
/**
 * Plugin Name:       Stock Notifier Pro For WooCommerce
 * Plugin URI:        https://unravelersglobal.com/stock-notifier-pro/
 * Description:       Notifies customers when an out-of-stock product is available again.
 * Version:           1.0.0
 * Author:            Unravelers Global
 * Author URI:        https://unravelersglobal.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stock-notifier-pro-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WooCommerce requires at least: 5.0
 * WooCommerce tested up to: 8.1
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Define plugin constants
define( 'SNPFWC_VERSION', '1.0.0' ); // Incremented for new feature release
define( 'SNPFWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNPFWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that initializes everything.
 */
final class Stock_Notifier_Pro {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once SNPFWC_PLUGIN_DIR . 'includes/class-snpfwc-database.php';
        require_once SNPFWC_PLUGIN_DIR . 'includes/class-snpfwc-frontend.php';
        require_once SNPFWC_PLUGIN_DIR . 'includes/class-snpfwc-backend.php';
        require_once SNPFWC_PLUGIN_DIR . 'includes/class-snpfwc-email-manager.php';
    }

    private function init_hooks() {
        add_action('init', array('SNPFWC_Database', 'update_check'));
        register_activation_hook( __FILE__, array( 'SNPFWC_Database', 'create_table' ) );
        register_activation_hook( __FILE__, array( __CLASS__, 'activate_cron' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate_cron' ) );

        SNPFWC_Frontend::init();
        SNPFWC_Backend::init();
        SNPFWC_Email_Manager::init();
    }

    /**
     * Schedules the cron jobs on plugin activation.
     */
    public static function activate_cron() {
        if ( ! wp_next_scheduled( 'snpfwc_send_notifications_cron' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'snpfwc_send_notifications_cron' );
        }
        if ( ! wp_next_scheduled( 'snpfwc_send_reminder_emails' ) ) {
            wp_schedule_event( time(), 'daily', 'snpfwc_send_reminder_emails' );
        }
    }

    /**
     * Clears the cron jobs on plugin deactivation.
     */
    public static function deactivate_cron() {
        wp_clear_scheduled_hook( 'snpfwc_send_notifications_cron' );
        wp_clear_scheduled_hook( 'snpfwc_send_reminder_emails' );
    }
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'snpfwc_add_action_links' );

function snpfwc_add_action_links( $links ) {
    $pro_link = '<a href="https://unravelersglobal.com/stock-notifier-pro/" target="_blank" style="font-weight: bold; color: #0073aa;">Go Pro</a>';
    array_unshift( $links, $pro_link );
    return $links;
}

/**
 * Add a custom 5-minute interval to the cron schedules.
 */
add_filter( 'cron_schedules', function ( $schedules ) {
    if ( ! isset( $schedules['five_minutes'] ) ) {
        $schedules['five_minutes'] = array(
            'interval' => 300, 'display'  => __( 'Every Five Minutes', 'stock-notifier-pro-for-woocommerce' ),
        );
    }
    return $schedules;
} );

function stock_notifier_pro() {
    return Stock_Notifier_Pro::instance();
}

stock_notifier_pro();