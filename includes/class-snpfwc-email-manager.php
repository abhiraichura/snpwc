<?php
/**
 * Manages all email sending functionality.
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNPFWC_Email_Manager {

    public static function init() {
        add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'detect_stock_status_change' ), 10, 2 );
        add_action( 'snpfwc_send_notifications_cron', array( __CLASS__, 'process_notification_queue' ) );
        add_action( 'snpfwc_run_single_notification_event', array( __CLASS__, 'process_notification_queue' ) );
        add_action( 'admin_post_snpfwc_run_queue', array( __CLASS__, 'handle_manual_queue_run' ) );
        add_action( 'snpfwc_send_reminder_emails', array( __CLASS__, 'send_waitlist_reminders' ) );
        add_action( 'snpfwc_send_get_ready_emails', array( __CLASS__, 'send_get_ready_emails' ) );
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'track_conversions' ) );
    }
    
    public static function detect_stock_status_change( $product, $data_store ) {
        $changes = $product->get_changes();
        if ( ! isset( $changes['stock_status'] ) ) return;
        
        $product_id = $product->get_id();
        $old_stock_status = get_post_meta( $product_id, '_stock_status', true );
        $new_stock_status = $product->get_stock_status();

        if ( 'outofstock' === $old_stock_status && 'instock' === $new_stock_status ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'snpfwc_subscribers';
            $product_id_for_query = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $variation_id_for_query = $product->is_type('variation') ? $product->get_id() : 0;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query is necessary here to fetch subscriber IDs for notification, and it's prepared. Caching is not strictly required for this specific lookup.
            $subscriber_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE product_id = %d AND variation_id = %d AND status = 'subscribed'", $product_id_for_query, $variation_id_for_query ) );
            if ( ! empty( $subscriber_ids ) ) {
                update_post_meta( $product_id, '_snpfwc_needs_notification', $subscriber_ids );
                wp_schedule_single_event( time() + 5, 'snpfwc_run_single_notification_event' );
            }
        }
    }
    
    public static function handle_manual_queue_run() {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check handles validation and sanitization via wp_verify_nonce.
        if ( ! current_user_can('manage_woocommerce') || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'snpfwc_run_queue_nonce') ) {
            wp_die('Security check failed.');
        }
        self::process_notification_queue();
        wp_redirect(admin_url('admin.php?page=stock-notifier-pro&queue_run=true'));
        exit;
    }

    public static function process_notification_queue() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is necessary to fetch flagged products for notification processing. Caching is not strictly required for this specific lookup.
        $flagged_products = $wpdb->get_results( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_snpfwc_needs_notification'" );
        if ( empty( $flagged_products ) ) return;
        foreach ( $flagged_products as $flagged_product ) {
            $product_id = $flagged_product->post_id;
            $subscriber_ids = get_post_meta( $product_id, '_snpfwc_needs_notification', true );
            $product = wc_get_product( $product_id );
            if ( ! $product || empty( $subscriber_ids ) || ! is_array( $subscriber_ids ) ) {
                delete_post_meta( $product_id, '_snpfwc_needs_notification' );
                continue;
            }
            self::send_notifications_for_product( $product, $subscriber_ids );
            delete_post_meta( $product_id, '_snpfwc_needs_notification' );
        }
    }
    
    public static function send_notifications_for_product( $product, $subscriber_ids ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        $ids_placeholder = implode( ',', array_fill( 0, count( $subscriber_ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query for subscribers is necessary and is prepared; caching not strictly needed here.
        $subscribers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id IN ({$ids_placeholder})", $subscriber_ids ) );
        if ( empty( $subscribers ) ) return;

        $options = get_option( 'snpfwc_settings', [] );
        $low_stock_threshold = get_post_meta( $product->get_id(), '_snpfwc_low_stock_threshold', true );
        $is_limited_stock = !empty($low_stock_threshold) && $product->get_stock_quantity() <= (int) $low_stock_threshold;
        $upsell_ids = get_post_meta( $product->get_id(), '_snpfwc_upsell_products', true );
        // translators: %s: Product name.
        $subject = sprintf( __( 'Good News! %s is back in stock!', 'stock-notifier-pro-for-woocommerce' ), $product->get_name() );
        
        foreach ( $subscribers as $subscriber ) {
            // Get the coupon code using the new unified method that will now ALWAYS generate a new coupon
            $coupon_code = self::get_coupon_for_email($subscriber->customer_email);
            
            if ($is_limited_stock) {
                // translators: %s: Product name.
                $body_text = sprintf(
                    /* translators: %s: Product name. */
                    __( "Good news! The product you were waiting for, %s, is now available for purchase, but stock is very limited! Order now before it sells out again.", 'stock-notifier-pro-for-woocommerce' ),
                    "<strong>{$product->get_formatted_name()}</strong>"
                );
            } else {
                // translators: %s: Product name.
                $body_text = sprintf(
                    /* translators: %s: Product name. */
                    __( "Good news! The product you were waiting for, %s, is now available for purchase.", 'stock-notifier-pro-for-woocommerce' ),
                    "<strong>{$product->get_formatted_name()}</strong>"
                );
            }
            
            if (!empty($coupon_code)) {
                // translators: %s: Coupon code.
                $body_text .= '<br><p>' . sprintf( __( 'As promised, here is your exclusive discount code: %s', 'stock-notifier-pro-for-woocommerce'), '<strong style="font-size:1.2em; padding: 5px; background: #f0f0f1;">' . esc_html($coupon_code) . '</strong>' ) . '</p>';
            }

            $message = self::get_email_template( $product, __('It\'s Back in Stock!', 'stock-notifier-pro-for-woocommerce'), $body_text, 'upsells', (array) $upsell_ids );
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail( $subscriber->customer_email, $subject, $message, $headers );
            
            if ($sent) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update to subscriber status is direct and doesn't require caching.
                $wpdb->update( $table_name, [ 'status' => 'notified', 'notified_date' => current_time( 'mysql' ), 'generated_coupon' => $coupon_code ], [ 'id' => $subscriber->id ] );
            }
        }
    }
    
    public static function send_get_ready_emails() {
        $options = get_option( 'snpfwc_settings', [] );
        if ( !isset($options['enable_get_ready_email']) || empty($options['get_ready_email_days']) ) return;
        
        global $wpdb;
        $days = absint($options['get_ready_email_days']);
        $target_date = gmdate('Y-m-d', strtotime("+{$days} days")); // Use gmdate for timezone consistency
        
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Meta query is necessary for this custom logic.
        $products = get_posts(['post_type' => 'product', 'posts_per_page' => -1, 'meta_query' => [['key' => '_snpfwc_restock_date', 'value' => $target_date, 'compare' => '=']]]);
        
        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query for subscribers is necessary. Caching not strictly needed.
            $subscribers = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}snpfwc_subscribers WHERE status = 'subscribed' AND product_id = %d", $product->get_id()) );
            
            foreach ($subscribers as $subscriber) {
                // translators: %s: Product name.
                $subject = sprintf( __( "Get Ready! %s is almost back in stock!", 'stock-notifier-pro-for-woocommerce' ), $product->get_name() );
                // translators: %s: Product name.
                $body_text = sprintf( __( "Hi there! Just a heads-up, the %s you're waiting for is scheduled to be restocked in the next few days. We wanted you to be the first to know! We'll send you one final email the moment it's available for purchase.", 'stock-notifier-pro-for-woocommerce' ), "<strong>{$product->get_formatted_name()}</strong>" );
                $message = self::get_email_template( $product, __("It's Almost Here!", 'stock-notifier-pro-for-woocommerce'), $body_text );
                wp_mail( $subscriber->customer_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8'] );
            }
        }
    }

    public static function send_waitlist_reminders() {
        $options = get_option( 'snpfwc_settings', [] );
        if ( !isset($options['enable_reminder_email']) || empty($options['reminder_email_days']) ) return;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        $days = absint($options['reminder_email_days']);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query for subscribers is necessary. Caching not strictly needed.
        $subscribers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE status = 'subscribed' AND subscription_date < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) );
        
        foreach ($subscribers as $subscriber) {
            $product = wc_get_product( $subscriber->variation_id > 0 ? $subscriber->variation_id : $subscriber->product_id );
            if (!$product) continue;

            // translators: %s: Product name.
            $subject = sprintf( __( "Still waiting for the %s?", 'stock-notifier-pro-for-woocommerce' ), $product->get_name() );
            // translators: %s: Product name.
            $body_text = sprintf( __( "Hi there! We know you're still interested in the %s and wanted to let you know we haven't forgotten about you. We're working hard to get it back in stock. Thanks for your patience!", 'stock-notifier-pro-for-woocommerce' ), "<strong>{$product->get_formatted_name()}</strong>" );
            $alternative_ids = get_post_meta( $product->get_id(), '_snpfwc_alternative_products', true );
            $message = self::get_email_template( $product, __('Still on the Waitlist!', 'stock-notifier-pro-for-woocommerce'), $body_text, 'alternatives', (array) $alternative_ids );
            wp_mail( $subscriber->customer_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8'] );
        }
    }
    
    /**
     * Determines which coupon code to use based on settings.
     * If automatic coupon generation is enabled, it generates a new unique coupon
     * based on the selected template (if any) and returns its code.
     *
     * @param string $customer_email The email of the customer (used for new coupon restrictions).
     * @return string The newly generated unique coupon code, or an empty string.
     */
    private static function get_coupon_for_email($customer_email = '') {
        $options = get_option('snpfwc_settings', []);
        
        // If automatic coupon generation is NOT enabled, return empty.
        if (empty($options['enable_coupon_generation'])) {
            return '';
        }

        $selected_coupon_template_id = isset($options['coupon_template']) ? absint($options['coupon_template']) : 0;

        // Always generate a new unique coupon if generation is enabled.
        // Pass the template ID to the generation function so it can copy properties.
        $coupon_code_to_use = self::generate_new_unique_coupon($selected_coupon_template_id, $customer_email);
        
        return $coupon_code_to_use;
    }

    /**
     * Generates a new unique WooCommerce coupon based on a template (if provided).
     * This function now always generates a new coupon.
     *
     * @param int $template_coupon_id The ID of the coupon to use as a template.
     * @param string $customer_email The email of the customer for email restrictions.
     * @return string The newly generated unique coupon code, or empty string if disabled/failed.
     */
    private static function generate_new_unique_coupon( $template_coupon_id, $customer_email ) {
        // Generate a shorter, unique code.
        // Using a combination of a prefix and a short, random alphanumeric string.
        // Adjust the length of the random string as needed for uniqueness vs brevity.
        $new_coupon_code = 'SNPFWC-' . strtoupper( wp_generate_password( 6, false ) ); // e.g., SNPFWC-ABCDEF

        $coupon_amount = 0; // Default if no template or amount from settings
        $discount_type = 'fixed_cart'; // Default discount type
        $individual_use = 'no'; // Default
        $expiry_date = null; // Default
        $free_shipping = false; // Default
        $minimum_amount = ''; // Default
        $maximum_amount = ''; // Default
        $product_ids = []; // Default
        $exclude_product_ids = []; // Default
        $product_categories = []; // Default
        $exclude_product_categories = []; // Default

        // If a valid template ID is provided, use its settings for the NEW coupon generation
        if ($template_coupon_id > 0) {
            $template_coupon = new WC_Coupon($template_coupon_id);
            if ($template_coupon->get_id()) {
                $discount_type            = $template_coupon->get_discount_type();
                $coupon_amount            = $template_coupon->get_amount();
                $individual_use           = $template_coupon->get_individual_use();
                $expiry_date              = $template_coupon->get_date_expires() ? $template_coupon->get_date_expires()->format('Y-m-d') : null;
                $free_shipping            = $template_coupon->get_free_shipping();
                $minimum_amount           = $template_coupon->get_minimum_amount();
                $maximum_amount           = $template_coupon->get_maximum_amount();
                $product_ids              = $template_coupon->get_product_ids();
                $exclude_product_ids      = $template_coupon->get_excluded_product_ids();
                $product_categories       = $template_coupon->get_product_categories();
                $exclude_product_categories = $template_coupon->get_excluded_product_categories();
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is a development warning for an invalid template.
                error_log('SNPFWC Warning: Coupon template ID (' . $template_coupon_id . ') for new coupon generation is invalid or not found.');
            }
        }
        
        $new_coupon = new WC_Coupon();
        $new_coupon->set_props(array(
            'code'                      => $new_coupon_code,
            'discount_type'             => $discount_type,
            'amount'                    => $coupon_amount,
            'individual_use'            => $individual_use,
            'usage_limit'               => 1, // Ensure single-use per generated coupon
            'usage_limit_per_user'      => 1, // Ensure single-use per user
            'expiry_date'               => $expiry_date,
            'email_restrictions'        => !empty($customer_email) ? array($customer_email) : array(), // Restrict to subscriber's email
            'free_shipping'             => $free_shipping,
            'minimum_amount'            => $minimum_amount,
            'maximum_amount'            => $maximum_amount,
            'product_ids'               => $product_ids,
            'excluded_product_ids'      => $exclude_product_ids,
            'product_categories'        => $product_categories,
            'excluded_product_categories' => $exclude_product_categories,
        ));
        
        $new_coupon_id = $new_coupon->save();

        if ( $new_coupon_id ) {
            // IMPORTANT: Mark this coupon as generated by SNPFWC
            update_post_meta($new_coupon_id, '_snpfwc_generated_coupon', 1);
            return $new_coupon_code;
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is a development warning for a coupon generation failure.
            error_log('SNPFWC Error: Failed to generate a new unique coupon.');
            return '';
        }
    }


    public static function track_conversions( $order_id ) {
        if ( !$order_id ) return;
        $order = wc_get_order( $order_id );
        if ( !$order ) return;
        $customer_email = $order->get_billing_email();
        $items = $order->get_items();
        global $wpdb;
        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update to subscriber status is necessary and caching is not required.
            $wpdb->update(
                $table_name,
                ['conversion_status' => 'converted', 'order_id' => $order_id],
                ['customer_email' => $customer_email, 'product_id' => $product_id, 'variation_id' => $variation_id, 'status' => 'notified'],
                ['%s', '%d'],
                ['%s', '%d', '%d', '%s']
            );
        }
    }

    /**
     * Sends a manual back-in-stock notification email to a single subscriber.
     *
     * @param int $subscriber_id The ID of the subscriber to notify.
     * @return bool True if the email was sent, false otherwise.
     */
    public static function send_manual_notification( $subscriber_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'snpfwc_subscribers';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct query for subscriber details is necessary. The query is prepared and caching is not strictly required for this specific lookup.
        $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $subscriber_id));
        
        if ( !$subscriber ) {
            return false;
        }
        
        $product = wc_get_product( $subscriber->variation_id > 0 ? $subscriber->variation_id : $subscriber->product_id );
        if ( !$product ) {
            return false;
        }
        
        // Get the coupon code using the new unified method
        $coupon_code = self::get_coupon_for_email($subscriber->customer_email);

        // translators: %s: Product name.
        $subject = sprintf( __( 'Good News! %s is back in stock!', 'stock-notifier-pro-for-woocommerce' ), $product->get_name() );
        
        // translators: %s: Product formatted name.
        $body_text = sprintf( 
            /* translators: %s: Product formatted name. */
            __( "Hi there!<br><br>The product you've been patiently waiting for, <strong>%s</strong>, is now available for purchase.", 'stock-notifier-pro-for-woocommerce' ),
            $product->get_formatted_name()
        );

        if (!empty($coupon_code)) {
            // translators: %s: Coupon code.
            $body_text .= '<br><p>' . sprintf( 
                /* translators: %s: Coupon code. */
                __( 'As promised, here is your exclusive discount code: %s', 'stock-notifier-pro-for-woocommerce'), 
                '<strong style="font-size:1.2em; padding: 5px; background: #f0f0f1;">' . esc_html($coupon_code) . '</strong>' 
            ) . '</p>';
        }

        $upsell_ids = get_post_meta( $product->get_id(), '_snpfwc_upsell_products', true );
        if (!is_array($upsell_ids)) {
            $upsell_ids = []; // Ensure it's an array for get_email_template
        }

        $message = self::get_email_template( 
            $product, 
            __('It\'s Back in Stock!', 'stock-notifier-pro-for-woocommerce'), 
            $body_text, 
            'upsells', 
            $upsell_ids 
        );
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail( $subscriber->customer_email, $subject, $message, $headers );
        
        if ($sent) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update to subscriber status is necessary and caching is not required.
            $wpdb->update(
                $table_name,
                [ 
                    'status' => 'notified', 
                    'notified_date' => current_time( 'mysql' ),
                    'generated_coupon' => $coupon_code // Store the generated coupon for tracking
                ],
                [ 'id' => $subscriber->id ],
                [ '%s', '%s', '%s' ], // Format for status, notified_date, generated_coupon
                [ '%d' ] // Format for id
            );
        }
        
        return $sent;
    }
    
    public static function get_email_template( $product, $header_text, $body_text, $section_type = '', $product_ids = [] ) {
        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- This is for an email template, not a front-end display, and direct URL is needed.
        $product_image_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
        if (!$product_image_url) { $product_image_url = wc_placeholder_img_src(); }
        $product_url = $product->get_permalink();
        $options = get_option( 'snpfwc_settings', [] );
        $button_bg_color = !empty($options['button_bg_color']) ? esc_attr($options['button_bg_color']) : '#2271b1';
        $button_text_color = !empty($options['button_text_color']) ? esc_attr($options['button_text_color']) : '#ffffff';
        ob_start();
        ?>
        <!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title><style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color:#f4f4f4;margin:0;padding:0}.container{max-width:600px;margin:20px auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden}.header{background:#333;color:#fff;padding:25px;text-align:center}.header h1{margin:0;font-size:24px;font-weight:600}.content{padding:30px;line-height:1.6;color:#555}.content p{margin:0 0 15px}.product-card{display:block;border:1px solid #e0e0e0;border-radius:8px;text-decoration:none;margin:20px 0}.product-image{text-align:center;padding:20px}.product-image img{max-width:200px;height:auto}.product-details{padding:20px;background-color:#f9f9f9;text-align:center}.product-details h3{margin:0 0 10px;font-size:18px;color:#333}.button-cta{text-align:center;margin-top:30px}.button-cta a{background-color:<?php echo esc_attr($button_bg_color); ?>;color:<?php echo esc_attr($button_text_color); ?>;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:700;display:inline-block}.footer{background:#f4f4f4;color:#777;padding:20px;text-align:center;font-size:12px}.related-section{border-top:1px solid #e0e0e0;padding-top:20px;margin-top:20px}.related-title{font-size:18px;font-weight:600;color:#333;margin:0 0 15px;text-align:center}.related-grid{font-size:0;text-align:center}.related-product{display:inline-block;width:30%;margin:1%;text-align:center;vertical-align:top;font-size:14px}.related-product a{text-decoration:none;color:#555}.related-product img{max-width:100px;height:auto;margin-bottom:10px}.related-product-title{font-weight:600}</style></head>
        <body><table class="container" width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td class="header"><h1><?php echo esc_html($header_text); ?></h1></td></tr><tr><td class="content"><p><?php esc_html_e('Hi there,', 'stock-notifier-pro-for-woocommerce'); ?></p><div><?php echo wp_kses_post( wpautop( $body_text ) ); ?></div><a href="<?php echo esc_url($product_url); ?>" class="product-card"><div class="product-image"><img src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>"></div><div class="product-details"><h3><?php echo esc_html($product->get_name()); ?></h3></div></a><div class="button-cta"><a href="<?php echo esc_url($product_url); ?>"><?php esc_html_e('View Product Now', 'stock-notifier-pro-for-woocommerce'); ?></a></div>
        <?php if ( !empty($product_ids) && is_array($product_ids) ): 
            // translators: Title for the related products section (alternatives).
            $section_title_alternatives = __('While You Wait, Check These Out...', 'stock-notifier-pro-for-woocommerce'); 
            // translators: Title for the related products section (upsells).
            $section_title_upsells = __('You Might Also Like...', 'stock-notifier-pro-for-woocommerce');
            $section_title = ($section_type === 'alternatives') ? $section_title_alternatives : $section_title_upsells;
        ?>
        <div class="related-section"><h3 class="related-title"><?php echo esc_html($section_title); ?></h3><div class="related-grid">
        <?php foreach ($product_ids as $id): $related_product = wc_get_product($id); if(!$related_product) continue; 
        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- This is for an email template, not a front-end display, and direct URL is needed.
        $related_image_url = wp_get_attachment_image_url($related_product->get_image_id(), 'thumbnail'); 
        if (!$related_image_url) {$related_image_url = wc_placeholder_img_src();} ?>
        <div class="related-product"><a href="<?php echo esc_url($related_product->get_permalink()); ?>"><img src="<?php echo esc_url($related_image_url); ?>" alt="<?php echo esc_attr($related_product->get_name()); ?>"><div class="related-product-title"><?php echo esc_html($related_product->get_name()); ?></div></a></div>
        <?php endforeach; ?>
        </div></div><?php endif; ?>
        </td></tr><tr><td class="footer"><p>&copy; <?php echo esc_html( gmdate('Y') ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e('All rights reserved.', 'stock-notifier-pro-for-woocommerce'); ?></p></td></tr></table></body></html>
        <?php
        return ob_get_clean();
    }
}