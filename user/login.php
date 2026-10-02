<?php

defined( 'ABSPATH' ) || exit;

add_action( 'bricks/form/custom_action', 'login_to_TG', 10, 1 );
function login_to_TG( $form ) {
	global $tabibgroup_base_url;
	$form_fields   = $form->get_fields();
	$form_id       = $form_fields['formId'];

	if( $form_id !== 'bwhekz' ) return;

	// Get submitted fields
	$prefix = '00966';
	$raw_phone = $form_fields['form-field-uplxme'] ?? '';
	$phone = $prefix . $raw_phone;
	$password = $form_fields['form-field-yyfttd'] ?? '';
	$redirect_url = $form_fields['form-field-tfihez'] ?? '';

	// API URL
	$api_url = $tabibgroup_base_url . '/api/endpoints/auth/users/v2/sessions.json';

	// API request body
	$body = array(
		'lang' => 'en',
		'phone' => $phone,
		'password' => $password
	);

	$response = wp_remote_post($api_url, array(
		'method'    => 'POST',
		'headers'   => array('Content-Type' => 'application/json'),
		'body'      => json_encode($body)
	));

	// Check for API request errors
	if (is_wp_error($response)) {
		$error_message = $response->get_error_message();
		$form->set_result([
			'action' => 'log_api_custom_action',
			'type'    => 'error',
			'message' => $error_message,
		]);
		return;
	}

	$response_code = wp_remote_retrieve_response_code($response);
	$response_body = json_decode(wp_remote_retrieve_body($response), true);
	$headers = wp_remote_retrieve_headers($response);
	$status = $response_body['status'] ?? '';
	$first_name = $response_body['data']['user']['first_name'] ?? '';
	$middle_name = $response_body['data']['user']['middle_name'] ?? '';
	$last_name = $response_body['data']['user']['last_name'] ?? '';
	$full_name = $first_name . ' ' . $middle_name . ' ' . $last_name;
	$email = $response_body['data']['user']['email'] ?? '';
	$date_of_birth = $response_body['data']['user']['date_of_birth'] ?? '';
	$gender = $response_body['data']['user']['gender'] ?? '';
	if ($gender === 'M') {
		$gender = 'مذكر';
	} elseif ($gender === 'F') {
		$gender = 'نسائي';
	} else {
		$gender = '';
	}
	
	$phoneNumber = $response_body['data']['user']['phone'] ?? '';
	$formattedNumber = '+' . ltrim($phoneNumber ?? '', '0');
	$formattedNumber = preg_replace('/(\+966)(\d{3})(\d{3})(\d{3})/', '$1 $2 $3 $4', $formattedNumber);

	// Debugging log for API response
	// error_log("API response status: $status");

	if ($response_body && $response_code === 200 && $status === 'success') {
		// Retrieve the access token, client, uid, cookie
		$access_token = $headers['access-token'] ?? '';
		$client = $headers['client'] ?? '';
		$uid = $headers['uid'] ?? '';
		$id = $response_body['data']['user']['id'] ?? '';

		// Check if the access token is returned
		if ($access_token) {
			// Check if the user exists in WordPress
			//$user = get_user_by('email', $response_body['data']['user']['email']);
			$user = get_user_by('login', $phoneNumber);
			if (!$user) {
				// Create a new WordPress user if not exists
				$user_id = wp_create_user($phone, $password, $email);

				if (is_wp_error($user_id)) {
					$error_message_wp = $user_id->get_error_message();
					$form->set_result([
						'action' => 'log_wp_action',
						'type'    => 'error',
						'message' => $error_message_wp,
					]);
					return;
				}

				$user = get_user_by('id', $user_id);

				// Debugging log for new user creation
				// error_log("New user created with ID: $user_id");
				
				wp_update_user([
					'ID' => $user_id,
					'first_name' => $first_name,
					'last_name' => $last_name,
					'display_name' => $full_name,
				]);

				// Store the access token as user meta
				update_user_meta($user->ID, 'api_access_token', $access_token);
				update_user_meta($user->ID, 'api_client', $client);
				update_user_meta($user->ID, 'api_uid', $uid);
				update_user_meta($user->ID, 'api_id', $id);
				update_user_meta($user->ID, 'api_phone', $formattedNumber);
				update_user_meta($user->ID, 'api_full_name', $full_name);
				update_user_meta($user->ID, 'api_date_of_birth', $date_of_birth);
				update_user_meta($user->ID, 'api_gender', $gender);

				// Log the user in
				wp_set_current_user($user->ID);
				wp_set_auth_cookie($user->ID);

				// Debugging log for successful login
				//error_log("User logged in successfully: {$user->ID}");

				$access_token_cart = get_user_meta($user->ID, 'api_access_token', true);
				$client_cart = get_user_meta($user->ID, 'api_client', true);
				$uid_cart = get_user_meta($user->ID, 'api_uid', true);

				//error_log("Access Token: $access_token_cart, Client: $client_cart, UID: $uid_cart");

				// Make the second API request to get cart details
				$cart_url = $tabibgroup_base_url . '/api/endpoints/mobile/v1/carts.json?lang=en';

				$cart_response = wp_remote_get($cart_url, [
					'headers' => [
						'access-token' => $access_token_cart,
						'client' => $client_cart,
						'uid' => $uid_cart,
					],
				]);
	
				if (is_wp_error($cart_response)) {
					//error_log('Cart API Error: ' . $cart_response->get_error_message());
				} else {
					$cart_response_code = wp_remote_retrieve_response_code($cart_response);
					$cart_response_body = wp_remote_retrieve_body($cart_response);
				
					// Decode the response and check for cart_id
					$cart_data = json_decode($cart_response_body, true);
	
					// Update user meta with cart data
					if (isset($cart_data['data']['cart'])) {
						$cart = $cart_data['data']['cart'];
						update_user_meta($user->ID, 'api_cart_id', $cart['cart_id']);
						update_user_meta($user->ID, 'api_cart_items', $cart['cart_items']);
						update_user_meta($user->ID, 'api_cart_is_active', $cart['is_active']);
						error_log('Cart Data updated');
					}
				}

				// Redirect to a home page
				$redirect_to = get_site_url() . '/';
				$form->set_result(
					[
						'action'          => 'tg_afterlogin_redirect',
						'type'            => 'redirect',
						'redirectTo'      => $redirect_to,
						'redirectTimeout' => 0
					]
				);
			} else {
				wp_update_user([
					'ID' => $user->ID,
					'first_name' => $first_name,
					'last_name' => $last_name,
					'display_name' => $full_name,
				]);

				// Store the access token as user meta
				update_user_meta($user->ID, 'api_access_token', $access_token);
				update_user_meta($user->ID, 'api_client', $client);
				update_user_meta($user->ID, 'api_uid', $uid);
				update_user_meta($user->ID, 'api_id', $id);
				update_user_meta($user->ID, 'api_phone', $formattedNumber);
				update_user_meta($user->ID, 'api_full_name', $full_name);
				update_user_meta($user->ID, 'api_date_of_birth', $date_of_birth);
				update_user_meta($user->ID, 'api_gender', $gender);

				// Log the user in
				wp_set_current_user($user->ID);
				wp_set_auth_cookie($user->ID);

				// Debugging log for successful login
				// error_log("User logged in successfully (Already registered): {$user->ID}");

				$access_token_cart = get_user_meta($user->ID, 'api_access_token', true);
				$client_cart = get_user_meta($user->ID, 'api_client', true);
				$uid_cart = get_user_meta($user->ID, 'api_uid', true);

				// error_log("Access Token: $access_token_cart, Client: $client_cart, UID: $uid_cart");

				// CART API URL
				$cart_url = $tabibgroup_base_url . '/api/endpoints/mobile/v1/carts.json?lang=en';

				$cart_response = wp_remote_get($cart_url, [
					'headers' => [
						'access-token' => $access_token_cart,
						'client' => $client_cart,
						'uid' => $uid_cart,
					],
				]);
	
				if (is_wp_error($cart_response)) {
					error_log('Cart API Error: ' . $cart_response->get_error_message());
				} else {
					$cart_response_code = wp_remote_retrieve_response_code($cart_response);
					$cart_response_body = wp_remote_retrieve_body($cart_response);
				
					// Decode the response and check for cart_id
					$cart_data = json_decode($cart_response_body, true);
	
					// Update user meta with cart data
					if (isset($cart_data['data']['cart'])) {
						$cart = $cart_data['data']['cart'];
						update_user_meta($user->ID, 'api_cart_id', $cart['cart_id']);
						update_user_meta($user->ID, 'api_cart_items', $cart['cart_items']);
						update_user_meta($user->ID, 'api_cart_is_active', $cart['is_active']);
					}
				}

				// Redirect to home page
				if ( empty ($redirect_url) ) {
					$redirect_url = get_site_url() . '/';
				}
				$form->set_result(
					[
						'action'          => 'log_success_redirect',
						'type'            => 'redirect',
						'redirectTo'      => $redirect_url,
						'redirectTimeout' => 0
					]
				);
			}
		} else {
			// Return the API response to the user
			$error_message_login_failure = $response_body['message'] ?? 'Login failed no user account found.';
			$form->set_result([
				'action' => 'log_failure_tg_action',
				'type'    => 'error',
				'message' => sprintf(
					esc_html__('%s', 'bricks'),
					esc_html($error_message_login_failure)
				),
			]);
		}
	} else {
		// Handle response failure and print message for user
		$error_message_user = $response_body['message'] ?? 'Login was unsuccessful.';
		$form->set_result([
			'action' => 'log_api_tg_action',
			'type'    => 'error',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($error_message_user)
			),
		]);
	}
}