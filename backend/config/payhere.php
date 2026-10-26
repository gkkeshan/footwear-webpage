<?php
// PayHere Payment Gateway Configuration
// Merchant ID and Merchant Secret provided by PayHere account

define('PAYHERE_MERCHANT_ID', '1238502');
define('PAYHERE_MERCHANT_SECRET', 'Mzc1NTY4NDA1OTM5MTg1NTU3NzgyODk5ODE1ODA0MzMxMzMwOTAzNw==');
define('PAYHERE_CURRENCY', 'LKR');

// Toggle Sandbox / Live mode: true = Sandbox, false = Live
define('PAYHERE_SANDBOX', true);

// Get PayHere Checkout Submission URL
function getPayHereUrl() {
    return PAYHERE_SANDBOX 
        ? 'https://sandbox.payhere.lk/pay/checkout' 
        : 'https://www.payhere.lk/pay/checkout';
}

// Generate PayHere MD5 Checkout Hash
function generatePayHereHash($orderId, $amount, $currency = PAYHERE_CURRENCY) {
    $merchantId = PAYHERE_MERCHANT_ID;
    $merchantSecret = PAYHERE_MERCHANT_SECRET;
    
    $hash = strtoupper(
        md5(
            $merchantId . 
            $orderId . 
            number_format($amount, 2, '.', '') . 
            $currency . 
            strtoupper(md5($merchantSecret))
        )
    );
    return $hash;
}

// Verify PayHere Notify callback signature MD5 Hash
function verifyPayHereNotifyHash($postData) {
    $merchantId = $postData['merchant_id'] ?? '';
    $orderId = $postData['order_id'] ?? '';
    $payhereAmount = $postData['payhere_amount'] ?? '';
    $payhereCurrency = $postData['payhere_currency'] ?? '';
    $statusCode = $postData['status_code'] ?? '';
    $md5sig = $postData['md5sig'] ?? '';
    
    $merchantSecret = PAYHERE_MERCHANT_SECRET;
    
    $localMd5sig = strtoupper(
        md5(
            $merchantId . 
            $orderId . 
            $payhereAmount . 
            $payhereCurrency . 
            $statusCode . 
            strtoupper(md5($merchantSecret))
        )
    );
    
    return ($localMd5sig === $md5sig);
}
