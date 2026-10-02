<?php

defined( 'ABSPATH' ) || exit;

function fetch_appointments($status, $headers, $page = 1) {
    $api_url = tg_api_url( 'api/endpoints/mobile/v2/user/user_billings' ) . '?payment_status_code=' . $status . '&page=' . $page;
    $args = [
        'headers' => $headers,
        'timeout' => 30
    ];
    $response = wp_remote_get($api_url, $args);

    if (is_wp_error($response)) {
        return ['appointments' => [], 'total_pages' => 1];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    // Extract total pages and count
    $total_pages = isset($body['total_pages']) ? $body['total_pages'] : 1;
    $per_page = count($body['data']['user_payments']);
    $total_count = $per_page * $total_pages;

    return [
        'appointments' => $body['data']['user_payments'] ?? [],
        'total_pages' => $body['total_pages'] ?? 1,
        'total_count' => $total_count
    ];
}

add_shortcode('user_appointments', 'render_appointments');
function render_appointments() {
    $current_user = wp_get_current_user();
    $current_user_id = get_current_user_id();
    if ( ! is_user_logged_in() ) {
        return 'يجب عليك تسجيل الدخول لعرض هذه الصفحة.';
    }
    if ( ! in_array( 'subscriber', (array) $current_user->roles ) ) {
        return 'ليس لديك الصلاحية لعرض هذه الصفحة.';
    }
    $headers = [
        'access-token' => get_user_meta($current_user_id, 'api_access_token', true),
        'client' => get_user_meta($current_user_id, 'api_client', true),
        'uid' => get_user_meta($current_user_id, 'api_uid', true)
    ];
    
    // Fetch first page data
    $current_data = fetch_appointments(0, $headers, 1);
    $past_data = fetch_appointments(1, $headers, 1);

    // Get counts
    $current_count = $current_data['total_count'];
    $past_count = $past_data['total_count'];

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
                    <div class="af-item active" onclick="showAppointments('current')">
                        <p class="afi-name">المواعيد المؤكدة</p>
                        <p class="afi-number"><?php echo $current_count; ?></p>
                    </div>
                    <div class="af-item" onclick="showAppointments('past')">
                        <p class="afi-name">المواعيد السابقة</p>
                        <p class="afi-number"><?php echo $past_count; ?></p>
                    </div>
                </div>
            </div>
            <div id="appointment-listing" class="appointment-listing-row">
                <div id="tg-loader-overlay">
                    <div class="tg-spinner"></div>
                </div>
                <div id="current-appointments" class="appointments-list">
                    <?php render_appointments_list($current_data['appointments'], 'current'); ?>
                    <?php render_pagination('current', $current_data['total_pages']); ?>
                </div>
                <div id="past-appointments" class="appointments-list" style="display:none;">
                    <?php render_appointments_list($past_data['appointments'], 'past'); ?>
                    <?php render_pagination('past', $past_data['total_pages']); ?>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function render_appointments_list($appointments, $type) { 
    if (!empty($appointments)) {
        foreach ($appointments as $appointment) { 
            $scheduled_date = $appointment['scheduled_date'];
            if ($scheduled_date === "Not Set" || empty($scheduled_date)) {
                $day_name = "";
                $suffix = "";
                $month_year = "";
                $time = "";
            } else {
                list($date_part, $time) = explode(" - ", $scheduled_date);
                $date_parts = explode("-", $date_part);
                if (count($date_parts) === 3) {
                    $formatted_date = $date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0];
                    $timestamp = strtotime($formatted_date);
                } else {
                    $timestamp = false;
                }

                if ($timestamp) {
                    $day_name = strtoupper(date('D', $timestamp));
                    $day_number = date('j', $timestamp);
                    $month = strtoupper(date('M', $timestamp));
                    $year = date('Y', $timestamp);
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
                            <?php echo $scheduled_date; ?>
                            <?php if ($scheduled_date === "Not Set" || empty($scheduled_date)) : ?>
                                <h3 class="aiic-d-date">N/A</h3>
                            <?php else : ?>
                                <h3 class="aiic-d-day"><?php echo esc_html($day_name); ?></h3>
                                <h3 class="aiic-d-date"><?php echo esc_html($suffix); ?></h3>
                                <h3 class="aiic-d-year"><?php echo esc_html($month . ', ' . $year); ?></h3>
                            <?php endif; ?>
                        </div>
                        <div class="aiic-offer-info">
                            <?php if ( $appointment['status'] == "confirmed" ) : ?>
                                <span class="al-purchase-sms d-mobile">العرض تم شراء</span>
                            <?php elseif ( $appointment['status'] == "canceled" ) : ?>
                                <span class="al-purchase-sms d-mobile">تم الإلغاء</span>
                            <?php else: ?>
                                <span class="al-purchase-sms d-mobile"><?php echo esc_html($appointment['status']); ?></span>
                            <?php endif; ?>
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
                                <?php if (!empty($appointment['doctor_name'])) : ?>
                                    <div class="al-oi-lt">
                                        <img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__ ); ?>" alt="map icon">
                                        <span class="al-oilt-text"><?php echo esc_html($appointment['doctor_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($time)) : ?>
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
                    <?php if ( $appointment['status'] == "confirmed" ) : ?>
                        <span class="al-purchase-sms h-mobile">العرض تم شراء</span>
                    <?php elseif ( $appointment['status'] == "canceled" ) : ?>
                        <span class="al-purchase-sms h-mobile">تم الإلغاء</span>
                    <?php else: ?>
                        <span class="al-purchase-sms h-mobile"><?php echo esc_html($appointment['status']); ?></span>
                    <?php endif; ?>
                    <div class="ai-ac-btns-wrapper">
                        <?php if ($type === 'current') : ?>
                            <div class="current-app-btns">
                                <a href="" class="btn refund-btn <?php echo $appointment['is_complaint'] ? 'gray-btn' : ''; ?>">استرداد / شكوى</a>
                                <a href="" class="btn change-offer-btn <?php echo $appointment['is_requested'] ? 'gray-border' : ''; ?>"><?php echo $appointment['is_requested'] ? 'تم إرسال الطلب' : 'تغييرالعرض '; ?></a>
                            </div>
                            <div class="current-app-btns">
                                <button data-appointment-id="<?php echo $appointment['id']; ?>" data-bill-type="<?php echo $appointment['bill_type']; ?>" data-receipt-hash="<?php echo $appointment['receipt_hash']; ?>" class="btn view-invoice-btn">عرض الفاتورة</button>
                                <a href="" class="btn track-status-btn">تتبع حالة تاكيد موعدك هنا</a>
                            </div>
                        <?php else : ?>
                            <div class="past-app-btns">
                                <?php if ($appointment['status_four'] == "true"): ?>
                                    <button data-appointment-id="<?php echo $appointment['id']; ?>" data-bill-type="<?php echo $appointment['bill_type']; ?>" data-receipt-hash="<?php echo $appointment['receipt_hash']; ?>" class="btn view-invoice-btn">عرض الفاتورة</button>
                                <?php endif; ?>
                                <a href="" class="btn refund-btn <?php echo $appointment['is_complaint'] ? 'gray-btn' : ''; ?>">استرداد / شكوى</a>
                            </div>
                            <?php if ($appointment['status'] == "confirmed" && !$appointment['is_rated']): ?>
                                <a href="#" class="btn rate-appointment-btn wm-100">أضف تقييمًا لموعدك السابق</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                        // Check if any invoice buttons are present
                        const invoiceButtons = document.querySelectorAll('.view-invoice-btn');
                        if (invoiceButtons.length === 0) {
                            return;
                        }

                        // Inject Modal HTML
                        const modalHTML = `
                            <div id="invoiceModal">
                            <div class="modal-content">
                                <div class="modal-header">
                                <h2 id="invoiceModalTitle">الفاتورة</h2>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <a id="downloadInvoiceBtn" href="#" target="_blank">تحميل</a>
                                    <button id="closeInvoiceModal">&times;</button>
                                </div>
                                </div>
                                <div class="modal-body">
                                    <div class="tg_spinner" id="TGspinnerLoader">
                                        <div class="bounce1"></div>
                                        <div class="bounce2"></div>
                                        <div class="bounce3"></div>
                                    </div>
                                    <iframe id="invoiceIframe" src=""></iframe>
                                </div>
                            </div>
                            </div>
                        `;
                        document.body.insertAdjacentHTML('beforeend', modalHTML);

                        // Variables
                        const domainURL = window.location.origin;
                        const serverUrl = `${domainURL}/api/endpoints/partner/p1/tabby/`;
                        const pdfBaseUrl = `${domainURL}/receipts/pdf/`;
                        const modal = document.getElementById('invoiceModal');
                        const iframe = document.getElementById('invoiceIframe');
                        const title = document.getElementById('invoiceModalTitle');
                        const downloadBtn = document.getElementById('downloadInvoiceBtn');
                        const closeBtn = document.getElementById('closeInvoiceModal');
                        const spinner = document.getElementById('TGspinnerLoader');

                        // Open Modal
                        invoiceButtons.forEach(function (btn) {
                            btn.addEventListener('click', function (e) {
                            e.preventDefault();

                            const appointmentId = btn.getAttribute('data-appointment-id');
                            const billType = btn.getAttribute('data-bill-type');
                            const receiptHash = btn.getAttribute('data-receipt-hash');

                            let finalPdfUrl = '';
                            let downloadUrl = '';

                            if (billType && billType.toLowerCase() === 'tabby') {
                                const tabbyUrl = serverUrl + appointmentId + '/receipt';
                                downloadUrl = tabbyUrl;
                                finalPdfUrl = "https://drive.google.com/viewerng/viewer?embedded=true&url=" + encodeURIComponent(tabbyUrl);
                            } else {
                                const pdfUrl = pdfBaseUrl + receiptHash;
                                downloadUrl = pdfUrl;
                                finalPdfUrl = "https://drive.google.com/viewerng/viewer?embedded=true&url=" + encodeURIComponent(pdfUrl);
                            }

                            title.textContent = 'الفاتورة # ' + appointmentId;
                            iframe.style.display = 'none';
                            spinner.style.display = 'block';

                            iframe.onload = function () {
                                spinner.style.display = 'none';
                                iframe.style.display = 'block';
                            };

                            iframe.src = finalPdfUrl;
                            downloadBtn.href = downloadUrl;
                            modal.style.display = 'flex';
                            });
                        });

                        // Close Modal
                        closeBtn.addEventListener('click', function () {
                            modal.style.display = 'none';
                            iframe.src = '';
                        });

                        modal.addEventListener('click', function (e) {
                            if (e.target === modal) {
                            modal.style.display = 'none';
                            iframe.src = '';
                            }
                        });
                        });
                        </script>
                    </div>
                </div>
            </div>
        <?php }
    } else {
        echo "<p>لم يتم العثور على مواعيد.</p>";
    }
}

function render_pagination($type, $total_pages, $current_page = 1) {
    if ($total_pages > 1) {
        echo '<div class="tg-listing-pagination">';
        // Previous Button
        $prev_disabled = ($current_page == 1) ? 'disabled' : '';
        echo '<button class="page-numbers prev ' . $prev_disabled . '" onclick="fetchAppointments(' . ($current_page - 1) . ', \'' . $type . '\')">‹ خلف</button>';
        // Page Numbers
        $visible_pages = 3;
        $dots_added = false;
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == 1 || $i == $total_pages || abs($i - $current_page) < $visible_pages) {
                $active_class = ($i == $current_page) ? 'current' : '';
                echo '<button class="page-numbers ' . $active_class . '" onclick="fetchAppointments(' . $i . ', \'' . $type . '\')">' . $i . '</button>';
                $dots_added = false;
            } else {
                if (!$dots_added) {
                    echo '<span class="dots">...</span>';
                    $dots_added = true;
                }
            }
        }
        // Page Text
        echo '<div class="pagination-text">صفحة ' . $current_page . ' ل ' . $total_pages . '</div>';
        // Next Button
        $next_disabled = ($current_page == $total_pages) ? 'disabled' : '';
        echo '<button class="page-numbers next ' . $next_disabled . '" onclick="fetchAppointments(' . ($current_page + 1) . ', \'' . $type . '\')">التالي ›</button>';
        echo '</div>';
    }
}

add_action('wp_ajax_load_appointments', 'load_appointments_callback');
add_action('wp_ajax_nopriv_load_appointments', 'load_appointments_callback');
function load_appointments_callback() {
    $current_user_id = get_current_user_id();
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'current';
    $headers = [
        'access-token' => get_user_meta($current_user_id, 'api_access_token', true),
        'client' => get_user_meta($current_user_id, 'api_client', true),
        'uid' => get_user_meta($current_user_id, 'api_uid', true)
    ];
    $status_code = ($type === 'current') ? 0 : 1;
    $data = fetch_appointments($status_code, $headers, $page);
    ob_start();
    render_appointments_list($data['appointments'], $type);
    render_pagination($type, $data['total_pages'], $page);
    echo ob_get_clean();
    wp_die();
}
