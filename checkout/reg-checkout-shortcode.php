<?php 

defined( 'ABSPATH' ) || exit;

add_shortcode('tg_checkout', function() {
	if (!is_user_logged_in()) {
        return '<div class="message error"><span class="text">يرجى تسجيل الدخول أولا إلى حسابك.</span></div>';
    }

    global $tabibgroup_base_url;
    $current_user_id = get_current_user_id();
	// Fetch user cart details 
	$user_cart_id = get_user_meta($current_user_id, 'api_cart_id', true);

    // Validate required URL parameters
    $cart_id = isset($_GET['cart_id']) ? sanitize_text_field($_GET['cart_id']) : null;
    $bill_id = isset($_GET['bill_id']) ? sanitize_text_field($_GET['bill_id']) : null;
    $checkout_type = isset($_GET['check_out_type']) ? sanitize_text_field($_GET['check_out_type']) : null;

    if (empty($cart_id) || empty($bill_id) || $checkout_type === null) {
        return '<div class="message error"><span class="text">البيانات غير صحيحة. يُرجى الانتقال إلى صفحة سلة التسوق والمحاولة مرة أخرى.</span></div>';
    }

	if ($checkout_type !== "0" && $checkout_type !== "1") {
        return '<div class="message error"><span class="text">الدفع غير صالح!</span></div>';
    }

	if (empty($user_cart_id) || $cart_id !== $user_cart_id) {
        return '<div class="message error"><span class="text">بيانات عربة التسوق الخاصة بك غير صحيحة!</span></div>';
    }

	// Retrieve API credentials
	$access_token = get_user_meta($current_user_id, 'api_access_token', true);
	$client = get_user_meta($current_user_id, 'api_client', true);
	$uid = get_user_meta($current_user_id, 'api_uid', true);

	if (!$access_token || !$client || !$uid) {
		return '<div class="message error"><span class="text">رؤوس مصادقة API مفقودة.</span></div>';
	}

    // Choose API endpoint based on checkout type
    if ($checkout_type === '0') {
        $api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v2/carts/installments_checkout.json?lang=en&cart_id=' . $user_cart_id;
    } else {
        $api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v2/cart_items.json?lang=en&cart_id=' . $user_cart_id;
    }

	// Make API call with authentication headers
	$response = wp_remote_get($api_url, [
		'headers' => [
			'access-token' => $access_token,
			'client'       => $client,
			'uid'          => $uid,
		],
	]);

	if (is_wp_error($response)) {
		return '<div class="message error"><span class="text">حدث خطأ أثناء جلب بيانات الخروج.</span></div>';
	}
	$data = json_decode(wp_remote_retrieve_body($response), true);
	if ($data['status'] !== 'success' || empty($data['data'])) {
		return '<div class="message error"><span class="text">حدث خطأ أثناء جلب بيانات السلة.</span></div>';
	}

    // Extract summary details (structure is consistent in both endpoints)
    $summary = $data['data']['summary_details'];
	if (empty($summary)) {
		return '<div class="message error"><span class="text">ملخص الدفع غير متوفر.</span></div>';
	}
    $total_amount = floatval($summary['total_amount'] ?? 0);
    $total_discount = floatval($summary['total_discount'] ?? 0);
    $vat_percentage = floatval($summary['vat_percentage'] ?? 0);
    $total_discounted_amount = floatval($summary['total_discounted_amount'] ?? 0);
    $remaining_amount = floatval($summary['remaining_amount'] ?? 0);
    $invoice_amount = floatval($summary['invoice_amount'] ?? 0);

    $cart_items = $data['data']['cart_items'] ?? [];
    $count_cartItems = count($cart_items);

	ob_start();
?>

<div class="checkout-wrapper">
	<div class="checkout-main-div">
		<div class="checkout-payments">
			<h2 class="cop-heading">الدفع</h2>
			<!-- Payment Methods -->
			<div class="cop-wrapper">
				<h2 class="c-ph">طرق الدفع</h2>
				<?php if ($checkout_type === '1' ) : ?>
				<div class="cp-uyw">
					<div class="cp-uyw-switch-wrapper">
						<h2 class="cp-sw-heading">استخدم محفظتك</h2>
						<label class="cp-sw-switch">
							<input type="checkbox" id="cp-sw-toggle-switch">
							<span class="cp-sw-slider"></span>
						</label>
					</div>
					<div class="cp-uyw-switch-wrapper cp-uyw-bi">
						<h2 class="cp-sw-balance"><img src="<?php echo plugins_url('../assets/images/wallet-icon.svg', __FILE__ ); ?>" alt="wallet" class="co-pmw-card"> الرصيد المتاح: <span class="cp-swb">SAR <span id="wallet-amount"></span></span></h2>
						<h6 class="cp-swb-info">سيتم خصم المبلغ من محفظتك</h6>
					</div>
				</div>
				<?php endif; ?>

				<?php if ($checkout_type === '0' ) : ?>
				<div class="co-pm-tab-item">
					<!-- Tab 1 -->
					<div class="co-pm-tab-header active" data-tab="1" payment-method="Tabby">
						<div class="co-pm-tab-radio"></div>
						<img src="<?php echo get_site_url() . '/wp-content/uploads/image-2.png'; ?>" alt="tabby" class="co-pm-card">
						<h3 class="co-pm-tab-title">تابي</h3>
					</div>
				</div>
				<div class="co-pm-tab-item">
					<!-- Tab 2 -->
					<div class="co-pm-tab-header" data-tab="2" payment-method="Tamara">
						<div class="co-pm-tab-radio"></div>
						<img src="<?php echo get_site_url() . '/wp-content/uploads/image-3.png'; ?>" alt="tamara" class="co-pm-card">
						<h3 class="co-pm-tab-title">تمارا</h3>
					</div>                        
				</div>
				<?php endif; ?>
				<div class="co-pm-tab-item">
					<!-- Tab 1 -->
					<div class="co-pm-tab-header <?php echo ($checkout_type === '0') ? '' : 'active'; ?>" data-tab="1" payment-method="Credit Card">
						<div class="co-pm-tab-radio"></div>
						<img src="<?php echo plugins_url('../assets/images/credit-card.svg', __FILE__ ); ?>" alt="credit-card" class="co-pm-card">
						<img src="<?php echo plugins_url('../assets/images/visa-card.svg', __FILE__ ); ?>" alt="visa-card">
						<h3 class="co-pm-tab-title">بطاقة الائتمان</h3>
					</div>
				</div>
				<div class="co-pm-tab-item">
					<!-- Tab 2 -->
					<div class="co-pm-tab-header" data-tab="2" payment-method="Mada">
						<div class="co-pm-tab-radio"></div>
						<img src="<?php echo plugins_url('../assets/images/mada-card.svg', __FILE__ ); ?>" alt="mada-card" class="co-pm-card">
						<h3 class="co-pm-tab-title">بطاقة الصراف الآلي ( مدى )</h3>
					</div>
				</div>
				<div class='co-pm-tab-item'>
					<div class="co-pm-tab-header" data-tab="3" payment-method="Apple Pay">
						<div class="co-pm-tab-radio"></div>
						<div class="apple-container">
							<div class="unsupportedBrowserMessage">
								Your browser doesn’t support Apple&nbsp;Pay on the web.<br>
								Open this page in Safari.
							</div>
							<div class="configureWalletMessage">
								Your device does not have any active cards in Wallet.<br>
								Please <a href="x-apple.systempreferences://com.apple.WalletSettingsExtension?addPass">open Wallet in Settings</a> and add at least one card.
							</div>
							<div class="applePayButtonContainer">
								<img src="<?php echo plugins_url('../assets/images/apple-pay.png', __FILE__ ); ?>" alt="apple-pay" class="co-pm-card">
								<h3 class="co-pm-tab-title">Apple Pay</h3>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="checkout-summary-wrapper">
			<h2 class="csp-heading">كشف حساب</h2>
			<div class="cs-offers-wrapper">
				<?php foreach ($cart_items as $item): ?>
				<?php $image_phone_small = $item['new_offer_images'][0]['image_phone_small'] ?? ''. plugins_url("../assets/images/ap-img.png", __FILE__ ).''; ?>
				<div class="cs-offer-item">
					<div class="cs-oi-info">
						<div class="csoi-inof-tw">
							<h3 class="cs-oi-on">رقم العرض: <?php echo $item['offer_id']; ?></h3>
							<h3 class="cs-oi-price">
								<?php if ($checkout_type === '0') { ?>
								SAR <?php echo $item['new_price']; ?>
								<?php } else { ?>
								<?php if ($count_cartItems === 1) { ?>
								SAR <?php echo $item['partial_amount']; ?>
								<?php  } else { ?>
								SAR <?php echo $item['discounted_price']; ?>
								<?php } ?>
								<?php } ?>
							</h3>
						</div>
						<div class="csoi-info-lt">
							<h6 class="csoi-location">
								<img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__ ); ?>" alt="map-pin"> <?php echo $item['doctor_name']; ?>
							</h6>
							<?php if ( !empty($item['reserved_date']) && !empty($item['reserved_time']) ) { ?>
							<h6 class="csoi-time">
								<img src="<?php echo plugins_url('../assets/images/cal.svg', __FILE__ ); ?>" alt="calendar"> <?php echo $item['reserved_date'] . ' - ' . $item['reserved_time']; ?>
							</h6>
							<?php } ?>
						</div>
					</div>
					<div class="cs-oi-img">
						<img src="<?php echo esc_url($image_phone_small); ?>" alt="product image">
					</div>
				</div>
				<?php endforeach; ?>

				<div class="cs-offer-pricing">
					<div class="csof-pricing-wrapper">
						<h4 class="csof-pa">SAR <span id="total-amount"><?php echo esc_html($total_amount); ?></span></h4>
						<h4 class="csof-pl">قیمة العروض</h4>
					</div>
					<div class="csof-pricing-wrapper">
						<h4 class="csof-pa">SAR <span id="total-discount"><?php echo esc_html($total_discount); ?></span></h4>
						<h4 class="csof-pl">مبلغ الخصم</h4>
					</div>
					<div class="csof-pricing-wrapper">
						<h4 class="csof-pa">SAR <span id="VAT-percentage"><?php echo esc_html($vat_percentage); ?></span></h4>
						<h4 class="csof-pl">ضريبة القيمة المضافة (VAT)</h4>
					</div>
					<div class="csof-pricing-wrapper">
						<h4 class="csof-pa">SAR <span id="discounted-amount"><?php echo esc_html($total_discounted_amount); ?></span></h4>
						<h4 class="csof-pl">المبلغ الجزئي لتأكید الحجز</h4>
					</div>
					<div class="csof-pricing-wrapper">
						<h4 class="csof-pa">SAR <span id="remaining-amount"><?php echo esc_html($remaining_amount); ?></span></h4>
						<h4 class="csof-pl">المبلغ المتبقي یوم الموعد</h4>
					</div>
					<div id="wallet-deduction-label" style="display: none;">
						<div class="csof-pricing-wrapper">
							<h4 class="csof-pa">SAR <span id="wallet-deduction">0.00</span></h4>
							<h4 class="csof-pl">خصم من المحفظة</h4>
						</div>
					</div>
				</div>

				<div class="cs-offer-total">
					<div class="csof-total-wrapper">
						<h4 class="csot-pa">SAR <span id="invoice-amount"><?php echo esc_html($invoice_amount); ?></span></h4>
						<h4 class="csot-pl">المبلغ الإجمالي</h4>
					</div>
				</div>

				<div class="checkout-btn-wrapper">
					<button id="checkout-button-btn" class="btn checkout-btn">استمرار</button>
					<?php 
						if (is_user_logged_in()) {
							$current_user = wp_get_current_user();
							?>
								<input type="hidden" id="currentUser" name="currentUser" value="<?php echo esc_attr($current_user->display_name); ?>">
								<input type="hidden" id="currentUserID" name="currentUserID" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'api_id', true)); ?>">
							<?php
						}
					?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
	return ob_get_clean();
});

