 
<?php
/***************************************************************************
*                                                                          *
*   (c) 2014 Sam Takunda                                                   *
*   Credit to //victorkd2@yahoo.com
* 
Installing the plugin
====================

Maybe we can apologize on their behalf, but CSCart does not have a straight forward install process as Wordpress say would give you.


* Go to your database management and find the table 'payment' processors
* Note the processor_id of the last row in that table
* Add a new row to the database. CSCart say 3rd party gateways must have their IDs  starting at 1000 and upward.
* Enter a processor_id of 1000 or greater (one that's not taken)
* Enter ```PayNow``` in the 'processor' field
* ```paynow.php``` in processor_script
* ```views/orders/components/payments/cc_outside.tpl``` in 'processor template' (without the quotes)
* ```paynow.tpl``` in the admin_template field
* callback must be ```N``` and type ```P``` (both without the quotes)
* Browse to your cscart installation folder. Add the paynow.php file to the folder path app/payments/paynow.php
* Also add the paynow.tpl file to path:
        ```design/backend/templates/views/payments/components/cc_processors/paynow.tpl```

* In your CSCart admin, go to the 'Administration'->'Payments' menu and pick PayNow from the list.

* Enter your merchant details as they are provided you in your PayNow account

****************************************************************************/

use Tygh\Http;
use Tygh\Registry;

/**
 * @param $payload Checks if the 'hash' in payload is what we expect
 */
function verifyPayNowHash($payload, $processor_data)
{
	$hashString = '';
	foreach ($payload as $key => $value) {
		if($key == 'hash') continue;
		$hashString .= $value;
	}
	$hashString .= $processor_data['processor_params']['integrationkey'];
	$hashString = strtoupper(hash('sha512', $hashString));
	return ($hashString === $payload['hash']);
}

if (!defined('BOOTSTRAP')) { die('Access denied'); }

// Return from PayNow's website
if (defined('PAYMENT_NOTIFICATION')) {

	if ($mode == 'return') {
    	//set notification to not confuse customer
    	fn_set_notification('N', 'Thank you for your payment', 'We will upate you about your order soon');
    	fn_order_placement_routines('checkout_redirect');

    } else if ($mode == 'callback' && !empty($_REQUEST['order'])) {

        if (fn_check_payment_script('paynow.php', $_REQUEST['order'], $processor_data)) {

            
            $paynow_response = array();

            if ( !$order_info = fn_get_order_info($_REQUEST['order']) ){
                die('Order not found');
                exit();
            }

            
            if (empty($processor_data)) {
                $processor_data = fn_get_processor_data($order_info['payment_id']);
            }

            $paynow_statuses	= $processor_data['processor_params']['statuses'];
            $success_status		= $paynow_statuses['completed'];

            if(!(
				isset($_POST['reference']) ||
				isset($_POST['amount']) ||
				isset($_POST['paynowreference']) ||
				isset($_POST['pollurl']) ||
				isset($_POST['status']) ||
				isset($_POST['hash'])
			)) die('Incomplete request');
	
			if (!verifyPayNowHash($_POST, $processor_data)) die('Invalid hash');
	
			//Verify again with PayNow
			$pollURL = ''; //@todo Retrieve saved polled URL
	
			//Will check only reference and status. But store paynowreference
			$extra = array(
		        'headers' => array(
		            'Connection: close'
		        ),
		    );
		    $response = Http::get($_POST['pollurl'], array(), $extra);
		    if (empty($response)) die('Failed to poll'); //@todo maybe email admin
		    parse_str($response, $proper_response);
		    
		    if(!(
				array_key_exists('reference', $proper_response) &
				array_key_exists('amount', $proper_response) &
				array_key_exists('paynowreference', $proper_response) &
				array_key_exists('pollurl', $proper_response) &
				array_key_exists('status', $proper_response) &
				array_key_exists('hash', $proper_response)
			)) //response missing expected fields
				//@todo email request to admin so that they check in PayNow admin since their servers won't repeat this
				return;
			if (!verifyPayNowHash($proper_response, $processor_data)) die('Invalid hash (poll)');
			if (!$proper_response['status'] == 'Paid') die('Status not Paid. Ineffectual');
            
            // End PayNow checks
            if ( fn_format_price($_REQUEST['amount']) != fn_format_price($order_info['total']) ) {
                $paynow_response['order_status']  	= "Y";
                $paynow_response['reason_text']    	= 'PayNow payment and expected order amount mismatch';
                $paynow_response['paynowreference']    	= $_REQUEST['paynowreference'];
            
            } else {
                $paynow_response['order_status'] = $success_status;
                $paynow_response['reason_text'] = 'Payment completed successfully';
                $paynow_response['paynowreference'] = $_REQUEST['paynowreference'];
            }
            
            fn_finish_payment($_REQUEST['order'], $paynow_response);
            
        }
    }

} else {
    
    $paynow_url = 'https://www.paynow.co.zw/interface/initiatetransaction';
    $paynow_integrationid = $processor_data['processor_params']['integrationid'];
    $paynow_integrationkey  = $processor_data['processor_params']['integrationkey'];
    //Order Total    
    $paynow_total = fn_format_price($order_info['total']);
    $paynow_reference = ($order_info['repaid']) ? ($order_id . '_' . $order_info['repaid']) : $order_id;
    $return_url = fn_url("payment_notification.return?payment=paynow&order=".$paynow_reference, AREA, 'current');
    $result_url = fn_url("payment_notification.callback?payment=paynow&order=".$paynow_reference, AREA, 'current');

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
    $response = Http::post($paynow_url, $parameters, $extra);
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

	if ($proper_response['status'] == 'Ok'){
		if (!(
			array_key_exists('browserurl', $proper_response) AND
			array_key_exists('pollurl', $proper_response) AND
			array_key_exists('hash', $proper_response) ))
		{
			$error_text = 'There was a challenge initiating your checkout with PayNow. '.
				'Please retry, or contact us if the problem persists';
        	fn_set_notification('E', 'PayNow response field validation failed', $error_text);
        	fn_order_placement_routines('checkout.cart');
        	exit;
		}
		 	
		if(!verifyPayNowHash($proper_response, $processor_data)){
			$error_text = 'There was a challenge initiating your checkout with PayNow. '.
				'Please retry, or contact us if the problem persists';
        	fn_set_notification('E', 'PayNow response hash validation fail', $error_text);
        	exit;
		}

		$paynow_browser_url = $proper_response['browserurl'];
		$res = fn_change_order_status($paynow_order_id, "O"); //so that it's visible
	    fn_clear_cart($_SESSION['cart'], false, true);         //clear the cart
	    fn_create_payment_form($paynow_browser_url, $post_data, 'PayNow');
	}
		
	if ($proper_response['status'] == "error"){
		$error_text = 'There was an error while initiating your checkout with PayNow. '.
			'We advise you contact us. Process failed with error: '.@$proper_response['error'];
    	fn_set_notification('E', 'PayNow Error', $error_text);
    	fn_order_placement_routines('checkout.cart');
    	exit;
		//@todo email admin the response body
	}

	$error_text = 'There was a challenge initiating your checkout with PayNow. '.
		'Please retry, or contact us if the problem persists';
	fn_set_notification('E', 'Unknown response status from PayNow', $error_text);
	fn_order_placement_routines('checkout.cart');
	exit;
    
}
exit;
