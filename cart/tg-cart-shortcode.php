<?php 

defined( 'ABSPATH' ) || exit;

// Shortcode for cart page
add_shortcode('tg_cart', 'tg_cart_shortcode');
function tg_cart_shortcode() {
    if( !is_user_logged_in() ) {
        return '<div class="message error"><span class="text">يرجى تسجيل الدخول أولا إلى حسابك.</span></div>';
    }
    ob_start();
    ?>
    <section class="cart-main-section">
        <button class="ab-go-back"><img src="<?php echo plugins_url('../assets/images/go-back.svg', __FILE__ ); ?>" alt="go-back">الرجوع</button>
        <div class="cart-wrapper">
            <div class="cart-main-div">
                <div class="cart-shop-item-wrapper">
                    <h2 class="csi-heading">السلة</h2>
                    <div id="cart-items-container" class="csiw-md">
                        <p>جارٍ تحميل عناصر سلة التسوق...</p>
                    </div>
                </div>
                <div class="cart-pay-wrapper">
                    <h2 class="cp-heading">ادفع</h2>
                    <div class="cp-btn-wrapper">
                        <?php render_cart_buttons(); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function render_cart_buttons() {
    $cart_id = get_user_meta(get_current_user_id(), 'api_cart_id', true);
    // if (!$cart_id) {
    //     wp_send_json_error(['message' => 'Cart ID not found.']);
    // }
    // API URL
    $api_url = tg_api_url( 'api/endpoints/mobile/v2/cart_items.json' ) . '?lang=en&cart_id=' . $cart_id;
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        //error_log("Cart is empty!");
        return;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($data['status'] !== 'success' || empty($data['data']['cart_items'])) {
        //error_log("Cart is empty!");
        return;
    }
    $cart_items = $data['data']['cart_items'];
    $count_cartItems = count($cart_items);
    $current_user_id = get_current_user_id();
    update_user_meta($current_user_id, 'api_cart_items', $count_cartItems ?? '0');
    $accept_instalments = false;
    $has_invalid_items = false;
    foreach ($cart_items as $item) {
        if ($item['is_offer_accept_installment']) $accept_instalments = true;
        else $has_invalid_items = true;
    }
    echo '<script>
        var acceptInstalments = ' . ($accept_instalments ? 'true' : 'false') . ';
        var hasInvalidItems = ' . ($has_invalid_items ? 'true' : 'false') . ';
    </script>';
    if ($count_cartItems > 1 ) {
        // API URL
        $discount_api_url = tg_api_url('/api/v7/settings.json?lang=ar');
        $discount_response = wp_remote_get($discount_api_url);

        if (is_wp_error($discount_response)) {
            error_log('Error fetching API data: ' . $discount_response->get_error_message());
            return;
        }

        // Decode the JSON response
        $discount_api_data = wp_remote_retrieve_body($discount_response);
        $decoded_data = json_decode($discount_api_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error decoding JSON data: ' . json_last_error_msg());
            return;
        }

        $discount = $decoded_data['setting']['cart_discount'];

        echo '<script>
            let heading = document.createElement("h5");
            heading.className = "discount-congrats";
            heading.innerHTML = "مبروك لقد حصلت على خصم %'. $discount .' اضافي على المبلغ الجزئي لأي عرض مضاف بعد الأول.";
            let cartItemsContainer = document.getElementById("cart-items-container");
            if (cartItemsContainer) {
                cartItemsContainer.parentNode.insertBefore(heading, cartItemsContainer);
            }
        </script>
        <style>
            .cart-shop-item-wrapper .csi-heading { margin-bottom: 0; }
            .cart-pay-wrapper .cp-heading { margin-bottom: 60px; }
        </style>';
    }
    if ( $accept_instalments ) {
        echo '<button class="cart-pay-full-btn">سداد كامل المبلغ </button>';
    }
    ?>
    <button class="cart-partial-pay-btn">ادفع المبلغ الجزئي فقط</button>
    <input type="hidden" id="cartId" name="cartId" value="<?php echo $cart_id; ?>">                    
    <input type="hidden" id="cartItemsCount" name="cartItemsCount" value="<?php echo $count_cartItems; ?>">
    <input type="hidden" id="checkOutType" name="checkOutType" value="<?php echo $has_invalid_items ? '1' : '0'; ?>">
    <?php
}

// AJAX handler to fetch cart items
add_action('wp_ajax_tg_get_cart_items', 'tg_get_cart_items');
function tg_get_cart_items() {
    $cart_id = get_user_meta(get_current_user_id(), 'api_cart_id', true);

    if (!$cart_id) {
        wp_send_json_error(['message' => 'Cart ID not found.']);
    }

    // API URL
	$api_url = tg_api_url( 'api/endpoints/mobile/v2/cart_items.json' ) . '?lang=en&cart_id=' . $cart_id;
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to connect to API.']);
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($data['status'] !== 'success' || empty($data['data']['cart_items'])) {
        wp_send_json_error(['message' => 'لم يتم العثور على عناصر سلة التسوق.']);
    }

    $cart_items = $data['data']['cart_items'];
    $count_cartItems = count($cart_items);

    // Build the HTML for cart items
    ob_start();
    foreach ($cart_items as $item) {
        $accept_instalments = $item['is_offer_accept_installment'];
        $image_phone_small = $item['new_offer_images'][0]['image'] ?? ''. plugins_url("../assets/images/ap-img.png", __FILE__ ).'';
        ?>
        <div class="csi-main-div" accept-instalments="<?php echo $accept_instalments ? 'true' : 'false'; ?>" reserved-time="<?php echo $item['reserved_time'] ?? 'null'; ?>" patient-availability="<?php // echo $item['patient_availability_check']; ?> false">
            <div class="css-on-ai show-tab">
                <span class="csi-on-mobile">رقم العرض: <?php echo $item['offer_id']; ?></span>
                <?php if ( $accept_instalments ) { ?>
                <span class="csi-ia"><img src="<?php echo plugins_url('../assets/images/ia.svg', __FILE__ ); ?>" alt="instalments">الاقساط المتاحة </span>
                <?php } ?>
            </div>
            <div class="csi-offer-wrapper">
                <div class="csi-featured-img">
                    <img src="<?php echo esc_url($image_phone_small); ?>" alt="product image">
                </div>
                <div class="csi-info">
                    <div class="csi-info-wrapper">
                        <div class="csi-on hide-tab">
                            <h6 class="csi-info-sub-heading">رقم العرض</h6>
                            <h2 class="csi-iw-heading csi-on-heading"><?php echo $item['offer_id']; ?></h2>
                        </div>
                        <div class="csi-op">
                            <h6 class="csi-info-sub-heading">سعر العرض</h6>
                            <div class="csi-op-price">
                                <h2 class="csi-iw-heading csi-op-valid-price">SAR <?php echo $item['new_price']; ?></h2>
                            </div>
                        </div>
                        <div class="csi-pa">
                            <h6 class="csi-info-sub-heading">المبلغ الجزئي</h6>
                            <div class="csi-op-price">
                                <?php if ( $count_cartItems === 1 || $item['discount'] === 0.0 ) { ?>
                                    <h2 class="csi-iw-heading csi-pa-price">SAR <?php echo $item['partial_amount']; ?></h2>
                                <?php  } else { ?>
                                    <h2 class="csi-iw-heading csi-pa-price">SAR <?php echo $item['discounted_price']; ?></h2>
                                    <h3 class="csi-op-regular-price">SAR <?php echo $item['partial_amount']; ?></h3>
                                <?php } ?>
                            </div>
                        </div>                        
                    </div>
                    <div class="csi-info-tl hide-tab">
                        <?php if ( !empty($item['reserved_date']) && !empty($item['reserved_time']) ) { ?>
                            <div class="csi-itl-time">
                                <img src="<?php echo plugins_url('../assets/images/cal.svg', __FILE__ ); ?>" alt="calendar"><span class="csi-itl-dt"><?php echo $item['reserved_date'] . ' - ' . $item['reserved_time']; ?></span>
                            </div>
                        <?php } else { ?>
                            <a href="/reservation/?appointment_type=<?php echo $item['appointment_type']; ?>&offer_id=<?php echo $item['offer_id']; ?>&cart_item_id=<?php echo $item['cart_item_id']; ?>&action=cartUpdate" class="csi-offer-update-bt">احجز موعدك</a>
                        <?php } ?>
                        <div class="csi-itl-location">
                            <img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__ ); ?>" alt="map icon"><span class="csi-itl-dt"><?php echo $item['doctor_name']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="csi-info-tl show-tab">
                <?php if ( !empty($item['reserved_date']) && !empty($item['reserved_time']) ) { ?>
                <div class="csi-itl-time">
                    <img src="<?php echo plugins_url('../assets/images/cal.svg', __FILE__ ); ?>" alt="calendar"><span class="csi-itl-dt"><?php echo $item['reserved_date'] . ' - ' . $item['reserved_time']; ?></span>
                </div>
                <?php } ?>
                <div class="csi-itl-location">
                    <img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__ ); ?>" alt="map icon"><span class="csi-itl-dt"><?php echo $item['doctor_name']; ?></span>
                </div>
            </div>
            <div class="csi-btns">
                <?php if ( $accept_instalments ) { ?>
                    <span class="csi-ia hide-tab"><img src="<?php echo plugins_url('../assets/images/ia.svg', __FILE__ ); ?>" alt="الاقساط"> الاقساط المتاحة</span>
                <?php } ?>
                <div class="csi-btns-wrapper">
                    <button class="csi-remove-btn" cart-item="<?php echo $item['cart_item_id']; ?>"><img src="<?php echo plugins_url('../assets/images/trash.svg', __FILE__ ); ?>" alt="delete"> حذف</button>
                    <a href="/offers/<?php echo $item['offer_id']; ?>" class="csi-offer-detail-bt">عرض تفاصيل العرض</a>
                </div>
            </div>
        </div>
        <?php
    }
    $html = ob_get_clean();
    $current_user_id = get_current_user_id();
    update_user_meta($current_user_id, 'api_cart_items', $count_cartItems ?? '0');
    //error_log("Cart Count:".$count_cartItems);
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_tg_get_cart_items_count', 'tg_get_cart_items_count_callback');
function tg_get_cart_items_count_callback() {
    $cart_id = get_user_meta(get_current_user_id(), 'api_cart_id', true);

    if (!$cart_id) {
        wp_send_json_error(['message' => 'Cart ID not found.']);
    }

    // Check if check_out_type is set
    // if (!isset($_POST['check_out_type'])) {
    //     wp_send_json_error(['message' => 'Missing checkout type']);
    // }

    $check_out_type = isset($_POST['check_out_type']) ? intval($_POST['check_out_type']) : '';

    // API URL
    $api_url = tg_api_url( 'api/endpoints/mobile/v2/cart_items.json' ) . '?lang=en&cart_id=' . $cart_id;
    $response = wp_remote_get($api_url);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to connect to API.']);
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if ($data['status'] !== 'success' || empty($data['data']['cart_items'])) {
        wp_send_json_error(['message' => 'لم يتم العثور على عناصر سلة التسوق.']);
    }

    $cart_items = $data['data']['cart_items'];
    $count_cartItems = count($cart_items);

    // If there is more than 1 cart item, send a PUT request to checkout API
    if ($count_cartItems > 1 && $check_out_type) {
        //error_log("check_out_type = ".$check_out_type);
        //error_log("count_cartItems = ".$count_cartItems);

        $current_user_id = get_current_user_id();
        
        // Retrieve API credentials from user meta
        $access_token = get_user_meta($current_user_id, 'api_access_token', true);
        $client = get_user_meta($current_user_id, 'api_client', true);
        $uid = get_user_meta($current_user_id, 'api_uid', true);
        $cart_id = get_user_meta($current_user_id, 'api_cart_id', true);

        // Ensure required API credentials exist
        if (empty($access_token) || empty($client) || empty($uid)) {
            wp_send_json_error(['message' => 'API credentials are missing for the current user.']);
            //error_log('API credentials are missing for the current user.');
            return;
        }

        // API URL
        $checkout_url = tg_api_url('/api/endpoints/mobile/v1/carts/check_out.json?lang=en&cart_id='.$cart_id.'&check_out_type='.$check_out_type.'');

        //error_log("checkout_url " . $checkout_url);

        // Use wp_remote_request to make a PUT request
        $response_checkout = wp_remote_request($checkout_url, [
            'method'  => 'PUT',
            'headers' => [
                'access-token' => $access_token,
                'client' => $client,
                'uid' => $uid,
            ],
        ]);

        // Check if the request was successful
        if (is_wp_error($response_checkout)) {
            wp_send_json_error(['message' => $response_checkout->get_error_message()]);
        }

        $checkout_response_code = wp_remote_retrieve_response_code($response_checkout);
        $checkout_response_body = wp_remote_retrieve_body($response_checkout);
        // Log the raw response to the console for debugging
        //error_log('API Response: ' . $checkout_response_body);

        if ($checkout_response_code === 200) {
            wp_send_json_success(['data' => json_decode($checkout_response_body, true)]);
        } else {
            wp_send_json_error(['message' => 'API request failed', 'response' => $checkout_response_body]);
        }

    }

    // Build the HTML for popup
    ob_start();
    if ($count_cartItems === 1) {
        // $stored_data = get_option('tabibgroup_api_settings');
        // API URL
        $discount_api_url = tg_api_url('/api/v7/settings.json?lang=ar');
        $discount_response = wp_remote_get($discount_api_url);

        if (is_wp_error($discount_response)) {
            error_log('Error fetching API data: ' . $discount_response->get_error_message());
            return;
        }

        // Decode the JSON response
        $discount_api_data = wp_remote_retrieve_body($discount_response);
        $decoded_data = json_decode($discount_api_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error decoding JSON data: ' . json_last_error_msg());
            return;
        }

        $discount = $decoded_data['setting']['cart_discount'];

        ?>
        <!-- Dialog HTML -->
        <div id="reservation-modal" style="display: none;">
            <div>
                <h2>خصم اضافي</h2>
                <p>لديك عرض واحد مضاف بسلة المشتريات ، اضف
                عروض اخرى واحصل علي خصم اضافي %<?php if ($discount) { echo intval($discount); } ?> من المبلغ الجزئي بداية من العرض الثاني</p>
                <div id="action-btns">
                    <button id="go-to-payment" class="dialog-button">الإنتقال للدفع</button>
                    <button id="continue-shopping" class="dialog-button">استمرار التسوق</button>
                </div>
            </div>
        </div>
        <div id="reservation-modal-overlay" style="display: none;"></div>
        <?php
    }
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}



