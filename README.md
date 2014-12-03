Pay4App CSCart Plugin
============

Installing the plugin
====================

Maybe we should apologize on their behalf, but CSCart does not have a straight forward install process as Wordpress say would give you.


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
