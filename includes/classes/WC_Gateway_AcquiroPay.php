<?php
/**
 * Gateway for AcquiroPay in WooCommerce
 *
 * @class 		WC_Gateway_AcquiroPay
 * @extends		WC_Payment_Gateway
 * @version		0.1.1
 * @package		WooCommerce/Classes/Payment
 * @author 		Ivan Matveev
 */

class WC_Gateway_AcquiroPay extends WC_Payment_Gateway {

	public $id;
	public $icon;
	public $has_fields;
	public $method_title;
	public $method_description; 
	public $enabled;
	public $title;
	public $description;
	public $instructions;
	public $pay_button_title;
	public $terms_page_prefix;
	public $terms_page_id;
	public $secret_word;
	public $merchant_id;
	public $product_id;
	public $pay_url;
	public $check_pay_status_url;
	public $product_order_purpose;
	public $hiding_by_cookie_enabled;
	public $hiding_cookie_rule;
	public $enable_debug_log;
	public $test_total_price_value;

	public function __construct() {

		// Init localization
		add_action( 'init', array( $this, 'real_load_plugin_textdomain' ) );

		// Init Required Methods
		$this->id                 		= AGW_GATEWAY_ID;
		$this->icon               		= apply_filters( 'woocommerce_gateway_icon', '' );
		$this->has_fields         		= false;
		$this->method_title       		= __( 'AcquiroPay', AGW_GATEWAY_NAME );
		$this->method_description 		= __( 'Enable to accept AcquiroPay payments with credit cards.', AGW_GATEWAY_NAME );
		$this->plugin_name				= __( 'AcquiroPay Gateway for WooCommerce', AGW_GATEWAY_NAME );
	
		// Only support base product payments (exclude subscriptions, refunds, saved payment methods)
		$this->supports = array(
			'products'
		);

		// Init Gateway settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user variables
		$this->enabled					= $this->get_option( 'enabled' );
		$this->title					= $this->get_option( 'title' );
		$this->description				= $this->get_option( 'description' );
		$this->instructions				= $this->get_option( 'instructions' );
		$this->pay_button_title			= $this->get_option( 'pay_button_title' );
		$this->terms_page_prefix		= $this->get_option( 'terms_page_prefix' );
		$this->terms_page_id			= $this->get_option( 'terms_page_id' );
		$this->product_order_purpose	= $this->get_option( 'product_order_purpose' );
		$this->secret_word				= $this->get_option( 'secret_word' );
		$this->merchant_id				= $this->get_option( 'merchant_id' );
		$this->product_id				= $this->get_option( 'product_id' );
		$this->pay_url					= $this->get_option( 'pay_url' );
		$this->check_pay_status_url		= $this->get_option( 'check_pay_status_url' );
		$this->hiding_by_cookie_enabled	= $this->get_option( 'hiding_by_cookie_enabled' );
		$this->hiding_cookie_rule		= $this->get_option( 'hiding_cookie_rule' );
		$this->enable_debug_log			= $this->get_option( 'enable_debug_log' );
		$this->test_total_price_value	= $this->get_option( 'test_total_price_value' );

		// Add a save hook for settings
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, 
				array( $this, 'process_admin_options' ) );
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', 
				array( $this, 'process_admin_options' ) );
		}

		// Add receipt handler to generate acquiring form
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Register WC API callback URL: <HOME_URL>/wc-api/gateway_acquiropay
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'webhook' ) );

		// Add filter to reset our payment gateway if hiding cookie rule is set in plugin settings
		add_filter( 'woocommerce_available_payment_gateways', 
			array( $this, 'acquiropay_gateway_check_enabled' ), 10, 1 );

		// Check requirements and show messages in admin area
