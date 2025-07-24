<?php
/**
 * Renders the Waitlist Insights dashboard page.
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'snpfwc_subscribers';
$is_pro = defined( 'SNPFWC_PRO_VERSION' );

// --- Start Analytics Calculations ---

// 1. Core KPI Metrics
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, direct query necessary for analytics. Table name is escaped.
$total_notified = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM `" . esc_sql( $table_name ) . "` WHERE status = %s OR status = %s", 'notified', 'converted' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, direct query necessary for analytics. Table name is escaped.
$total_converted = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM `" . esc_sql( $table_name ) . "` WHERE status = %s", 'converted' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, direct query necessary for analytics. Table name is escaped.
$active_subscribers = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM `" . esc_sql( $table_name ) . "` WHERE status = %s", 'subscribed' ) );
$conversion_rate = ( $total_notified > 0 ) ? ( $total_converted / $total_notified ) * 100 : 0;

// 2. Recovered Revenue
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, direct query necessary for analytics. Table name is escaped.
$converted_order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT order_id FROM `" . esc_sql( $table_name ) . "` WHERE status = %s AND order_id IS NOT NULL", 'converted' ) );
$recovered_revenue = 0;
if ( ! empty( $converted_order_ids ) ) {
    foreach ( $converted_order_ids as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $recovered_revenue += $order->get_total();
        }
    }
}

// 3. Top 5 Most Requested Products (Waitlist)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, direct query necessary for analytics. Table name is escaped.
$most_requested_products = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, COUNT(id) AS count FROM `" . esc_sql( $table_name ) . "` WHERE status = %s GROUP BY product_id ORDER BY count DESC LIMIT 5", 'subscribed' ) );
$max_requests = 0;
if ( ! empty( $most_requested_products ) ) {
	$max_requests = max( wp_list_pluck( $most_requested_products, 'count' ) );
}


// 4. Top 5 Converting Products
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, direct query necessary for analytics. Table name is escaped.
$top_converting_products = $wpdb->get_results( $wpdb->prepare("
    SELECT
        product_id,
        COUNT(id) as total_notifications,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as total_conversions
    FROM `" . esc_sql( $table_name ) . "`
    WHERE status IN (%s, %s)
    GROUP BY product_id
    HAVING SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) > 0
    ORDER BY
        (SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) / COUNT(id)) DESC,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) DESC
    LIMIT 5",
    'notified', 'converted'
) );

?>
<div class="wrap snpfwc-admin-wrap">
    <h1><?php esc_html_e( 'Waitlist Insights (Premium)', 'stock-notifier-pro-for-woocommerce' ); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=stock-notifier-pro-for-woocommerce" class="nav-tab"><?php esc_html_e('Subscribers', 'stock-notifier-pro-for-woocommerce'); ?></a>
        <a href="?page=snpfwc-insights" class="nav-tab nav-tab-active"><?php esc_html_e('Waitlist Insights', 'stock-notifier-pro-for-woocommerce'); ?></a>
        <a href="?page=snpfwc-settings" class="nav-tab"><?php esc_html_e('Settings', 'stock-notifier-pro-for-woocommerce'); ?></a>
    </h2>

    <div class="snpfwc-insights-grid">
        <div class="snpfwc-stat-card">
            <h4><?php esc_html_e('Conversion Rate', 'stock-notifier-pro-for-woocommerce'); ?></h4>
            <p class="snpfwc-stat-number"><?php echo esc_html( number_format( $conversion_rate, 2 ) ); ?>%</p>
            <p class="snpfwc-stat-desc">
                <?php
                echo esc_html( sprintf(
                    // translators: %d: The total number of conversions.
                    _n( '%d conversion', '%d conversions', $total_converted, 'stock-notifier-pro-for-woocommerce' ),
                    $total_converted
                ) );
                ?> from <?php
                echo esc_html( sprintf(
                    // translators: %d: The total number of notifications.
                    _n( '%d notification', '%d notifications', $total_notified, 'stock-notifier-pro-for-woocommerce' ),
                    $total_notified
                ) );
                ?>

            </p>
        </div>
        <div class="snpfwc-stat-card">
            <h4><?php esc_html_e('Recovered Revenue', 'stock-notifier-pro-for-woocommerce'); ?></h4>
            <p class="snpfwc-stat-number"><?php echo wp_kses_post( wc_price( $recovered_revenue ) ); ?></p>
            <p class="snpfwc-stat-desc">From <?php echo esc_html( count( $converted_order_ids ) ); ?> orders</p>
        </div>
        <div class="snpfwc-stat-card">
            <h4><?php esc_html_e('Active Subscribers', 'stock-notifier-pro-for-woocommerce'); ?></h4>
            <p class="snpfwc-stat-number"><?php echo esc_html( $active_subscribers ); ?></p>
            <p class="snpfwc-stat-desc"><?php esc_html_e('Currently waiting for a restock', 'stock-notifier-pro-for-woocommerce'); ?></p>
        </div>
    </div>
    
    <div class="snpfwc-insights-grid">
        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('Top 5 Most Requested Products', 'stock-notifier-pro-for-woocommerce'); ?></span></h2>
            <div class="inside">
                <?php if ( ! empty( $most_requested_products ) ) : ?>
                    <div class="snpfwc-chart">
                        <?php foreach ($most_requested_products as $item): 
                            $product = wc_get_product($item->product_id);
                            if (!$product) continue;
                            $percentage = ( $max_requests > 0 ) ? ( $item->count / $max_requests ) * 100 : 0;
                        ?>
                            <div class="snpfwc-chart-row">
                                <div class="snpfwc-chart-label"><a href="<?php echo esc_url(get_edit_post_link($item->product_id)); ?>"><?php echo esc_html($product->get_name()); ?></a></div>
                                <div class="snpfwc-chart-bar-container">
                                    <div class="snpfwc-chart-bar" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                                </div>
                                <div class="snpfwc-chart-value"><?php echo esc_html($item->count); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php esc_html_e('No active subscriptions yet to analyze.', 'stock-notifier-pro-for-woocommerce'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="postbox">
            <h2 class="hndle"><span><?php esc_html_e('Top 5 Converting Products', 'stock-notifier-pro-for-woocommerce'); ?></span></h2>
            <div class="inside">
                   <table class="snpfwc-data-table">
                   <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'stock-notifier-pro-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Conversions', 'stock-notifier-pro-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Conversion Rate', 'stock-notifier-pro-for-woocommerce'); ?></th>
                        </tr>
                   </thead>
                   <tbody>
                        <?php if ( ! empty( $top_converting_products ) ) : ?>
                            <?php foreach ($top_converting_products as $item): 
                                $product = wc_get_product($item->product_id);
                                if (!$product) continue;
                                $rate = ( $item->total_notifications > 0 ) ? ( $item->total_conversions / $item->total_notifications ) * 100 : 0;
                            ?>
                                <tr>
                                    <td><a href="<?php echo esc_url(get_edit_post_link($item->product_id)); ?>"><?php echo esc_html($product->get_name()); ?></a></td>
                                    <td><?php echo esc_html($item->total_conversions); ?> / <?php echo esc_html($item->total_notifications); ?></td>
                                    <td><?php echo esc_html(number_format($rate, 1)); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3"><?php esc_html_e('No converted subscribers yet to analyze.', 'stock-notifier-pro-for-woocommerce'); ?></td>
                            </tr>
                        <?php endif; ?>
                   </tbody>
                   </table>
            </div>
        </div>
    </div>
</div>