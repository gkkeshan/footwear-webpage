<?php
/**
 * ============================================================================
 * PAYHERE PAYMENT GATEWAY CONFIGURATION & HASH GENERATION
 * ============================================================================
 * Purpose: Manages credentials and cryptographic signature validation for
 *          Sri Lanka's PayHere payment gateway.
 * Security: Uses two-way MD5 hash generation:
 *           1. Checkout Hash: Sent from merchant to PayHere to prevent front-end price tampering.
 *           2. Notify Hash: Received from PayHere webhook to prevent forged payment approvals.
 */

// PayHere Merchant Account credentials (obtained from PayHere Merchant Portal)
define('PAYHERE_MERCHANT_ID', '1238502');
define('PAYHERE_MERCHANT_SECRET', 'Mzc1NTY4NDA1OTM5MTg1NTU3NzgyODk5ODE1ODA0MzMxMzMwOTAzNw==');
define('PAYHERE_CURRENCY', 'LKR'); // Sri Lankan Rupees (LKR)

// Environment toggle: true for development/testing sandbox, false for real production transactions
define('PAYHERE_SANDBOX', true);

/**
 * Returns the PayHere checkout endpoint URL based on current environment mode.
 * 
 * @return string The gateway submission URL
 */
function getPayHereUrl() {
    return PAYHERE_SANDBOX 
        ? 'https://sandbox.payhere.lk/pay/checkout' 
        : 'https://www.payhere.lk/pay/checkout';
}

/**
 * Generates the PayHere Checkout MD5 Security Hash.
 *
 * Why this is needed:
 * If an attacker inspects the checkout form and changes the amount from 5000 to 1,
 * PayHere recalculates the hash with our secret. Since the attacker doesn't know
 * the Merchant Secret, their hash won't match, and PayHere will reject the transaction.
 *
 * Formula required by PayHere:
 * UPPERCASE(MD5(merchant_id + order_id + formatted_amount + currency + UPPERCASE(MD5(merchant_secret))))
 *
 * @param int|string $orderId  Unique ID of the order in our database
 * @param float|int  $amount   Total payable amount
 * @param string     $currency Currency code (defaults to LKR)
 * @return string 32-character uppercase hexadecimal MD5 hash
 */
function generatePayHereHash($orderId, $amount, $currency = PAYHERE_CURRENCY) {
    $merchantId = PAYHERE_MERCHANT_ID;
    $merchantSecret = PAYHERE_MERCHANT_SECRET;
    
    // Amount must strictly have 2 decimal places with '.' as decimal separator
    $formattedAmount = number_format($amount, 2, '.', '');
    
    $hash = strtoupper(
        md5(
            $merchantId . 
            $orderId . 
            $formattedAmount . 
            $currency . 
            strtoupper(md5($merchantSecret))
        )
    );
    return $hash;
}

/**
 * Verifies the authenticity of a PayHere IPN (Instant Payment Notification) callback.
 *
 * When PayHere contacts our server asynchronously (`payhere_notify.php`), it passes
 * an `md5sig` parameter. We independently compute the hash from the POST data and compare.
 *
 * Formula for notification verification:
 * UPPERCASE(MD5(merchant_id + order_id + payhere_amount + payhere_currency + status_code + UPPERCASE(MD5(merchant_secret))))
 *
 * @param array $postData The $_POST array received from PayHere IPN
 * @return bool True if signature matches authentic PayHere transmission, False otherwise
 */
function verifyPayHereNotifyHash($postData) {
    $merchantId = $postData['merchant_id'] ?? '';
    $orderId = $postData['order_id'] ?? '';
    $payhereAmount = $postData['payhere_amount'] ?? '';
    $payhereCurrency = $postData['payhere_currency'] ?? '';
    $statusCode = $postData['status_code'] ?? '';
    $md5sig = $postData['md5sig'] ?? '';
    
    $merchantSecret = PAYHERE_MERCHANT_SECRET;
    
    // Compute our local signature using our private secret
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
    
    // Compare local signature with the signature sent by PayHere
    return ($localMd5sig === $md5sig);
}

