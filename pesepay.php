<?php
/**
 * Plugin Name: Pesepay Payment Gateway
 * Plugin URI: https://developers-india.com
 * Description: Pesepay Payment Gateway for woocommerce.
 * Author: Developers India Team
 * Author URI: https://developers-india.com/
 * Version: 1.0.0
 * Text Domain: pesepay
 * Domain Path: /languages/
 * License: GNU General Public License v2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package   WC_Gateway_Pesepay
 * @author    Raj Kumar Singh
 * @category  Admin
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + WC_Gateway_Pesepay
 */
function wc_pesepay_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Pesepay';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_pesepay_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_pesepay_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pesepay_gateway' ) . '">' . __( 'Configure', 'pesepay' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_pesepay_gateway_plugin_links' );


/**
 * Pesepay Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Pesepay
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 */
add_action( 'plugins_loaded', 'wc_pesepay_gateway_init', 11 );

function wc_pesepay_gateway_init() {

	class WC_Gateway_Pesepay extends WC_Payment_Gateway {

		public static $log_enabled = false;
	    public static $log = false;
		public function __construct() {
	  
			$this->id                 = 'pesepay_gateway';
			$this->icon               = apply_filters('woocommerce_online_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Pesepay Payment', 'pesepay' );
			$this->method_description = __( 'Allows omnline payments. Redirects customers to Pesepay gateway to enter their payment information..', 'pesepay' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		    $this->debug      = 'yes' === $this->get_option( 'debug', 'no' );
		
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			
			$this->supports           = array(
                'products',
                 //'refunds',
                //'pre-orders'
            );
			self::$log_enabled    = $this->debug;
		$this->callback =  get_home_url().'/wc-api/pesepay_gateway';
            	
		  add_action( 'woocommerce_api_pesepay_gateway', array( $this, 'pesepay_gateway_ipn' ) );
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		    add_action( 'template_redirect', array($this,'pesepay_capture_payment' ));
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'order_received_text' ), 10, 2 );
		}
	
	

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_pesepay_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'pesepay' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Pesepay Payment', 'pesepay' ),
					'default' => 'no'
				),
				'encryption_key' => array(
					'title'       => __( 'Encryption Key', 'pesepay' ),
					'type'        => 'password'
				),
				'integration_key' => array(
					'title'       => __( 'Integration Key', 'pesepay' ),
					'type'        => 'password'
				),
				'title' => array(
					'title'       => __( 'Title', 'pesepay' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'pesepay' ),
					'default'     => __( 'Pesepay Payment', 'pesepay' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'pesepay' ),
					'type'        => 'text',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'pesepay' ),
					'default'     => __( 'Pay with Pesepay.', 'pesepay' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'pesepay' ),
					'type'        => 'text',
					'description' => __( 'Instructions that will be added to the thank you page and emails.(Optional)', 'pesepay' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'debug'                 => array(
					'title'       => __( 'Debug log', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce' ),
					'default'     => 'no',
				)
			) );
		}
	    public static function log( $message, $level = 'info' ) {
		    if ( self::$log_enabled ) {
			    if ( empty( self::$log ) ) {
				    self::$log = wc_get_logger();
			    }
			    self::$log->log( $level, $message, array( 'source' => 'pesepay_gateway' ) );
		    }
	    }
		public function process_admin_options() {
			$saved = parent::process_admin_options();

			// Maybe clear logs.
			if ( 'yes' !== $this->get_option( 'debug', 'no' ) ) {
				if ( empty( self::$log ) ) {
					self::$log = wc_get_logger();
				}
				self::$log->clear( 'pesepay_gateway' );
			}

			return $saved;
		}
	    public function aes_encrypt($data){
	        
        	$encryption_key = $this->get_option('encryption_key');
            $iv = substr($encryption_key,0,16);
        	$encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
	        return $encrypted;
        }
        public function aes_decrypt($data){
        	$encryption_key = $this->get_option('encryption_key');
        	$iv = substr($encryption_key,0,16);
        	$encrypted = $data . ':' . base64_encode($iv);
	        $parts = explode(':', $encrypted);
        	$decrypted = openssl_decrypt($parts[0], 'aes-256-cbc', $encryption_key, 0, base64_decode($parts[1]));
	        return $decrypted;
        }
	    public function initiate_gateway_request($order){
	        $url='https://api.pesepay.com/api/payments-engine/v1/payments/initiate';	
	        $integration_key = $this->get_option('integration_key');
	        $order_data = $order->get_data();
	        $order_currency = $order_data['currency'];
	        $order_total = $order_data['total'];
	        $blog_title=$this->get_order_item_names($order);
	        $returnurl=$this->get_return_url( $order );
	        $resulturl=$this->callback;
	        $datasend='{"amountDetails" : {"amount" : '.$order_total.',"currencyCode" : "'.$order_currency.'"   },"reasonForPayment" : "'.$blog_title.'","resultUrl": "'.$resulturl.'","returnUrl" : "'.$returnurl.'"}';
	        $data = $this->aes_encrypt($datasend);
	        $payload = json_encode( array( "payload"=> $data ) );
	        $headers=array('Content-Type'=>'application/json','Authorization'=>$integration_key);
		    $response = wp_safe_remote_post(
			$url,
		    	array(
			    	'method'  => 'POST',
				    'headers' => $headers,
				    'body'    => $payload,
				    'timeout' => 300,
		    	    )
		    );
		   return json_decode( $response['body'],true ); 
		}
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			// Mark as on-hold (we're awaiting the payment)
			WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order_id);
			$order->update_status( 'pending', __( 'Awaiting payment from gateway', 'pesepay'));
			
			$response=$this->initiate_gateway_request($order);
			
			if(!isset($response)){
			   return array(
			        'result' 	=> 'failed',
			        'redirect'	=> $this->get_return_url( $order )
		    	); 
			}
			if(!isset($response['payload'])){
			    wc_add_notice( __('Payment Initialization failed with api end point:', 'pesepay') , 'error' );return ;
			 
			}
		    $data = $this->aes_decrypt($response['payload']);
		    $respnseJson=json_decode($data,true);
		    $referenceNumber=$respnseJson['referenceNumber'];
			$this->log( 'Payment Initialized: ' . wc_print_r( $respnseJson, true ) );
		    $order->update_meta_data( 'referenceNumber', $referenceNumber );
            $order->save();
		    $redirectUrlr=$respnseJson['redirectUrl'];
		    
		    $order->add_order_note( __('Payment request initialize by api endpoint', 'pesepay') );
		    $order->add_order_note( __('Payment reference number : '.$referenceNumber, 'pesepay') );
		    return array(
		        'result' 	=> 'success',
			    'redirect'	=> $redirectUrlr
			);
			
		}
		
	public function pesepay_gateway_ipn(){
		
		$request_body    = file_get_contents( 'php://input' );
		$this->log( 'Pesepay IPN Received: ' . wc_print_r( $request_body, true ) );
		$oreder_meta=$request_body['referenceNumber'];
		$order_id=$this->get_order_id_from_ref($oreder_meta);
		$order = new WC_Order( $order_id );
	    $integration_key = $this->get_option('integration_key');
	    $url='https://api.pesepay.com/api/payments-engine/v1/payments/check-payment?referenceNumber='.$oreder_meta;
	    if ( 'pesepay_gateway' === $order->get_payment_method()){
	        $payload = json_encode( array( "referenceNumber"=> $oreder_meta ) );
	        $args = array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => $integration_key
                
                )
            );
		    $response = wp_remote_get($url, $args );
		    if ( is_wp_error( $response ) ) {
				
				$order->add_order_note( __( 'Payment could not be captured: Initialization failed', 'pesepay' ) );
				$this->log( 'IPN Payment could not be captured: Initialization failed', 'error'  );
				return;
			}
		   $responses= json_decode( $response['body'],true ); 
		   if(!isset($responses['payload']) && isset($responses['message'])){
		      	$order->add_order_note( $responses['message']  );return;
				$this->log( 'Pesepay IPN Response: '.$responses['message'], 'error' );
		   }
	       $data = $this->aes_decrypt($responses['payload']);
	       $respnseJson=json_decode($data,true); 
		   if(isset($respnseJson['transactionStatus']) && $respnseJson['transactionStatus']=='SUCCESS'){
		       $order->payment_complete( $oreder_meta );
			   $this->log( 'IPN Payment Update: Order Marked Completed', 'success'  );
			   $this->log( 'IPN Payment Update: '.$respnseJson['transactionStatusDescription'], 'success'  );
		       WC()->mailer()->emails['WC_Email_Customer_Completed_Order']->trigger($order_id);
		         $amountTotal=$respnseJson['amountDetails']['currencyCode'].' '.$respnseJson['amountDetails']['totalTransactionAmount'];
		         $application_id=$respnseJson['applicationId'];
		         $txnID=$respnseJson['referenceNumber'];
		         $amountDetails=$respnseJson['amountDetails'];
		         $order->add_order_note( $respnseJson['transactionStatusDescription'] );
		         $order->add_order_note( sprintf( __( 'Payment of %1$s was captured - Application ID: %2$s, Refrence Number : %3$s', 'pesepay' ), $amountTotal, $application_id, $txnID ) );
		          $order->update_status( 'completed',__('Order Completed Successfully','pesepay'));
				  
						update_post_meta( $order->get_id(), '_payment_status', $respnseJson['transactionStatus'] );
						update_post_meta( $order->get_id(), '_payment_paid_time', $respnseJson['dateOfTransaction'] );
						update_post_meta( $order->get_id(), '_transaction_id', $txnID);
						update_post_meta( $order->get_id(), '_amount_details', $amountDetails);
		    }
		    elseif(isset($respnseJson['transactionStatus']) && $respnseJson['transactionStatus']=='CANCELLED'){
		        $order->payment_complete( $oreder_meta );
				$this->log( 'IPN Payment Update: Order Marked Cancelled', 'error' );
				$this->log( 'IPN Payment Update: '.$respnseJson['transactionStatusDescription'], 'error' );
		         $amountTotal=$respnseJson['amountDetails']['currencyCode'].' '.$respnseJson['amountDetails']['totalTransactionAmount'];
		         $application_id=$respnseJson['applicationId'];
		         $txnID=$respnseJson['referenceNumber'];
		         $amountDetails=$respnseJson['amountDetails'];
		         $order->add_order_note( $respnseJson['transactionStatusDescription'] );
		         $order->add_order_note( sprintf( __( 'Payment of %1$s could not be captured - Application ID: %2$s, Refrence Number : %3$s', 'pesepay' ), $amountTotal, $application_id, $txnID ) );
		          $order->update_status( 'cancelled',__('Order Cancelled Successfully','pesepay'));
				 
						update_post_meta( $order->get_id(), '_payment_status', $respnseJson['transactionStatus'] );
						update_post_meta( $order->get_id(), '_payment_paid_time', $respnseJson['dateOfTransaction'] );
						update_post_meta( $order->get_id(), '_transaction_id', $txnID);
						update_post_meta( $order->get_id(), '_amount_details', $amountDetails);
		         
		     }
		    else{
		    $order->add_order_note( $respnseJson['transactionStatusDescription'] );
			$this->log( 'IPN Payment Update: '.$respnseJson['transactionStatusDescription'], 'error'  );
			//$order->payment_complete( $oreder_meta );
			$order->update_status( 'cancelled',__('Order Cancelled Successfully','pesepay'));
		    }
		    $order->save();
		    WC()->cart->empty_cart();
	    } 
	    
	}
	
	public function pesepay_capture_payment(){
	global $wp;
	if ( is_checkout() && !empty( $wp->query_vars['order-received'] ) )
	{
	     
	    $order_id=$wp->query_vars['order-received'];
	    $order = wc_get_order( $order_id );
	    if(!empty( $order->get_meta( '_transaction_id', true ))){return;}
	    $integration_key = $this->get_option('integration_key');
	    $oreder_meta = $order->get_meta( 'referenceNumber', true );
	    if(empty( $order->get_meta( 'referenceNumber', true ))){return;}
	    $url='https://api.pesepay.com/api/payments-engine/v1/payments/check-payment?referenceNumber='.$oreder_meta;
	    if ( 'pesepay_gateway' === $order->get_payment_method()){
	        $payload = json_encode( array( "referenceNumber"=> $oreder_meta ) );
	        $args = array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => $integration_key
                
                )
            );
		    $response = wp_remote_get($url, $args );
		    if ( is_wp_error( $response ) ) {
				
				$order->add_order_note( __( 'IPN Payment could not be captured: Initialization failed', 'pesepay' ) );
				$this->log( 'IPN Payment Update: '.$respnseJson['transactionStatusDescription'], 'error'  );
				return;
			}
		   $responses= json_decode( $response['body'],true ); 
		   if(!isset($responses['payload']) && isset($responses['message'])){
		      	$order->add_order_note( $responses['message']  );return;
		   }
	       $data = $this->aes_decrypt($responses['payload']);
	       $respnseJson=json_decode($data,true); 
	     
		   if(isset($respnseJson['transactionStatus']) && $respnseJson['transactionStatus']=='SUCCESS'){
		       $order->payment_complete( $oreder_meta );
			   $this->log( 'Payment Update: Order Marked Completed', 'success'  );
			   $this->log( 'Payment Update: '.$respnseJson['transactionStatusDescription'], 'success'  );
			   
		       WC()->mailer()->emails['WC_Email_Customer_Completed_Order']->trigger($order_id);
		         $amountTotal=$respnseJson['amountDetails']['currencyCode'].' '.$respnseJson['amountDetails']['totalTransactionAmount'];
		         $application_id=$respnseJson['applicationId'];
		         $txnID=$respnseJson['referenceNumber'];
		         $amountDetails=$respnseJson['amountDetails'];
		         $order->add_order_note( $respnseJson['transactionStatusDescription'] );
		         $order->add_order_note( sprintf( __( 'Payment of %1$s was captured - Application ID: %2$s, Refrence Number : %3$s', 'pesepay' ), $amountTotal, $application_id, $txnID ) );
		          $order->update_status( 'completed',__('Order Completed Successfully','pesepay'));
				  
						update_post_meta( $order->get_id(), '_payment_status', $respnseJson['transactionStatus'] );
						update_post_meta( $order->get_id(), '_payment_paid_time', $respnseJson['dateOfTransaction'] );
						update_post_meta( $order->get_id(), '_transaction_id', $txnID);
						update_post_meta( $order->get_id(), '_amount_details', $amountDetails);
		    }
		     elseif(isset($respnseJson['transactionStatus']) && $respnseJson['transactionStatus']=='CANCELLED'){
		         $order->payment_complete( $oreder_meta );
				 $this->log( 'Payment Update:Order Marked canelled. ', 'error'  );
				 $this->log( 'Payment Update: '.$respnseJson['transactionStatusDescription'], 'error' );
		         $amountTotal=$respnseJson['amountDetails']['currencyCode'].' '.$respnseJson['amountDetails']['totalTransactionAmount'];
		         $application_id=$respnseJson['applicationId'];
		         $txnID=$respnseJson['referenceNumber'];
		         $amountDetails=$respnseJson['amountDetails'];
		         $order->add_order_note( $respnseJson['transactionStatusDescription'] );
		         $order->add_order_note( sprintf( __( 'Payment of %1$s could not be captured - Application ID: %2$s, Refrence Number : %3$s', 'pesepay' ), $amountTotal, $application_id, $txnID ) );
		          $order->update_status( 'cancelled',__('Order Cancelled.','pesepay'));
				  	update_post_meta( $order->get_id(), '_payment_status', $respnseJson['transactionStatus'] );
						update_post_meta( $order->get_id(), '_payment_paid_time', $respnseJson['dateOfTransaction'] );
						update_post_meta( $order->get_id(), '_transaction_id', $txnID);
						update_post_meta( $order->get_id(), '_amount_details', $amountDetails);
		         
		     }
		    else{
		    $order->add_order_note( $respnseJson['transactionStatusDescription'] );
			$this->log( 'Payment Update: '.$respnseJson['transactionStatusDescription'], 'error' );
			//$order->payment_complete( $oreder_meta );
			$order->update_status( 'cancelled',__('Order Cancelled.','pesepay'));
		    }
		    $order->save();
		    WC()->cart->empty_cart();
	    }
	  }
	
	}
	protected function get_order_item_names( $order ) {
		$item_names = array();
        $order_data = $order->get_data();
	        $odcurrency = $order_data['currency'];
		foreach ( $order->get_items() as $item ) {
			$item_name = $item->get_name();
			$item_meta = wp_strip_all_tags(
				wc_display_item_meta(
					$item,
					array(
						'before'    => '',
						'separator' => ', ',
						'after'     => '',
						'echo'      => false,
						'autop'     => false,
					)
				)
			);

			if ( $item_meta ) {
				$item_name .= ' (' . $item_meta . ')';
			}

			$item_names[] = $item_name . ' x ' . $item->get_quantity();
		}

		return implode(',  ',$item_names);
	}
	public function get_order_id_from_ref($referenceNumber) {
        global $wpdb;
        $results = $wpdb->get_results( 
                    $wpdb->prepare("SELECT * {$wpdb->prefix}postmeta WHERE meta_key='referenceNumber' AND meta_value= %s", $referenceNumber),ARRAY_A  
                 );
                 return $results[0]['post_id'];
    }
    public function order_received_text($text, $order_id){
         if ( !$order_id ){return;}
        $order = new WC_Order( $order_id );
         $ref = $order->get_meta( 'referenceNumber', true );
        //echo $order->get_status();
        if ( $order && $this->id === $order->get_payment_method() ) {
            if ( 'completed' == $order->get_status()) {
			return __( 'Thank you for your payment. Your transaction has been completed, and a receipt for your purchase has been emailed to you.', 'pesepay' );
            }
            elseif('cancelled' == $order->get_status()){
                return __('Your Order has been cancelled. Your Payment Reference Number is:'.$ref,'pesepay');
            }
            else{return $text;}
		}
		else{
		   return $text; 
		}

		
    }
  } 
}

