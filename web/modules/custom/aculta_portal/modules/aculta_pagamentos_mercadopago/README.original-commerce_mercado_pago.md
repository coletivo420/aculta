
# Commerce Mercado Pago

The **Commerce Mercado Pago** module integrates **Mercado Pago Checkout Pro** with **Drupal Commerce**. It allows stores to process payments across Latin America using the latest **Mercado Pago SDK**, featuring support for installments, payment method exclusions, and 2 modes of integration.

For a full description of the module, visit the [project page](https://www.drupal.org/project/commerce_mercado_pago).

Submit bug reports and feature suggestions, or track changes in the issue queue.


## CONTENTS OF THIS FILE
--------------------- 
      
    • Requirements
    • Installation
    • Configuration
    • Maintainers


## REQUIREMENTS
------------

This module requires:
    • Commerce Payment (Commerce Core)
    • Mercado Pago PHP SDK (^3.0) 


## INSTALLATION
------------

Installation via Composer is recommended to automatically manage SDK dependencies:

composer require drupal/commerce_mercado_pago

Install as you would normally install a contributed Drupal module. Visit:
https://www.drupal.org/docs/extending-drupal/installing-modules for further information.


## CONFIGURATION
-------------

1. Once the module has been installed, navigate to: /admin/commerce/config/payment-gateways/add
    
2. Select **"Mercado Pago CheckoutPro"** as the payment gateway plugin.
    
3. If you don't have an account, you will need to create one at **Mercado Pago** and then create an "Application".
    
4. Within your application, you will find the necessary credentials (Public Key and Access Token) to integrate Mercado Pago with your store. 

For more information visit:
https://www.mercadopago.com/developers/en/docs/checkout-pro/overview
https://www.mercadopago.com/developers/en/docs/your-integrations/credentials
https://www.mercadopago.com/developers/en/docs/your-integrations/test/accounts


## MAINTAINERS
-----------

Drupal Entity - https://www.drupal.org/u/drupal-entity