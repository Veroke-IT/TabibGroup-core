<?php

defined( 'ABSPATH' ) || exit;

// Shortcode to display offer categories based on the city selected
add_shortcode('categories_slider', 'display_categories_slider');
function display_categories_slider() {
    ob_start();
    ?>
    <div id="categories-wrapper"></div>
    <?php
    return ob_get_clean();
}

// Homepage offer categories sliders
add_shortcode('home_offer_sliders', function () {
    return '<div id="home-offer-sliders" data-loaded="false"><p class="loading-text">جارٍ تحميل العروض...</p></div>';
});

// Related offers slider on offer detail page
add_shortcode('related_offers_slider', function () {
    return '<div id="related-offers-slider" data-loaded="false"><p class="loading-text">جارٍ تحميل العروض...</p></div>';
});

/* Shortcode: display prouct categories with a bookmark icon */
add_shortcode('tg_product_categories_badges', function($atts) {
    $atts = shortcode_atts([
        'id' => 0,
        'class' => '',
    ], $atts, 'tg_product_categories_badges');
    $product_id = intval($atts['id']);
    if (!$product_id) {
        if ( function_exists('wc_get_product') ) {
            global $product;
            if ($product && $product->get_id()) {
                $product_id = $product->get_id();
            }
        }
    }
    if (!$product_id) {
        return '';
    }
    $terms = get_the_terms($product_id, 'product_cat');
    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }
    $terms = array_values(array_unique($terms, SORT_REGULAR));
    foreach ($terms as $i => $t) {
        $anc = get_ancestors($t->term_id, 'product_cat');
        $terms[$i]->_depth = is_array($anc) ? count($anc) : 0;
    }
    usort($terms, function($a, $b) {
        if ($a->_depth === $b->_depth) {
            return strcmp(mb_strtolower($a->name), mb_strtolower($b->name));
        }
        return ($a->_depth < $b->_depth) ? -1 : 1;
    });
    $html = '';
    $html .= '<div class="tg-cat-badges ' . esc_attr($atts['class']) . '">';
    foreach ($terms as $term) {
        $term_link = esc_url(get_term_link($term));
        $term_name = esc_html($term->name);
        $html .= '<a href="' . $term_link . '" class="tg-cat-badge" rel="tag" aria-label="' . $term_name . '">';
        $html .= '<span class="tg-cat-text">' . $term_name . '</span>';
        $html .= '</a>';
    }
    $html .= '</div>';
    return $html;
});

// Shortcode to Display the Discount Percentage as a Sale Badge on Offer Cards
add_shortcode('sale_percentage', 'show_sale_percentage_shortcode');
function show_sale_percentage_shortcode($atts) {
    $atts = shortcode_atts(array('product_id' => ''), $atts);
    $product_id = $atts['product_id'];
    if (!empty($product_id)) {
        $product = wc_get_product($product_id);
    } else {
        global $product;
    }
    if (!$product) return '';
    $regular_price = (float) $product->get_regular_price();
    $sale_price    = (float) $product->get_sale_price();
    // Make sure both prices exist and are different
    if ( $regular_price > 0 && $sale_price > 0 && $regular_price != $sale_price) {
        $percentage_off = round((($regular_price - $sale_price) / $regular_price) * 100);
        if ($percentage_off != 0) {
            return '<span dir="ltr">-' . abs($percentage_off) . '%</span>';
        }
    }
    return '';
}