// AJAX handler for wallet deduction
add_action('wp_ajax_apply_wallet_deduction', 'apply_wallet_deduction');
function apply_wallet_deduction() {
	check_ajax_referer('get_checkout_nonce', 'security');

	global $tabibgroup_base_url;

	if (!is_user_logged_in()) {
		wp_send_json_error(['message' => 'User not logged in']);
	}

	$current_user_id = get_current_user_id();
	$access_token = get_user_meta($current_user_id, 'api_access_token', true);
	$client = get_user_meta($current_user_id, 'api_client', true);
	$uid = get_user_meta($current_user_id, 'api_uid', true);

	$walletapi_url = $tabibgroup_base_url . '/api/v7/users/wallet.json?lang=ar';

	$wallet_response = wp_remote_get($walletapi_url, [
		'headers' => [
			'access-token' => $access_token,
			'client' => $client,
			'uid' => $uid,
		],
	]);

	if (is_wp_error($wallet_response)) {
		wp_send_json_error(['message' => 'Failed to fetch wallet balance']);
	}

	$wallet_data = json_decode(wp_remote_retrieve_body($wallet_response), true);

	if (!isset($wallet_data['status']) || $wallet_data['status'] !== 'success') {
		wp_send_json_error(['message' => 'Invalid wallet response']);
	}

	$wallet_balance = isset($wallet_data['data']['wallet_amount']) ? floatval($wallet_data['data']['wallet_amount']) : 0;

	wp_send_json_success(['wallet_balance' => $wallet_balance]);
}

