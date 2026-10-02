<?php
/**
 * Plugin Name: Tabib Group Core
 * Plugin URI: https://tabibgroup.net
 * Description: Integrates website with Tabeeb CRM using APIs and provides offer sync.
 * Version: 1.0
 * Author: Veroke
 * Author URI: https://veroke.com
 * License: GPL2
 * Text Domain: TabibGroup-core
 */

defined( 'ABSPATH' ) || exit;

// Fetch categories for the product, ordered by hierarchy
add_filter('woocommerce_product_categories', function($categories, $product_id) {
    $terms = get_the_terms($product_id, 'product_cat');
    if (empty($terms) || is_wp_error($terms)) {
        return $categories;
    }
    // Reorder terms so parents come first
    usort($terms, function($a, $b) {
        if ($a->parent === $b->term_id) return 1;
        if ($b->parent === $a->term_id) return -1;
        return $a->term_id - $b->term_id;
    });
    // Rebuild HTML like WooCommerce does
    $links = [];
    foreach ($terms as $term) {
        $links[] = sprintf(
            '<a href="%s" rel="tag">%s</a>',
            esc_url(get_term_link($term, 'product_cat')),
            esc_html($term->name)
        );
    }
    return implode(', ', $links);
}, 10, 2);

// wp_enqueue_scripts for single offers, clinics, login, register, confirmation, city popup etc.
add_action('wp_enqueue_scripts', 'add_tg_js_import');
function add_tg_js_import() {
    wp_enqueue_script('socket-io', 'https://cdnjs.cloudflare.com/ajax/libs/socket.io/2.1.1/socket.io.js', [], null, true);
    wp_enqueue_script('tg-chat', plugins_url('assets/js/tg-chat.js', __FILE__ ), array('socket-io', 'jquery'), null, true);
    $current_user = wp_get_current_user();
    $is_logged_in = is_user_logged_in();
    $user_id = $is_logged_in ? get_current_user_id() : 0;
    $user_data = [
        'is_logged_in' => $is_logged_in,
        'id'            => $is_logged_in ? (get_user_meta($user_id, 'api_id', true) ?: 0) : 0,
        'name'          => $is_logged_in ? $current_user->display_name : '',
        'email'         => $is_logged_in ? $current_user->user_email : '',
        'access_token'  => $is_logged_in ? get_user_meta($user_id, 'api_access_token', true) : '',
        'ajax_url'      => admin_url('admin-ajax.php'),
        'home_api' => esc_url_raw( tg_api_url( 'api/endpoints/mobile/v3/home.json' ) ),
        'guest_api'     => get_option('enable_test_mode', false)
            ? 'https://staging.tgchat.app/api/chat/registerGuest'
            : 'https://tgchat.app/api/chat/registerGuest',
    ];
    wp_localize_script('tg-chat', 'TG_CHAT_DATA', $user_data);

    // Enqueue other scripts conditionally
    if ( is_front_page() || is_page(15925) ) {
        wp_enqueue_script('tg-home-js', plugins_url('assets/js/tg-home.js', __FILE__ ), array('jquery'), null, true);
            
        wp_localize_script( 'tg-home-js', 'homeData', array(
            'baseUrl' => esc_url_raw( tg_base_url() ),
        ) );
    }

    if ( !is_page('thank-you') ) {
        wp_enqueue_script('tg-main-js', plugins_url('assets/js/tg-main.js', __FILE__ ), array('jquery'), null, true);
        wp_localize_script('tg-main-js', 'mainData', [
            'baseUrl'    => esc_url_raw( tg_base_url() ),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( 'mainData_nonce' ),
            'is_logged_in' => is_user_logged_in(),
            'isTestMode' => get_option('enable_test_mode', false),
            ]);
    }

	if ( is_product() ) {
		wp_enqueue_script( 'tg-offers-js', plugins_url('assets/js/tg-offers.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_localize_script( 'tg-offers-js', 'offersObj', array (
            'baseUrl'    => esc_url_raw( tg_base_url() ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'offers_frontend_nonce' ),
            'isTestMode' => get_option('enable_test_mode', false),
        ));
        wp_enqueue_script('tg-reservation-js', plugins_url('assets/js/tg-reservation.js', __FILE__ ), array('jquery'), null, true);
        global $post;
        $product = wc_get_product($post->ID);
        $product_id = $product ? $product->get_id() : '';
        $offer_id = $product ? $product->get_sku() : '';
        wp_localize_script('tg-reservation-js', 'reservationData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('get_reservation_type_nonce'),
            'is_logged_in' => is_user_logged_in(),
            'offer_id' => $offer_id,
            'product_id' => $product_id,
            'reservation_page_url' => site_url('/reservation/'),
        ]);
        wp_enqueue_style('swiper-css', get_template_directory_uri() . '/assets/css/libs/swiper.min.css', [], null);
        wp_enqueue_script('swiper-js', get_template_directory_uri() . '/assets/js/libs/swiper.min.js', [], null, true);
    }

    if ( is_shop() ) {
		wp_enqueue_script( 'tg-home-navi-js', plugins_url('assets/js/tg-home-navi.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_localize_script('tg-home-navi-js', 'naviData', [
            'baseUrl'    => esc_url_raw( tg_base_url() ),
            'query_vars' => [
                'collection_id' => get_query_var('collection_id'),
                'section_id'    => get_query_var('section_id'),
                'specialist_id' => get_query_var('specialist_id'),
                'doctor_id'     => get_query_var('doctor_id'),
            ]
        ]);
    }

    if ( is_product_category() ) {
		wp_enqueue_script( 'tg-category-js', plugins_url('assets/js/tg-category.js', __FILE__ ), array( 'jquery' ), null, true );
    }

    if ( is_page('2603') ) {
        wp_enqueue_script('tg-availability-js', plugins_url('assets/js/tg-availability.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_localize_script('tg-availability-js', 'availabilityData', [
            'baseUrl'    => esc_url_raw( tg_base_url() ),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( 'get_availability_type_nonce' ),
            'isTestMode' => get_option('enable_test_mode', false),
        ]);
    }

    if ( is_page('cart') ) {
        wp_enqueue_script('tg-cart-js', plugins_url('assets/js/tg-cart.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_localize_script('tg-cart-js', 'cartData', [
            'baseUrl'    => esc_url_raw( tg_base_url() ),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( 'get_cart_type_nonce' ),
            'isTestMode' => get_option('enable_test_mode', false),
        ]);
    }

    if ( is_page('checkout') ) {
        wp_enqueue_script('tg-checkout-js', plugins_url('assets/js/tg-checkout.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_localize_script('tg-checkout-js', 'checkoutData', [
            'baseUrl'    => esc_url_raw( tg_base_url() ),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce( 'get_checkout_nonce' ),
            'isTestMode' => get_option('enable_test_mode', false),
        ]);
        wp_enqueue_script( 'apple-pay-script', 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js', [], null, true );
    }
	
    if ( is_post_type_archive('clinic_listing') || is_singular('clinic_listing') ) {
		wp_enqueue_script( 'tg-clinics-cpt-js', plugins_url('assets/js/tg-clinics-cpt.js', __FILE__ ), array( 'jquery' ), null, true );
    }

	if ( is_page('1985') ) {
		wp_enqueue_script( 'tg-confirmation-js', plugins_url('assets/js/tg-confirmation.js', __FILE__ ), array( 'jquery' ), null, true );
		wp_localize_script('tg-confirmation-js', 'confirmationObj', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('confirm_user_registration_nonce'),
		]);
    }

    if ( is_page('15618') ) {
		wp_enqueue_script( 'tg-appointments-js', plugins_url('assets/js/tg-appointments.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_localize_script('tg-appointments-js', 'appointmentsObj', admin_url('admin-ajax.php'));
    }

    if ( is_page('1985') || is_page('16') || is_page('2000') ) {
        wp_enqueue_script( 'tg-password-icon-js', plugins_url('assets/js/tg-password-icon.js', __FILE__ ), array('jquery'), null, true );
    }

    // Only load on search results page
    if (is_page_template('page-search-results.php')) {
        wp_enqueue_script('search-js', plugins_url('assets/js/tg-search-results.js', __FILE__ ), [], null, true);
        wp_enqueue_style('swiper-css', get_template_directory_uri() . '/assets/css/libs/swiper.min.css', [], null);
        wp_enqueue_script('swiper-js', get_template_directory_uri() . '/assets/js/libs/swiper.min.js', [], null, true);
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-ar', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js', ['flatpickr-js'], null, true);
    }

}

