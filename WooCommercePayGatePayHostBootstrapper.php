<?php
/*
 * Plugin Name: WooCommerce PayGate PayHost
 * Plugin URI: https://www.refresh.co.za/
 * Description: Make payments on your site using PayGate's PayHost
 * Author: Refresh Creative Media
 * Author URI: https://www.refresh.co.za/
 * Version: 1.0
 * Text Domain: woocommerce-paygate-payhost
 * Domain Path: /languages
 *
 * Copyright (c) 2016 Refresh Creative Media
 */

	if ( ! defined ( 'ABSPATH' ) )
		exit;
	
	define ( 'WC_PAYGATE_PLUGIN_URL', untrailingslashit ( 
										plugins_url ( 
											basename ( plugin_dir_path ( __FILE__ ) ), 
											basename ( __FILE__ ) 
										) 
									) );
									
	define ( 'WC_PAYGATE_PLUGIN_PATH', plugin_dir_path ( __FILE__ ) );
	
	# set the include path
	ini_set( 'include_path', WC_PAYGATE_PLUGIN_PATH . '/' );
	
	add_action( 'plugins_loaded', function() {
		
		add_filter( 'woocommerce_payment_gateways', function( $methods ) {
			if( ! class_exists( 'WooCommercePayGatePayHost' ) )
				include 'gateway/WooCommercePayGatePayHost.php';
			
			$methods[] = 'WooCommercePayGatePayHost';
			
			return $methods;
		});
	});
	
	
