<div class="wrap snpfwc-admin-wrap">
    <h1><?php esc_html_e('Stock Notifier Settings (Premium)', 'stock-notifier-pro-for-woocommerce'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=stock-notifier-pro-for-woocommerce" class="nav-tab"><?php esc_html_e('Subscribers', 'stock-notifier-pro-for-woocommerce'); ?></a>
        <a href="?page=snpfwc-insights" class="nav-tab"><?php esc_html_e('Waitlist Insights', 'stock-notifier-pro-for-woocommerce'); ?></a>
        <a href="?page=snpfwc-settings" class="nav-tab nav-tab-active"><?php esc_html_e('Settings', 'stock-notifier-pro-for-woocommerce'); ?></a>
    </h2>

    <form method="post" action="options.php">
        <?php
        settings_fields('snpfwc_settings_group');
        do_settings_sections('snpfwc-settings');
        submit_button();
        ?>
    </form>
</div>
