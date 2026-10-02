<?php

defined( 'ABSPATH' ) || exit;

add_action( 'bricks/form/custom_action', 'handle_pw_reset_TG', 10, 1 );
function handle_pw_reset_TG( $form ) {
	$form_fields   = $form->get_fields();
	$form_id       = $form_fields['formId'];

	if( $form_id !== 'reisup' ) return;

	$current_user_id = get_current_user_id();
	$uid = get_user_meta($current_user_id, 'api_uid', true);

	// Get submitted fields
	$new_password = $form_fields['form-field-olzksk'] ?? '';
	$password_confirmation = $form_fields['form-field-javdod'] ?? '';
	$password = $form_fields['form-field-yyfttd'] ?? '';

	// API URL
	$api_url = tg_api_url('/api/v7/auth/password.json');

	// API request body
    $body = [
        'password' => $new_password,
        'password_confirmation' => $password_confirmation,
        'old_password' => $password,
        'uid' => $uid,
    ];

	// Debug Log request data
	//error_log('Request body: ' . print_r($body, true));

    // Make the PUT request
	$response = wp_remote_post($api_url, [
		'method' => 'PUT',
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 30,
    ]);

	// Check for API request errors
	if (is_wp_error($response)) {
		$error_message = $response->get_error_message();
		$form->set_result([
			'action' => 'rp_api_custom_action',
			'type'    => 'error',
			'message' => $error_message,
		]);
		return;
	}

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
	$api_response = json_decode($response_body, true);
	$status = $api_response['status'] ?? '';

	if ($response_body && $response_code === 200 && $status === 'success') {
		// Get current user & Update WordPress password
		$current_user = get_user_by('id', $current_user_id);
		if ($current_user) {
			wp_set_password($new_password, $current_user->ID);
		}

		// Success message
		// error_log('Password updated successfully for WordPress user.');

		// Handle response success
		$success_message = $api_response['data']['message'] ?? 'تم تغيير كلمة المرور بنجاح.';
		$form->set_result([
			'action' => 'tg_rp_custom_action',
			'type'    => 'success',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($success_message)
			),
		]);

		// Log the user in
		// wp_set_current_user($current_user_id);
		// wp_set_auth_cookie($current_user_id);

		// Debugging log for successful login
		// error_log("User logged in successfully: {$current_user_id}");

		// Redirect to account page
		$redirect_to = get_site_url() . '/account';
		$form->set_result(
			[
				'action'          => 'tg_success_redirect',
				'type'            => 'redirect',
				'redirectTo'      => $redirect_to,
				'redirectTimeout' => 1000
			]
		);
	} else {
		// Handle response failure and print message for user
		// $error_message_user = $api_response['message'] ?? 'Reset password was unsuccessful.';
		$error_message_user = 'الرجاء إدخال كلمة المرور الحالية الصالحة وحاول مرة أخرى!';
		$form->set_result([
			'action' => 'rp_api_tg_action',
			'type'    => 'error',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($error_message_user)
			),
		]);

		// Error log API request failure
		// error_log("Reset password API Response: $error_message_user");
	}
}

// Bricks reset password form password field validation
add_filter( 'bricks/form/validate', function( $errors, $form ) {
    $form_settings = $form->get_settings();
    $form_fields   = $form->get_fields();
    $form_id       = $form_fields['formId'];
    if ($form_id !== 'reisup') return $errors;

    $new_password = $form->get_field_value( 'olzksk' );
    $confirmation_password = $form->get_field_value( 'javdod' );
    if ($new_password !== $confirmation_password) {
        $errors[] = esc_html__( 'كلمة المرور الخاصة بك وتأكيد كلمة المرور غير متطابقين.', 'bricks' );
    }
    return $errors;
}, 10, 2 );