<?php
/**
 * Plugin Name: WooCommerce Order Dispatched
 * Plugin URI: https://github.com/drizzlyowl/woocommerce-order-dispatched
 * Description: Adds a custom Email option into WooCommerce for marking Orders as dispatched as well as allowing a Shop Manager to manually set an Order as Dispatched
 * Author: Ash Davies
 * Author URI: http://drizzlyowl.co.uk/
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly



/**
 * Add custom order statuses
 */
function wd_wc_register_new_order_statuses() {

    register_post_status( 'wc-dispatched', array(
        'label'                     => 'Order Dispatched',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Dispatched <span class="count">(%s)</span>', 'Order Dispatched <span class="count">(%s)</span>', 'woocommerce' )
    ) );

    // repeat register_post_status() for each new status
}
add_action( 'init', 'wd_wc_register_new_order_statuses' );



// Add new statuses to list of WC Order statuses
function wd_wc_new_order_statuses( $order_statuses ) {

    /**
     * WooCommerce Defaults
     Array
     (
         [wc-pending] => Pending Payment
         [wc-processing] => Processing
         [wc-on-hold] => On Hold
         [wc-completed] => Completed
         [wc-cancelled] => Cancelled
         [wc-refunded] => Refunded
         [wc-failed] => Failed
     )
    */

     foreach ( $order_statuses as $key => $status ) {

        $new_order_statuses[$key] = $status;

        // Use the defaults above to determine where you want to place your new
        // order status
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-dispatched'] = 'Dispatched';
        }
    }


    return $new_order_statuses;

};

add_filter( 'wc_order_statuses', 'wd_wc_new_order_statuses' );

/**
 * Register "woocommerce_order_status_processing_to_delivered" as an email trigger
 */
add_filter( 'woocommerce_email_actions', 'wd_additional_email_action' );
function wd_additional_email_action( $actions ){
    $actions[] = "woocommerce_order_status_processing_to_dispatched";
    return $actions;
}



/**
 * Add a custom option in the Order Actions metabox
 */
function wd_wc_add_order_meta_box_actions($actions) {
   $actions['wc-dispatched'] = 'Mark as dispatched';

   return $actions;
}

add_action( 'woocommerce_order_actions', 'wd_wc_add_order_meta_box_actions' );



/**
 * Force order status to change to 'Dispatched' and then trigger the email
 */
function wd_change_order_to_dispatched_status( $order ) {
    $new_status = "wc-dispatched";
    $order->update_status( $new_status );
    $sendback = add_query_arg([
        'action' => 'edit',
        'wc-dispatched' => true,
        'post' => $order->id
    ], '' );

    wp_redirect( $sendback );
    exit;
}

add_action( 'woocommerce_order_action_wc-dispatched', 'wd_change_order_to_dispatched_status', 10, 1 );



/**
 * Notification on order processing to dispatched status change
 */
function wd_dispatched_notification( $order_id ) {

    $order = wc_get_order( $order_id );

    // load the mailer class
    $mailer = WC()->mailer();
    $recipient = $order->get_user()->user_email;

    $subject = 'Your order has been dispatched';
    $content = wd_dispatched_email_content( $order, $subject, $mailer );
    $headers = "Content-Type: text/html\r\n";

    $mailer->send( $recipient, $subject, $content, $headers );

}

add_action( 'woocommerce_order_status_processing_to_dispatched', 'wd_dispatched_notification', 10, 1 );



/**
 * Build the HTML email
 */
function wd_dispatched_email_content( $order, $subject = false, $mailer ) {

    $template = 'emails/customer-dispatched-order.php';

    return wc_get_template_html( $template, array(
        'order'         => $order,
        'email_heading' => $subject,
        'sent_to_admin' => true,
        'plain_text'    => false,
        'email'         => $mailer
    ) );
}



/**
 * Add custom icon into admin area
 */
add_action( 'wp_print_scripts', 'skyverge_add_custom_order_status_icon' );
function skyverge_add_custom_order_status_icon() {

    if( ! is_admin() ) {
        return;
    }

    ?> <style>
        /* Add custom status order icons */
        .column-order_status mark.dispatched {
            content: url(<?php echo plugin_dir_url('woocommerce-dispatched/icon.png') . 'icon.png'; ?>);
        }

        /* Repeat for each different icon; tie to the correct status */

    </style> <?php
}