// PHP Action for Handling the PUT Request of update_bill_checkout
add_action('wp_ajax_tg_update_bill_checkout', 'tg_update_bill_checkout_handler');
function tg_update_bill_checkout_handler() {
	check_ajax_referer('get_checkout_nonce', 'security');

	global $tabibgroup_base_url;

	if (!is_user_logged_in()) {
		wp_send_json_error(['message' => 'User not logged in']);
	}

	// Debugging: Log the raw POST data
	// error_log("---- RAW POST DATA ----");
	// error_log(print_r($_POST, true));

	// Retrieve and decode the JSON data
	$bill_data = isset($_POST['tg_bill']) ? json_decode(stripslashes($_POST['tg_bill']), true) : [];

	// Debugging: Log the decoded JSON request
	// error_log("---- DECODED BILL DATA ----");
	// error_log(print_r($bill_data, true));

	$current_user_id = get_current_user_id();
	$access_token = get_user_meta($current_user_id, 'api_access_token', true);
	$client = get_user_meta($current_user_id, 'api_client', true);
	$uid = get_user_meta($current_user_id, 'api_uid', true);

	// API URL
	$api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v3/bills/update_bill.json?lang=en';

	$response = wp_remote_request($api_url, [
		'method'    => 'PUT',
		'body'      => json_encode($bill_data),
		'headers'   => [
			'Content-Type' => 'application/json',
			'access-token' => $access_token,
			'client' => $client,
			'uid' => $uid,
		],
	]);

	// Debugging: Log the API request body
	// error_log("---- API REQUEST BODY ----");
	// error_log(json_encode($bill_data));

	// Debugging: Log the API response
	// error_log("---- API RESPONSE ----");
	// error_log(print_r($response, true));

	// Check for errors with the request
	if (is_wp_error($response)) {
		error_log("---- API ERROR ----");
		error_log($response->get_error_message());
		wp_send_json_error(['message' => $response->get_error_message()]);
	}

	$response_code = wp_remote_retrieve_response_code($response);
	$response_body = wp_remote_retrieve_body($response);

	// Debugging: Log the final response body
	// error_log("---- API RESPONSE BODY ----");
	// error_log($response_body);

	// If the request was successful, send a success response
	if ($response_code === 204) {
		wp_send_json_success(['message' => 'Bill updated successfully']);
	} else {
		wp_send_json_error(['message' => 'API request failed', 'response' => $response_body]);
	}
	exit();
}


