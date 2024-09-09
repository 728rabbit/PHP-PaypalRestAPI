# PHP-PaypalRestAPI
The PaypalRestAPI class integrates PayPal’s REST API for payment processing in PHP. It handles authentication, adds checkout items, manages discounts, taxes, and shipping, and executes transactions. The class supports live and sandbox modes and provides methods for retrieving transaction details and error messages, simplifying PayPal payments.

    $items = 
    [
        [
            'name'      =>  'Item 1',
            'price'     =>  '10.00',
            'quantity'  =>  1
        ],
        [
            'name'      =>  'Item 2',
            'price'     =>  '5.00',
            'quantity'  =>  2
        ]
    ];
    
    $clientId = 'AYOjBBa1DVAzyPWU5wQqImLPPfarEKPsEnXHPEyNEbgxPMX8zuVqqTLkQe_CBJRE-EzWS59QWdmYlDlc';
    $clientSecret = 'EIxwxlsxQ_wHkNkxdkv7UJG8EY91-BjiG2qT7T6DkeYRMXT8gHLVT9KgAblbxLEYEQPCPVUQhGjhELpb';
    
    // goto paypal
    $PaypalRestAPI = new PaypalRestAPI($clientId, $clientSecret, true);
    
    // add item once by once
    $PaypalRestAPI->addItem(
    [
        'name'      =>  'Item 1',
        'price'     =>  '10.00',
        'quantity'  =>  1
    ]);
    $PaypalRestAPI->addItem(
    [
        'name'      =>  'Item 2',
        'price'     =>  '5.00',
        'quantity'  =>  2
    ]);
    
    $PaypalRestAPI->setReturnUrl('http://localhost:8000/payment/success');
    $PaypalRestAPI->setCancelUrl('http://localhost:8000/payment/faild');
    
    $paypal_checkout = $PaypalRestAPI->doCheckout($items); // if $items not empty, will overwrite existing items
    if(!empty($paypal_checkout)) {
        header('Location:'.$paypal_checkout['payUrl']);
        exit;
    }
    
    // feedback
    $PaypalRestAPI = new PaypalRestAPI($clientId, $clientSecret, true);
    if($payment_result = $PaypalRestAPI->doComplete($status)) {
        dump($payment_result);
    }
    
    // get transaction details
    $PaypalRestAPI->transactionDetails($paymentId)
    
    // get transaction id
    $PaypalRestAPI->transactionID($paymentId)  // $paymentId - optional


