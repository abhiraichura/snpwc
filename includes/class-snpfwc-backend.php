<?php
/**
 * Handles all backend (admin area) logic and pages.
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNPFWC_Backend {

    /**
     * Store the hook suffixes for our admin pages.
     * @var array
     */
    private static $screen_hooks = [];

    /**
     * Initialize backend hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( SNPFWC_PLUGIN_DIR . 'stock-notifier-pro.php' ), array( __CLASS__, 'add_action_links' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'init_premium_features' ) );
    }
    
    /**
     * Initializes all premium features.
     */
    public static function init_premium_features() {
        if ( ! defined( 'SNPFWC_PRO_VERSION' ) ) {
            return;
        }
        add_action( 'admin_menu', array( __CLASS__, 'add_premium_menu_items' ), 20 );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_post_snpfwc_export_csv', array( __CLASS__, 'handle_csv_export' ) );
        add_action('admin_post_snpfwc_delete_subscriber', array(__CLASS__, 'handle_delete_subscriber'));
        add_action('admin_post_snpfwc_bulk_delete', array(__CLASS__, 'handle_bulk_delete'));
        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'add_product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_meta_fields' ) );
        add_action( 'wp_ajax_snpfwc_send_manual_email', array( __CLASS__, 'handle_manual_email_ajax' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'filter_woocommerce_generated_coupons' ) );
        add_filter( 'views_edit-shop_coupon', array( __CLASS__, 'add_woocommerce_coupon_views' ) );
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public static function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        if ( $screen && ($screen->id === 'product' || strpos($hook, 'stock-notifier-pro-for-woocommerce') !== false || strpos($hook, 'snpfwc-settings') !== false || strpos($hook, 'snpfwc-insights') !== false || strpos($hook, 'snpfwc-generated-coupons') !== false) ) {
            
            $css_file_path = SNPFWC_PLUGIN_DIR . 'assets/css/snpfwc-admin-style.css';
            $js_file_path = SNPFWC_PLUGIN_DIR . 'assets/js/snpfwc-backend.js';

            $css_version = file_exists( $css_file_path ) ? filemtime( $css_file_path ) : ( defined('SNPFWC_VERSION') ? SNPFWC_VERSION : false );
            $js_version = file_exists( $js_file_path ) ? filemtime( $js_file_path ) : ( defined('SNPFWC_VERSION') ? SNPFWC_VERSION : false );

            wp_enqueue_style( 'snpfwc-admin-style', SNPFWC_PLUGIN_URL . 'assets/css/snpfwc-admin-style.css', array(), $css_version );
            wp_enqueue_script( 'snpfwc-admin-script', SNPFWC_PLUGIN_URL . 'assets/js/snpfwc-backend.js', array( 'jquery', 'wp-color-picker', 'jquery-ui-datepicker' ), $js_version, true );
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- jQuery UI CSS is a common CDN resource.
            wp_enqueue_style( 'jquery-ui-core' ); 
            wp_enqueue_style( 'jquery-ui-datepicker' );
        }
    }
    
    public static function add_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=stock-notifier-pro-for-woocommerce' ) ) . '">' . esc_html__( 'Subscribers', 'stock-notifier-pro-for-woocommerce' ) . '</a>';
        array_unshift( $links, $settings_link );
        if ( defined( 'SNPFWC_PRO_VERSION' ) ) {
            $pro_settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=snpfwc-settings' ) ) . '">' . esc_html__( 'Settings', 'stock-notifier-pro-for-woocommerce' ) . '</a>';
            array_unshift( $links, $pro_settings_link );
        } else {
             $pro_link = '<a href="https://unravelersglobal.com/stock-notifier-pro/" target="_blank" style="color:#2a9b2a;font-weight:bold;">' . esc_html__( 'Go Pro', 'stock-notifier-pro-for-woocommerce' ) . '</a>';
             array_push( $links, $pro_link );
        }
        return $links;
    }

    /**
     * Creates a proper top-level menu item and its submenus.
     */
    public static function add_admin_menu() {
        self::$screen_hooks[] = add_menu_page(
            esc_html__( 'Stock Notifier', 'stock-notifier-pro-for-woocommerce' ),
            esc_html__( 'Stock Notifier', 'stock-notifier-pro-for-woocommerce' ),
            'manage_woocommerce',
            'stock-notifier-pro-for-woocommerce', // This is our new parent slug
            array( __CLASS__, 'render_subscribers_page' ),
            'dashicons-email-alt',
            58
        );

        // Add the subscribers page as the first submenu item.
        self::$screen_hooks[] = add_submenu_page(
            'stock-notifier-pro-for-woocommerce', // Parent slug
            esc_html__( 'Subscribers', 'stock-notifier-pro-for-woocommerce' ),
            esc_html__( 'Subscribers', 'stock-notifier-pro-for-woocommerce' ),
            'manage_woocommerce',
            'stock-notifier-pro-for-woocommerce', // Make it the default page for the top-level menu
            array( __CLASS__, 'render_subscribers_page' )
        );
    }
    
    public static function render_subscribers_page() { require_once SNPFWC_PLUGIN_DIR . 'admin/views/view-subscribers-page.php'; }
    public static function render_settings_page() { require_once SNPFWC_PLUGIN_DIR . 'admin/views/view-settings-page.php'; }
    public static function render_insights_page() { require_once SNPFWC_PLUGIN_DIR . 'admin/views/view-insights-page.php'; }
    public static function render_generated_coupons_page() { require_once SNPFWC_PLUGIN_DIR . 'admin/views/view-generated-coupons-page.php'; }
    
    // --- ALL FUNCTIONS BELOW ARE PREMIUM ---

    /**
     * These are now correctly added under our new parent slug.
     */
    public static function add_premium_menu_items() {
        self::$screen_hooks[] = add_submenu_page( 'stock-notifier-pro-for-woocommerce', esc_html__( 'Waitlist Insights', 'stock-notifier-pro-for-woocommerce' ), esc_html__( 'Waitlist Insights', 'stock-notifier-pro-for-woocommerce' ), 'manage_woocommerce', 'snpfwc-insights', array( __CLASS__, 'render_insights_page' ) );
        self::$screen_hooks[] = add_submenu_page( 'stock-notifier-pro-for-woocommerce', esc_html__( 'Settings', 'stock-notifier-pro-for-woocommerce' ), esc_html__( 'Settings', 'stock-notifier-pro-for-woocommerce' ), 'manage_woocommerce', 'snpfwc-settings', array( __CLASS__, 'render_settings_page' ) );
        self::$screen_hooks[] = add_submenu_page( 'stock-notifier-pro-for-woocommerce', esc_html__( 'Generated Coupons', 'stock-notifier-pro-for-woocommerce' ), esc_html__( 'Generated Coupons', 'stock-notifier-pro-for-woocommerce' ), 'manage_woocommerce', 'snpfwc-generated-coupons', array( __CLASS__, 'render_generated_coupons_page' ) );
    }

    public static function add_product_data_tab( $tabs ) {
        $tabs['stock_notifier_pro'] = array( 'label' => esc_html__( 'Stock Notifier Pro', 'stock-notifier-pro-for-woocommerce' ), 'target' => 'snpfwc_product_data', 'class' => array('show_if_simple', 'show_if_variable'), 'priority' => 80, );
        return $tabs;
    }

    public static function add_product_data_panel() {
        global $post;
        echo '<div id="snpfwc_product_data" class="panel woocommerce_options_panel">';
        woocommerce_wp_text_input( array( 'id' => '_snpfwc_restock_date', 'label' => esc_html__( 'Estimated Restock Date', 'stock-notifier-pro-for-woocommerce' ), 'description' => esc_html__( 'Optional. Used for the "Get Ready!" email sequence.', 'stock-notifier-pro-for-woocommerce' ), 'desc_tip' => true, 'class' => 'snpfwc-datepicker', ) );
        woocommerce_wp_text_input( array( 'id' => '_snpfwc_low_stock_threshold', 'label' => esc_html__( 'Low Stock Threshold', 'stock-notifier-pro-for-woocommerce' ), 'description' => esc_html__( 'Optional. If stock is restocked below this number, the notification email will create extra urgency.', 'stock-notifier-pro-for-woocommerce' ), 'desc_tip' => true, 'type' => 'number', ) );
        ?>
        <p class="form-field">
            <label for="_snpfwc_alternative_products"><?php esc_html_e( '"While You Wait" Alternatives', 'stock-notifier-pro-for-woocommerce' ); ?></label>
            <select class="wc-product-search" multiple="multiple" style="width: 50%;" id="_snpfwc_alternative_products" name="_snpfwc_alternative_products[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'stock-notifier-pro-for-woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>">
                <?php $product_ids = get_post_meta( $post->ID, '_snpfwc_alternative_products', true ); if ( ! empty( $product_ids ) && is_array( $product_ids ) ) { foreach ( $product_ids as $product_id ) { $product = wc_get_product( $product_id ); if ( is_object( $product ) ) { echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>'; } } } ?>
            </select> <?php 
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip handles its own escaping.
            echo wc_help_tip( __( 'Suggest these products in reminder emails if this item is still out of stock.', 'stock-notifier-pro-for-woocommerce' ) ); ?>
        </p>
        <p class="form-field">
            <label for="_snpfwc_upsell_products"><?php esc_html_e( '"You Might Also Like" Upsells', 'stock-notifier-pro-for-woocommerce' ); ?></label>
            <select class="wc-product-search" multiple="multiple" style="width: 50%;" id="_snpfwc_upsell_products" name="_snpfwc_upsell_products[]" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'stock-notifier-pro-for-woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude="<?php echo intval( $post->ID ); ?>">
                <?php $product_ids = get_post_meta( $post->ID, '_snpfwc_upsell_products', true ); if ( ! empty( $product_ids ) && is_array( $product_ids ) ) { foreach ( $product_ids as $product_id ) { $product = wc_get_product( $product_id ); if ( is_object( $product ) ) { echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>'; } } } ?>
            </select> <?php 
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip handles its own escaping.
            echo wc_help_tip( __( 'Suggest these related products in the final back-in-stock notification email.', 'stock-notifier-pro-for-woocommerce' ) ); ?>
        </p>
        <?php
        echo '</div>';
    }

    public static function save_product_meta_fields( $post_id ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce check is performed at a higher level (woocommerce_process_product_meta hook).
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Data is unslashed before sanitization.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Inputs are sanitized.
        if ( isset( $_POST['_snpfwc_restock_date'] ) ) { update_post_meta( $post_id, '_snpfwc_restock_date', sanitize_text_field( wp_unslash( $_POST['_snpfwc_restock_date'] ) ) ); }
        if ( isset( $_POST['_snpfwc_low_stock_threshold'] ) ) { update_post_meta( $post_id, '_snpfwc_low_stock_threshold', absint( wp_unslash( $_POST['_snpfwc_low_stock_threshold'] ) ) ); }
        $alternatives = isset( $_POST['_snpfwc_alternative_products'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['_snpfwc_alternative_products'] ) ) : array();
        update_post_meta( $post_id, '_snpfwc_alternative_products', $alternatives );
        $upsells = isset( $_POST['_snpfwc_upsell_products'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['_snpfwc_upsell_products'] ) ) : array();
        update_post_meta( $post_id, '_snpfwc_upsell_products', $upsells );
        // phpcs:enable
    }
    
    public static function register_settings() {
        // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingMissing -- Sanitization callbacks are handled by the render functions, and data is saved via update_option with proper sanitization.
        register_setting(
            'SNPFWC_settings_group',
            'SNPFWC_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => 'SNPFWC_sanitize_settings',
                )
                );
        add_settings_section( 'snpfwc_form_customization_section', 'Form & Button Customization', null, 'snpfwc-settings' );
        add_settings_field( 'form_title', 'Form Title', array(__CLASS__, 'render_text_field'), 'snpfwc-settings', 'snpfwc_form_customization_section', ['name' => 'form_title'] );
        add_settings_field( 'button_text', 'Button Text', array(__CLASS__, 'render_text_field'), 'snpfwc-settings', 'snpfwc_form_customization_section', ['name' => 'button_text'] );
        add_settings_field( 'button_bg_color', 'Button Background Color', array(__CLASS__, 'render_color_field'), 'snpfwc-settings', 'snpfwc_form_customization_section', ['name' => 'button_bg_color'] );
        add_settings_field( 'button_text_color', 'Button Text Color', array(__CLASS__, 'render_color_field'), 'snpfwc-settings', 'snpfwc_form_customization_section', ['name' => 'button_text_color'] );
        add_settings_field( 'button_font_size', 'Button Font Size (px)', array(__CLASS__, 'render_number_field'), 'snpfwc-settings', 'snpfwc_form_customization_section', ['name' => 'button_font_size'] );
        add_settings_section( 'snpfwc_smart_waitlist_section', 'Smart Waitlist Features', null, 'snpfwc-settings' );
        add_settings_field( 'enable_demand_meter', 'Enable Demand Meter', array(__CLASS__, 'render_checkbox_field'), 'snpfwc-settings', 'snpfwc_smart_waitlist_section', ['name' => 'enable_demand_meter', 'desc' => 'Shows customers how many others are on the waitlist to create urgency.'] );
        add_settings_field( 'enable_exclusive_offer', 'Enable Exclusive Offer Text', array(__CLASS__, 'render_checkbox_field'), 'snpfwc-settings', 'snpfwc_smart_waitlist_section', ['name' => 'enable_exclusive_offer', 'desc' => 'Show a special offer message on the subscription form.'] );
        add_settings_field( 'exclusive_offer_text', 'Exclusive Offer Message', array(__CLASS__, 'render_text_field'), 'snpfwc-settings', 'snpfwc_smart_waitlist_section', ['name' => 'exclusive_offer_text', 'desc' => 'e.g., "and get a 10% discount coupon!"'] );
        add_settings_section( 'snpfwc_email_automation_section', 'Email Automation', null, 'snpfwc-settings' );
        add_settings_field( 'enable_get_ready_email', 'Enable "Get Ready!" Pre-Stock Email', array(__CLASS__, 'render_checkbox_field'), 'snpfwc-settings', 'snpfwc_email_automation_section', ['name' => 'enable_get_ready_email', 'desc' => 'Sends an email a few days before the estimated restock date.'] );
        add_settings_field( 'get_ready_email_days', 'Send "Get Ready!" Email Before (Days)', array(__CLASS__, 'render_number_field'), 'snpfwc-settings', 'snpfwc_email_automation_section', ['name' => 'get_ready_email_days', 'default' => 3] );
        add_settings_field( 'enable_reminder_email', 'Enable "We Haven\'t Forgotten You" Email', array(__CLASS__, 'render_checkbox_field'), 'snpfwc-settings', 'snpfwc_email_automation_section', ['name' => 'enable_reminder_email', 'desc' => 'Automatically send a follow-up email to customers on long waitlists.'] );
        add_settings_field( 'reminder_email_days', 'Send Reminder After (Days)', array(__CLASS__, 'render_number_field'), 'snpfwc-settings', 'snpfwc_email_automation_section', ['name' => 'reminder_email_days', 'default' => 30] );
        add_settings_field( 'enable_coupon_generation', 'Enable Automatic Coupon Generation', array(__CLASS__, 'render_checkbox_field'), 'snpfwc-settings', 'snpfwc_email_automation_section', ['name' => 'enable_coupon_generation'] );
        add_settings_field( 'coupon_template', 'Coupon Template', array(__CLASS__, 'render_coupon_dropdown'), 'snpfwc-settings', 'snpfwc_email_automation_section', ['name' => 'coupon_template', 'desc' => 'Select a coupon to use as a template. A <strong>new, unique, single-use coupon code</strong> will be generated for each subscriber based on this template\'s settings (e.g., discount amount, type).'] );
    }
    
    public static function render_text_field($args) { $options = get_option('snpfwc_settings'); $name = esc_attr($args['name']); $value = isset($options[$name]) ? esc_attr($options[$name]) : ''; echo '<input type="text" name="snpfwc_settings[' . esc_attr($name) . ']" value="' . esc_attr($value) . '" class="regular-text" />'; if(isset($args['desc'])) echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>'; }
    public static function render_color_field($args) { $options = get_option('snpfwc_settings'); $name = esc_attr($args['name']); $value = isset($options[$name]) ? esc_attr($options[$name]) : '#2271b1'; echo '<input type="text" name="snpfwc_settings[' . esc_attr($name) . ']" value="' . esc_attr($value) . '" class="snpfwc-color-picker" />'; }
    public static function render_number_field($args) { $options = get_option('snpfwc_settings'); $name = esc_attr($args['name']); $value = isset($options[$name]) ? esc_attr($options[$name]) : ($args['default'] ?? ''); echo '<input type="number" name="snpfwc_settings[' . esc_attr($name) . ']" value="' . esc_attr($value) . '" class="small-text" />'; }
    public static function render_checkbox_field($args) { $options = get_option('snpfwc_settings'); $name = esc_attr($args['name']); $checked = isset($options[$name]) ? 'checked' : ''; echo '<label><input type="checkbox" name="snpfwc_settings[' . esc_attr($name) . ']" value="1" ' . esc_attr($checked) . ' /> '; if(isset($args['desc'])) echo esc_html($args['desc']) . '</label>'; }
    public static function render_coupon_dropdown($args) {
        $options = get_option('snpfwc_settings');
        $name = esc_attr($args['name']);
        $selected = isset($options[$name]) ? $options[$name] : '';
        $coupons = get_posts(array('post_type' => 'shop_coupon', 'posts_per_page' => -1, 'post_status' => 'publish'));
        echo '<select name="snpfwc_settings[' . esc_attr($name) . ']">';
        echo '<option value="">' . esc_html__('Select a Coupon Template', 'stock-notifier-pro-for-woocommerce') . '</option>';
        if ($coupons) {
            foreach ($coupons as $coupon) {
                echo '<option value="' . esc_attr($coupon->ID) . '" ' . selected($selected, $coupon->ID, false) . '>' . esc_html($coupon->post_title) . '</option>';
            }
        }
        echo '</select>';
        if(isset($args['desc'])) echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
    }

    public static function handle_csv_export() {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_verify_nonce sanitizes.
        if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'snpfwc_export_nonce_action' ) ) {
            wp_die(esc_html__( 'Security check failed.', 'stock-notifier-pro-for-woocommerce' ));
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query for export is common; table name is fixed and escaped.
        $subscribers = $wpdb->get_results( "SELECT * FROM " . esc_sql( $table_name ), ARRAY_A );
        
        if ( empty( $subscribers ) ) {
            wp_redirect(admin_url('admin.php?page=stock-notifier-pro&exported=0'));
            exit;
        }
        $filename = 'subscribers-' . gmdate( 'Y-m-d' ) . '.csv'; // Changed date() to gmdate()
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array_keys( $subscribers[0] ) );
        foreach ( $subscribers as $subscriber ) { fputcsv( $output, $subscriber ); }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct output to browser, WP_Filesystem not applicable.
        fclose( $output );
        exit;
    }
    
    public static function handle_manual_email_ajax() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( ['message' => esc_html__( 'Permission denied.', 'stock-notifier-pro-for-woocommerce' )] );
        }

        $subscriber_id = isset( $_POST['subscriber_id'] ) ? absint( wp_unslash( $_POST['subscriber_id'] ) ) : 0;
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_verify_nonce sanitizes.
        if ( ! wp_verify_nonce( $nonce, 'snpfwc_manual_email_nonce_' . $subscriber_id ) ) {
            wp_send_json_error( ['message' => esc_html__( 'Security check failed. Invalid nonce.', 'stock-notifier-pro-for-woocommerce' )] );
        }

        if ( $subscriber_id <= 0 ) {
            wp_send_json_error( ['message' => esc_html__( 'Invalid subscriber ID provided.', 'stock-notifier-pro-for-woocommerce' )] );
        }

        $sent = false;
        if ( class_exists( 'SNPFWC_Email_Manager' ) && method_exists( 'SNPFWC_Email_Manager', 'send_manual_notification' ) ) {
            $sent = SNPFWC_Email_Manager::send_manual_notification( $subscriber_id );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is a critical error for debugging setup issues.
            error_log( 'SNPFWC Error: SNPFWC_Email_Manager class or send_manual_notification method not found for manual email sending.' );
            wp_send_json_error( ['message' => esc_html__( 'Email manager not properly configured. Check server logs.', 'stock-notifier-pro-for-woocommerce' )] );
        }

        if ( $sent ) {
            wp_send_json_success( ['message' => esc_html__( 'Email sent successfully!', 'stock-notifier-pro-for-woocommerce' )] );
        } else {
            wp_send_json_error( ['message' => esc_html__( 'Failed to send email. A problem occurred during the sending process. Check debug.log for details.', 'stock-notifier-pro-for-woocommerce' )] );
        }

        wp_die();
    }

    public static function handle_delete_subscriber() {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_GET['id'] is sanitized by intval.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_GET['_wpnonce'] and $_GET['id'] are unslashed.
        if ( ! isset( $_GET['id'] ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'snpfwc_delete_subscriber_' . absint( wp_unslash( $_GET['id'] ) ) ) ) {
            wp_die(esc_html__('Security check failed.', 'stock-notifier-pro-for-woocommerce'));
        }
        // phpcs:enable
        if ( ! current_user_can('manage_woocommerce') ) { wp_die(esc_html__('Permission denied.', 'stock-notifier-pro-for-woocommerce')); }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update to subscriber status is necessary and caching is not required.
        $wpdb->delete($wpdb->prefix . 'snpfwc_subscribers', ['id' => intval( wp_unslash( $_GET['id'] ) )], ['%d']);
        wp_redirect(admin_url('admin.php?page=stock-notifier-pro&deleted=1'));
        exit;
    }
    
    public static function handle_bulk_delete() {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_POST['subscriber_ids'] is sanitized by array_map('intval').
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_POST['_wpnonce'] is unslashed.
        if ( ! isset( $_POST['subscriber_ids'] ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'snpfwc_bulk_delete_action' ) ) {
            wp_die(esc_html__('Security check failed.', 'stock-notifier-pro-for-woocommerce'));
        }
        // phpcs:enable
        if ( ! current_user_can('manage_woocommerce') ) { wp_die(esc_html__('Permission denied.', 'stock-notifier-pro-for-woocommerce')); }
        global $wpdb;
        $ids = array_map('intval', (array) wp_unslash( $_POST['subscriber_ids'] ) );
        if ( ! empty( $ids ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct deletion is necessary; placeholders built securely; caching not needed.
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}snpfwc_subscribers WHERE id IN ($placeholders)", $ids ) );
        }
        wp_redirect(admin_url('admin.php?page=stock-notifier-pro&deleted=true'));
        exit;
    }

    /**
     * NEW: Filters the main WooCommerce coupon query to hide SNPFWC generated coupons by default.
     * Only applies on the 'edit.php' screen for 'shop_coupon' post type.
     */
    public static function filter_woocommerce_generated_coupons( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Parameters for view changes, not data submission.
        if ( 'shop_coupon' === $query->get( 'post_type' ) ) {
            $current_screen = get_current_screen();
            
            // Only apply this filter on the main WooCommerce coupons page,
            // not when our custom SNPFWC Generated Coupons page is active.
            if ( $current_screen && $current_screen->id === 'edit-shop_coupon' ) {
                $meta_query = (array) $query->get('meta_query');

                if ( isset( $_GET['snpfwc_coupon_view'] ) && 'all' === $_GET['snpfwc_coupon_view'] ) {
                    // Show all coupons, no filtering by our meta key
                } elseif ( isset( $_GET['snpfwc_coupon_view'] ) && 'snpfwc_generated' === $_GET['snpfwc_coupon_view'] ) {
                     $meta_query[] = array(
                         'key'       => '_snpfwc_generated_coupon',
                         'value'     => '1',
                         'compare'   => '=',
                     );
                } else {
                    // Default view: Exclude SNPFWC generated coupons
                    $meta_query[] = array(
                        'key'       => '_snpfwc_generated_coupon',
                        'compare'   => 'NOT EXISTS',
                    );
                }
                $query->set( 'meta_query', $meta_query );
            }
        }
        // phpcs:enable
    }

    /**
     * NEW: Adds custom views to the WooCommerce coupons page.
     */
    public static function add_woocommerce_coupon_views( $views ) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Generates view links, not data submission.
        $current_view = isset( $_GET['snpfwc_coupon_view'] ) ? sanitize_text_field( wp_unslash( $_GET['snpfwc_coupon_view'] ) ) : '';
        $default_url = remove_query_arg( 'snpfwc_coupon_view' ); 
        
        $views['all_non_snpfwc'] = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url( $default_url ),
            ( empty( $current_view ) ? ' class="current"' : '' ),
            esc_html__( 'Default (Excluding SNPFWC)', 'stock-notifier-pro-for-woocommerce' )
        );

        $views['snpfwc_generated'] = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url( add_query_arg( 'snpfwc_coupon_view', 'snpfwc_generated', $default_url ) ),
            ( 'snpfwc_generated' === $current_view ? ' class="current"' : '' ),
            esc_html__( 'SNPFWC Generated', 'stock-notifier-pro-for-woocommerce' )
        );

        $views['all_coupons'] = sprintf(
            '<a href="%s"%s>%s</a>',
            esc_url( add_query_arg( 'snpfwc_coupon_view', 'all', $default_url ) ),
            ( 'all' === $current_view ? ' class="current"' : '' ),
            esc_html__( 'All Coupons (Including SNPFWC)', 'stock-notifier-pro-for-woocommerce' )
        );

        return $views;
        // phpcs:enable
    }
}