// PHP Action for Handling the PUT Request of update_bill_checkout
add_action('wp_ajax_tg_update_bill_tabby_tamara_checkout', 'tg_update_bill_tabby_tamara_checkout_handler');
function tg_update_bill_tabby_tamara_checkout_handler() {
	check_ajax_referer('get_checkout_nonce', 'security');

	global $tabibgroup_base_url;

	if (!is_user_logged_in()) {
		wp_send_json_error(['message' => 'User not logged in']);
	}

	// Decode the received bill data from the AJAX request
	$tabbyData = json_decode(stripslashes($_POST['tabby_bill_data']), true);

	//error_log( "tabbyData Bill Data" . $tabbyData);

	if (empty($tabbyData)) {
		wp_send_json_error(['message' => 'Missing or invalid bill data']);
	}

	$current_user_id = get_current_user_id();
	$access_token = get_user_meta($current_user_id, 'api_access_token', true);
	$client = get_user_meta($current_user_id, 'api_client', true);
	$uid = get_user_meta($current_user_id, 'api_uid', true);

	// API URL
	$api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v2/bills/update_bill.json?lang=en';

	$response = wp_remote_request($api_url, [
		'method'    => 'PUT',
		'body'      => json_encode($tabbyData),
		'headers'   => [
			'Content-Type' => 'application/json',
			'access-token' => $access_token,
			'client' => $client,
			'uid' => $uid,
		],
	]);

	// Check for errors with the request
	if (is_wp_error($response)) {
		wp_send_json_error(['message' => $response->get_error_message()]);
	}

	$response_code = wp_remote_retrieve_response_code($response);
	$response_body = wp_remote_retrieve_body($response);

	// If the request was successful, send a success response
	if ($response_code === 200) {
		wp_send_json_success(['message' => 'Bill updated successfully']);
	} else {
		wp_send_json_error(['message' => 'API request failed', 'response' => $response_body]);
	}
}

