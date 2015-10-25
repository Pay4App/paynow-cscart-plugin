<?php
/***************************************************************************
*                                                                          *
*   (c) 2014 Sam Takunda                                                   *
*   Credit to //victorkd2@yahoo.com
* 
Installing the plugin
====================

Maybe we can apologize on their behalf, but CSCart does not have a straight forward install process as Wordpress say would give you.


Credit to //victorkd2@yahoo.com


* Go to your database management and find the table 'payment' processors
* Note the processor_id of the last row in that table
* Add a new row to the database. CSCart say 3rd party gateways must have their IDs  starting at 1000 and upward.
* Enter a processor_id of 1000 or greater (one that's not taken)
* Enter ```Pay4App``` in the 'processor' field
* ```pay4app.php``` in processor_script
* ```views/orders/components/payments/cc_outside.tpl``` in 'processor template' (without the quotes)
* ```pay4app.tpl``` in the admin_template field
* callback must be ```N``` and type ```P``` (both without the quotes)
* Browse to your cscart installation folder. Add the pay4app.php file to the folder path app/payments/pay4app.php
* Also add the pay4app.tpl file to path:
        ```design/backend/templates/views/payments/components/cc_processors/pay4app.tpl```

* In your CSCart admin, go to the 'Administration'->'Payments' menu and pick Pay4App from the list.

* Enter your merchant details as they are provided you in your Pay4App Merchant dashboard on Pay4App

****************************************************************************/

use Tygh\Http;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

