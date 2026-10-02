<?php 

defined( 'ABSPATH' ) || exit;

// Shortcode for reservations screen
add_shortcode('reservation_page', 'reservation_page_shortcode');
function reservation_page_shortcode() {
    ob_start();
    ?>

    <section class="ab-section">
        <button class="ab-go-back"><img src="<?php echo plugins_url('../assets/images/go-back.svg', __FILE__ ); ?>" alt="go-back">الرجوع</button>
        <div class="ab-wrapper">            
            <div class="ab-calendar">
                <div class="abc-wraper">
                    <div class="dtDates">
                        <h2 class="abc-title">حدد التاريخ</h2>
                        <div id="dt-calendar">
                            <div id="dt-calendar-header">
                                <div class="dt-calendar-header">
                                    <button id="dt-nextMonth"><img src="<?php echo plugins_url('../assets/images/chevron-right.svg', __FILE__ ); ?>" alt="next"></button>                                    
                                    <h2 id="dt-monthYear"></h2>
                                    <button id="dt-prevMonth"><img src="<?php echo plugins_url('../assets/images/chevron-left.svg', __FILE__ ); ?>" alt="previous"></button>
                                </div>
                            </div>
                            <div id="dt-calendarDayNames" class="dt-calendar-grid dt-cd"></div>
                            <div id="dt-calendarDays" class="dt-calendar-grid"></div>
                        </div>
                    </div>
                    <div class="dtSlots">
                        <div class="abc-title-wrapper">
                            <button id="dt-gotoDate">
                                <img src="<?php echo plugins_url('../assets/images/go-back.svg', __FILE__ ); ?>" alt="previous">
                            </button>
                            <h2 class="abc-title">حدد الموعد </h2>
                        </div>
                        <div class="time-slot-wrapper">
                            <div id="time-slots"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ab-apointment-item">
                <div class="abai-wrapper">
                    <div class="abai-header">
                        <div class="abai-header-inner">
                            <div class="abai-img-wrapper">
                                <img src="" alt="offer-image" class="abai-featured-img">
                            </div>
                        </div>
                        <div>
                            <div class="abai-offer-number">
                                <span>رقم العرض: <span class="offer_id"></span></span>
                            </div>
                            <h1 class="abai-title"></h1>
                            <!-- <p class="abai-excerpt"></p> -->
                        </div>
                    </div>
                    <div class="abai-body">
                        <a href="" class="content">
                            <span class="abai-category"></span>
                        </a>
                        <div class="abai-lr-info">
                            <div class="abai-location">
                                <img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__ ); ?>" alt="star"> <span></span>
                            </div>
                            <div class="abai-review">
                                <img src="<?php echo plugins_url('../assets/images/rating-star.svg', __FILE__ ); ?>" alt="star"> <span class="avg-rating"></span> <span class="abai-total-reivews">(<span class="count-reviews"></span> تعليق)</span>
                            </div>
                        </div>
                    </div>
                    <div class="abai-footer">
                        <h2 class="abai-dt-title hide-tab">حدد الوقت و التاريخ من الجهة اليسرى</h2>
                        <h2 class="abai-dt-title show-tab">حدد الوقت والتاريخ من الجدول التالي</h2>
                        <button id="confirm-reservation" class="abai-booking-btn abai-disabled-btn">قم بتأكيد الحجز</button>
                        <!-- <input type="hidden" class="patient_availability_check" value=""> -->
                        <input type="hidden" class="patient_availability_check" value="false">
                        <?php 
                            if( !is_user_logged_in() ) {
                                ?>
                                <div class="error-message" style="display: none;">الرجاء تسجيل الدخول لإضافة العرض إلى سلة التسوق الخاصة بك.</div>
                                <?php
                            } else {
                                ?>
                                <div class="error-message" style="display: none;">فشلت إضافة الحجز إلى سلة التسوق. يرجى المحاولة مرة أخرى.</div>
                                <?php
                            }
                        ?>
                        <input type="hidden" class="doctor_id" value="">
                        <?php 
                            if( !is_user_logged_in() ) {
                                return;
                            }
                            $current_user_id = get_current_user_id();
                            $cart_id = get_user_meta($current_user_id, 'api_cart_id', true);
                        ?>
                        <input type="hidden" class="cart_id" value="<?php echo $cart_id; ?>">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    return ob_get_clean();
}

// Custom action to get calendar dates/time
add_action('wp_ajax_get_calendar_data', 'proxy_calendar_data');
add_action('wp_ajax_nopriv_get_calendar_data', 'proxy_calendar_data');
function proxy_calendar_data() {
    $offer_id = intval($_GET['offer_id']);
    $appointment_type = sanitize_text_field($_GET['appointment_type']) ?? '';
    if (!$offer_id) {
        wp_send_json_error(['message' => 'Invalid offer_id parameter.']);
        exit;
    }
	// API URL
	$api_url = tg_api_url( 'api/endpoints/mobile/v1/offers/' . $offer_id . '/get_calendar.json' ) . '?appointment_type=' . rawurlencode( $appointment_type );
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error fetching data from API']);
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            wp_send_json_success(json_decode(wp_remote_retrieve_body($response), true));
        } else {
            wp_send_json_error(['message' => 'API returned an error', 'status_code' => $status_code]);
        }
    }
}

// Custom action to show offer data on reservation page
add_action('wp_ajax_get_offer_data', 'handle_get_offer_data');
add_action('wp_ajax_nopriv_get_offer_data', 'handle_get_offer_data');
function handle_get_offer_data() {
    $offer_id = intval($_GET['offer_id']);
    if (!$offer_id) {
        wp_send_json_error(['message' => 'Invalid offer ID.']);
        exit;
    }
	// API URL
	$api_url = tg_api_url( "api/v7/offers/{$offer_id}.json" ) . '?lang=en';
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error fetching data from API']);
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            wp_send_json_success(json_decode(wp_remote_retrieve_body($response), true));
        } else {
            wp_send_json_error(['message' => 'API returned an error', 'status_code' => $status_code]);
        }
    }
}

// Custom action to add offer into cart
add_action('wp_ajax_add_to_cart', 'proxy_add_to_cart_request');
add_action('wp_ajax_nopriv_add_to_cart', 'proxy_add_to_cart_request');
function proxy_add_to_cart_request() {
	// API URL
	$api_url = tg_api_url( 'api/endpoints/mobile/v1/cart_items.json' ) . '?lang=en';
    $body = json_decode(file_get_contents('php://input'), true);
    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
    ]);
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Unable to connect to API']);
    }
    wp_send_json_success(wp_remote_retrieve_body($response));
}