// AJAX handler to delete and show confirm dialog before cart item deletion
add_action('wp_ajax_tg_delete_cart_item', 'tg_delete_cart_item_callback');
function tg_delete_cart_item_callback() {
    // Check if the cartItemId is provided
    if (!isset($_POST['cartItemIdToDelete']) || empty($_POST['cartItemIdToDelete'])) {
        wp_send_json_error(['message' => 'Invalid cart item ID']);
    }

    // Get the cart item ID
    $cartItemIdToDelete = sanitize_text_field($_POST['cartItemIdToDelete']);
    
    if (!$cartItemIdToDelete) {
        wp_send_json_error(['message' => 'Cart Item ID not found.']);
    }

    // API URL
    $api_url = tg_api_url( 'api/endpoints/mobile/v1/cart_items/' . $cartItemIdToDelete . '.json' ) . '?lang=en';
    // Make the DELETE request
    $response = wp_remote_request($api_url, [
        'method' => 'DELETE',
    ]);

    // Check for errors
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to delete the item', 'error' => $response->get_error_message()]);
    }

    // Get the response code
    $statusCode = wp_remote_retrieve_response_code($response);

    if ($statusCode === 200) {
        wp_send_json_success(['message' => 'Item deleted successfully']);
    } else {
        wp_send_json_error([
            'message' => 'Failed to delete item',
            'response' => wp_remote_retrieve_body($response),
        ]);
    }
}

