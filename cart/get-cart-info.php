<?php

defined( 'ABSPATH' ) || exit;

add_action('wp_ajax_get_cart_data', 'fetch_and_store_cart_info');
function fetch_and_store_cart_info() {
    if (is_user_logged_in()) {
        check_ajax_referer('mainData_nonce', '_wpnonce');
        global $tabibgroup_base_url;
        $current_user_id = get_current_user_id();
        
        // Retrieve API credentials from user meta
        $access_token = get_user_meta($current_user_id, 'api_access_token', true);
        $client = get_user_meta($current_user_id, 'api_client', true);
        $uid = get_user_meta($current_user_id, 'api_uid', true);

        // Ensure required API credentials exist
        if (empty($access_token) || empty($client) || empty($uid)) {
            //error_log('API credentials are missing for the current user.');
            return;
        }

        // Check if cart_id is already stored
        // $existing_cart_id = get_user_meta($current_user_id, 'api_cart_id', true);
        // if (!empty($existing_cart_id)) {
        //     return;
        // }

        // API URL
        $api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v1/carts.json?lang=en';

        // Perform the API request
        $response = wp_remote_get($api_url, [
            'headers' => [
                'access-token' => $access_token,
                'client' => $client,
                'uid' => $uid,
            ],
        ]);

        // Handle API response errors
        if (is_wp_error($response)) {
            error_log('Get Cart ID API Error: ' . $response->get_error_message());
            return;
        }

        // Decode and process the response body
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        // Check if the cart_id exists in the response
        if (isset($data['data']['cart']['cart_id'])) {
            $cart_id = sanitize_text_field($data['data']['cart']['cart_id']);
            $cart_items = $data['data']['cart']['cart_items'];
            $cart_is_active = $data['data']['cart']['is_active'];
            update_user_meta($current_user_id, 'api_cart_id', $cart_id ?? '');
            update_user_meta($current_user_id, 'api_cart_items', $cart_items ?? '0');
            update_user_meta($current_user_id, 'api_cart_is_active', $cart_is_active ?? '');
            wp_send_json_success($response);
        } else {
            error_log('Cart ID not found in API response');
            wp_send_json_error($data['message']);
        }
    }
}

add_action('wp_ajax_update_cart_data', 'fetch_and_update_cart_info');
function fetch_and_update_cart_info() {
    if (is_user_logged_in()) {
        check_ajax_referer('get_availability_type_nonce', '_wpnonce');
        global $tabibgroup_base_url;
        $current_user_id = get_current_user_id();
        
        // Retrieve API credentials from user meta
        $access_token = get_user_meta($current_user_id, 'api_access_token', true);
        $client = get_user_meta($current_user_id, 'api_client', true);
        $uid = get_user_meta($current_user_id, 'api_uid', true);

        // Ensure required API credentials exist
        if (empty($access_token) || empty($client) || empty($uid)) {
            //error_log('API credentials are missing for the current user.');
            return;
        }

        // Check if cart_id is already stored
        // $existing_cart_id = get_user_meta($current_user_id, 'api_cart_id', true);
        // if (!empty($existing_cart_id)) {
        //     return;
        // }

        // API URL
        $api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v1/carts.json?lang=en';

        // Perform the API request
        $response = wp_remote_get($api_url, [
            'headers' => [
                'access-token' => $access_token,
                'client' => $client,
                'uid' => $uid,
            ],
        ]);

        // Handle API response errors
        if (is_wp_error($response)) {
            error_log('Get Cart ID API Error: ' . $response->get_error_message());
            return;
        }

        // Decode and process the response body
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        // Check if the cart_id exists in the response
        if (isset($data['data']['cart']['cart_id'])) {
            $cart_id = sanitize_text_field($data['data']['cart']['cart_id']);
            $cart_items = $data['data']['cart']['cart_items'];
            $cart_is_active = $data['data']['cart']['is_active'];
            update_user_meta($current_user_id, 'api_cart_id', $cart_id ?? '');
            update_user_meta($current_user_id, 'api_cart_items', $cart_items ?? '0');
            update_user_meta($current_user_id, 'api_cart_is_active', $cart_is_active ?? '');
            wp_send_json_success($response);
        } else {
            error_log('Cart ID not found in API response');
            wp_send_json_error($data['message']);
        }
    }
}

// Update cart item inside the cart
add_action('wp_ajax_update_cart_item_info', 'update_cart_item_info_callback');
function update_cart_item_info_callback() {
    if (is_user_logged_in()) {
        check_ajax_referer('get_availability_type_nonce', '_wpnonce');
        global $tabibgroup_base_url;
        $current_user_id = get_current_user_id();
        
        // Retrieve API credentials from user meta
        $access_token = get_user_meta($current_user_id, 'api_access_token', true);
        $client = get_user_meta($current_user_id, 'api_client', true);
        $uid = get_user_meta($current_user_id, 'api_uid', true);

        // Ensure required API credentials exist
        if (empty($access_token) || empty($client) || empty($uid)) {
            //error_log('API credentials are missing for the current user.');
            return;
        }

        $cart_item_id = sanitize_text_field($_POST['cart_item_id']);
        $cart_id = sanitize_text_field($_POST['cart_id']);
        $reserved_date = sanitize_text_field($_POST['reserved_date']);
        $reserved_time = sanitize_text_field($_POST['reserved_time']);

        // Construct API URL
        // API URL
        $api_url = $tabibgroup_base_url . "/api/endpoints/mobile/v1/cart_items/{$cart_item_id}.json?lang=en&cart_id={$cart_id}&reserved_date={$reserved_date}&reserved_time={$reserved_time}";

        // Send PUT request with auth headers
        $response = wp_remote_request($api_url, [
            'method' => 'PUT',
            'headers' => [
                'access-token' => $access_token,
                'client' => $client,
                'uid' => $uid,
            ],
            'timeout' => 15
        ]);

        // Handle API response errors
        if (is_wp_error($response)) {
            error_log('Update Cart Item API Error: ' . $response->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 200) {
            wp_send_json_success(['message' => 'Cart item updated', 'response' => $body]);
        } else {
            wp_send_json_error(['code' => $code, 'response' => $body]);
        }
    }
}