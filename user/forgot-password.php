<?php

defined( 'ABSPATH' ) || exit;

add_action('bricks/form/custom_action', 'handle_forgot_pw_TG', 10, 1);
function handle_forgot_pw_TG($form) {
    $form_fields = $form->get_fields();
    $form_id     = $form_fields['formId'];

    if ($form_id !== 'jyyvop') return;

    // Get submitted fields
	$prefix = '00966';
	$raw_phone = $form_fields['form-field-uplxme'] ?? '';
	$phone = $prefix . $raw_phone;

    // API URL
	$api_url = tg_api_url('/api/endpoints/mobile/v1/users.json?lang=en&phone=' . urlencode($phone));
    $response = wp_remote_get($api_url);

	if (is_wp_error($response)) {
		$error_message = $response->get_error_message();
		$form->set_result([
			'action' => 'tg_fp_api_custom_action',
			'type'    => 'error',
			'message' => $error_message,
		]);
		return;
	}

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $api_response  = json_decode($response_body, true);
    $status = $api_response['status'] ?? '';

    if ($response_body && $response_code === 200 && $status === 'success') {
		
        // Save data in session
        $_SESSION['phone']              = $phone;
        $_SESSION['confirmation_token'] = $api_response['data']['user']['confirmation_token'] ?? '';

		// Debug Log session data
		//error_log('Session phone set : ' . print_r($_SESSION['phone'], true));
		//error_log('Session confirmation_token set: ' . print_r($_SESSION['confirmation_token'], true));

        $form->set_result([
            'action' => 'tg_fp_custom_action',
            'type'   => 'success',
            'message' => 'تم التحقق من رقم الهاتف. تابع لإعادة تعيين كلمة المرور الخاصة بك.',
        ]);

    } else {
        // Handle response failure and print message for user
		$error_message_user = $api_response['message'] ?? 'Accont update was unsuccessful.';
		$form->set_result([
			'action' => 'log_api_tg_action',
			'type'    => 'error',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($error_message_user)
			),
		]);

		// Error log API request failure
		$error_message_API_failure = $api_response['message'] ?? 'API request was unsuccessful.';
		error_log("Forgot Password API Response: $error_message_API_failure");
    }
}

// Confirmation action to handle update pw
add_action('bricks/form/custom_action', 'handle_update_password_TG', 10, 1);
function handle_update_password_TG($form) {
    $form_fields = $form->get_fields();
    $form_id     = $form_fields['formId'];

    if ($form_id !== 'rfzfrp') return;

    // Get stored data from session
    $phone              = $_SESSION['phone'] ?? '';
    $confirmation_token = $_SESSION['confirmation_token'] ?? '';

    // Debug Log session data
    // error_log('Session phone get: ' . print_r($phone, true));
    // error_log('Session confirmation_token get: ' . print_r($confirmation_token, true));

    if (!$phone || !$confirmation_token) {
        error_log('Forgot Password Error: Missing session data need confirmation again.');
        $form->set_result([
            'action' => 'missing_data_tg_action',
            'type'   => 'error',
            'message' => 'لم يتم العثور على رمز التأكيد.',
        ]);

        $redirect_to = get_site_url() . $_SERVER['REQUEST_URI'];
		$form->set_result(
			[
				'action'          => 'reload_tg_action',
				'type'            => 'redirect',
				'redirectTo'      => $redirect_to,
				'redirectTimeout' => 2000
			]
		);
        return;
    }

    // Get submitted fields
	$new_password =  $form_fields['form-field-uplxme'] ?? '';
	$password_confirmation = $form_fields['form-field-ijendt'] ?? '';

	// API URL
	$api_url = tg_api_url('/api/endpoints/auth/users/v2/passwords/update_password.json');

	// Prepare the POST data
	$body = [
		'password'             => $new_password,
		'password_confirmation' => $password_confirmation,
		'phone'                => $phone,
		'confirmation_token'   => $confirmation_token,
	];

	// Debug Log request data
	//error_log('Request body: ' . print_r($body, true));

    // Make the POST request
    $response = wp_remote_post($api_url, [
        'method'    => 'POST',
        'timeout'   => 45,
        'body'      => $body,
    ]);
    
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
    $response_body = wp_remote_retrieve_body($response);
    $api_response  = json_decode($response_body, true);
    $status = $api_response['status'] ?? '';

    if ($response_body && $response_code === 200 && $status === 'success') {
		// Update WordPress user password
		$user = get_user_by('login', $phone);
		if ($user) {
			$update_result = wp_update_user([
				'ID'        => $user->ID,
				'user_pass' => $new_password,
			]);

			if (is_wp_error($update_result)) {
				error_log('Password updated in API, but failed to update on WordPress: ' . $update_result->get_error_message());
				$form->set_result([
					'action' => 'log_api_custom_action',
					'type'   => 'error',
					'message' => 'Password update failed: ' . $update_result->get_error_message(),
				]);
				return;
			}

			// Success message
			// error_log('Password updated successfully for WordPress user.');

			$form->set_result([
				'action' => 'pw_update_tg_action',
				'type'   => 'success',
				'message' => 'تم تحديث كلمة المرور بنجاح.',
			]);

		} else {
			error_log('No WordPress user found with username: ' . $phone);
		}

        $form->set_result([
            'action' => 'tg_success_custom_action',
            'type'   => 'success',
            'message' => 'تم تحديث كلمة المرور بنجاح.',
        ]);

		// Redirect to account page
		//$redirect_to = get_site_url() . '/account';
		// $form->set_result(
		// 	[
		// 		'action'          => 'tg_success_redirect',
		// 		'type'            => 'redirect',
		// 		'redirectTo'      => $redirect_to,
		// 		'redirectTimeout' => 1000
		// 	]
		// );

    } else {
       // Handle response failure and print message for user
		$error_message_user = $api_response['message'] ?? 'Forgot password was unsuccessful.';
		$form->set_result([
			'action' => 'fp_api_tg_action',
			'type'    => 'error',
			'message' => sprintf(
				esc_html__('%s', 'bricks'),
				esc_html($error_message_user)
			),
		]);

		// Error log API request failure
		$error_message_API_failure = $api_response['message'] ?? 'API request was unsuccessful.';
		error_log("Forgot password API Response: $error_message_API_failure");
    }
}

// Bricks reset password form password field validation
add_filter( 'bricks/form/validate', function( $errors, $form ) {
    $form_settings = $form->get_settings();
    $form_fields   = $form->get_fields();
    $form_id       = $form_fields['formId'];
    if ($form_id !== 'rfzfrp') return $errors;

    $new_password = $form->get_field_value( 'uplxme' );
    $confirmation_password = $form->get_field_value( 'ijendt' );
    if ($new_password !== $confirmation_password) {
        $errors[] = esc_html__( 'كلمة المرور الخاصة بك وتأكيد كلمة المرور غير متطابقين.', 'bricks' );
    }
    return $errors;
}, 10, 2 );