// Cart Checkout action callback
add_action('wp_ajax_tg_cart_checkout', 'tg_cart_checkout_handler');
function tg_cart_checkout_handler() {
    // Verify nonce for security
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'get_cart_type_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    // Check if check_out_type is set
    if (!isset($_POST['check_out_type'])) {
        wp_send_json_error(['message' => 'Missing checkout type']);
    }

    $check_out_type = intval($_POST['check_out_type']);

    $current_user_id = get_current_user_id();
    $access_token = get_user_meta($current_user_id, 'api_access_token', true);
    $client = get_user_meta($current_user_id, 'api_client', true);
    $uid = get_user_meta($current_user_id, 'api_uid', true);
    $cart_id = get_user_meta($current_user_id, 'api_cart_id', true);

    // Ensure required API credentials exist
    if (empty($access_token) || empty($client) || empty($uid)) {
        wp_send_json_error(['message' => 'API credentials are missing for the current user.']);
        //error_log('API credentials are missing for the current user.');
        return;
    }

    // API URL
    $api_url = tg_api_url( 'api/endpoints/mobile/v1/carts/check_out.json' ) . '?lang=en&cart_id=' . $cart_id . '&check_out_type=' . $check_out_type;

    //error_log($api_url);

    // Use wp_remote_request to make a PUT request
    $response = wp_remote_request($api_url, [
        'method'  => 'PUT',
        'headers' => [
            'access-token' => $access_token,
            'client' => $client,
            'uid' => $uid,
        ],
    ]);

    // Check if the request was successful
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    // Log the raw response to the console for debugging
    //error_log('API Response: ' . $response_body);

    if ($response_code === 200) {
        wp_send_json_success(['data' => json_decode($response_body, true)]);
    } else {
        wp_send_json_error(['message' => 'API request failed', 'response' => $response_body]);
    }
}


