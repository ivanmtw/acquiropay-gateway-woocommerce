<?php
/**
 * @wordpress-plugin
 * Plugin Name:				Gateway for AcquiroPay in WooCommerce
 * Plugin URI:				https://github.com/ivanmtw/acquiropay_gateway_woocommerce
 * Description:				Enable to accept payments with credit cards through AcquiroPay.com in your WooCommerce store
 * Author:					Ivan Matveev
 * Author URI:				https://github.com/ivanmtw/
 * Version:					0.1.1
 * WC requires at least:	3.0.0
 * WC tested up to:			4.0.0
 * Text Domain:				acquiropay-gateway-woocommerce
 * Domain Path:				/i18n/languages
 *
 * Copyright:				(c) 2020-2020 Ivan Matveev (ivanmtw@gmail.com) and WooCommerce
 *
 * License:					GNU General Public License v3.0
 * License URI:				http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package					AcquiroPay-Gateway-WooCommerce
 * @author					Ivan Matveev	ivanmtw@gmail.com
 * @category				Admin
 * @copyright				Copyright (c) 2020 Ivan Matveev (ivanmtw@gmail.com) and WooCommerce
 * @version					0.0.2
 * @license					http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * Gateway for online credit card payments with AcquiroPay.com and iPSP.com services.
 */

/**
 * Exit if accessed directly.
 */
defined( 'ABSPATH' ) or exit;

define( 'AGW_CURRENT_VERSION', '0.1.1' );
define( 'AGW_GATEWAY_ID', 'gateway_acquiropay' );
define( 'AGW_GATEWAY_NAME', 'acquiropay-gateway-woocommerce' );
define( 'AGW_GATEWAY_TITLE', 'Gateway for AcquiroPay in WooCommerce' );

include_once( __DIR__ . '/includes/functions/functions.php' );

add_action( 'plugins_loaded', 'wc_gateway_acquiropay_init' );

function wc_gateway_acquiropay_init() {
	include_once( __DIR__ . '/includes/classes/WC_Gateway_AcquiroPay.php' );
}
