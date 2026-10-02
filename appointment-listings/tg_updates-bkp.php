<?php 

defined( 'ABSPATH' ) || exit;

// Shortcode for appointment listings page
add_shortcode('tg_user_billings', 'fetch_user_billings_shortcode');
function fetch_user_billings_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>يجب عليك تسجيل الدخول لعرض مواعيدك.</p>';
    }
    
    ob_start();
    ?>

    <section class="al-main-section">
        <button class="ab-go-back"><img src="<?php echo plugins_url('../assets/images/go-back.svg', __FILE__ ); ?>" alt="go-back">الرجوع</button>
        <div class="hf-container">
            <div class="af-row">
                <div class="section-title">
                    <h1 class="csi-heading">المواعيد</h1>
                </div>
                <div class="af-wrapper">
                    <div class="af-item active" data-status="0">
                        <p class="afi-name">المواعيد المؤكدة</p>
                        <p class="afi-number">0</p>
                    </div>
                    <div class="af-item" data-status="1">
                        <p class="afi-name">المواعيد السابقة</p>
                        <p class="afi-number">0</p>
                    </div>
                </div>
            </div>
            <div id="appointment-listing" class="appointment-listing-row">
                <p>جارٍ تحميل المواعيد...</p>
            </div>
        </div>
    </section>
    <script>
        jQuery(document).ready(function($) {
            function fetchAppointments(payment_status_code) {
                $("#appointment-listing").html("<p>جارٍ تحميل المواعيد...</p>");
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'fetch_user_appointments',
                        payment_status_code: payment_status_code
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            $('#appointment-listing').html(response.data.html);
                            $('.af-item[data-status="' + payment_status_code + '"] .afi-number').text(response.data.count);
                        } else {
                            $('#appointment-listing').html('<p>لم يتم العثور على مواعيد.</p>');
                            $('.af-item[data-status="' + payment_status_code + '"] .afi-number').text(0);
                        }
                    },
                    error: function() {
                        $('#appointment-listing').html('<p>Error loading appointments.</p>');
                    }
                });
            }

            fetchAppointments(0);

            $('.af-item').click(function() {
                $('.af-item').removeClass('active');
                $(this).addClass('active');
                let status = $(this).data('status');
                fetchAppointments(status);
            });

            // Handle "Go Back" buttons
            $('.ab-go-back').on('click', function () {
                if (document.referrer) {
                    window.location.href = document.referrer;
                } else {
                    window.location.href = '/offers';
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

function fetch_user_appointments_callback() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not logged in']);
    }

    global $tabibgroup_base_url;
    $current_user_id = get_current_user_id();
    $access_token = get_user_meta($current_user_id, 'api_access_token', true);
    $client = get_user_meta($current_user_id, 'api_client', true);
    $uid = get_user_meta($current_user_id, 'api_uid', true);
    $payment_status_code = isset($_GET['payment_status_code']) ? sanitize_text_field($_GET['payment_status_code']) : '0';

    $api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v2/user/user_billings?payment_status_code=' . $payment_status_code . '&page=1';

    $response = wp_remote_get($api_url, [
        'headers' => [
            'access-token' => $access_token,
            'client' => $client,
            'uid' => $uid
        ],
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to fetch data', 'error' => $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['data']['user_payments'])) {
        wp_send_json_success([
            'html' => '<p>لم يتم العثور على مواعيد.</p>',
            'count' => 0
        ]);
    }

    $appointment_count = count($data['data']['user_payments']);

    ob_start();
    foreach ($data['data']['user_payments'] as $appointment) {
        $scheduled_date = $appointment['scheduled_date'];

        if ($scheduled_date === "Not Set" || empty($scheduled_date)) {
            $day_name = "N/A";
            $suffix = "Not Set";
            $month_year = "";
            $time = "";
        } else {
            // Split the date and time
            list($date_part, $time) = explode(" - ", $scheduled_date);
    
            // Convert date from "22-12-2024" to "2024-12-22" for strtotime()
            $date_parts = explode("-", $date_part);
            if (count($date_parts) === 3) {
                $formatted_date = $date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]; // "2024-12-22"
                $timestamp = strtotime($formatted_date);
            } else {
                $timestamp = false;
            }
    
            if ($timestamp) {
                // Extract parts
                $day_name = strtoupper(date('D', $timestamp)); // WED
                $day_number = date('j', $timestamp); // 28
                $month = strtoupper(date('M', $timestamp)); // NOV
                $year = date('Y', $timestamp); // 2024
    
                // Add suffix (st, nd, rd, th)
                $suffix = date('jS', $timestamp);
                $suffix = str_replace(['1S', '2S', '3S'], ['1st', '2nd', '3rd'], $suffix);
            } else {
                $day_name = "N/A";
                $suffix = "Invalid Date";
                $month_year = "";
            }
        }
        ?>
        <div class="appointment-listing-row appintment-listing-item">
            <div class="appointment-item-info-col">
                <div class="aiic-wrapper">
                    <div class="aiic-date">
                        <h3 class="aiic-d-day"><?php echo esc_html($day_name); ?></h3>
                        <h3 class="aiic-d-date"><?php echo esc_html($suffix); ?></h3>
                        <h3 class="aiic-d-year"><?php echo esc_html($month . ', ' . $year); ?></h3>
                    </div>
                    <div class="aiic-offer-info">
                        <span class="al-purchase-sms d-mobile"><?php echo esc_html($appointment['status']); ?></span>
                        <div class="aiic-offer-info1">
                            <div class="aiic-oi-offer-number">
                                <span class="aiic-oin-sh">رقم العرض</span>
                                <h2 class="al-of-heading"><?php echo esc_html($appointment['offer_id']); ?></h2>
                            </div>
                            <div class="aiic-oi-offer-price">
                                <span class="aiic-oin-sh">سعر الفاتورة</span>
                                <h2 class="al-of-price">SAR <?php echo esc_html($appointment['total_price']); ?></h2>
                            </div>
                        </div>
                        <div class="aiic-offer-info2 h-mobile">
                            <?php if(!empty($appointment['doctor_name'])) : ?>
                                <div class="al-oi-lt">
                                    <img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__ ); ?>" alt="map icon">
                                    <span class="al-oilt-text"><?php echo esc_html($appointment['doctor_name']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($time)) : ?>
                                <div class="al-oi-lt">
                                    <img src="<?php echo plugins_url('../assets/images/cal.svg', __FILE__ ); ?>" alt="calendar">
                                    <span class="al-oilt-text"><?php echo esc_html($time ?: 'N/A'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="aiic-offer-info2 d-mobile">
                            <?php if(!empty($appointment['doctor_name'])) : ?>
                                <div class="al-oi-lt">
                                    <img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__ ); ?>" alt="map icon">
                                    <span class="al-oilt-text"><?php echo esc_html($appointment['doctor_name']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($time)) : ?>
                                <div class="al-oi-lt">
                                    <img src="<?php echo plugins_url('../assets/images/cal.svg', __FILE__ ); ?>" alt="calendar">
                                    <span class="al-oilt-text"><?php echo esc_html($time ?: 'N/A'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="appointment-item-action-col">
                <span class="al-purchase-sms h-mobile"><?php echo esc_html($appointment['status']); ?></span>
                <div class="ai-ac-btns-wrapper">
                    <div>
                        <a href="" class="btn refunt-btn">Refund / Complaint</a>
                        <a href="" class="btn change-offer-btn">Change Offer</a>
                    </div>
                    <div>
                        <a href="" class="btn view-invoice-btn">View Invoice</a>
                        <a href="" class="btn track-status-btn">Track Status</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    wp_send_json_success([
        'html' => ob_get_clean(),
        'count' => $appointment_count
    ]);
}
add_action('wp_ajax_fetch_user_appointments', 'fetch_user_appointments_callback');