// Get Update Bill details action callback
add_action('wp_ajax_tg_get_bill', 'tg_get_bill_callback');
function tg_get_bill_callback() {
    // Verify nonce for security
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'get_cart_type_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }
    
    $current_user_id = get_current_user_id();
    $access_token = get_user_meta($current_user_id, 'api_access_token', true);
    $client = get_user_meta($current_user_id, 'api_client', true);
    $uid = get_user_meta($current_user_id, 'api_uid', true);
    $cart_id = get_user_meta($current_user_id, 'api_cart_id', true);

    // Ensure required API credentials exist
    if (empty($access_token) || empty($client) || empty($uid)) {
        wp_send_json_error(['message' => 'API credentials are missing for the current user.']);
        //error_log('API credentials are missing for the current user.');
        return;
    }

    // API URL
    $api_url = tg_api_url( 'api/endpoints/mobile/v1/bills.json' ) . '?lang=en';

    //error_log($api_url);

    // Use wp_remote_get
    $response = wp_remote_get($api_url, [
        'headers' => [
            'access-token' => $access_token,
            'client' => $client,
            'uid' => $uid,
        ],
    ]);

    // Check if the request was successful
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    // $data = json_decode(wp_remote_retrieve_body($response_body), true);
    // Log the raw response to the console for debugging
    //error_log('API Response: ' . $response_code);

    // if (!isset($data['status']) || $data['status'] !== 'success') {
    //     wp_send_json_error(['message' => 'Invalid wallet response']);
    // }

    if ($response_code === 200) {
        // $bill_id = isset($wallet_data['data']['bill']['bill_id']) ? floatval($wallet_data['data']['bill']['bill_id']) : '';
        // wp_send_json_success(['bill_id' => $bill_id]);
        wp_send_json_success(['data' => json_decode($response_body, true)]);
    } else {
        wp_send_json_error(['message' => 'API request failed', 'response' => $response_body]);
    }
}