// Set clinic listing archive to display 12 items per page (4 columns × 3 rows)
add_filter('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_post_type_archive('clinic_listing') && $query->is_main_query()) {
        $query->set('posts_per_page', 12);
    }
    return $query;
});

// Remove unwanted scripts/styles from Website
add_action( 'wp_enqueue_scripts', function() {
    if( ! is_cart() && ! is_checkout() && ! is_account_page() && ! is_shop() && ! is_product() ) {
        // Remove WooCommerce styles
        wp_dequeue_style( 'wc-blocks-style' );
        wp_dequeue_style( 'wc-blocks-style-rtl' );
        wp_dequeue_style( 'jquery-blockui' );
        wp_dequeue_style( 'jquery-blockui' );
        // Remove WooCommerce scripts
        wp_dequeue_script( 'woocommerce' );
        wp_deregister_script( 'woocommerce' );
        // Remove cart related scripts
        wp_dequeue_script( 'wc-cart-fragments' );
        wp_deregister_script( 'wc-cart-fragments' );
        wp_dequeue_script( 'wc-add-to-cart' );
        wp_deregister_script( 'wc-add-to-cart' );
    }
}, 999 );

// Register extra custom fields in YOAST SEO to print bold and light titles
add_action('wpseo_register_extra_replacements', function () {
    // Register %%bold_title%%
    wpseo_register_var_replacement(
        '%%bold_title%%',
        function () {
            if (is_singular('product')) {
                $bold = get_post_meta(get_the_ID(), 'bold_title', true);
                return $bold ?: '';
            }
            return '';
        },
        'advanced',
        'Returns the bold title from product meta'
    );

    // Register %%light_title%%
    wpseo_register_var_replacement(
        '%%light_title%%',
        function () {
            if (is_singular('product')) {
                $light = get_post_meta(get_the_ID(), 'light_title', true);
                return $light ?: '';
            }
            return '';
        },
        'advanced',
        'Returns the light title from product meta'
    );

    // Register %%clinic_name%%
    wpseo_register_var_replacement(
        '%%clinic_name%%',
        function () {
            if (is_singular('product')) {
                $clinic = get_post_meta(get_the_ID(), 'hospital_name', true);
                return $clinic ?: '';
            }
            return '';
        },
        'advanced',
        'Returns the clinic name from product meta'
    );

    // Register %%city%%
    wpseo_register_var_replacement(
        '%%city%%',
        function () {
            if (is_singular('product')) {
                $cities = get_post_meta(get_the_ID(), 'cities', true);
                return $cities ?: '';
            }
            return '';
        },
        'advanced',
        'Returns the cities from product meta'
    );
});

