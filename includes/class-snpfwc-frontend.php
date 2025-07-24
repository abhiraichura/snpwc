<?php
/**
 * Handles all frontend logic and displays.
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNPFWC_Frontend {

    /**
     * Initialize frontend hooks.
     */
    public static function init() {
        // --- FREE FEATURES ---
        add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'display_subscription_form' ), 31 );
        add_action( 'wp_ajax_snpfwc_subscribe', array( __CLASS__, 'handle_subscription' ) );
        add_action( 'wp_ajax_nopriv_snpfwc_subscribe', array( __CLASS__, 'handle_subscription' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        
        // --- PREMIUM FEATURES ---
        add_action( 'plugins_loaded', array( __CLASS__, 'init_premium_features' ) );
    }

    /**
     * Initializes premium frontend features after all plugins are loaded.
     */
    public static function init_premium_features() {
        if ( ! defined( 'SNPFWC_PRO_VERSION' ) ) {
            return;
        }
        add_filter( 'woocommerce_available_variation', array( __CLASS__, 'display_variation_subscription_form' ), 20, 3 );
    }

    public static function enqueue_assets() {
        if ( is_product() ) {
            wp_enqueue_style( 'snpfwc-style', SNPFWC_PLUGIN_URL . 'assets/css/snpfwc-style.css', array(), SNPFWC_VERSION );
            wp_enqueue_script( 'snpfwc-script', SNPFWC_PLUGIN_URL . 'assets/js/snpfwc-frontend.js', array( 'jquery' ), SNPFWC_VERSION, true );
            wp_localize_script( 'snpfwc-script', 'snpfwc_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'snpfwc-subscribe-nonce' ), 'messages' => [ 'success' => __( 'Thank you! We will notify you when this product is back in stock.', 'stock-notifier-pro-for-woocommerce' ), 'error' => __( 'An error occurred. Please try again.', 'stock-notifier-pro-for-woocommerce' ), 'invalid_email' => __( 'Please enter a valid email address.', 'stock-notifier-pro-for-woocommerce' ), 'already_subscribed' => __( 'You are already subscribed for this product.', 'stock-notifier-pro-for-woocommerce' ), ] ) );
        }
    }

    public static function display_subscription_form() {
        global $product;
        if ( $product->is_type('variable') || ! $product->is_type('simple') || $product->is_in_stock() ) {
            return;
        }
        self::render_form( $product->get_id() );
    }

    public static function display_variation_subscription_form( $variation_data, $product, $variation ) {
        if ( ! $variation_data['is_in_stock'] ) {
            ob_start();
            self::render_form( $product->get_id(), $variation->get_id() );
            $variation_data['availability_html'] = ob_get_clean();
        }
        return $variation_data;
    }

    /**
     * Renders the HTML form, now with all the new premium display options.
     */
    public static function render_form( $product_id, $variation_id = 0 ) {
        global $wpdb;

        $options = get_option( 'snpfwc_settings', [] );
        $is_pro = defined('SNPFWC_PRO_VERSION');

        // Default values
        $form_title = esc_html__( 'Out of Stock!', 'stock-notifier-pro-for-woocommerce' );
        $button_text = esc_html__( 'Notify Me', 'stock-notifier-pro-for-woocommerce' );
        $style = "background-color: #2271b1; color: #ffffff; font-size: 16px;";
        
        // Use the parent product ID for meta fields for both simple and variations
        $main_product_id = $product_id;

        if ( $is_pro ) {
            $form_title = !empty($options['form_title']) ? esc_html($options['form_title']) : $form_title;
            $button_text = !empty($options['button_text']) ? esc_html($options['button_text']) : $button_text;
            $button_bg_color = !empty($options['button_bg_color']) ? esc_attr($options['button_bg_color']) : '#2271b1';
            $button_text_color = !empty($options['button_text_color']) ? esc_attr($options['button_text_color']) : '#ffffff';
            $button_font_size = !empty($options['button_font_size']) ? esc_attr($options['button_font_size']) . 'px' : '16px';
            $style = "background-color: {$button_bg_color}; color: {$button_text_color}; font-size: {$button_font_size};";
        }
        
        $restock_date = get_post_meta( $main_product_id, '_snpfwc_restock_date', true );
        $display_date = !empty($restock_date) ? date_i18n( get_option( 'date_format' ), strtotime( $restock_date ) ) : '';

        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        $sql_count = "SELECT COUNT(id) FROM `" . esc_sql( $table_name ) . "` WHERE status = %s AND product_id = %d AND variation_id = %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $table_name is a fixed prefix and the query is prepared using a variable.
        $subscriber_count = $wpdb->get_var( $wpdb->prepare( $sql_count, 'subscribed', $product_id, $variation_id ) );
        ?>
        <div class="snpfwc-subscribe-form-wrapper" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-variation-id="<?php echo esc_attr( $variation_id ); ?>">
            <h4><?php echo esc_html($form_title); ?></h4>
            
            <?php if ($is_pro && !empty($display_date)): ?>
                <p class="snpfwc-meta-info"><strong><?php esc_html_e('Estimated back in stock around:', 'stock-notifier-pro-for-woocommerce'); ?></strong> <?php echo esc_html($display_date); ?></p>
            <?php endif; ?>
            
            <?php if ($is_pro && isset($options['enable_demand_meter']) && $subscriber_count > 0): ?>
                 <p class="snpfwc-meta-info">🔥 <strong><?php
                 // translators: %d: The number of other subscribers on the waitlist.
                 printf( esc_html(_n( 'Join %d other on the waitlist!', 'Join %d others on the waitlist!', $subscriber_count, 'stock-notifier-pro-for-woocommerce' )), esc_html($subscriber_count) ); ?></strong></p>
            <?php endif; ?>
            
            <p>
                <?php esc_html_e( 'Enter your email to be notified when this product is back in stock.', 'stock-notifier-pro-for-woocommerce' ); ?>
                <?php if ($is_pro && isset($options['enable_exclusive_offer']) && !empty($options['exclusive_offer_text'])): ?>
                    <?php echo esc_html($options['exclusive_offer_text']); ?>
                <?php endif; ?>
            </p>
            
            <div class="snpfwc-subscribe-form">
                <input type="email" name="snpfwc_email" class="snpfwc-email-input" placeholder="<?php esc_attr_e( 'Your Email Address', 'stock-notifier-pro-for-woocommerce' ); ?>" required>
                <button type="button" class="snpfwc-submit-button" style="<?php echo esc_attr($style); ?>"><?php echo esc_html($button_text); ?></button>
            </div>
            <div class="snpfwc-form-message"></div>
        </div>
        <?php
    }

    public static function handle_subscription() {
        check_ajax_referer( 'snpfwc-subscribe-nonce', 'nonce' );
        
        $email = isset($_POST['email']) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $product_id = isset($_POST['product_id']) ? intval( wp_unslash( $_POST['product_id'] ) ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? intval( wp_unslash( $_POST['variation_id'] ) ) : 0;

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid email address.', 'stock-notifier-pro-for-woocommerce' ) ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        
        $sql_select = "SELECT * FROM `" . esc_sql( $table_name ) . "` WHERE customer_email = %s AND product_id = %d AND variation_id = %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $table_name is a fixed prefix and the query is prepared using a variable.
        $existing = $wpdb->get_row( $wpdb->prepare( $sql_select, $email, $product_id, $variation_id ) );
        
        if ( $existing && $existing->status === 'subscribed' ) {
            wp_send_json_error( [ 'message' => __( 'You are already subscribed for this product.', 'stock-notifier-pro-for-woocommerce' ) ] );
        }

        if ( $existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update( 
                $table_name, 
                [ 
                    'status' => 'subscribed', 
                    'subscription_date' => current_time( 'mysql' ), 
                    'notified_date' => null, 
                    'conversion_status' => 'pending', 
                    'order_id' => null, 
                    'generated_coupon' => null 
                ], 
                [ 'id' => $existing->id ],
                [ '%s', '%s', '%s', '%s', '%d', '%s' ], // Data format
                [ '%d' ] // Where format
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->insert( 
                $table_name, 
                [ 
                    'customer_email' => $email, 
                    'product_id' => $product_id, 
                    'variation_id' => $variation_id, 
                    'subscription_date' => current_time( 'mysql' ), 
                    'status' => 'subscribed' 
                ],
                [ '%s', '%d', '%d', '%s', '%s' ] // Data format
            );
        }

        if ( $result !== false ) {
            wp_send_json_success( [ 'message' => __( 'Subscription successful!', 'stock-notifier-pro-for-woocommerce' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Could not save subscription. Please try again.', 'stock-notifier-pro-for-woocommerce' ) ] );
        }
    }
}