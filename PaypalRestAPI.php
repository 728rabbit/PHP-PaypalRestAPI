<?php
namespace App\Libs\payment;

class PaypalRestAPI {

    protected $_clientId = '';
    protected $_clientSecret = '';
    protected $_sandboxMode = false;
    protected $_error_message = '';

    protected $_checkout_items = [];
    protected $_currency = 'HKD';
    protected $_discount = 0;
    protected $_discount_description = 'Discount Amount';
    protected $_shipping = 0;
    protected $_tax = 0;

    protected $_return_url = '';
    protected $_cancel_url = '';
    protected $_end_point = 'https://api.paypal.com/v1';

    protected $_transaction_id = '';

    // Constructor to initialize the class with client credentials and sandbox mode.
    public function __construct($clientId, $clientSecret, $sandboxMode = false) {
        $this->_clientId = $clientId;
        $this->_clientSecret = $clientSecret;
        $this->_sandboxMode = $sandboxMode;

        // Set the endpoint to PayPal's sandbox URL if in sandbox mode.
        if (!empty($this->_sandboxMode)) {
            $this->_end_point = 'https://api.sandbox.paypal.com/v1';
        }
    }

    // Authentication method to get an access token from PayPal.
    public function doAuth() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_end_point . '/oauth2/token');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_clientId . ':' . $this->_clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->_error_message = curl_error($ch);
            return false;
        } else {
            $response = json_decode($response, true);
            if (!empty($response['error'])) {
                $this->_error_message = !empty($response['error_description']) ? $response['error_description'] : '';
                return false;
            }
        }
        curl_close($ch);

        return !empty($response) ? $response : false;
    }

    // Adds an item to the checkout list.
    public function addItem($data = []) {
        if (!empty($data)) {
            $this->_checkout_items = array_merge($this->_checkout_items, [$data]);
        }
        return $this;
    }

    // Sets the currency for the transaction.
    public function setCurrency($value) {
        $this->_currency = strtoupper($value);
        return $this;
    }

    // Sets the discount value and description.
    public function setDiscount($value, $description = '') {
        $this->_discount = round((double)max(0, $value), 2);
        if (!empty($description)) {
            $this->_discount_description = $description;
        }
        return $this;
    }

    // Sets the shipping cost.
    public function setShipping($value) {
        $this->_shipping = round((double)max(0, $value), 2);
        return $this;
    }

    // Sets the tax amount.
    public function setTax($value) {
        $this->_tax = round((double)max(0, $value), 2);
        return $this;
    }

    // Sets the return URL for successful payment.
    public function setReturnUrl($value) {
        $this->_return_url = (string)$value;
        return $this;
    }

    // Sets the cancel URL for cancelled payment.
    public function setCancelUrl($value) {
        $this->_cancel_url = (string)$value;
        return $this;
    }

    // Initiates the checkout process by creating a payment on PayPal.
    public function doCheckout($items = []) {
        if (!empty($items)) {
            $this->_checkout_items = $items;
        }

        if (!empty($this->_checkout_items) && $auth_info = $this->doAuth()) {
            $subtotal = 0;
            foreach ($this->_checkout_items as $key => $item) {
                $this->_checkout_items[$key]['currency'] = $this->_currency;
                $subtotal += round(($item['price'] * $item['quantity']), 2);
            }
            $subtotal = round($subtotal, 2);

            $payment = [
                'intent' => 'sale',
                'payer' => [
                    'payment_method' => 'paypal'
                ],
                'transactions' => [
                    [
                        'amount' => [
                            'total' => round(($subtotal + ($this->_discount * -1) + $this->_shipping + $this->_tax), 2),
                            'currency' => $this->_currency,
                            'details' => [
                                'subtotal' => $subtotal,
                                'discount' => $this->_discount,
                                'shipping' => $this->_shipping,
                                'tax' => $this->_tax,
                            ]
                        ],
                        'item_list' => [
                            'items' => $this->_checkout_items
                        ]
                    ]
                ],
                'redirect_urls' => [
                    'return_url' => $this->_return_url,
                    'cancel_url' => $this->_cancel_url
                ]
            ];

            $access_token = $auth_info['access_token'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_end_point . '/payments/payment');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment));

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $this->_error_message = curl_error($ch);
                return false;
            } else {
                $response = json_decode($response, true);
                if (!empty($response['error'])) {
                    $this->_error_message = !empty($response['error_description']) ? $response['error_description'] : '';
                    return false;
                }
            }
            curl_close($ch);

            $approvalUrl = '';
            if (!empty($response['links'])) {
                foreach ($response['links'] as $link) {
                    if (strtolower($link['rel']) == 'approval_url') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }
            }

            if (!empty($approvalUrl)) {
                return [
                    'payID' => $response['id'],
                    'payUrl' => $approvalUrl
                ];
            }
        }

        return false;
    }

    // Completes the payment after the user has approved it on PayPal.
    public function doComplete($status = '') {
        if (strtolower($status) == 'success' && $auth_info = $this->doAuth()) {
            $access_token = $auth_info['access_token'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_end_point . '/payments/payment/' . ($_GET['paymentId']) . '/execute');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['payer_id' => $_GET['PayerID']]));

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $this->_error_message = curl_error($ch);
                return false;
            } else {
                $response = json_decode($response, true);
                if (!empty($response['error'])) {
                    $this->_error_message = !empty($response['error_description']) ? $response['error_description'] : '';
                    return false;
                }
            }
            curl_close($ch);

            $this->_transaction_id = '';
            $this->_error_message = '';
            if (!empty($response) && !empty($response['state']) && strtolower($response['state']) == 'approved') {
                $this->_transaction_id = $response['transactions'][0]['related_resources'][0]['sale']['id'];
            } else if (!empty($response) && !empty($response['message'])) {
                $this->_error_message = $response['message'];
            }

            return $response;
        }

        return false;
    }

    // Retrieves the details of a specific transaction by payment ID.
    public function transactionDetails($paymentId = '') {
        if (!empty($paymentId) && $auth_info = $this->doAuth()) {
            $access_token = $auth_info['access_token'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_end_point . '/payments/payment/' . $paymentId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $this->_error_message = curl_error($ch);
                return false;
            } else {
                $response = json_decode($response, true);
                if (!empty($response['error'])) {
                    $this->_error_message = !empty($response['error_description']) ? $response['error_description'] : '';
                    return false;
                }
            }
            curl_close($ch);

            $this->_transaction_id = '';
            $this->_error_message = '';
            if (!empty($response) && !empty($response['state']) && strtolower($response['state']) == 'approved') {
                $this->_transaction_id = $response['transactions'][0]['related_resources'][0]['sale']['id'];
            } else if (!empty($response) && !empty($response['message'])) {
                $this->_error_message = $response['message'];
            }

            return $response;
        }

        return false;
    }

    // Retrieves the transaction ID of a completed transaction.
    public function transactionID($paymentId = '') {
        if (!empty($paymentId)) {
            $this->transactionDetails($paymentId);
        }
        return $this->_transaction_id;
    }

    // Retrieves the latest error message.
    public function getErrorMessage() {
        return $this->_error_message;
    }
}