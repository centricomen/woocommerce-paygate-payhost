<?php


	class WooCommercePayGatePayHost extends WC_Payment_Gateway_CC {
		
		public $testmodeEnabled;
		
		public $paygateId;
		
		public $paygatePassword;

		static $enableLogs;
		
		public function __construct ( ) {
			
			$this -> id                   	= 'paygate_payhost';
			$this -> method_title         	= __( 'PayGate PayHost', 'woocommerce-paygate-payhost' );
			$this -> method_description   	= __( 'Makes Visa / MasterCard credit card payments via PayGate PayHost.<br><i>Find out more about PayHost <a href="https://developer.paygate.co.za/product/3" target="_blank">here</a></i>', 'woocommerce-paygate-payhost' );
			$this -> title					= __( 'Credit Card | PayGate', 'woocommerce-paygate-payhost' );
			$this -> has_fields           	= true;
			
			$this -> supports             	= array (
				'products',
				'refunds',
				'default_credit_card_form',
				'pre-orders',
				'tokenization'
			);
			
			# Load the form fields.
			$this -> init_form_fields ( );	/** @see inherited classes **/
	
			# Load the settings.
			$this -> init_settings ( );		/** @see inherited classes **/
			
			$this -> title                  = $this -> get_option ( 'title' );
			$this -> description            = $this -> get_option ( 'description' );
			$this -> enabled                = $this -> get_option ( 'enabled' );
			self::$enableLogs               = 'yes' === $this -> get_option ( 'logs' );
			$this -> testmodeEnabled        = 'yes' === $this -> get_option ( 'testmode' );
			$this -> paygateId				= ( $this -> testmodeEnabled ) ? '10011072130'	: $this -> get_option ( 'paygate_id' ); // Test-mode id
			$this -> paygatePassword		= ( $this -> testmodeEnabled ) ? 'test' 		: $this -> get_option ( 'paygate_password' ); // Test-mode password
			
			# Save settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this -> id, array ( $this, 'process_admin_options' ) );


			# Adding scripts for the card
			add_action( 'wp_enqueue_scripts', array( &$this, 'paymentCardScripts' ) );
			
			# add function to redirect to PayGate (3D Secure stuff)
			add_action( 'woocommerce_receipt_' . $this -> id, array( &$this, 'receiptPage' ) );
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'checkPaymentResponse' ) );
		}

		/**
		 * Creates a settings panel for the payment gateway in the backend
		 * @see inherited class
		 */
		public function init_form_fields ( ) {
			
			$this -> form_fields = array (
				'enabled' 		  => array (
					'title'       => __( 'Enable/Disable', 'woocommerce' ),
					'label'       => __( 'Enable Payment Gateway', 'woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' 		  => array (
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Credit Card | PayGate', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description' 	  => array (
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Pay with your credit card via PayGate.', 'woocommerce'),
					'desc_tip'    => true,
				),
				'testmode' 		  => array (
					'title'       => __( 'Test mode', 'woocommerce' ),
					'label'       => __( 'Enable Test Mode', 'woocommerce' ),
					'type'        => 'checkbox',
					'description' => __( 'Place the payment gateway in test mode.', 'woocommerce' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'paygate_id' 	  => array (
					'title'       => __( 'PayGate ID (Live mode)', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Enter your PayGate Internet Merchant Account ID.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'paygate_password' => array (
					'title'       => __( 'PayGate Password (Live mode)', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Enter your PayGate Internet Merchant Account password.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
                'logs' => array (
                    'label'       => __( 'Enable Transaction Logging.<br><br><i>Logs can be found here: <code>' . str_replace( '\\', '/', WC_PAYGATE_PLUGIN_PATH ) . 'gateway/logs</code></i>' ),
                    'title'       => __( 'Logging' ),
                    'type'        => 'checkbox',
                    'default'     => 'yes',
                    'desc_tip'    => false,
                )
			);
			
		}

		public function checkPaymentResponse() {
			
			global $woocommerce;
			
			session_start();
			
			if( ! empty( $_REQUEST[ 'CHECKSUM' ] ) && ! empty( $_REQUEST[ 'PAY_REQUEST_ID' ] ) ) {
				$key      = $_REQUEST[ 'order' ];
				$orderId = wc_get_order_id_by_order_key( $key );
				
				if ( $orderId != '' ) {
					$order = wc_get_order( $orderId );
				
					# found the order. now update the status
					$requestId		= $_REQUEST[ 'PAY_REQUEST_ID' ];
					# check for matching checksum
					$sourceChecksum = $this -> paygateId . $requestId . $_REQUEST[ 'TRANSACTION_STATUS' ] . $order -> get_order_number() . $this -> paygatePassword;
					$sourceChecksum	= md5( $sourceChecksum );

                    ob_start();
                    print_r( $_REQUEST );
                    $content = ob_get_contents();
                    ob_end_clean();

                    self::log( $orderId, $content );
                    self::log( $orderId, "-------- Ending payment transaction for order #{$orderId}: -------------\n\n" );

					if( $sourceChecksum == $_REQUEST[ 'CHECKSUM' ] ) {
						if ( $order -> status !== 'processing' || $order -> status !== 'completed' ) {
						
							# check response and update accordingly
							if( $_REQUEST[ 'TRANSACTION_STATUS' ] == 1 ) {
								# success
								$order -> add_order_note ( __( 'PayGate PayHost payment complete', 'woocommerce' ) );
								
								$order -> reduce_order_stock ( ); // Reduce stock from this order
								
								$order -> payment_complete ( );
								
								$woocommerce -> cart -> empty_cart ( );
								
							} else {
								
								$message	= 'Error on payment. [Unknown]';
								$status		= $_REQUEST[ 'TRANSACTION_STATUS' ];
								if( $status == 2 )
									$message		= 'Transaction has been declined by the bank';
								else if ( $status == 0 )
									$message		= 'Transaction cannot be processed';
								
								$order -> update_status( 'failed', 'PayGate: ' . $message . '<br/>' );
							}
							
						}
					} else {
						$order -> update_status('failed', 'Response via Redirect, Security Error: Checksum mismatch. Illegal access detected' . '<br/>');
					}
					
					
				}


				$_SESSION[ 'cc' ] = '';
				unset( $_SESSION[ 'cc' ] );
				
				header( 'Location: ' . $order -> get_checkout_order_received_url() );
				die();
			}
			
			
		} 
		
		public function receiptPage( $orderId ) {
			
			$order = new WC_Order( $orderId );

            # Getting the payload to send
            $payload = $this -> getSoapPayload( $order );

            self::log( $orderId, 'Payload: '. $payload . "\n" );

            # Perform the SOAP call to PayGate
            $soapClient = new SoapClient( 'https://secure.paygate.co.za/PayHost/process.trans?wsdl',
                array ( 'trace' => 1 )
            ); # point to WSDL and set trace value to debug

            try {
                self::log( $orderId, "Send payload to PayGate\n" );

                # Sending request
                $result = $soapClient -> __soapCall( 'SinglePayment', array (
                    new SoapVar( $payload, XSD_ANYXML )
                ) );

                // self::log( $orderId, $content );
                self::log( $orderId, "Result recevied\n" );
                self::log( $orderId, "Result code: 200\n" );
                self::log( $orderId, "Updating order\n" );

                ob_start();
                print_r( $result );
                $log = ob_get_contents();
                ob_end_clean();

                self::log( $orderId, $log );

                if ( $order -> status !== 'processing' || $order -> status !== 'completed' ) {

                    if( $this -> testmodeEnabled ) {
                        $payRequestId   = $result -> WebPaymentResponse -> Redirect -> UrlParams[1] -> value;
                        $checkSum       = $result -> WebPaymentResponse -> Redirect -> UrlParams[3] -> value;
                    } else {

                    }

                    # check the result to see if its a success
                    if( isset( $result -> WebPaymentResponse -> Redirect ) ) {
                        # this card has been approved
                        ?>
                        <form action="https://secure.paygate.co.za/PayHost/process.trans" style="display: none" id="<?php echo $this -> id ?>-req" method="post">
                            <input type="hidden" name="PAY_REQUEST_ID" value="<?php echo $payRequestId ?>" />
                            <input type="hidden" name="PAYGATE_ID" value="<?php echo $this -> paygateId ?>" />
                            <input type="hidden" name="CHECKSUM" value="<?php echo $checkSum ?>" />
                            <input type="hidden" name="REFERENCE" value="<?php echo $order -> id ?>" />
                            <input type="submit" name="submitBtn" value="Proceed to PayGate" />
                            <script type="text/javascript">
                                jQuery( function( $ ) {
                                    $( '#<?php echo $this -> id ?>-req' ).submit();
                                });
                            </script>
                        </form>
                        <?php
                    } else {

                        $order -> update_status( 'failed', 'PayGate: ' . $result -> WebPaymentResponse -> Status -> ResultDescription . '<br/>' );

                        self::log( $orderId, 'PayGate: ' . $result -> WebPaymentResponse -> Status -> ResultDescription . "\n" );

                        header( 'Location: ' . $order -> get_checkout_order_received_url() );
                    }
                }



            } catch ( SoapFault $sf ) {
                # Log error and do not redirect the page. Show error on Checkout instead
                self::log( $orderId, "SoapFault: [ {$sf -> getMessage()} ]\n" );
                self::log( $orderId, "SoapFault: [ {$sf -> getTraceAsString()} ]\n" );
                # self::log( $orderId, "Payload : [ {$payload} ]" );

                # This will / should auto-scroll the page to show the error on top of the checkout form
                throw new Exception( __( 'We are currently experiencing problems trying to connect to PayGate. Sorry for the inconvenience.', 'woocommerce' ) );
                ?>
                <script type="text/javascript" id="overlay-remover">
                    jQuery( function() {

                    });
                </script>
                <?php
            }

			# die(); # end the string
	
		}
		
		/**
		 * This function is called whenever a payment is being made
		 * @see inherited class
		 */
		public function process_payment ( $orderId, $retry = true, $forceCustomer = false ) {
			
			$order = new WC_Order( $orderId );
			
			self::log( $orderId, "-------- Beginning payment transaction for order #{$orderId}: -------------\n" );

			return array(
				'result'   => 'success',
				'redirect' => $order -> get_checkout_payment_url( true )
			);
			
			
		}
		
		/**
		 * This functions displays the credit card form in the front-end
		 * @see inherited class
		 */
		public function payment_fields ( ) {
			
			?>

            <div class="paygate-PayHost-container">
                <!--div class="card-wrapper"></div>-->

                <div class="form-container active">
                    <div class="row">
                        <p class="col-xs-12">
                            <?php

                            if( ! empty( $this -> description ) )
                                echo $this -> description;
                            else echo 'Pay with your credit card using PayGate secure payment gateway.';

                            if( $this -> testmodeEnabled ) {
                                ?>
                                <br><b>TEST MODE ENABLED.</b><br>Credit card details are handled by PayGate.
                                <?php
                            }
                            ?>
                        </p>

                        <br style="clear:both">
                        <div class="col-xs-12 badge-row">
                            <img src="<?php echo WC_PAYGATE_PLUGIN_URL . '/assets/images/visa.svg' ?>" width="32" alt="Visa" />
                            <img src="<?php echo WC_PAYGATE_PLUGIN_URL . '/assets/images/mastercard.svg' ?>" width="32" alt="Mastercard" />
                        </div>
                        <br style="clear:both">

                    </div>
                </div>
            </div>
			
			<?php
		}
		
		public function paymentCardScripts ( ) {
			
			# include stylesheets for the form
			wp_enqueue_style( $this -> id . '-style', WC_PAYGATE_PLUGIN_URL . '/assets/css/paygate-payhost-form.css' );
			
		}


		/**
		 * Validates the credit card data entered on form during checkout
		 * @see inherited class
		 */
		public function validate_fields ( ) {
			return true;
		}
		
		private function getSoapPayload ( $order ) {

            $currency	= $order -> get_currency();
            $amount		= number_format( $order -> order_total, 2, '', '' ); #$order -> order_total * 100;
            $returnUrl	= home_url( '/' ) . '/wc-api/' . get_class( $this ) . '?order=' . $order -> order_key;

		    return "<ns1:SinglePaymentRequest>
                        <ns1:WebPaymentRequest>
                            <!-- Account Details -->
                            <ns1:Account>
                                <ns1:PayGateId>{$this -> paygateId}</ns1:PayGateId>
								<ns1:Password>{$this -> paygatePassword}</ns1:Password>
                            </ns1:Account>
                            
                            <!-- Customer Details -->
                            <ns1:Customer>
                                <ns1:FirstName>{$order -> billing_first_name}</ns1:FirstName>
                                <ns1:LastName>{$order -> billing_last_name}</ns1:LastName>
                                <ns1:Email>{$order -> billing_email}</ns1:Email>
                                <!-- Address Details -->
                                <ns1:Address>
                                    <ns1:Country>ZAF</ns1:Country>
                                </ns1:Address>
                            </ns1:Customer>
                            
                            <!-- Redirect Details -->
                            <ns1:Redirect>
                                <ns1:NotifyUrl>" . $order -> get_checkout_payment_url( true ) . "</ns1:NotifyUrl>
                                <ns1:ReturnUrl>{$returnUrl}</ns1:ReturnUrl>
                            </ns1:Redirect>
                            
                            <!-- Order Details -->
                            <ns1:Order>
                                <ns1:MerchantOrderId>{$order -> id}</ns1:MerchantOrderId>
                                <ns1:Currency>{$currency}</ns1:Currency>
                                <ns1:Amount>{$amount}</ns1:Amount>
                                <ns1:TransactionDate>" . date( 'Y-m-d' ) . "T" . date( 'H:i:s' ) . "</ns1:TransactionDate>
                                <ns1:BillingDetails>
                                    <!-- Customer Details -->
                                    <ns1:Customer>
                                        <ns1:FirstName>{$order -> billing_first_name}</ns1:FirstName>
                                        <ns1:LastName>{$order -> billing_last_name}</ns1:LastName>
                                        <ns1:Email>{$order -> billing_email}</ns1:Email>
                                        
                                        <!-- Address Details -->
                                        <ns1:Address>
                                            <ns1:Country>ZAF</ns1:Country>
                                        </ns1:Address>
                                    </ns1:Customer>
                                    
                                    <!-- Address Details -->
                                    <ns1:Address>
                                        <ns1:Country>ZAF</ns1:Country>
                                    </ns1:Address>
                                    
                                </ns1:BillingDetails>
                                
                                <ns1:Locale>en-us</ns1:Locale>
                            </ns1:Order>
                            <!-- User Fields -->
                        </ns1:WebPaymentRequest>
                    </ns1:SinglePaymentRequest>";
		}
		
		
		public static function log( $orderId, $message ) {

		    if( self::$enableLogs ) {
                date_default_timezone_set( 'Africa/Johannesburg' );
                $logFilename = date( 'Y-F-d' ) . '.log';

                $dateTime	= date( 'Y-F-d' ) . ' at ' . date('h:i A');
                $message	= "[{$orderId}] - {$dateTime} >>> {$message}";

                $handle		= fopen( plugin_dir_path ( __FILE__ ) . '/logs/' . $logFilename, 'a' );
                fwrite( $handle, $message );
                fclose( $handle );
            }

			
		}
		
	}
