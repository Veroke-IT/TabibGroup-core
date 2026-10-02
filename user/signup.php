<?php

defined( 'ABSPATH' ) || exit;

add_action( 'bricks/form/custom_action', 'signup_to_TG', 10, 1 );
function signup_to_TG( $form ) {
	$form_fields   = $form->get_fields();
	$form_id       = $form_fields['formId'];

	if( $form_id !== 'wyznna' ) return;

	// Get submitted fields
	$name = $form_fields['form-field-tgshap'] ?? '';
	$prefix = '00966';
	$raw_phone = $form_fields['form-field-uplxme'] ?? '';
	$phone =  $prefix . $raw_phone;
	$email = $form_fields['form-field-repcjn'] ?? '';
	$password = $form_fields['form-field-yyfttd'] ?? '';
	$password_confirmation = $form_fields['form-field-uwdypv'] ?? '';

	// API URL
	$api_url = tg_api_url('/api/endpoints/auth/users/v2/registrations.json');

	// API request body
	$body = array(
		'lang' => 'en',
		'name' => $name,
		'phone' => $phone,
		'password' => $password,
		'email' => $email,
		'password_confirmation' => $password_confirmation,
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
	$status = $response_body['status'] ?? '';

	// Debugging log for API response
	// error_log("API response status: $status");

	if ($response_body && $response_code === 200 && $status === 'success') {
		// Extract data from the response
		$id = $response_body['data']['id'] ?? '';
		$confirmation_code = $response_body['data']['confirmation_code'] ?? '';

		// Save registration data in session
		$_SESSION['registration_data'] = [
			'id'                  => $id,
			'confirmation_code'   => $confirmation_code,
			'password'            => $password,
			'password_confirmation' => $password_confirmation,
			'name'                => $name,
			'phone'               => $phone,
			'email'               => $email,
			'accepted_terms'      => true,
		];

		// Log the global variable
		//error_log("Registration Data Stored in Session: " . print_r($_SESSION['registration_data'], true));

		// Handle response success
		$success_message = $response_body['message'] ?? 'Account verification completed.';
		$form->set_result([
			'action' => 'reg_tg_action',
			'type'    => 'success',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($success_message)
			),
		]);
	} else {
		// Handle response failure and print message for user
		$error_message_user = $response_body['message'] ?? 'Signup was unsuccessful.';
		$form->set_result([
			'action' => 'reg_api_tg_action',
			'type'    => 'error',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($error_message_user)
			),
		]);

		// Error log API request failure
		$error_message_API_failure = $response_body['message'] ?? 'API request was unsuccessful.';
		error_log("Signun API Response: $error_message_API_failure");
	}
}

// Bricks signup form password field validation
add_filter( 'bricks/form/validate', function( $errors, $form ) {
	$form_settings = $form->get_settings();
	$form_fields   = $form->get_fields();
	$form_id       = $form_fields['formId'];
	if ($form_id !== 'wyznna') return $errors;

	$password = $form->get_field_value( 'yyfttd' );
	$confirm_password = $form->get_field_value( 'uwdypv' );
	if ($password !== $confirm_password) {
		$errors[] = esc_html__( 'كلمة المرور الخاصة بك وتأكيد كلمة المرور غير متطابقين.', 'bricks' );
	}
	return $errors;
}, 10, 2 );

// AJAX endpoint to fetch the registration data
add_action('wp_ajax_get_registration_data', 'get_registration_data');
add_action('wp_ajax_nopriv_get_registration_data', 'get_registration_data');
function get_registration_data() {
    // Check if registration data exists
    if (!isset($_SESSION['registration_data'])) {
		//error_log("Registration data is not found in get_registration_data AJAX endpoint.");
        wp_send_json_error(['message' => 'Registration data not found.']);
        return;
    }
    // Send the registration data as a response
    wp_send_json_success(['registration_data' => $_SESSION['registration_data']]);
}

// Custom action to send confirmation API request
add_action('wp_ajax_confirm_user_registration', 'confirm_user_registration');
add_action('wp_ajax_nopriv_confirm_user_registration', 'confirm_user_registration');
function confirm_user_registration() {
	check_ajax_referer('confirm_user_registration_nonce', '_wpnonce');

	// Check if registration data exists in the session
	if (!isset($_SESSION['registration_data'])) {
		wp_send_json_error(['message' => 'Registration data is not found in confirm_user_registration action.']);
		error_log("Registration data is not found in confirm_user_registration action.");
		return;
	}
	
	$data = $_SESSION['registration_data'];

	// API URL
	$api_url = tg_api_url('/api/endpoints/auth/users/v2/confirmations.json?lang=ar');

	// Make the API request
	$response = wp_remote_post($api_url, [
		'method'  => 'POST',
		'headers'   => array('Content-Type' => 'application/json'),
		'body'    => json_encode($data),
	]);

	// Check for API request errors
	if (is_wp_error($response)) {
		$error_message = $response->get_error_message();
		error_log("API Request Error: $error_message");
		wp_send_json_error(['message' => "Request Failed: $error_message"]);
	}

	$response_code = wp_remote_retrieve_response_code($response);
	$response_body = json_decode(wp_remote_retrieve_body($response), true);
	$status = $response_body['status'] ?? '';

	// Debugging Log for the API response
	// error_log("API Response Code: $response_code");
	// error_log("API Response Body: " . print_r($response_body, true));

	if ($response_body && $response_code === 200 && $status === 'success') {
		// Attempt to create the WordPress user
		$user_id = wp_create_user($data['phone'], $data['password'], $data['email']);
		if (is_wp_error($user_id)) {
			$error_message_wp = $user_id->get_error_message();
			error_log("User Creation Error: $error_message_wp");
			wp_send_json_error(['message' => "Failed to create user: $error_message_wp"]);
		}

		// Update user details
		wp_update_user([
			'ID'           => $user_id,
			'first_name'   => $data['name'],
			'display_name' => $data['name'],
		]);

		// Store the access token as user meta
		// update_user_meta($user_id, 'api_phone', $formattedNumber);
		// update_user_meta($user_id, 'api_full_name', $full_name);

		// Log the user in
		// wp_set_current_user($user_id);
		// wp_set_auth_cookie($user_id);

		// Send success response
		wp_send_json_success(['message' => 'User successfully created.']);
	} else {
		// Handle response failure and print message for user
		$error_message_user = $response_body['message'] ?? 'Signup was unsuccessful.';
		$form->set_result([
			'action' => 'log_api_tg_action',
			'type'    => 'error',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($error_message_user)
			),
		]);

		// Error log API request failure
		$error_message_API_failure = $response_body['message'] ?? 'API request was unsuccessful.';
		error_log("Signup API Response: $error_message_API_failure");
	}
}