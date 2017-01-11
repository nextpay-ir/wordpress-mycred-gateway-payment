<?php
/**
* Plugin Name: درگاه نکست پی MyCred
* Description: درگاه پردخت نکست پی MyCred
* Version: 1.0.0
* Author: Nextpay
* Author URI: http://nextpay.ir
*/

add_action('plugins_loaded','mycred_nextpay_plugins_loaded');
function mycred_nextpay_plugins_loaded(){
	
    add_filter('mycred_setup_gateways', 'Add_Nextpay_to_Gateways');
	function Add_Nextpay_to_Gateways($installed) {
        $installed['nextpay'] = array(
            'title' => get_option('nextpay_name') ? get_option('nextpay_name') : 'نکست پی',
            'callback' => array('myCred_Nextpay')
        );
        return $installed;
    }

	add_filter('mycred_buycred_refs', 'Add_Nextpay_to_Buycred_Refs');
	function Add_Nextpay_to_Buycred_Refs($addons ) {
		$addons['buy_creds_with_nextpay']          = __( 'buyCRED Purchase (Nextpay)', 'mycred' );
		return $addons;
	}
	
	add_filter('mycred_buycred_log_refs', 'Add_Nextpay_to_Buycred_Log_Refs');
	function Add_Nextpay_to_Buycred_Log_Refs( $refs ) {
		$nextpay = array('buy_creds_with_nextpay');
		return $refs = array_merge($refs, $nextpay);
	}
}
	