// Shortcode to display a custom search form that submits to a /search-results page
function tabibgroup_search_form_shortcode() {
    ob_start();
    ?>
    <form id="tabibgroup-search-form" action="/search-results" method="get" class="tabibgroup-search-form">
        <input
            type="text"
            name="search"
            placeholder="ابحث هنا"
            required
        />
        <button type="submit" aria-label="search">
            <i class="ti-search overlay-trigger"></i>
        </button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('tabib_search_form', 'tabibgroup_search_form_shortcode');

// Shortcode to display the chat widget
add_shortcode('tg_chat', 'tg_chat_shortcode');
function tg_chat_shortcode($atts = []) {
    $user = wp_get_current_user();
    $user_json = json_encode([
        'is_logged_in' => is_user_logged_in(),
        'id' => is_user_logged_in() ? $user->ID : 0,
        'name' => is_user_logged_in() ? $user->display_name : '',
        'email' => is_user_logged_in() ? $user->user_email : '',
    ]);

    ob_start();
    ?>
    <div id="tg-chat-container" class="tg-chat-closed" data-user='<?php echo esc_attr($user_json); ?>'>
        <div class="tg-chat-widget">
            <div class="tg-chat-overlay"></div>
            <div class="tg-chat-header">
                <div class="title-logo-wrap">
                    <img src="<?php echo plugins_url('../assets/images/tg-chat-logo.svg', __FILE__ ); ?>" alt="Logo" class="tg-chat-logo" />
                    <span class="tg-chat-title">خدمة العملاء</span>
                </div>
                <div class="tg-chat-controls">
                    <button class="tg-chat-close" aria-label="Close chat">
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.42329 0.57685C9.74187 0.895435 9.74187 1.41196 9.42329 1.73055L1.73195 9.42188C1.41337 9.74047 0.896837 9.74047 0.578252 9.42188C0.259666 9.1033 0.259667 8.58677 0.578252 8.26818L8.26959 0.57685C8.58817 0.258265 9.1047 0.258265 9.42329 0.57685Z" fill="black"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.42193 9.42182C9.10334 9.74041 8.58681 9.74041 8.26823 9.42182L0.576896 1.73049C0.258311 1.4119 0.258311 0.895371 0.576896 0.576786C0.895482 0.258201 1.41201 0.258201 1.7306 0.576786L9.42193 8.26812C9.74051 8.58671 9.74051 9.10324 9.42193 9.42182Z" fill="black"/>
                        </svg>
                    </button>
                </div>
            </div>
            <?php if ( ! is_user_logged_in() ) : ?>
                <div class="tg-chat-intro">
                    <p>تواصل مع خدمة عملاء مجموعة طبيب واحصل على إجابات فورية لاستفساراتك برسالة واحدة. عرفنا بنفسك أو <a href="/account/">سجل دخولك</a>.. وهيا نبدأ!</p>
                    <form id="tg-chat-guest-form">
                        <div class="form-group">
                            <label for="guestName">الاسم الكامل *</label>
                            <input type="text" id="guestName" name="guestName" placeholder="أدخل اسمك الكامل" required>
                        </div>
                        <div class="form-group">
                            <label for="guestPhone">رقم الهاتف *</label>
                            <input type="tel" id="guestPhone" name="guestPhone" placeholder="أدخل رقم هاتفك" required>
                        </div>
                        <button type="submit" class="chat-submit">ابدأ المحادثة</button>
                    </form>
                </div>
            <?php else: ?>
            <div class="tg-chat-body">
                <div class="tg-chat-messages" aria-live="polite"></div>
                <div class="tg-chat-typing" style="display:none;">المدير يكتب...</div>
            </div>
            <div class="tg-chat-input">
                <input type="text" id="tg-chat-input-text" placeholder="أكتب رسالتك هنا..." />
                <!-- <input type="file" id="tg-chat-file" /> -->
                <button id="tg-chat-send">إرسال</button>
            </div>
            <?php endif; ?>
        </div>

        <button class="tg-chat-launch">
            <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3.00392 18.0279C3.00468 14.7341 4.08784 11.5319 6.08672 8.914C8.08559 6.29613 10.8894 4.40769 14.0666 3.53933C17.2438 2.67097 20.6184 2.8708 23.6709 4.10809C26.7234 5.34537 29.2847 7.55152 30.9607 10.387C32.6366 13.2225 33.3343 16.5302 32.9463 19.801C32.5583 23.0718 31.1061 26.1245 28.8133 28.4892C26.5206 30.8539 23.5142 32.3996 20.2569 32.8884C16.9996 33.3772 13.672 32.782 10.7861 31.1944L4.94496 32.9732C4.68442 33.0526 4.40721 33.0596 4.14298 32.9936C3.87875 32.9276 3.63744 32.791 3.44485 32.5984C3.25227 32.4058 3.11566 32.1645 3.04964 31.9003C2.98362 31.636 2.99066 31.3588 3.07003 31.0983L4.84881 25.2481C3.63477 23.0355 3.00013 20.5517 3.00392 18.0279ZM12.018 16.5255C12.018 16.924 12.1763 17.3061 12.458 17.5878C12.7398 17.8696 13.1219 18.0279 13.5204 18.0279H22.5344C22.9329 18.0279 23.315 17.8696 23.5968 17.5878C23.8785 17.3061 24.0368 16.924 24.0368 16.5255C24.0368 16.1271 23.8785 15.7449 23.5968 15.4632C23.315 15.1814 22.9329 15.0232 22.5344 15.0232H13.5204C13.1219 15.0232 12.7398 15.1814 12.458 15.4632C12.1763 15.7449 12.018 16.1271 12.018 16.5255ZM13.5204 21.0325C13.1219 21.0325 12.7398 21.1908 12.458 21.4726C12.1763 21.7543 12.018 22.1364 12.018 22.5349C12.018 22.9333 12.1763 23.3155 12.458 23.5972C12.7398 23.879 13.1219 24.0372 13.5204 24.0372H19.5297C19.9282 24.0372 20.3103 23.879 20.5921 23.5972C20.8738 23.3155 21.0321 22.9333 21.0321 22.5349C21.0321 22.1364 20.8738 21.7543 20.5921 21.4726C20.3103 21.1908 19.9282 21.0325 19.5297 21.0325H13.5204Z" fill="white"/>
            </svg>
        </button>
    </div>
    <?php
    return ob_get_clean();
}