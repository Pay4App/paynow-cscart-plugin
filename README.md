PayNow CSCart Plugin
============

##Installing the plugin
Maybe we can apologize on their behalf, but CSCart does not have a straight forward install process as Wordpress say would give you.
Credit to //victorkd2@yahoo.com
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

## Configuring the plugin
* In your CSCart admin, go to the 'Administration'->'Payment methods' menu
* Click the '+' icon to add a new payment method
* In the 'General' tab on the dialog that shows enter a name such as PayNow
* For *Processor*, pick PayNow from the *Checkouts* list
* Click the *Configure* header and enter your merchant details as they are provided you in your PayNow account
* Fill in all other fields on either headers as fits you
* Click *Create*