//		add_action( 'admin_notices', array( $this, 'show_admin_messages') );

		$this->log( 'Plugin initialized' );
	}

	public function real_load_plugin_textdomain() {

		if( !load_plugin_textdomain( 
			AGW_GATEWAY_NAME, false, dirname( plugin_basename( __FILE__ ) ) . '/../../i18n/languages' ) ) {
			
			$this->log( 'Localization file not found' );
		}
	}

	public function show_admin_messages() {

		$this->log( 'Show admin messages' );

	    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	    $plugins_required = array(
			array(
				'message'	=> sprintf( '<strong>%s</strong> requires you to install WooCommerce plugin. You can find it in <a href="%s">Wordpress Marketplace</a>.', $this->plugin_name, '/wp-admin/plugin-install.php?s=woocommerce&tab=search&type=term' ),
				'path'		=> 'woocommerce/woocommerce.php',
				'level'		=> 'notice-error',
			),
	    );

	    $features_required = array(
			array(
				'message'	=> sprintf( '<strong>%s</strong> requires you to set up SSL sertificate to use HTTPS connections.', $this->plugin_name ), 
				'enabled'	=> !is_ssl(), 
				'level'		=> 'notice-warning',
			),
	    );

	    foreach( $plugins_required as $plugin ) {
	        // Check if plugin exists
	        if( !is_plugin_active( $plugin['path'] ) )
	        {
				printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', $plugin['level'], $plugin['message'] );
	        }
	    }

	    foreach( $features_required as $feature ) {
	        // Check if feature enabled
	        if( !$feature['enabled'] )
	        {
//				printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', $plugin['level'], $feature['message'] );
	        }
	    }

		echo '<div class="notice notice-warning is-dismissible"><p>TEST</p></div>';

	}

	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		// Get all pages to select Public offer agreement pages
		$term_posts = array( '' => __( 'Select Public offer agreement Page', AGW_GATEWAY_NAME ));
		$args = array(
			'post_type' => 'page',
			'post_status'  => 'publish',
			'orderby' => 'title', 
			'order' => 'ASC',
			'nopaging' => true
		);
		$query = new WP_Query;
		$posts = $query->query( $args );
		foreach( $posts as $post ) {
			$term_posts[$post->ID] = $post->post_title;
		}

		$this->form_fields = array(

			'enabled' => array(
				'title'			=> __( 'Enable/Disable', AGW_GATEWAY_NAME ),
				'label'			=> __( 'Enable payments via AcquiroPay.com', AGW_GATEWAY_NAME ),
				'description'	=> '',
				'type'			=> 'checkbox',
				'default'		=> 'no'
			),

			'title' => array(
				'title'			=> __( 'Title', AGW_GATEWAY_NAME ),
				'description'	=> __( 'This controls the title for the payment method the customer sees on the Checkout page.', AGW_GATEWAY_NAME ),
				'default'		=> __( 'Credit Card payment', AGW_GATEWAY_NAME ),
				'type'			=> 'text',
			),

			'description' => array(
				'title'			=> __( 'Description', AGW_GATEWAY_NAME ),
				'description'	=> __( 'Payment method description that the customer will see on the Checkout page.', AGW_GATEWAY_NAME ),
				'default'		=> __( 'Pay by credit card via secure service AcquiroPay.com', AGW_GATEWAY_NAME ),
				'type'			=> 'textarea',
			),

			'instructions' => array(
				'title'			=> __( 'Instructions', AGW_GATEWAY_NAME ),
				'description'	=> __( 'Instructions that will be added to the "Thank you" page and emails.', AGW_GATEWAY_NAME ),
				'default'		=> __( 'Click "Continue" to go to the payment page on AcquiroPay.com', AGW_GATEWAY_NAME ),
				'type'			=> 'textarea',
			),

			'pay_button_title' => array(
				'title'			=> __( 'Pay Button title', AGW_GATEWAY_NAME ),
				'description'	=> __( 'Text on the button on the Checkout page to go to the external Payment page.', AGW_GATEWAY_NAME ),
				'default'		=> __( 'Pay by card', AGW_GATEWAY_NAME ),
				'type'			=> 'text',
			),

			'terms_page_prefix' => array(
				'title'			=> __( 'Text before Public offer agreement link', AGW_GATEWAY_NAME ),
				'description'	=> __( 'This text with a link to the Public offer agreement placed next to Pay Button on the Checkout page.', AGW_GATEWAY_NAME ),
				'default'		=> __( 'I have read and accept the conditions of the', AGW_GATEWAY_NAME ),
				'type'			=> 'text',
			),

			'terms_page_id' => array(
				'title'			=> __( 'Public offer agreement', AGW_GATEWAY_NAME ),
				'description'	=> __( 'Public offer to conclude with the Seller a contract for the sale of goods remotely. The customer sees a link to this page on the Checkout page.', AGW_GATEWAY_NAME ),
				'default'		=> false,
				'type'			=> 'select',
				'options'		=> $term_posts
			),

			'product_order_purpose' => array(
				'title'			=> __( 'Order purpose', AGW_GATEWAY_NAME ),
				'description'	=> __( 'Purpose of a payment in AcquiroPay form. Insert \'%s\' to replace by Order ID.', AGW_GATEWAY_NAME ),
				'default'		=> __( 'Payment for an Order: %s', AGW_GATEWAY_NAME ),
				'type'			=> 'text',
			),

			'integration_settings_sectionstart' => array(
				'title'			=> __( 'Integration Settings', AGW_GATEWAY_NAME ),
				'description'	=> __( 'You need to register on <a href="https://acquiropay.com/?from=acquiropay-gateway-woocommerce" target="_blank">AcquiroPay.com</a> and get these parameters from your AcquiroPay account.', AGW_GATEWAY_NAME ),
				'type'			=> 'title',
			),

			'secret_word' => array(
				'title'			=> __( 'Secret Word', AGW_GATEWAY_NAME ),
				'description'	=> '',
				'default'		=> '',
				'type'			=> 'text',
			),

			'merchant_id' => array(
				'title'			=> __( 'Merchant ID', AGW_GATEWAY_NAME ),
				'description'	=> '',
				'default'		=> '',
				'type'			=> 'text',
			),

			'product_id' => array(
				'title'			=> __( 'Product ID', AGW_GATEWAY_NAME ),
				'description'	=> '',
				'default'		=> '',
				'type'			=> 'text',
			),

			'pay_url' => array(
				'title'			=> __( 'Processing HTTP URL', AGW_GATEWAY_NAME ),
				'description'	=> __( 'AcquiroPay payment processing API URL.', AGW_GATEWAY_NAME ),
				'default'		=> 'https://secure.ipsp.com/',
				'type'			=> 'text',
			),

			'check_pay_status_url' => array(
				'title'			=> __( 'Check Pay HTTP URL', AGW_GATEWAY_NAME ),
				'description'	=> __( 'AcquiroPay payment checking API URL.', AGW_GATEWAY_NAME ),
				'default'		=> 'https://gateway.ipsp.com/',
				'type'			=> 'text',
			),

			'technical_settings_sectionstart' => array(
				'title'			=> __( 'Technical Settings', AGW_GATEWAY_NAME ),
				'description'	=> '',
				'type'			=> 'title',
			),

			'hiding_by_cookie_enabled' => array(
				'title'			=> __( 'Hide Payment option', AGW_GATEWAY_NAME ),
				'label'			=> __( 'Hide AcquiroPay Card Payment Option for all customers. This will be useful for safe testing purposes.', AGW_GATEWAY_NAME ),
				'description'	=> '',
				'type'			=> 'checkbox',
				'default'		=> 'yes'
			),

			'hiding_cookie_rule' => array(
				'title'			=> __( 'Show Payment Option if Cookie exists', AGW_GATEWAY_NAME ),
				'description'	=> __( 'Only customers who have added this non-empty cookie to their browser will see AcquiroPay Payment Option. You can add this cookie in your browser inspector by pressing F12 in Chrome for example.', AGW_GATEWAY_NAME ),
				'default'		=> $this->id . '_enabled',
				'type'			=> 'text',
			),

			'enable_debug_log' => array(
				'title'			=> __( 'Enable debug log', AGW_GATEWAY_NAME ),
				'label'			=> __( 'Send debug info into system debug log file', AGW_GATEWAY_NAME ),
				'description'	=> 'Define WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY in wp-config.php to see debug info from plugin.',
				'type'			=> 'checkbox',
				'default'		=> 'yes'
			),

			'test_total_price_value' => array(
				'title'			=> __( 'Order Total Test Value', AGW_GATEWAY_NAME ),
				'description'	=> __( 'You can set any non-zero price value for all orders for testing purposes. If field is empty, the real price value will be used.', AGW_GATEWAY_NAME ),
				'default'		=> '',
				'type'			=> 'text',
			),
		);

		$this->log( 'Form Fields initialized' );
	}

	/**
	 * There are no payment fields for AcquiroPay but we want to show the description if set.
	 */
	public function payment_fields() {

		$this->log( 'Show Payment fields on the checkout page' );

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : "";

		$this->log( 'Received GET data from [' . $referer. ']:' );
		$this->log( print_r($_GET, true ) );

		$this->log( 'Received POST data from [' . $referer. ']:' );
		$this->log( print_r($_POST, true ) );

		if ( $this->description ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}
	}

	/**
	 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
	 */
 	public function payment_scripts() {

 	}

	/**
	 * Fields validation
	 */
	public function validate_fields() {
	}

	/**
	 * Processing the payments here
	 */
	public function process_payment( $order_id ) {

		$this->log( 'Process Payment' );

		$order = wc_get_order( $order_id );

		if( $order->get_status() == 'processing' ) { 

			// Always redirect to Thank you page if order is processed
			return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );				
			//return array( 'result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ) );				
		}

		// Default routing to Checkout page
		return array( 'result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ) );				
 	}

	/**
	 * Webhook to get acquirer callbacks
	 */
	public function webhook() {

		$this->log( 'Fire webhook' );

		/* 
		 * Received redirect from acquiring service with succesful order status
		 */
		if( $_SERVER['REQUEST_METHOD'] == 'POST' and isset( $_POST['payment_id'] ) ) {

			$this->log( 'Received callback from acquiring service with order processing status' );
			$this->log( print_r($_POST, true ) );

			// Get payment UUID
			$paymentcode = isset( $_POST['payment_id'] ) ? $_POST['payment_id'] : null;

			// Get our Order ID returned through acquiring
			$order_id = isset ( $_POST['cf'] ) ? $_POST['cf'] : null;

			// Get Order status (ОК, КО, CANCEL, CHARGEBACK)
			$status = isset ( $_POST['status'] ) ? $_POST['status'] : null;

			// Get Order signature to verify payment validity
			$sign = isset ( $_POST['sign'] ) ? $_POST['sign'] : null;
			$sign_check = md5( $this->merchant_id . $paymentcode . $status . $order_id . $this->secret_word );

			$order = wc_get_order( $order_id );

			// Validate payment data
			if( $sign == $sign_check ) {

				switch( $status ) {
					case 'OK':
						// Payment succesful

						// Check if Order stay in our payment method
						if( $order->get_payment_method() == $this->id ) {

							// Link acquiring payment UUID with our Order
							if( strlen( $paymentcode ) ) {
								update_post_meta( $order_id, '_' . $this->id . '_paymentcode', $paymentcode );
							}

							// Set Order status to Processing
							$order->update_status('processing');

							$this->log( 'Payment processed sucesfully' );
						}
						else {
							// Wrong payment method
							$this->log( 'Wrong payment method' );
						}
					break;
					case 'КО':
					// Do nothing. Keep order status as is
						$this->log( 'Payment not processed' );
					break;
					case 'CANCEL':
						// Do nothing. Keep order status as is
						$this->log( 'Payment cancelled by acquirer' );
					break;
				}
			}
		}

		// Clear http output. 
		echo '0';
		exit;
 	}

	/**
	 * Receipt Page
	 */
	public function receipt_page( $order ) {

		$this->log( 'Render Receipt Page' );

		echo $this->payment_form( $order );
	}

	/**
	 * Form with acquiring params 
	 */
	public function payment_form( $order_id ) {

		$this->log( 'Render Payment Form' );

		if( !$order_id or !$this->product_id or !$this->merchant_id or !$this->secret_word ) {

			$this->log( 'Error: Empty payment parameters' );

			return;
		}

		$this->order = new WC_Order( $order_id );

		$amount = ( $this->test_total_price_value > 0 ) ? $this->test_total_price_value :  $this->order->get_total();

		$language = get_locale();
		switch( $language ) {
			case 'ru_RU':
				$language = 'ru';
			break;
			default:
				$language = 'en';
			break;
		}

		$form_fields = array(
			'product_id'	=> $this->product_id,
			'product_name'	=> sprintf( $this->product_order_purpose, $order_id ),
			'token'			=> md5( 
									$this->merchant_id . 
									$this->product_id . 
									$amount . 
									$order_id . 
									$this->secret_word 
								),
			'amount'		=> $amount,
			'cf'			=> $order_id,
			'email'			=> $this->order->get_billing_email(),
			'phone'			=> $this->order->get_billing_phone(),
			'cb_url'		=> get_home_url() . '/wc-api/' . $this->id, // callback url
			'ok_url'		=> $this->order->get_checkout_order_received_url(), // success return to "/order-received"
			'ko_url'		=> $this->order->get_checkout_payment_url(), // cancel return to "/order-pay"
			'language'		=> $language,
		);

		$result ='';
		$result .= sprintf( '<form name="pay_form" method="POST" action="%s">', $this->pay_url );

		foreach( $form_fields as $key => $val ) {
 			$result .= sprintf( '<input type="hidden" name="%s" value="%s">', $key, $form_fields[$key] );
		}

		// Show link to Terms Page
		if ( $this->terms_page_id ) {

			$result .= sprintf( '<div class="%s__textblock">%s <a href="%s" target="_blank">%s</a></div>', 
				$this->id,
				$this->terms_page_prefix,
				esc_url( get_page_link( $this->terms_page_id ) ), 
				get_the_title( $this->terms_page_id ) );
		}

		$result .= sprintf( '<input type="submit" value="%s">', $this->pay_button_title );
		$result .= '</form>';

		return $result;
	}


	/**
	 * Hide our gateway payment option on the checkout page,
	 * when a non-empty custom cookie is presented in a client browser
	 */
	function acquiropay_gateway_check_enabled( $gateways ) {

		$this->log( 'Check payment option' );

		// Check if current user have debugging cookie
		$hiding_cookie_value = "";
		if( strlen( $this->hiding_cookie_rule ) ) {
			$hiding_cookie_value = isset( $_COOKIE[$this->hiding_cookie_rule] ) ? 
				$_COOKIE[$this->hiding_cookie_rule] : "";
		}

		// Hide payment option for all users without debugging cookie
		if( $this->hiding_by_cookie_enabled == 'yes' ) {
			if( strlen( $hiding_cookie_value ) ) {
			}
			else {					
				$this->log( 'Payment option HIDDEN' );
				unset( $gateways[$this->id] );
			}
		}

		return $gateways;
	}

	/**
	 * Simple logger
	 * 
	 * since 0.0.1
	 * @param array $message: text to error_log
	 * @return array $message: formatted error_log + text
	 */
	private function log( $message ) {

		if( defined( 'WP_DEBUG' ) and defined( 'WP_DEBUG_LOG' ) and $this->enable_debug_log == 'yes' ) {
			error_log( '[' . $this->id . '] ' . $message . ' - ' . esc_url( $_SERVER['REQUEST_URI'] ) );
		}

	}
}