// Decrease the number of URLs per sitemap file to 200
add_filter( 'wpseo_sitemap_entries_per_page', function( $n ) {
    return 200;
});

// Redirect the user to the actual product URL from sku
add_action('template_redirect', 'redirect_offer_sku_to_product');
function redirect_offer_sku_to_product() {
    if (is_singular('product')) {
        return;
    }
    $request_uri = trim($_SERVER['REQUEST_URI'], '/');
    $base_slug = 'offers/';
    if (strpos($request_uri, $base_slug) === 0) {
        $sku = str_replace($base_slug, '', $request_uri);        
        if (is_numeric($sku)) {
            global $wpdb;

            // Get the product ID based on SKU
            $product_id = $wpdb->get_var($wpdb->prepare("
                SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_sku' 
                AND meta_value = %s
            ", $sku));

            if ($product_id) {
                $product_url = get_permalink($product_id);
                if ($product_url) {
                    wp_redirect($product_url, 301);
                    exit;
                }
            }
        }
    }
}

// Clinic listings logic handling
function tg_add_slugs_rewrite_rules() {

    // Offer Subcategory
    add_rewrite_rule(
        '^offer-category/([^/]+)/([^/]+)/?',
        'index.php?taxonomy=product_cat&term=$matches[1]&subcategory=$matches[2]',
        'top'
    );

    // Offer Category
    add_rewrite_rule(
        '^offer-category/([^/]+)/?',
        'index.php?taxonomy=product_cat&term=$matches[1]',
        'top'
    );

    // Collection
    add_rewrite_rule(
        '^offers/collection/([0-9]+)/([^/]+)/?',
        'index.php?pagename=offers&collection_id=$matches[1]',
        'top'
    );

    // Section
    add_rewrite_rule(
        '^offers/section/([0-9]+)/([^/]+)/?',
        'index.php?pagename=offers&section_id=$matches[1]',
        'top'
    );

    // Specialist
    add_rewrite_rule(
        '^offers/specialist/([0-9]+)/([^/]+)/?',
        'index.php?pagename=offers&specialist_id=$matches[1]',
        'top'
    );

    // Doctor
    add_rewrite_rule(
        '^offers/doctor/([0-9]+)/([^/]+)/?',
        'index.php?pagename=offers&doctor_id=$matches[1]',
        'top'
    );

}
add_action('init', 'tg_add_slugs_rewrite_rules');

function tg_add_query_vars($vars) {
    $vars[] = 'subcategory';
    $vars[] = 'collection_id';
    $vars[] = 'section_id';
    $vars[] = 'specialist_id';
    $vars[] = 'doctor_id';
    return $vars;
}
add_filter('query_vars', 'tg_add_query_vars');

// Clear Session and redirect to account page after logout
add_action('wp_logout','clear_session_redirect_after_logout');
function clear_session_redirect_after_logout() {
	if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
	wp_redirect( '/account' );
	exit();
}

// Hook to add an admin menu for manual sync
add_action('admin_menu', 'woocommerce_offer_importer_menu');
function woocommerce_offer_importer_menu() {
	add_menu_page('Offers Importer', 'Offers Importer', 'manage_options', 'offer-importer', 'woocommerce_offer_importer_page', 'dashicons-update');
}

add_action('admin_init', 'register_offer_importer_settings');
function register_offer_importer_settings() {
    register_setting('offer_importer_settings', 'enable_test_mode');
}

// Register custom action to detect city
function tg_bricks_city_output() {
    $city = isset($_COOKIE['selected_city_name']) ? urldecode($_COOKIE['selected_city_name']) : 'جده';
    echo esc_html($city);
}
add_action('tg_get_city', 'tg_bricks_city_output');

// Convert a clinic or doctor name into the exact WordPress slug
function custom_tg_clinic_slug( $clinic_name ) {
    if (empty($clinic_name)) return '';
    $clinic_name = preg_replace('/\s+/u', ' ', trim($clinic_name));
    $slug = sanitize_title( $clinic_name );
    return $slug;
}

/* Bricks Builder dynamic clinic slug helper */
function custom_tg_get_clinic_slug_bricks($product_id) {
    if (!$product_id) return get_the_ID();
    $doctor_name = get_post_meta($product_id, 'doctor_name', true);
    if (!$doctor_name) return '';
    return custom_tg_clinic_slug($doctor_name);
}

// Modify query vars of Bricks builder on category page template
add_filter( 'bricks/posts/query_vars', function( $query_vars, $settings, $element_id, $element_name ) {
    $global_css_classes = isset( $settings['_cssGlobalClasses'] ) ? \Bricks\Element::get_element_global_classes( $settings['_cssGlobalClasses'] ) : [];
    if( empty( $global_css_classes ) || ! in_array( 'modifyQuery', $global_css_classes ) ) {
        return $query_vars;
    }
    $product_id = get_the_ID();
    $product    = wc_get_product( $product_id );
    if ( ! is_a( $product, 'WC_Product' ) ) {
        return $query_vars;
    }
    // Get all category IDs for current product
    $category_ids = wp_get_post_terms( $product_id, 'product_cat', ['fields' => 'ids'] );
    if ( ! empty( $category_ids ) ) {
        $query_vars['tax_query'] = [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category_ids,
            ]
        ];
    }
    $query_vars['post__not_in'] = [ $product_id ];
    $query_vars['post_type'] = 'product';
    return $query_vars;
}, 10, 4 );

// Always show both regular and sale prices, even if sale > regular
add_filter('woocommerce_get_price_html', function($price_html, $product) {
    $regular = (float) $product->get_regular_price();
    $sale = (float) $product->get_sale_price();
    if ($sale && $regular && $sale >= $regular) {
        $price_html = sprintf(
            '<del>%s</del> <ins>%s</ins>',
            wc_price($regular),
            wc_price($sale)
        );
    }
    return $price_html;
}, 10, 2);

// Custom AJAX endpoint to get categories info on single clinic page
add_action('wp_ajax_get_category_image', 'tg_get_category_image');
add_action('wp_ajax_nopriv_get_category_image', 'tg_get_category_image');
function tg_get_category_image() {
    $slug = sanitize_text_field($_GET['slug'] ?? '');
    if (!$slug) wp_send_json_error('No slug');
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term) wp_send_json_error('Category not found');
    $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
    $image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
    wp_send_json([
        'id' => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'image' => $image,
    ]);
}

