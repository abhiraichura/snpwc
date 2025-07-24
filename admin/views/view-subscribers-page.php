<?php
/**
 * Renders the admin page for viewing and managing subscribers.
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'snpfwc_subscribers';
$is_pro = defined( 'SNPFWC_PRO_VERSION' );

// Handle GET parameters and nonces for messages.
// Sanitize and validate nonce from GET.
// Ensure $_GET['_wpnonce'] is unslashed before sanitization and verification.
if (isset($_GET['deleted'])) {
    $deleted_status = sanitize_text_field(wp_unslash($_GET['deleted']));
    if ($deleted_status === '1' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'snpfwc_delete_subscriber_message')) { //
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Subscriber(s) deleted successfully.', 'stock-notifier-pro-for-woocommerce') . '</p></div>';
    }
}
if (isset($_GET['queue_run'])) {
    $queue_run_status = sanitize_text_field(wp_unslash($_GET['queue_run']));
    if ($queue_run_status === 'true' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'snpfwc_queue_run_message')) { //
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('The notification queue has been run manually.', 'stock-notifier-pro-for-woocommerce') . '</p></div>';
    }
}
if (isset($_GET['exported'])) {
    $exported_status = sanitize_text_field(wp_unslash($_GET['exported']));
    if ($exported_status === '0' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'snpfwc_export_message')) { //
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('No subscribers found to export.', 'stock-notifier-pro-for-woocommerce') . '</p></div>';
    }
}

// Added a success message for manual email sending
if (isset($_GET['manual_email_sent'])) {
    $manual_email_status = sanitize_text_field(wp_unslash($_GET['manual_email_sent']));
    if ($manual_email_status === 'success' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'snpfwc_manual_email_message')) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Manual email sent successfully.', 'stock-notifier-pro-for-woocommerce') . '</p></div>';
    } elseif ($manual_email_status === 'failed' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'snpfwc_manual_email_message')) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to send manual email.', 'stock-notifier-pro-for-woocommerce') . '</p></div>';
    }
}
?>

<div class="wrap snpfwc-admin-wrap">
    <h1><?php esc_html_e( 'Stock Notifier Subscribers', 'stock-notifier-pro-for-woocommerce' ); ?></h1>

    <?php
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessary direct query to check table existence. Table name is escaped.
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table_name ) . "'" ) === $table_name;
    if ( ! $table_exists ) {
        echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Stock Notifier Pro: Database Table Missing.', 'stock-notifier-pro-for-woocommerce' ) . '</strong> ' . esc_html__( 'Please deactivate and then reactivate the plugin to create the necessary database table.', 'stock-notifier-pro-for-woocommerce' ) . '</p></div>';
        echo '</div>';
        return;
    }
    ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=stock-notifier-pro" class="nav-tab nav-tab-active"><?php esc_html_e('Subscribers', 'stock-notifier-pro-for-woocommerce'); ?></a>
        <?php if ( $is_pro ) : ?>
            <a href="?page=snpfwc-insights" class="nav-tab"><?php esc_html_e('Waitlist Insights', 'stock-notifier-pro-for-woocommerce'); ?></a>
            <a href="?page=snpfwc-settings" class="nav-tab"><?php esc_html_e('Settings', 'stock-notifier-pro-for-woocommerce'); ?></a>
        <?php else : ?>
            <a href="https://unravelersglobal.com/stock-notifier-pro/" target="_blank" class="nav-tab snpfwc-pro-tab"><?php esc_html_e('Unlock Premium Features ✨', 'stock-notifier-pro-for-woocommerce'); ?></a>
        <?php endif; ?>
    </h2>
    
    <?php if ( $is_pro ) : ?>
        <div class="snpfwc-actions-bar">
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="snpfwc_export_csv">
                <?php wp_nonce_field('snpfwc_export_nonce_action'); ?>
                <?php submit_button( esc_html__( 'Export All to CSV', 'stock-notifier-pro-for-woocommerce' ), 'secondary', 'submit', false ); ?>
            </form>
            <?php
            // Ensure nonce is generated correctly for the URL
            $run_queue_url = wp_nonce_url(
                add_query_arg('action', 'snpfwc_run_queue', admin_url('admin-post.php')),
                'snpfwc_run_queue_nonce'
            );
            ?>
            <a href="<?php echo esc_url($run_queue_url); ?>" class="button button-primary">
                <?php esc_html_e('Run Notification Queue Manually', 'stock-notifier-pro-for-woocommerce'); ?>
            </a>
        </div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="stock-notifier-pro">
        <?php
        // Nonce field for the filter form
        wp_nonce_field('snpfwc_filter_subscribers_nonce', '_wpnonce_filter');

        $filter_product_id = 0; // Default value

        if (isset($_GET['filter_product'])) {
            // Unslash before sanitizing
            $filter_product_id = intval(sanitize_text_field(wp_unslash($_GET['filter_product'])));

            // Verify nonce after unslashing and sanitizing
            if (isset($_GET['_wpnonce_filter']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce_filter'])), 'snpfwc_filter_subscribers_nonce')) { //
                // If nonce check fails, reset filter to 0 to prevent acting on potentially malicious input
                $filter_product_id = 0;
            }
        }
        ?>

        <?php if ($is_pro): ?>
            <div class="alignleft actions">
                <?php
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, direct query for product filtering. Table name is escaped.
                $all_products = $wpdb->get_results( "SELECT DISTINCT product_id FROM `" . esc_sql( $table_name ) . "`" ); // Corrected: use esc_sql and concatenation
                ?>
                <select name="filter_product" id="filter_product">
                    <option value="0"><?php esc_html_e('Filter by Product...', 'stock-notifier-pro-for-woocommerce'); ?></option>
                    <?php
                    if ($all_products) {
                        foreach ($all_products as $prod_obj) {
                            $product = wc_get_product($prod_obj->product_id);
                            if ($product) {
                                // translators: %s: product name
                                printf(
                                    '<option value="%d" %s>%s</option>',
                                    esc_attr($prod_obj->product_id),
                                    selected($filter_product_id, $prod_obj->product_id, false),
                                    esc_html($product->get_name()) //
                                );
                            }
                        }
                    }
                    ?>
                </select>
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'stock-notifier-pro-for-woocommerce'); ?>">
            </div>
        <?php endif; ?>
    </form>


    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
        <input type="hidden" name="action" value="snpfwc_bulk_delete">
        <?php wp_nonce_field('snpfwc_bulk_delete_action'); ?>

        <div class="tablenav top">
            <?php if ( $is_pro ) : ?>
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'stock-notifier-pro-for-woocommerce'); ?></label>
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1"><?php esc_html_e('Bulk Actions', 'stock-notifier-pro-for-woocommerce'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'stock-notifier-pro-for-woocommerce'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'stock-notifier-pro-for-woocommerce'); ?>">
            </div>
            <?php endif; ?>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php if ( $is_pro ) : ?><td id="cb" class="manage-column column-cb check-column"><input type="checkbox" /></td><?php endif; ?>
                    <th scope="col"><?php esc_html_e( 'Email', 'stock-notifier-pro-for-woocommerce' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Product', 'stock-notifier-pro-for-woocommerce' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Status', 'stock-notifier-pro-for-woocommerce' ); ?></th>
                    <?php if ( $is_pro ) : ?><th scope="col"><?php esc_html_e( 'Conversion', 'stock-notifier-pro-for-woocommerce' ); ?></th><?php endif; ?>
                    <th scope="col"><?php esc_html_e( 'Subscribed', 'stock-notifier-pro-for-woocommerce' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Notified Date', 'stock-notifier-pro-for-woocommerce' ); ?></th>
                    <?php if ( $is_pro ) : ?><th scope="col"><?php esc_html_e( 'Actions', 'stock-notifier-pro-for-woocommerce' ); ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Construct the base query string
                $query = "SELECT * FROM `" . esc_sql( $table_name ) . "`"; // Ensure table name is escaped
                $query_args = array();

                if ($is_pro && $filter_product_id > 0) {
                    $query .= " WHERE product_id = %d";
                    $query_args[] = $filter_product_id;
                }
                $query .= " ORDER BY subscription_date DESC";
                
                // Use prepare only if there are actual arguments to escape.
                if ( ! empty( $query_args ) ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table, direct query. $query is constructed securely.
                    $subscribers = $wpdb->get_results( $wpdb->prepare( $query, ...$query_args ) );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table, direct query. $query is constructed securely.
                    $subscribers = $wpdb->get_results( $query );
                }

                if ( ! empty( $subscribers ) ) {
                    foreach ( $subscribers as $subscriber ) {
                        $product_object = wc_get_product( $subscriber->variation_id > 0 ? $subscriber->variation_id : $subscriber->product_id );
                        $product_name = $product_object ? $product_object->get_formatted_name() : esc_html__( 'Product not found', 'stock-notifier-pro-for-woocommerce' );
                        $edit_link = get_edit_post_link( $subscriber->product_id );
                        
                        // NEW: Generate nonce for the manual email button
                        $manual_email_nonce = wp_create_nonce( 'snpfwc_manual_email_nonce_' . $subscriber->id );
                        ?>
                        <tr>
                            <?php if ( $is_pro ) : ?><th scope="row" class="check-column"><input type="checkbox" name="subscriber_ids[]" value="<?php echo esc_attr($subscriber->id); ?>" /></th><?php endif; ?>
                            <td><?php echo esc_html( $subscriber->customer_email ); ?></td>
                            <td>
                                <?php if ($edit_link): ?><a href="<?php echo esc_url($edit_link); ?>"><strong><?php echo wp_kses_post($product_name); ?></strong></a><?php else: ?><strong><?php echo wp_kses_post($product_name); ?></strong><?php endif; ?>
                            </td>
                            <td><span class="snpfwc-status-<?php echo esc_attr($subscriber->status); ?>"><?php echo esc_html( ucfirst($subscriber->status) ); ?></span></td>
                            <?php if ( $is_pro ) : ?>
                                <td>
                                    <?php if ($subscriber->conversion_status === 'converted' && $subscriber->order_id): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($subscriber->order_id)); ?>" title="<?php esc_attr_e('View Order', 'stock-notifier-pro-for-woocommerce'); ?>">
                                            <span class="snpfwc-status-converted"><?php esc_html_e('Converted', 'stock-notifier-pro-for-woocommerce'); ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="snpfwc-status-pending"><?php esc_html_e('Pending', 'stock-notifier-pro-for-woocommerce'); ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscriber->subscription_date ) ) ); ?></td>
                            <td><?php echo ( ! empty( $subscriber->notified_date ) && '0000-00-00 00:00:00' != $subscriber->notified_date ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $subscriber->notified_date ) ) ) : esc_html__('—', 'stock-notifier-pro-for-woocommerce'); ?></td>
                            <?php if ( $is_pro ) : ?>
                                <td>
                                    <button type="button" class="button button-secondary snpfwc-manual-notify" 
                                            title="<?php esc_attr_e('Send Manual Email', 'stock-notifier-pro-for-woocommerce'); ?>" 
                                            data-subscriber-id="<?php echo esc_attr($subscriber->id); ?>"
                                            data-nonce="<?php echo esc_attr($manual_email_nonce); // ADDED THIS LINE ?>">
                                        <span class="dashicons dashicons-email-alt"></span>
                                    </button>
                                    <?php
                                    $delete_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action' => 'snpfwc_delete_subscriber',
                                                'id'     => $subscriber->id,
                                            ),
                                            admin_url('admin-post.php')
                                        ),
                                        'snpfwc_delete_subscriber_' . $subscriber->id
                                    );
                                    ?>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button button-danger snpfwc-delete-subscriber" title="<?php esc_attr_e('Delete Subscriber', 'stock-notifier-pro-for-woocommerce'); ?>"><span class="dashicons dashicons-trash"></span></a>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr><td colspan="<?php echo $is_pro ? esc_attr(8) : esc_attr(5); ?>"><?php esc_html_e( 'No subscribers found.', 'stock-notifier-pro-for-woocommerce' ); ?></td></tr> <?php
                }
                ?>
            </tbody>
        </table>
    </form>
</div>
<style>.snpfwc-pro-tab{color: #2a9b2a; font-weight: bold;}</style>