// Return from Pay4App's website
if (defined('PAYMENT_NOTIFICATION')) {

    
    if ($mode == 'callback' && !empty($_REQUEST['order'])) {

        if (fn_check_payment_script('pay4app.php', $_REQUEST['order'], $processor_data)) {

            
            $pay4app_response = array();

            if ( !$order_info = fn_get_order_info($_REQUEST['order']) ){
                die( json_encode( array( 'status'=>0, 'message'=>'Order not found' ) ) );
                exit();
            }

            
            if (empty($processor_data)) {
                $processor_data = fn_get_processor_data($order_info['payment_id']);
            }

            $pay4app_statuses   = $processor_data['processor_params']['statuses'];
            $success_status     = $pay4app_statuses['completed'];

            // check if valid response: Code from https://pay4app.com/merchants/solutions.php?tab=dvanced 
            
            if ( isset($_GET['merchant']) AND isset($_GET['checkout']) AND isset($_GET['order'])
                  AND isset($_GET['amount']) AND isset($_GET['email']) AND isset($_GET['phone'])
                  AND isset($_GET['timestamp']) AND isset($_GET['digest']) 
                ){ 


                //for readability the concatenation is split over two lines   
                $digest = $processor_data['processor_params']['merchantid'].$_GET['checkout'].$_GET['order'].$_GET['amount'];
                $digest .= $_GET['email'].$_GET['phone'].$_GET['timestamp'].$processor_data['processor_params']['apisecret'];

                $digesthash = hash("sha256", $digest);

                if ($_GET['digest'] !== $digesthash) die( json_encode( array( 'status'=>0 ) ) );
            
            } else { die( json_encode( array( 'status'=>0 ) ) ); }

            // end pay4app checks

            

            if ( fn_format_price($_REQUEST['amount']) != fn_format_price($order_info['total']) ) {
                $pay4app_response['order_status']       = "Y";
                $pay4app_response['reason_text']    = 'Pay4App payment and expected order amount mismatch';
                $pay4app_response['checkout_id']    = $_REQUEST['checkout'];
            
            } else {
                
                $pay4app_response['order_status'] = $success_status;
                $pay4app_response['reason_text'] = 'Payment completed successfully';
                $pay4app_response['checkout_id'] = $_REQUEST['checkout'];
                

            }


            if (!empty($_REQUEST['email'])) {
                $pay4app_response['customer_email'] = $_REQUEST['email'];
            }

            if (!empty($_REQUEST['phone'])) {
                $pay4app_response['customer_phone'] = $_REQUEST['phone'];
            }

            fn_finish_payment($_REQUEST['order'], $pay4app_response);

            if ( $pay4app_response['order_status'] == $success_status ){
                echo json_encode( array( 'status'=>1 ) ); exit();
            }
            else{
                echo json_encode( array( 'status'=>0 ) ); exit();
            }
            
        }
        exit;

    } elseif ($mode == 'return') {
        
        fn_order_placement_routines('route', $_REQUEST['order']);

    }
    


} else {
    
    $paynow_url = 'https://www.paynow.co.zw/interface/initiatetransaction';
    $paynow_integrationid = $processor_data['processor_params']['integrationid'];
    $paynow_integrationkey  = $processor_data['processor_params']['integrationkey'];
    //Order Total    
    $paynow_total = fn_format_price($order_info['total']);
    $paynow_reference = ($order_info['repaid']) ? ($order_id . '_' . $order_info['repaid']) : $order_id;
    $return_url = fn_url("payment_notification.return?payment=paynow&order_id=".$paynow_reference, AREA, 'current');
    $result_url = fn_url("payment_notification.callback?payment=paynow&order_id=".$paynow_reference, AREA, 'current');

    //prepare PayNow request
    $parameters = array (
		'id' 		=> $paynow_integrationid,
		'reference' => $paynow_reference,
		'amount' 	=> $paynow_total,
		//'additionalinfo' => '',
		'returnurl' => $return_url, //@todo
		'resulturl' => $result_url,
		'authemail' => '',//@todo
		'status'	=> 'Message',
		'hash'		=> ''
	);

	foreach ($parameters as $key => $value) {
		if($key == 'hash')	continue;
		$parameters['hash'] .= $value;
	}
	$parameters['hash'] .= $paynow_integrationkey;
	$parameters['hash'] = strtoupper(hash('sha512', $parameters['hash']));
	
	$extra = array(
        'headers' => array(
            'Connection: close'
        ),
    );
    $response = Http::post($post_url, $parameters, $extra);
    if (empty($response))
    {
    	$error_text = 'We could not connect to PayNow. Please retry, or contact us if the problem persists';
        fn_set_notification('E', 'Could not connect to PayNow', $error_text);
        fn_order_placement_routines('checkout.cart');
        exit;
    }
    	
    parse_str($response, $proper_response);

	if (!array_key_exists('status', $proper_response)){
		$error_text = 'There was a challenge initiating your checkout with PayNow. '.
			'Please retry, or contact us if the problem persists';
        fn_set_notification('E', 'Unexpected response from PayNow', $error_text);
        fn_order_placement_routines('checkout.cart');
        exit;
	}

	if ($proper_response['status'] == 'ok'){
		if (!(
			array_key_exists('browserurl', $proper_response) AND
			array_key_exists('pollurl', $proper_response) AND
			array_key_exists('hash', $proper_response) ))
		{
			$error_text = 'There was a challenge initiating your checkout with PayNow. '.
				'Please retry, or contact us if the problem persists';
        	fn_set_notification('E', 'Unexpected response from PayNow', $error_text);
        	fn_order_placement_routines('checkout.cart');
        	exit;
		}
		 	
		if(!verifyPayNowHash($proper_response)){
		 	$error_text = 'There was a challenge initiating your checkout with PayNow. '.
				'Please retry, or contact us if the problem persists';
        	fn_set_notification('E', 'Unexpected response from PayNow', $error_text);
        	fn_order_placement_routines('checkout.cart');
        	exit;
		 }
		 $paynow_browser_url = $proper_response['browserurl'];
	}
		
	if ($proper_response['status'] == "error"){
		$error_text = 'There was an error while initiating your checkout with PayNow. '.
			'We advise you contact us. Process failed with error: '.@$proper_response['error'];
    	fn_set_notification('E', 'PayNow Error', $error_text);
    	fn_order_placement_routines('checkout.cart');
    	exit;
		//@todo email admin the response body
	}
    
    $res = fn_change_order_status($paynow_order_id, "O"); //so that it's visible
    fn_clear_cart($_SESSION['cart'], false, true);         //clear the cart
    fn_create_payment_form($paynow_browser_url, $post_data, 'PayNow');
    
}
exit;
