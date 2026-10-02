<?php
if (!defined('ABSPATH')) exit;

// if (!function_exists('tg_log')) { 
//     function tg_log($message) { 
//         $file = WP_CONTENT_DIR . '/tg-offer-sync.log'; 
//         $time = date('Y-m-d H:i:s'); 
//         file_put_contents($file, "[$time] $message\n", FILE_APPEND | LOCK_EX); 
//     } 
// }

add_action('template_redirect', function () {
    $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    // Bail for WooCommerce core pages
    $woo_pages = ['cart', 'checkout', 'my-account'];
    foreach ($woo_pages as $page) {
        if (str_starts_with($request_uri, $page)) {
            return;
        }
    }
    // Bail for admin and AJAX
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) return;
    // -------------------------------
    // CLINIC URLs VALIDATION & REDIRECT
    // -------------------------------
    if (str_starts_with($request_uri, 'clinic-listings/')) {
        $slug = substr($request_uri, strlen('clinic-listings/'));
        $slug = sanitize_title($slug);
        // Empty slug → listings page
        if (!$slug) {
            wp_redirect(home_url('/clinic-listings/'), 301);
            // tg_log("Redirected empty clinic slug to listings page.");
            exit;
        }
        // Fetch clinic by slug
        $clinic = get_posts([
            'post_type'      => 'clinic_listing',
            'post_status'    => ['publish', 'draft', 'private'],
            'name'           => $slug,
            'posts_per_page' => 1,
        ]);
        // CASE 1: Clinic does not exist
        if (!$clinic) {
            wp_redirect(home_url('/clinic-listings/'), 301);
            // tg_log("Redirected non-existing clinic '$slug' to listings page.");
            exit;
        }
        $clinic_id = $clinic[0]->ID;
        // Read meta values
        $status       = get_post_meta($clinic_id, 'clinic_status', true);
        $offers_count = get_post_meta($clinic_id, 'clinic_offers_count', true);
        $is_inactive = ( $status === false || $status === 'false');
        $has_no_offers = ( empty($offers_count) || intval($offers_count) === 0 
                        || $offers_count === '0' || $offers_count < 0 
                        );
        // CASE 2: Clinic exists but inactive or empty
        if ($is_inactive || $has_no_offers) {
            wp_redirect(home_url('/clinic-listings/'), 301);
            // tg_log("Redirected inactive or empty clinic '$slug' to listings page from str_starts_with.");
            exit;
        }
    }

    // -------------------------------
    // OFFER SHARE URL NORMALIZATION
    // /offers/123/share -> /offers/123/
    // -------------------------------
    $normalized_uri = preg_replace('#/+#', '/', $_SERVER['REQUEST_URI']);
    if (preg_match('#^/offers/([0-9]+)/share/?$#', $normalized_uri, $matches)) {
        $sku = trim($matches[1]);
        wp_redirect(home_url("/offers/{$sku}/"), 301);
        exit;
    }

    // -------------------------------
    // Offer SKU URLs
    // Only handle numeric SKU URLs
    // -------------------------------
    if (preg_match('#^offers/([0-9]+)/?$#', $request_uri, $m) && is_404()) {
        $sku = trim($m[1]);
        global $wpdb;
        // Lookup product by SKU
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT product_id
            FROM {$wpdb->prefix}wc_product_meta_lookup
            WHERE sku = %s
            LIMIT 1
        ", $sku));
        if (!$product_id) {
            // Fallback to postmeta (handles trashed or old products)
            $product_id = $wpdb->get_var($wpdb->prepare("
                SELECT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1
            ", $sku));
        }
        // Product found and published → redirect to product page
        if ($product_id && get_post_status($product_id) === 'publish') {
            $current_url = home_url($request_uri);
            $product_url = get_permalink($product_id);

            // Avoid redirect loops
            if (untrailingslashit($current_url) !== untrailingslashit($product_url)) {
                wp_redirect($product_url, 301);
                // tg_log("Redirected offer SKU '$sku' to product ID '$product_id'.");
                exit;
            }
            return;
        }
        // Product missing or unpublished → fallback to clinic/category
        tg_offer_fallback_redirect($product_id);
        // tg_log("Redirected missing or unpublished offer SKU '$sku' to fallback.");
        exit;
    }

    // -------------------------------
    // Hardcoded exceptions
    // -------------------------------
    if ($request_uri === 'offers/53252') {
        wp_redirect(home_url('/مساج-تاج-الاسترخاء/'), 301);
        exit;
    }

    // -------------------------------
    // Catch-all 404 (only if not WooCommerce)
    // -------------------------------
    if (is_404()) {
        wp_redirect(home_url('/عروض-طبيب/'), 301);
        // tg_log("Redirected unknown URL '$request_uri' to main offers page considered as 404 error.");
        exit;
    }

}, 1);

// -------------------------------
// OFFER FALLBACK (WITH CLINIC RULES)
// -------------------------------
if (!function_exists('tg_offer_fallback_redirect')) {
    function tg_offer_fallback_redirect($product_id) {
        // No product → main offers page
        if (!$product_id) {
            wp_redirect(home_url('/عروض-طبيب/'), 301);
            exit;
        }
        // Product must belong to a doctor
        $doctor_id = get_post_meta($product_id, 'doctor_id', true);
        if (!$doctor_id) {
            tg_redirect_to_parent_and_child($product_id);
            exit;
        }
        // Fetch clinic by doctor_id
        $clinic = get_posts([
            'post_type'      => 'clinic_listing',
            'post_status'    => ['publish', 'draft', 'private'],
            'meta_query'     => [
                [
                    'key'   => 'clinic_doctor_id',
                    'value' => $doctor_id,
                ]
            ],
            'posts_per_page' => 1,
        ]);
        if (!$clinic) {
            tg_redirect_to_parent_and_child($product_id);
            exit;
        }
        $clinic_id = $clinic[0]->ID;
        // Clinic validation
        $status       = get_post_meta($clinic_id, 'clinic_status', true);
        $offers_count = get_post_meta($clinic_id, 'clinic_offers_count', true);
        $is_inactive   = ($status === false || $status === 'false');
        $has_no_offers = (empty($offers_count) || intval($offers_count) <= 0);
        if ($is_inactive || $has_no_offers) {
            tg_redirect_to_parent_and_child($product_id);
            exit;
        }
        // Get product categories
        $terms = wp_get_post_terms($product_id, 'product_cat');
        if (empty($terms) || is_wp_error($terms)) {
            wp_redirect(home_url('/عروض-طبيب/'), 301);
            exit;
        }
        // Resolve parent category (RAW slug)
        $parent = null;
        foreach ($terms as $term) {
            if ($term->parent === 0) {
                $parent = $term;
                break;
            }
        }
        if (!$parent) {
            $parent = $terms[0];
        }

        // Check clinic inventory for this category
        if (!tg_clinic_has_offers_in_category($clinic_id, $parent->slug)) {
            // tg_log("Category redirect triggered (reason=clinic_has_no_offers_in_category) → parent='{$parent_slug}'");
            tg_redirect_to_parent_and_child($product_id);
            exit;
        }
        // Valid clinic + valid category → clinic page with filter
        $clinic_url = get_permalink($clinic_id);
        $final_url = add_query_arg('_filter', $parent->slug, $clinic_url);
        // Loop protection
        if (untrailingslashit($final_url) !== untrailingslashit(home_url($_SERVER['REQUEST_URI']))) {
            wp_redirect($final_url, 301);
            exit;
        }
        // Absolute safety fallback
        tg_redirect_to_parent_and_child($product_id);
    }
}

// -------------------------------
// CATEGORY PAGE REDIRECT
// -------------------------------
if (!function_exists('tg_redirect_to_parent_and_child')) {
    function tg_redirect_to_parent_and_child($product_id) {
        // Fetch product categories
        $terms = wp_get_post_terms($product_id, 'product_cat');
        if (empty($terms) || is_wp_error($terms)) {
            // tg_log("Category redirect fallback → main offers page (no product categories)");
            wp_redirect(home_url('/عروض-طبيب/'), 301);
            exit;
        }
        $parent = null;
        $child  = null;
        // Resolve hierarchy deterministically
        foreach ($terms as $term) {
            if ($term->parent === 0) {
                $parent = $term;
            } else {
                $child = $term;
            }
        }
        if (!$parent) {
            $parent = $terms[0];
        }
        // Build URL (RAW slugs — no encoding here)
        $url = home_url('/offer-category/' . $parent->slug . '/');
        if ($child) {
            $url .= $child->slug . '/';
        }
        // Loop protection
        $current = untrailingslashit(home_url($_SERVER['REQUEST_URI']));
        $target  = untrailingslashit($url);
        if ($current !== $target) {
            wp_redirect($url, 301);
            exit;
        }
    }
}

// -------------------------------
// CHECK IF CLINIC HAS OFFERS IN CATEGORY
// -------------------------------
if (!function_exists('tg_clinic_has_offers_in_category')) {
    function tg_clinic_has_offers_in_category($clinic_id, $category_slug) {

        // Resolve product category
        $term = get_term_by('slug', $category_slug, 'product_cat');
        if (!$term || is_wp_error($term)) {
            // tg_log("Clinic offer check | INVALID TERM | slug={$category_slug}");
            return false;
        }

        // Get doctor_id from clinic post
        $doctor_id = get_post_meta($clinic_id, 'clinic_doctor_id', true);

        if (empty($doctor_id)) {
            // tg_log("Clinic offer check | clinic_id={$clinic_id} | MISSING clinic_doctor_id");
            return false;
        }

        // Query products
        $query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'doctor_id',
                    'value'   => (string) $doctor_id,
                    'compare' => '=',
                ],
            ],
            'tax_query'      => [
                [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => [$term->term_id],
                    'include_children' => true,
                ],
            ],
        ]);

        $has_offers = $query->have_posts();

        // tg_log(sprintf(
        //     "Clinic offer check | clinic_id=%d | doctor_id=%s | slug=%s | term_id=%d | has_offers=%s",
        //     $clinic_id,
        //     $doctor_id,
        //     $category_slug,
        //     $term->term_id,
        //     $has_offers ? 'YES' : 'NO'
        // ));

        return $has_offers;
    }
}