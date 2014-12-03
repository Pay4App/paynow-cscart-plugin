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
    
    $pay4app_url = 'https://pay4app.com/checkout.php';

    $pay4app_merchantid = $processor_data['processor_params']['merchantid'];
    $pay4app_apisecret  = $processor_data['processor_params']['apisecret'];

    //Order Total    
    $pay4app_total = fn_format_price($order_info['total']);

    $pay4app_order_id = ($order_info['repaid']) ? ($order_id . '_' . $order_info['repaid']) : $order_id;

    $return_url = fn_url("payment_notification.return?payment=pay4app&order_id=".$pay4app_order_id, AREA, 'current');
    $callback_url = fn_url("payment_notification.callback?payment=pay4app&order_id=".$pay4app_order_id, AREA, 'current');

    $str = $pay4app_merchantid.$pay4app_order_id.$pay4app_total.$pay4app_apisecret;
    $signature = hash('sha256', $str);

    $post_data = array(
        'orderid'           => $pay4app_order_id,
        'amount'            => $pay4app_total,
        'redirect'          => $return_url,
        'transferpending'   => $return_url,
        'merchantid'        => $pay4app_merchantid,
        'signature'         => $signature
    );

    
    $res = fn_change_order_status($pay4app_order_id, "O"); //so that it's visible
    fn_clear_cart($_SESSION['cart'], false, true);         //clear the cart
    fn_create_payment_form($pay4app_url, $post_data, 'Pay4App');
    
}
exit;