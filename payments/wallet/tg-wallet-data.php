<?php 

defined( 'ABSPATH' ) || exit;

// Add action for AJAX request upon successful payfort payment
add_action( 'wp_ajax_process_wallet_data', 'handle_wallet_data' );
add_action( 'wp_ajax_nopriv_process_wallet_data', 'handle_wallet_data' );
function handle_wallet_data() {
    global $tabibgroup_base_url;

    // Get PayFort data from AJAX request
    $wallet_data = isset($_POST['wallet_data']) ? json_decode(stripslashes($_POST['wallet_data']), true) : [];

    // If PayFort data is present, process it
    if (!empty($wallet_data)) {
        //wp_send_json_success($wallet_data);
        // Get current user meta for access-token, client, and uid
        $current_user_id = get_current_user_id();
        $access_token = get_user_meta($current_user_id, 'api_access_token', true);
        $client = get_user_meta($current_user_id, 'api_client', true);
        $uid = get_user_meta($current_user_id, 'api_uid', true);

        // API URL
        $api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v3/bills/payment_bill.json?lang=en';

        $response = wp_remote_request($api_url, [
            'method'    => 'PUT',
            'body'      => json_encode($wallet_data),
            'headers'   => [
                'Content-Type' => 'application/json',
                'access-token' => $access_token,
                'client' => $client,
                'uid' => $uid,
            ],
        ]);

        // $data = json_encode($wallet_data);
        // error_log(print_r($data, true));

        // Check for errors with the request
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
    
        // If the request was successful, send a success response
        if ($response_code === 200) {
            wp_send_json_success(array('message' => 'Wallet Payment data processed successfully'));
        } else {
            wp_send_json_error(['message' => 'Wallet Payment update failed. API Response: ', 'response' => $response_body]);
        }

    } else {
        wp_send_json_error(array('message' => 'No Wallet Payment data received.'));
    }

    wp_die();
}