// PHP Action for Handling the POST Request of tg_tabby_checkout
add_action('wp_ajax_tg_tabby_checkout', 'tg_tabby_checkout_handler');
function tg_tabby_checkout_handler() {
	check_ajax_referer('get_checkout_nonce', 'security');

	global $tabibgroup_base_url;

	if (!is_user_logged_in()) {
		wp_send_json_error(['message' => 'User not logged in']);
	}

	// Get amount from request
	$amount = isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : '';

	if (!$amount || !is_numeric($amount)) {
		wp_send_json_error(['message' => 'Invalid amount']);
	}

	$current_user_id = get_current_user_id();
	$access_token = get_user_meta($current_user_id, 'api_access_token', true);
	$client = get_user_meta($current_user_id, 'api_client', true);
	$uid = get_user_meta($current_user_id, 'api_uid', true);

	// API URL
	$api_url = $tabibgroup_base_url . '/api/endpoints/partner/p1/tabby/checkout_session';

	// Prepare the JSON body in the required format
	$request_body = json_encode(array(
		'tabby' => array(
			'amount' => $amount,
			'source' => 'Web'
		)
	));

	$response = wp_remote_post($api_url, array(
		'method'    => 'POST',
		'headers'   => array(
			'Content-Type' => 'application/json',
			'access-token' => $access_token,
			'client'       => $client,
			'uid'          => $uid,
		),
		'body'      => $request_body
	));

	// Check for API request errors
	if (is_wp_error($response)) {
		wp_send_json_error(['message' => 'API request failed: ' . $response->get_error_message()]);
	}

	$response_body = wp_remote_retrieve_body($response);
	$http_code = wp_remote_retrieve_response_code($response);

	//error_log('Tabby API Response: ' . $response_body);

	$result = json_decode($response_body, true);

	if ($http_code == 200 && isset($result['data']['web_url'][0])) {
		wp_send_json_success(['web_url' => $result['data']['web_url'][0]]);
	} else {
		error_log('Tabby API Error: ' . ($result['message'] ?? 'Unknown error'));
		wp_send_json_error(['message' => $result['message'] ?? 'API request failed']);
	}
}

// PHP Action for Handling the POST Request of tg_tamara_checkout
add_action('wp_ajax_tg_tamara_checkout', 'tg_tamara_checkout_handler');
function tg_tamara_checkout_handler() {
	check_ajax_referer('get_checkout_nonce', 'security');

	global $tabibgroup_base_url;

	if (!is_user_logged_in()) {
		wp_send_json_error(['message' => 'User not logged in']);
	}

	// Get amount from request
	$amount = isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : '';

	if (!$amount || !is_numeric($amount)) {
		wp_send_json_error(['message' => 'Invalid amount']);
	}

	$current_user_id = get_current_user_id();
	$access_token = get_user_meta($current_user_id, 'api_access_token', true);
	$client = get_user_meta($current_user_id, 'api_client', true);
	$uid = get_user_meta($current_user_id, 'api_uid', true);

	// API URL
	$api_url = $tabibgroup_base_url . '/api/endpoints/partner/p1/tamara/checkout_session';

	// Prepare the JSON body in the required format
	$request_body = json_encode(array(
		'tamara' => array(
			'amount' => $amount,
			'source' => 'Web'
		)
	));

	$response = wp_remote_post($api_url, array(
		'method'    => 'POST',
		'headers'   => array(
			'Content-Type' => 'application/json',
			'access-token' => $access_token,
			'client'       => $client,
			'uid'          => $uid,
		),
		'body'      => $request_body
	));

	// Check for API request errors
	if (is_wp_error($response)) {
		wp_send_json_error(['message' => 'API request failed: ' . $response->get_error_message()]);
	}

	$response_body = wp_remote_retrieve_body($response);
	$http_code = wp_remote_retrieve_response_code($response);

	//error_log('Tabby API Response: ' . $response_body);

	$result = json_decode($response_body, true);

	if ($http_code == 200 && isset($result['data']['checkout_url'])) {
		wp_send_json_success(['checkout_url' => $result['data']['checkout_url']]);
	} else {
		error_log('Tabby API Error: ' . ($result['message'] ?? 'Unknown error'));
		wp_send_json_error(['message' => $result['message'] ?? 'API request failed']);
	}
}