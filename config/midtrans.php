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

// Function to check Midtrans transaction status with improved error handling
function check_transaction_status($order_id) {
    global $midtrans_server_key, $midtrans_status_url;
    
    if (empty($order_id)) {
        return ['error' => "Invalid order ID"];
    }
    
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
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    // Log the response for debugging if needed
    // file_put_contents('midtrans_status_log.txt', date('Y-m-d H:i:s') . " - Order ID: $order_id - Response: $response\n", FILE_APPEND);
    
    if ($err) {
        return ['error' => "cURL Error #:" . $err];
    } else {
        $response_data = json_decode($response, true);
        
        // Check for error response from Midtrans
        if ($httpcode >= 400) {
            return [
                'error' => "HTTP Error: " . $httpcode, 
                'details' => $response_data,
                'raw_response' => $response
            ];
        }
        
        return $response_data;
    }
}

// Function to create Midtrans payment token/URL with improved error handling and validation
function create_midtrans_transaction($params) {
    global $midtrans_server_key, $midtrans_api_url;
    
    if (empty($params) || !is_array($params)) {
        return ['error' => "Invalid transaction parameters"];
    }
    
    // Validate required fields in params
    if (!isset($params['transaction_details']) || 
        !isset($params['transaction_details']['order_id']) || 
        !isset($params['transaction_details']['gross_amount'])) {
        return ['error' => "Missing required transaction details"];
    }
    
    // Additional validation for customer details
    if (isset($params['customer_details'])) {
        // Validate email format
        if (isset($params['customer_details']['email']) && 
            !filter_var($params['customer_details']['email'], FILTER_VALIDATE_EMAIL)) {
            // Provide a valid default email if the one provided is invalid
            $params['customer_details']['email'] = 'customer_' . time() . '@example.com';
        }
        
        // Make sure phone has a value
        if (empty($params['customer_details']['phone'])) {
            $params['customer_details']['phone'] = '08123456789';
        }
    }
    
    $curl = curl_init();
    
    $post_data = json_encode($params);
    
    // Log the request for debugging if needed
    // file_put_contents('midtrans_request_log.txt', date('Y-m-d H:i:s') . " - Request: $post_data\n", FILE_APPEND);
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $midtrans_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($midtrans_server_key . ':')
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    // Log the response for debugging if needed
    // file_put_contents('midtrans_response_log.txt', date('Y-m-d H:i:s') . " - Response: $response\n", FILE_APPEND);
    
    if ($err) {
        return ['error' => "cURL Error #:" . $err];
    } else {
        $response_data = json_decode($response, true);
        
        // Check for error response from Midtrans
        if ($httpcode >= 400) {
            return [
                'error' => "HTTP Error: " . $httpcode, 
                'details' => $response_data,
                'raw_response' => $response,
                'request_data' => $params
            ];
        }
        
        return $response_data;
    }
}
?>