// ========== Preserve redirect_to across login & registration ==========
// Add redirect_to param to Register link on the login page
add_action('wp_footer', function () {
    if (is_page('account')) :
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            const urlParams = new URLSearchParams(window.location.search);
            const redirectTo = urlParams.get("redirect_to");

            if (redirectTo) {
                const registerBtn = document.querySelector("#registerUser");
                if (registerBtn) {
                    let registerUrl = registerBtn.getAttribute("href") || "/account/?action=register";
                    registerUrl += (registerUrl.includes("?") ? "&" : "?") + "redirect_to=" + encodeURIComponent(redirectTo);
                    registerBtn.setAttribute("href", registerUrl);
                }
            }
        });
        </script>
        <?php
    endif;
});

// After registration, preserve redirect_to and send back to login with param
add_action('user_register', function ($user_id) {
    if (!empty($_REQUEST['redirect_to'])) {
        $redirect_to = esc_url_raw($_REQUEST['redirect_to']);
        wp_safe_redirect( wp_login_url($redirect_to) );
        exit;
    }
});

// After login, always respect redirect_to param
add_filter('login_redirect', function ($redirect_to, $request, $user) {
    if (!empty($_REQUEST['redirect_to'])) {
        return esc_url_raw($_REQUEST['redirect_to']);
    }
    return $redirect_to;
}, 10, 3);

