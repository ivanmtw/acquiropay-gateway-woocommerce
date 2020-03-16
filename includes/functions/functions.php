<?php
/**
 * Basic non-OOP functions
 *
 * @author		Ivan Matveev	ivanmtw@gmail.com
 * @copyright	Copyright (c) 2020 Ivan Matveev (ivanmtw@gmail.com) and WooCommerce
 * @version		0.1.1
 */

/**
 * Exit if accessed directly.
 */
defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Adds plugin page links into Wordpress Plugins Page
 * 
 * since 0.0.1
 * @param array $links: all plugins links
 * @return array $links: all plugins links + our custom links (i.e., 'Settings')
 */
add_filter( sprintf( 'plugin_action_links_%s/%s.php', AGW_GATEWAY_NAME, AGW_GATEWAY_NAME), 'wc_gateway_acquiropay_plugin_links' );
function wc_gateway_acquiropay_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=gateway_acquiropay' ) . '">' . 
			__( 'Settings', 'acquiropay-gateway-woocommerce' ) . '</a>'
	);
	error_log(print_r(array_merge( $plugin_links, $links ), true));
	return array_merge( $plugin_links, $links );
}

/**
 * Adds AcquiroPay to WooCommerce gateways
 * 
 * since 0.0.1
 * @param array $gateways: all gateways
 * @return array $gateways: all gateways + our custom AcquiroPay gateway
 */
add_filter( 'woocommerce_payment_gateways', 'wc_gateway_acquiropay_add_to_gateways' );
function wc_gateway_acquiropay_add_to_gateways( $gateways ) {

	$gateways[] = 'WC_Gateway_AcquiroPay';		

	return $gateways;
}

/**
 * Processing answer from acquiring service after customer payment
 * Capture HTTP POST answer from acquiring service with reserved WP variables calling 404 error
 * 
 * since 0.0.1
 */
add_action( 'template_redirect', 'wc_gateway_acquiropay_receive_acquirer_answer' );
function wc_gateway_acquiropay_receive_acquirer_answer() {

	if( is_404() and $_SERVER['REQUEST_METHOD'] == 'POST' ) {

		/** 
		 * Receiving the redirect from acquiring service with succesful order status
		 */
		if( isset( $_POST['product_id'] ) and !isset( $_POST['error'] ) ) {

			// Get payment UUID
			$paymentcode = isset( $_POST['paymentcode'] ) ? $_POST['paymentcode'] : null;

			// Get our Order ID returned through acquiring
			$order_id = isset ( $_POST['cf'] ) ? $_POST['cf'] : null;

			$order = wc_get_order( $order_id );

			// Check if Order stay in our payment method
			if( $order->get_payment_method() == AGW_GATEWAY_ID ) {

				// Check if Order payment status is in Awaiting payment
				switch( $order->get_status() ) {

					case 'pending':
					case 'on-hold':
					case 'processing': // rewrite

						// Link acquiring payment UUID with our Order
						if( strlen( $paymentcode ) ) {
							update_post_meta( $order_id, '_' . AGW_GATEWAY_ID . '_paymentcode', $paymentcode );
						}

						$order->payment_complete();
						wc_reduce_stock_levels( $order_id );

						// Redirect to Thank you page
						wp_redirect( $order->get_checkout_order_received_url(), $status = 302 );
						exit;

					break;
					case 'completed':

						// Redirect to Thank you page
						wp_redirect( $order->get_checkout_order_received_url(), $status = 302 );
						exit;

					break;
					case 'cancelled':
					case 'refunded':
					case 'failed':						

						// Wrong status. Send user to Checkout page
						wc_add_notice( __( 'Your order was cancelled. Please try checkout again.', 'acquiropay-gateway-woocommerce' ), 'error' );
						wp_redirect( wc_get_checkout_url(), $status = 302 );
						exit;

					break;
				}
			}
			else {

				// Wrong payment method. Send user to Checkout page
				wc_add_notice( __( 'Please try checkout again.', 'acquiropay-gateway-woocommerce' ), 'error' );
				wp_redirect( wc_get_checkout_url(), $status = 302 );
				exit;

			}
		}
		/** 
		 * Received redirect from acquiring service with cancelled order status
		 */
		elseif( isset( $_POST['product_id'] ) and isset( $_POST['error'] ) ) {

			// Get our Order ID returned through acquiring
			$order_id = isset ( $_POST['cf'] ) ? $_POST['cf'] : null;

			$order = wc_get_order( $order_id );

			// Override Order status to Cancelled
			$order->update_status('cancelled');

			// Send user to Checkout page (may be to Cart page?)
			wc_add_notice( __( 'Please try checkout again.', 'acquiropay-gateway-woocommerce' ), 'error' );
			wp_redirect( wc_get_checkout_url(), $status = 302 );
			exit;

		}
	}
}