spl_autoload_register('mycred_nextpay_plugin');
function mycred_nextpay_plugin(){	
	
	if ( ! class_exists( 'myCRED_Payment_Gateway' ) ) 
		return;
	
	if ( !class_exists( 'myCred_Nextpay' ) ) {
		class myCred_Nextpay extends myCRED_Payment_Gateway {
	
			function __construct($gateway_prefs) {        
				$types = mycred_get_types();
				$default_exchange = array();
				foreach ($types as $type => $label)
					$default_exchange[$type] = 1000;

				parent::__construct(array(
					'id' => 'nextpay',
					'label' => get_option('nextpay_name') ? get_option('nextpay_name') : 'نکست پی',
						'defaults'         => array(
							'nextpay_api'          => '',
							'nextpay_name'          => 'نکست پی',
							'currency'         => 'ریال',
							'exchange'         => $default_exchange,
							'item_name'        => __( 'Purchase of myCRED %plural%', 'mycred' ),
						)
				), $gateway_prefs );
			}
		
			public function Nextpay_Iranian_currencies( $currencies ) {
				unset( $currencies );
				$currencies['ریال'] = 'ریال';
				$currencies['تومان'] = 'تومان';
				return $currencies;
			}
			
			/**
			* Gateway Prefs
			* @since 1.4
			* @version 1.0
			*/
			function preferences() {
				add_filter( 'mycred_dropdown_currencies', array( $this, 'Nextpay_Iranian_currencies' ) );
				$prefs = $this->prefs; ?>

				<label class="subheader" for="<?php echo $this->field_id( 'nextpay_api' ); ?>"><?php _e( 'کلید مجوزدهی ApiKey', 'mycred' ); ?></label>
				<ol>
					<li>
						<div class="h2"><input type="text" name="<?php echo $this->field_name( 'nextpay_api' ); ?>" id="<?php echo $this->field_id( 'nextpay_api' ); ?>" value="<?php echo $prefs['nextpay_api']; ?>" class="long" /></div>
					</li>
				</ol>
				<label class="subheader" for="<?php echo $this->field_id( 'nextpay_name' ); ?>"><?php _e( 'نام نمایشی درگاه', 'mycred' ); ?></label>
				<ol>
					<li>
						<div class="h2"><input type="text" name="<?php echo $this->field_name( 'nextpay_name' ); ?>" id="<?php echo $this->field_id( 'nextpay_name' ); ?>" value="<?php echo $prefs['nextpay_name'] ? $prefs['nextpay_name'] : 'زرین پال'; ?>"  /></div>
					</li>
				</ol>
				<label class="subheader" for="<?php echo $this->field_id( 'currency' ); ?>"><?php _e( 'Currency', 'mycred' ); ?></label>
				<ol>
					<li>
						<?php $this->currencies_dropdown( 'currency', 'mycred-gateway-nextpay-currency' ); ?>
					</li>
				</ol>
				<label class="subheader" for="<?php echo $this->field_id( 'item_name' ); ?>"><?php _e( 'Item Name', 'mycred' ); ?></label>
				<ol>
					<li>
						<div class="h2"><input type="text" name="<?php echo $this->field_name( 'item_name' ); ?>" id="<?php echo $this->field_id( 'item_name' ); ?>" value="<?php echo $prefs['item_name']; ?>" class="long" /></div>
						<span class="description"><?php _e( 'Description of the item being purchased by the user.', 'mycred' ); ?></span>
					</li>
				</ol>
				<label class="subheader"><?php _e( 'Exchange Rates', 'mycred' ); ?></label>
				<ol>
					<?php $this->exchange_rate_setup(); ?>
				</ol>
			<?php
			}
		
			/**
			* Sanatize Prefs
			* @since 1.4
			* @version 1.1
			*/
			public function sanitise_preferences( $data ) {

				$new_data['nextpay_api'] = sanitize_text_field( $data['nextpay_api'] );
				$new_data['nextpay_name'] = sanitize_text_field( $data['nextpay_name'] );
				$new_data['currency'] = sanitize_text_field( $data['currency'] );
				$new_data['item_name'] = sanitize_text_field( $data['item_name'] );

				// If exchange is less then 1 we must start with a zero
				if ( isset( $data['exchange'] ) ) {
					foreach ( (array) $data['exchange'] as $type => $rate ) {
						if ( $rate != 1 && in_array( substr( $rate, 0, 1 ), array( '.', ',' ) ) )
							$data['exchange'][ $type ] = (float) '0' . $rate;
					}
				}
				$new_data['exchange'] = $data['exchange'];
			
				update_option('nextpay_name', $new_data['nextpay_name']);
			
				return $data;
			}

			/**
			* Buy Creds
			* @since 1.4
			* @version 1.1
			*/
			public function buy() {
				if ( ! isset( $this->prefs['nextpay_api'] ) || empty( $this->prefs['nextpay_api'] ) )
					wp_die( __( 'Please setup this gateway before attempting to make a purchase!', 'mycred' ) );

				// Type
				$type = $this->get_point_type();
				$mycred = mycred( $type );

				// Amount
				$amount = $mycred->number( $_REQUEST['amount'] );
				$amount = abs( $amount );

				// Get Cost
				$cost = $this->get_cost( $amount, $type );

				$to = $this->get_to();
				$from = $this->current_user_id;

				// Revisiting pending payment
				if ( isset( $_REQUEST['revisit'] ) ) {
					$this->transaction_id = strtoupper( $_REQUEST['revisit'] );
				}
				else {
					$post_id = $this->add_pending_payment( array( $to, $from, $amount, $cost, $this->prefs['currency'], $type ) );
					$this->transaction_id = get_the_title( $post_id );
				}

				$return_url =  add_query_arg('payment_id', $this->transaction_id, $this->callback_url());
			
				$ApiKey = $this->prefs['nextpay_api'];
				$Amount = ($this->prefs['currency'] == 'تومان') ? $cost : ($cost/10);
				$Amount = intval( str_replace( ',' , '', $Amount) );
				$CallbackURL = $return_url;

				$client = new SoapClient('https://api.nextpay.org/gateway/token.wsdl', array('encoding' => 'UTF-8'));
				$result = $client->TokenGenerator(
						array(
								'api_key' 	=> $ApiKey,
								'amount' 	=> $Amount,
								'order_id' 	=> $this->transaction_id,
								'callback_uri' 	=> $CallbackURL
							)
				);
				$result = $result->TokenGeneratorResult;

				//Redirect to Nextpay
				if(intval($result->code) == -1)
				{
					Header('Location: https://api.nextpay.org/gateway/payment/'.$result->trans_id);
				} 
				else 
				{	
					$this->get_page_header( __( 'Processing payment &hellip;', 'mycred' ) ); 
					echo $this->Fault($result->code);
					$this->get_page_footer();
				}
				// Exit
				unset( $this );
				exit;
			}

			/**
			* Process
			* @since 1.4
			* @version 1.1
			*/
			public function process() {
				// Required fields
				if (  isset($_REQUEST['payment_id']) && isset($_REQUEST['mycred_call']) && $_REQUEST['mycred_call'] == 'nextpay')
				{	
					$new_call = array();
					$redirect = $this->get_cancelled("");
					// Get Pending Payment
					$pending_post_id = sanitize_key( $_REQUEST['payment_id'] );
					$org_pending_payment = $pending_payment = $this->get_pending_payment( $pending_post_id );
					
					if (is_object($pending_payment))
						$pending_payment = (array) $pending_payment;
					
					if ( $pending_payment !== false ) {
					
						$cost = ( str_replace( ',' , '', $pending_payment['cost']) );
						$cost = (int) $cost;
						$Amount = ($this->prefs['currency'] == 'تومان') ? $cost : ($cost/10);
						
						$ApiKey = $this->prefs['nextpay_api'];
						$Trans_ID = $_POST['trans_id'];
						$Order_ID = $_POST['order_id'];

						$client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', array('encoding' => 'UTF-8'));
						$result = $client->PaymentVerification(
							array(
									'api_key'	 => $ApiKey,
									'trans_id' 	 => $Trans_ID,
									'order_id' => $Order_ID,
									'amount'	 => $Amount
								)
						);
						$result = $result->PaymentVerificationResult;

						if($result->code == 0){
							if ( $this->complete_payment( $org_pending_payment, $Trans_ID ) ) {
								$new_call[] = sprintf( __( 'تراکنش با موفقیت به پایان رسید . کد رهگیری : %s', 'mycred' ), $Trans_ID );
								$this->trash_pending_payment( $pending_post_id );
								$redirect = $this->get_thankyou();
							}
							else{
								$new_call[] = __( 'در حین تراکنش خطای نامشخصی رخ داده است .', 'mycred' );
							}
						}
						else {
							$new_call[] = sprintf( __( 'در حین تراکنش خطای رو به رو رخ داده است : %s', 'mycred' ), $this->Fault($result->code) );
						}


				
					}
					else
						$new_call[] = __( 'در حین تراکنش خطای نامشخصی رخ داده است .', 'mycred' );
			
			
					if ( !empty( $new_call ) )
						$this->log_call( $pending_post_id, $new_call );
				
					wp_redirect($redirect);
					die();
				
				}
			}
			
			
			/**
			* Returning
			* @since 1.4
			* @version 1.0
			*/
			public function returning() { 
				if (  isset($_REQUEST['payment_id']) && isset($_REQUEST['mycred_call']) && $_REQUEST['mycred_call'] == 'nextpay')
				{
					// DO Some Actions
				}
			}


			private static function Fault($err_code){
				$message = ' ';
				$error_code = intval($err_code);
				$error_array = array(
					0 => "Complete Transaction",
					-1 => "Default State",
					-2 => "Bank Failed or Canceled",
					-3 => "Bank Payment Pendding",
					-4 => "Bank Canceled",
					-20 => "api key is not send",
					-21 => "empty trans_id param send",
					-22 => "amount in not send",
					-23 => "callback in not send",
					-24 => "amount incorrect",
					-25 => "trans_id resend and not allow to payment",
					-26 => "Token not send",
					-30 => "amount less of limite payment",
					-32 => "callback error",
					-33 => "api_key incorrect",
					-34 => "trans_id incorrect",
					-35 => "type of api_key incorrect",
					-36 => "order_id not send",
					-37 => "transaction not found",
					-38 => "token not found",
					-39 => "api_key not found",
					-40 => "api_key is blocked",
					-41 => "params from bank invalid",
					-42 => "payment system problem",
					-43 => "gateway not found",
					-44 => "response bank invalid",
					-45 => "payment system deactived",
					-46 => "request incorrect",
					-48 => "commission rate not detect",
					-49 => "trans repeated",
					-50 => "account not found",
					-51 => "user not found"
				);
				$message = $error_array[$error_code];
				return $message;
			}

			
		}

	}
}
?>