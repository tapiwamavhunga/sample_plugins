<?php
/**
 * Email Customizer - Helper functions
 *
 * Used globally as tools across the plugin.
 *
 * @since 2.01
 */

// The following methods were only introduced in 3.0.

function cxsemls_order_get_id( $order ) {
	return method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id ;
}
function cxsemls_order_get_date_created( $order ) {
	return method_exists( $order, 'get_date_created' ) ? $order->get_date_created() : $order->date;
}
function cxsemls_order_get_billing_first_name( $order ) {
	return method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
}
function cxsemls_order_get_billing_last_name( $order ) {
	return method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
}
function cxsemls_order_get_billing_email( $order ) {
	return method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
}

?>