// Remove Yoast canonical on product category pages
add_filter('wpseo_frontend_presenter_classes', function ($presenters) {
    if (!is_tax('product_cat')) {
        return $presenters;
    }

    return array_filter($presenters, function ($presenter) {
        return $presenter !== 'Yoast\\WP\\SEO\\Presenters\\Canonical_Presenter';
    });
});

add_action('wp_head', function () {

    if (!is_tax('product_cat')) {
        return;
    }

    global $wp;

    // Get request path segments
    $request_path = trim($wp->request, '/');
    $slugs        = array_filter(explode('/', $request_path));

    if (empty($slugs)) {
        return;
    }

    $matched_terms = [];

    // Fetch all terms matching URL slugs
    foreach ($slugs as $slug) {
        $term = get_term_by('slug', urldecode($slug), 'product_cat');
        if ($term instanceof WP_Term) {
            $matched_terms[$term->term_id] = $term;
        }
    }

    if (empty($matched_terms)) {
        return;
    }

    /**
     * Resolve the deepest hierarchical term
     * (child wins over parent regardless of URL order)
     */
    $canonical_term = null;

    foreach ($matched_terms as $term) {
        if (
            !$canonical_term ||
            term_is_ancestor_of($canonical_term->term_id, $term->term_id, 'product_cat')
        ) {
            $canonical_term = $term;
        }
    }

    if ($canonical_term) {
        $url = get_term_link($canonical_term);
        if (!is_wp_error($url)) {
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        }
    }

}, 1);

// Include files
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'offers/sync-offers.php';
require_once plugin_dir_path(__FILE__) . 'offers/sync-offers-api.php';
require_once plugin_dir_path(__FILE__) . 'offers/home-offer-sliders.php';
require_once plugin_dir_path(__FILE__) . 'offers/related-offers-slider.php';
require_once plugin_dir_path(__FILE__) . 'clinic-listings/reg-cpt.php';
require_once plugin_dir_path(__FILE__) . 'clinic-listings/sync-clinics-api.php';
require_once plugin_dir_path(__FILE__) . 'user/login.php';
require_once plugin_dir_path(__FILE__) . 'user/signup.php';
require_once plugin_dir_path(__FILE__) . 'user/user-profile.php';
require_once plugin_dir_path(__FILE__) . 'user/reset-password.php';
require_once plugin_dir_path(__FILE__) . 'user/forgot-password.php';
require_once plugin_dir_path(__FILE__) . 'cities-popup/popup-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'appointment-booking/reservation-modal-action.php';
require_once plugin_dir_path(__FILE__) . 'appointment-booking/reservation-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'cart/get-cart-info.php';
require_once plugin_dir_path(__FILE__) . 'cart/tg-cart-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'checkout/reg-checkout-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'payments/payfort/reg-payfort-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'payments/payfort/tg-payfort-data.php';
require_once plugin_dir_path(__FILE__) . 'payments/wallet/tg-wallet-data.php';
require_once plugin_dir_path(__FILE__) . 'payments/apple/tg-apple-data.php';
require_once plugin_dir_path(__FILE__) . 'appointment-listings/tg-appointment-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'redirects/tg-redirects.php';

/**
 * Render clinic content with proper HTML tags
 * Converts plain text to formatted HTML with headings
 * Use: [clinic_content_html] in Bricks Builder
 * Updated: 2026-01-19 - Fixed empty check bug
 */
add_shortcode('clinic_content_html', 'tg_render_clinic_content');
function tg_render_clinic_content() {
    global $post;
    
    // Return empty if no post context
    if (!$post || empty($post->post_content)) {
        return '';
    }
    
    // Get raw content - PRESERVE HTML TAGS
    $content = $post->post_content;
    
    // Remove only script and style tags but keep other HTML
    $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
    $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
    
    // Apply WordPress filters to process shortcodes and formatting
    $content = apply_filters('the_content', $content);
    
    // Clean up extra whitespace but preserve structure
    $content = trim($content);
    
    return $content;}