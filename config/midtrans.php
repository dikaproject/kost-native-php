<?php
// Midtrans Configuration
// You should replace these with your actual Midtrans credentials

// Set to true for production environment, false for sandbox/development
$is_production = false;

// Get Midtrans API keys from environment or define directly here
$midtrans_client_key = $is_production ? 'YOUR_PRODUCTION_CLIENT_KEY' : 'SB-Mid-client-TXNqPL_AVexaGvGr';
$midtrans_server_key = $is_production ? 'YOUR_PRODUCTION_SERVER_KEY' : 'SB-Mid-server-Q_WOMulvwSXfwpw5-4T616K-';

// Midtrans API URLs
$midtrans_api_url = $is_production ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
$midtrans_status_url = $is_production ? 'https://api.midtrans.com/v2/' : 'https://api.sandbox.midtrans.com/v2/';

// Midtrans JS URL
$midtrans_js_url = $is_production ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';

/**
 * Get Midtrans payment token for Snap.js
 * 
 * @param array $transaction_data Transaction data
 * @return string Midtrans token
 */
function get_midtrans_token($transaction_data) {
    global $midtrans_server_key, $is_production;
    
    $url = $is_production 
        ? 'https://app.midtrans.com/snap/v1/transactions' 
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($transaction_data),
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Basic ' . base64_encode($midtrans_server_key . ':')
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return ['error' => 'cURL Error: ' . $err];
    }
    
    $response_arr = json_decode($response, true);
    return isset($response_arr['token']) ? $response_arr['token'] : ['error' => $response_arr['error_messages'][0] ?? 'Unknown error'];
}

/**
 * Check Midtrans transaction status
 * 
 * @param string $order_id Order ID
 * @return array Transaction details
 */
function check_transaction_status($order_id) {
    global $midtrans_server_key, $is_production;
    
    $url = $is_production 
        ? "https://api.midtrans.com/v2/{$order_id}/status" 
        : "https://api.sandbox.midtrans.com/v2/{$order_id}/status";
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'authorization: Basic ' . base64_encode($midtrans_server_key . ':')
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return ['error' => 'cURL Error: ' . $err];
    }
    
    return json_decode($response, true);
}

/**
 * Create Midtrans transaction (alias function for get_midtrans_token)
 * 
 * @param array $transaction_data Transaction data
 * @return array Transaction response containing token
 */
function create_midtrans_transaction($transaction_data) {
    $token = get_midtrans_token($transaction_data);
    if (is_string($token)) {
        return ['token' => $token];
    }
    return $token; // Return error array
}