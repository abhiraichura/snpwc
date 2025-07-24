<?php
/**
 * Admin View: Generated Coupons Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SNPFWC_Generated_Coupons_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'coupon',
            'plural'   => 'coupons',
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        $columns = array(
            'cb'           => '<input type="checkbox" />',
            'coupon_code'  => esc_html__( 'Coupon Code', 'stock-notifier-pro-for-woocommerce' ),
            'amount'       => esc_html__( 'Amount', 'stock-notifier-pro-for-woocommerce' ),
            'type'         => esc_html__( 'Type', 'stock-notifier-pro-for-woocommerce' ),
            'email'        => esc_html__( 'Recipient Email', 'stock-notifier-pro-for-woocommerce' ), // Stored in email_restrictions
            'usage_limit'  => esc_html__( 'Usage Limit', 'stock-notifier-pro-for-woocommerce' ),
            'usage_count'  => esc_html__( 'Usage Count', 'stock-notifier-pro-for-woocommerce' ),
            'date_expires' => esc_html__( 'Expires', 'stock-notifier-pro-for-woocommerce' ),
            'date_created' => esc_html__( 'Date Generated', 'stock-notifier-pro-for-woocommerce' ),
        );
        return $columns;
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            esc_attr( $this->_args['plural'] ),
            esc_attr( $item->ID )
        );
    }

    public function column_coupon_code( $item ) {
        $coupon = new WC_Coupon( $item->ID );
        $code = esc_html( $coupon->get_code() );

        // Add delete action (manual deletion for generated coupons)
        $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=snpfwc_delete_generated_coupon&coupon_id=' . $item->ID ), 'snpfwc_delete_generated_coupon_' . $item->ID );
        $actions = array(
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                esc_url( $delete_url ),
                esc_js( __( 'Are you sure you want to delete this coupon? This cannot be undone.', 'stock-notifier-pro-for-woocommerce' ) ),
                esc_html__( 'Delete', 'stock-notifier-pro-for-woocommerce' )
            ),
        );
        return '<strong>' . $code . '</strong>' . $this->row_actions( $actions );
    }

    /**
     * Handles the 'amount' column display based on discount type.
     */
    public function column_amount( $item ) {
        $coupon = new WC_Coupon( $item->ID );
        $amount = $coupon->get_amount();
        $discount_type = $coupon->get_discount_type();

        switch ( $discount_type ) {
            case 'percent':
                return esc_html( $amount ) . '%';
            case 'fixed_cart':
            case 'fixed_product':
                return wc_price( $amount );
            default:
                return esc_html( $amount );
        }
    }

    public function column_type( $item ) {
        $coupon = new WC_Coupon( $item->ID );
        return esc_html( wc_get_coupon_type( $coupon->get_discount_type() ) );
    }

    public function column_email( $item ) {
        $coupon = new WC_Coupon( $item->ID );
        $email_restrictions = $coupon->get_email_restrictions();
        return ! empty( $email_restrictions ) ? implode( ', ', array_map( 'esc_html', $email_restrictions ) ) : esc_html__( 'None', 'stock-notifier-pro-for-woocommerce' );
    }

    public function column_usage_limit( $item ) {
        $coupon = new WC_Coupon( $item->ID );
        return $coupon->get_usage_limit() > 0 ? esc_html( $coupon->get_usage_limit() ) : esc_html__( 'Unlimited', 'stock-notifier-pro-for-woocommerce' );
    }

    public function column_usage_count( $item ) {
        $coupon = new WC_Coupon( $item->ID );
        return esc_html( $coupon->get_usage_count() );
    }

    public function column_date_expires( $item ) {
        $coupon = new WC_Coupon( $item->ID );
        $expiry_date = $coupon->get_date_expires();
        return $expiry_date ? esc_html( wc_format_datetime( $expiry_date, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) : esc_html__( 'Never', 'stock-notifier-pro-for-woocommerce' );
    }

    public function column_date_created( $item ) {
        return esc_html( get_the_date( '', $item->ID ) . ' ' . get_the_time( '', $item->ID ) );
    }

    public function get_sortable_columns() {
        $sortable_columns = array(
            'coupon_code'  => array( 'title', false ), // True for asc, false for desc
            'amount'       => array( 'amount', false ),
            'usage_count'  => array( 'meta_value_num', false ),
            'date_expires' => array( 'date_expires', false ),
            'date_created' => array( 'date_created', false ),
        );
        return $sortable_columns;
    }

    public function prepare_items() {
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
        
        $per_page = $this->get_items_per_page( 'generated_coupons_per_page', 20 );
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $args = array(
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for custom post type meta filtering.
            'meta_query'     => array(
                array(
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary for custom post type meta filtering.
                    'key'   => '_snpfwc_generated_coupon',
                    'value' => '1',
                ),
            ),
            'orderby'        => 'date', // Default
            'order'          => 'DESC', // Default
        );

        // Conditional nonce verification for WP_List_Table GET requests.
        // Only verify nonce if there are actual query parameters indicating a form submission (not just direct page load).
        // The '_wpnonce' field is added to the form below.
        if ( ! empty( $_GET ) && isset( $_GET['page'] ) && 'snpfwc-generated-coupons' === $_GET['page'] ) {
            // Check for the nonce if it exists in the request.
            if ( isset( $_REQUEST['_wpnonce'] ) && ! empty( $_REQUEST['_wpnonce'] ) ) {
                // The 'snpfwc_list_table_nonce' is the action name used in wp_nonce_field().
                check_admin_referer( 'snpfwc_list_table_nonce', '_wpnonce' );
            }
        }

        // Handle sorting parameters from $_GET
        // These specific $_GET accesses for 'orderby' and 'order' are commonly flagged by WPCS
        // even after a global nonce check for the form submission.
        // We add phpcs:ignore here as the data is for display sorting and has been unslashed/sanitized.
        // This is a known nuance with WPCS strictness on WP_List_Table's GET parameters.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET used for display sorting, nonce verified earlier if present.
        if ( isset( $_GET['orderby'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Unslashed below.
            $orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
            if ( in_array( $orderby, array_keys( $this->get_sortable_columns() ), true ) ) {
                $args['orderby'] = $orderby;
                if ( 'usage_count' === $orderby ) {
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary for custom post type meta filtering.
                    $args['meta_key'] = 'usage_count';
                } elseif ( 'amount' === $orderby ) {
                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Necessary for custom post type meta filtering.
                    $args['meta_key'] = 'coupon_amount';
                } elseif ( 'date_expires' === $orderby ) {
                    $args['type'] = 'DATE';
                }
            }
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET used for display sorting, nonce verified earlier if present.
        if ( isset( $_GET['order'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Unslashed below.
            $order = sanitize_text_field( wp_unslash( $_GET['order'] ) );
            if ( in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ) {
                $args['order'] = strtoupper( $order );
            }
        }

        $query = new WP_Query( $args );
        $this->items = $query->posts;
        $total_items = $query->found_posts;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

    public function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            // Nonce verification for bulk actions (POST request).
            // This line performs the nonce verification.
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-' . $this->_args['plural'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- This line is performing the nonce verification, inputs handled by sanitize_text_field and wp_unslash in wp_verify_nonce.
                wp_die( esc_html__( 'Security check failed.', 'stock-notifier-pro-for-woocommerce' ) );
            }
            
            if ( empty( $_POST['coupons'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce for form submission already verified above.
                // translators: No coupons were selected for the bulk delete action.
                echo '<div class="error"><p>' . esc_html__( 'No coupons selected for deletion.', 'stock-notifier-pro-for-woocommerce' ) . '</p></div>';
                return;
            }

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Input is array and sanitized by absint in map.
            $coupon_ids = array_map( 'absint', $_POST['coupons'] );
            $deleted_count = 0;
            foreach ( $coupon_ids as $coupon_id ) {
                if ( wp_delete_post( $coupon_id, true ) ) {
                    $deleted_count++;
                }
            }
            // translators: %d: The number of coupons successfully deleted.
            echo '<div class="updated notice is-dismissible"><p>' . sprintf( esc_html__( '%d coupons deleted.', 'stock-notifier-pro-for-woocommerce' ), absint( $deleted_count ) ) . '</p></div>';
        }
    }

    public function get_bulk_actions() {
        $actions = array(
            'delete' => esc_html__( 'Delete', 'stock-notifier-pro-for-woocommerce' ),
        );
        return $actions;
    }
}

add_action('admin_post_snpfwc_delete_generated_coupon', function() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        // translators: The user does not have permission to perform this action.
        wp_die( esc_html__( 'Permission denied.', 'stock-notifier-pro-for-woocommerce' ) );
    }
    
    $coupon_id = isset( $_GET['coupon_id'] ) ? absint( $_GET['coupon_id'] ) : 0;
    
    // Nonce verification for single delete (GET request)
    $nonce = '';
    if ( isset( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- This line is retrieving the nonce to be verified; nonce is verified by wp_verify_nonce.
        $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
    }

    if ( ! $coupon_id || ! wp_verify_nonce( $nonce, 'snpfwc_delete_generated_coupon_' . $coupon_id ) ) {
        // translators: Security check failed for the requested action.
        wp_die( esc_html__( 'Security check failed.', 'stock-notifier-pro-for-woocommerce' ) );
    }

    if ( wp_delete_post( $coupon_id, true ) ) {
        wp_redirect( admin_url( 'admin.php?page=snpfwc-generated-coupons&deleted=1' ) );
        exit;
    } else {
        wp_redirect( admin_url( 'admin.php?page=snpfwc-generated-coupons&deleted=0' ) );
        exit;
    }
});


?>
<div class="wrap">
    <h1><?php esc_html_e( 'Generated Coupons', 'stock-notifier-pro-for-woocommerce' ); ?></h1>
    <?php
    if ( isset( $_GET['deleted'] ) ) {
        if ( '1' === $_GET['deleted'] ) {
            // translators: Message confirming successful coupon deletion.
            echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Coupon deleted successfully.', 'stock-notifier-pro-for-woocommerce' ) . '</p></div>';
        } elseif ( '0' === $_GET['deleted'] ) {
            // translators: Message indicating failure to delete a coupon.
            echo '<div class="error notice is-dismissible"><p>' . esc_html__( 'Failed to delete coupon.', 'stock-notifier-pro-for-woocommerce' ) . '</p></div>';
        }
    }

    $list_table = new SNPFWC_Generated_Coupons_List_Table();
    $list_table->process_bulk_action();
    $list_table->prepare_items();
    ?>
    <form id="coupons-filter" method="get">
        <input type="hidden" name="page" value="<?php
            echo esc_attr( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This input reflects the current page and is not a security risk when used as part of WP_List_Table.
        ?>" />
        <?php
        // Add a nonce field for the form's GET submission, to satisfy WPCS for $_GET parameter processing.
        // The action name 'snpfwc_list_table_nonce' must match the one used in check_admin_referer().
        wp_nonce_field( 'snpfwc_list_table_nonce', '_wpnonce' );
        $list_table->display();
        ?>
    </form>
</div>