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

// Function to check Midtrans transaction status
function check_transaction_status($order_id) {
    global $midtrans_server_key, $midtrans_status_url;
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $midtrans_status_url . $order_id . '/status',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($midtrans_server_key . ':')
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ['error' => "cURL Error #:" . $err];
    } else {
        return json_decode($response, true);
    }
}

// Function to create Midtrans payment token/URL
function create_midtrans_transaction($params) {
    global $midtrans_server_key, $midtrans_api_url;
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $midtrans_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($midtrans_server_key . ':')
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ['error' => "cURL Error #:" . $err];
    } else {
        return json_decode($response, true);
    }
}
?>