<?php

if (!defined('ABSPATH')) {
    exit;
}

// Register REST API Endpoint
add_action('rest_api_init', function () {
    register_rest_route('crm/v1', '/sync-offer', [
        'methods'  => ['POST', 'DELETE'],
        'callback' => 'sync_crm_offer',
		'permission_callback' => function () {
            $is_valid = is_valid_crm_basic_auth();
            $logger = wc_get_logger();
            $context = ['source' => 'webhook-sync-offers'];			
            if (!$is_valid) {
                $logger->warning('Webhook called: Authentication failed.', $context);
				return;
            }
            return current_user_can('manage_woocommerce') || $is_valid;
        },
    ]);
});

// Validate Request
if (!function_exists('is_valid_crm_basic_auth')) {
    function is_valid_crm_basic_auth() {
        $logger = wc_get_logger();
        $context = ['source' => 'webhook-sync-offers'];
        $auth_header = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['Authorization'])) {
            $auth_header = $_SERVER['Authorization'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }
        if (!$auth_header || stripos($auth_header, 'Basic ') !== 0) {
            $logger->warning('Missing or malformed Authorization header (must start with Basic).', $context);
            return false;
        }
        $encoded = trim(substr($auth_header, 6));
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            $logger->warning("Base64 decoding FAILED for string: $encoded", $context);
            return false;
        }
        if (strpos($decoded, ':') === false) {
            $logger->warning("Decoded auth string missing ':' separator. Received: $decoded", $context);
            return false;
        }
        list($consumer_key, $consumer_secret) = explode(':', $decoded, 2);
        $expected_key    = CRM_API_KEY;
        $expected_secret = CRM_API_SECRET;
        if ( hash_equals($expected_key, $consumer_key) && hash_equals($expected_secret, $consumer_secret) ) return true;
        $logger->warning(
            "Authorization failed: key/secret mismatch. Received key: $consumer_key",
            $context
        );
        return false;
    }
}

// Sync Categories
if (!function_exists('sync_product_categories')) {
    function sync_product_categories($product_id, $offer_data) {
        $parent_name = trim($offer_data['category'] ?? '');
        $sub_name    = trim($offer_data['sub_category'] ?? '');
        if (empty($parent_name)) {
            tg_log("sync_product_categories: No parent category provided for product {$product_id}");
            return;
        }
        $parent_term_id = 0;
        $found_parents = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
            'name'       => $parent_name,
        ]);
        if (!empty($found_parents) && !is_wp_error($found_parents)) {
            $parent_term_id = $found_parents[0]->term_id;
        } else {
            $inserted = wp_insert_term($parent_name, 'product_cat', ['parent' => 0]);
            if (is_wp_error($inserted)) {
                return;
            }
            $parent_term_id = $inserted['term_id'];
        }
        if (!empty($sub_name)) {
            $found_subs = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => $parent_term_id,
                'name'       => $sub_name,
            ]);
            if (!empty($found_subs) && !is_wp_error($found_subs)) {
                $sub_term_id = $found_subs[0]->term_id;
            } else {
                $inserted_sub = wp_insert_term($sub_name, 'product_cat', ['parent' => $parent_term_id]);
                if (is_wp_error($inserted_sub)) {
                    return;
                }
                $sub_term_id = $inserted_sub['term_id'];
            }
            wp_set_post_terms($product_id, [$parent_term_id, $sub_term_id], 'product_cat');
        } else {
            wp_set_post_terms($product_id, [$parent_term_id], 'product_cat');
        }
    }
}

// SKU lookup with fallback
if (!function_exists('get_strict_existing_product_id')) {
    function get_strict_existing_product_id($offer_id) {
        global $wpdb;
        $offer_id = trim($offer_id);
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND pm.meta_key = '_sku'
                AND pm.meta_value = %s
                LIMIT 1
                ",
                $offer_id
            )
        );
        return $product_id ? (int) $product_id : 0;
    }
}

// Sync Offers
if (!function_exists('sync_crm_offer')) {
    function sync_crm_offer(WP_REST_Request $request) {
        $offer_data = $request->get_json_params();
        if ( empty($offer_data['id'] )) {
            return new WP_REST_Response(['message' => 'Missing offer ID'], 400);
        }

        $sku = strval(trim($offer_data['id']));
        // $title = trim($offer_data['title']);
        $is_active = $offer_data['is_active'];
        $valid_till = !empty($offer_data['valid_till']) ? strtotime($offer_data['valid_till']) : null;
        $today = strtotime(current_time('Y-m-d'));

        $existing_product_id = get_strict_existing_product_id($sku);

        // Check if offer is expired
        if ($valid_till && $valid_till < $today) {
            if ($existing_product_id) {
                wp_trash_post($existing_product_id);
                error_log("Offer $sku moved to trash due to expired date.");
                return new WP_REST_Response(['message' => "Offer $sku expired and removed from website."], 200);
            }
            return new WP_REST_Response(['message' => "Offer $sku expired. No active offer found."], 200);
        }

        // Restore logic if valid_till is future, product is trashed, and offer is active
        if ($is_active && $valid_till && $valid_till >= $today && $existing_product_id && get_post_status($existing_product_id) === 'trash') {
            wp_untrash_post($existing_product_id);
            wp_update_post([
                'ID' => $existing_product_id,
                'post_status' => 'publish',
            ]);
            error_log("Offer $sku was in trash but valid and active. Restored.");
        }

        // Set post status
        $post_status = $is_active ? 'publish' : 'draft';

        $logger = wc_get_logger();
        $context = ['source' => 'webhook-sync-offers'];
        $logger->info("CRM Sync Request: " . json_encode($offer_data), ['source' => $context]);

        if ($request->get_method() === 'DELETE') {
            if ($existing_product_id) {
                wp_trash_post($existing_product_id);
                return new WP_REST_Response(['message' => "Offer $sku moved to trash"], 200);
            }
            return new WP_REST_Response(['message' => "Offer $sku not found"], 404);
        }

        if (!$offer_data['is_active']) {
            if ($existing_product_id) {
                wp_trash_post($existing_product_id);
                error_log("Offer $sku moved to trash due to non active status.");
                return new WP_REST_Response(['message' => "Offer $sku set to non active status."], 200);
            }
            // This is not in a loop, so don't use continue
            error_log("Offer $sku is inactive, nothing to do.");
            return new WP_REST_Response(['message' => "Offer $sku is inactive, nothing to do."], 200);
        }

        // Check if a trashed product exists and restore it
        if (!$existing_product_id) {
            // Try to find a trashed post with this title or SKU
            $existing_trashed = new WP_Query([
                'post_type'      => 'product',
                'post_status'    => 'trash',
                'meta_query'     => [
                    [
                        'key'     => '_sku',
                        'value'   => $sku,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1,
            ]);

            if ($existing_trashed->have_posts()) {
                $trashed_post = $existing_trashed->posts[0];
                $existing_product_id = $trashed_post->ID;
                // Restore the product
                wp_untrash_post($existing_product_id);
                wp_update_post([
                    'ID'          => $existing_product_id,
                    'post_status' => 'publish'
                ]);
                error_log("Previously trashed offer $sku restored and published.");
            }
        }

        $product = $existing_product_id ? wc_get_product($existing_product_id) : new WC_Product_Simple();
        // Ensure it's a valid product object
        if (!$product || !$product->get_id()) {
            $product = new WC_Product_Simple();
        }
        $action_type = $existing_product_id ? 'update' : 'create';

        $bold_title = $offer_data['bold_title'];
        $light_title = $offer_data['light_title'];
        $full_title = $bold_title .' '. $light_title;

        $product->set_name(sanitize_text_field($full_title ?? $sku));
        $product->set_description(sanitize_text_field($offer_data['announcement'] ?? ''));
        $product->set_price( floatval($offer_data['new_price'] ?? 0) );
        $product->set_regular_price(floatval($offer_data['old_price'] ?? 0));
        $product->set_sale_price(floatval($offer_data['new_price'] ?? 0));
        $product->set_status($post_status);
        $product->set_sku($sku);

        $product_id = $product->save();

        if (isset($offer_data['category'], $offer_data['sub_category'])) {
            sync_product_categories($product_id, $offer_data);
        }

        // Convert rating out of 10 to out of 5
        if (!function_exists('convert_rating')) {
            function convert_rating($rating) {
                return is_numeric($rating) ? round($rating / 2, 1) : 0;
            }
        }

        // ACF fields
        $acf_fields = [
            'distance'             => $offer_data['distance'] ?? '',
            'position'             => $offer_data['position'] ?? '',
            'url'                  => $offer_data['url'] ?? '',
            'like_count'           => $offer_data['like_count'] ?? '',
            'phone'                => $offer_data['phone'] ?? '',
            'latitude'             => $offer_data['latitude'] ?? '',
            'longitude'            => $offer_data['longitude'] ?? '',
            'map_title'            => $offer_data['map_title'] ?? '',
            'valid_till'           => $offer_data['valid_till'] ?? '',
            'promo_code'           => $offer_data['promo_code'] ?? '',
            'cities'   => isset($offer_data['cities']) && is_array($offer_data['cities'])
            ? implode(', ', array_column($offer_data['cities'], 'name'))
            : '',
            'promo_tag'            => $offer_data['promo_tag'] ?? '',
            'claimed_count'        => $offer_data['claimed_count'] ?? '',
            'pin_to_top'           => $offer_data['pin_to_top'] ?? '',
            'partial_amount'       => $offer_data['partial_amount'] ?? '',
            'avg_rating'           => convert_rating($offer_data['avg_rating'] ?? ''),
            'avg_score_doctor'     => convert_rating($offer_data['avg_score_doctor'] ?? ''),
            'avg_score_app'        => convert_rating($offer_data['avg_score_app'] ?? ''),
            'avg_score_clinic'     => convert_rating($offer_data['avg_score_clinic'] ?? ''),
            'avg_score_price'      => convert_rating($offer_data['avg_score_price'] ?? ''),
            'avg_score_service'    => convert_rating($offer_data['avg_score_service'] ?? ''),
            'avg_score_location'   => convert_rating($offer_data['avg_score_location'] ?? ''),
            'offer_reviews_count'  => $offer_data['offer_reviews_count'] ?? '0',
            'bold_title'           => $offer_data['bold_title'] ?? '',
            'light_title'          => $offer_data['light_title'] ?? '',
            'youtube_url'          => $offer_data['youtube_url'] ?? '',
            'num_sessions'         => $offer_data['num_sessions'] ?? '0',
            'num_body_parts'       => $offer_data['num_body_parts'] ?? '',
            'machines'             => $offer_data['machines'] ?? '',
            'service'              => $offer_data['service'] ?? '',
            'rule'                 => $offer_data['rule'] ?? '',
            'no_schedule'          => $offer_data['no_schedule'] ?? '',
            'preffered_time'       => $offer_data['preffered_time'] ?? '',
            'check_product'        => $offer_data['check_product'] ?? '',
            'available_date'       => $offer_data['available_date'] ?? '',
            'available_time_start' => $offer_data['available_time_start'] ?? '',
            'available_time_end'   => $offer_data['available_time_end'] ?? '',
            'availability_info'    => $offer_data['availability_info'] ?? '',
            'is_special_offer'     => $offer_data['is_special_offer'] ?? '',
            'is_doctor_visit'      => $offer_data['is_doctor_visit'] ?? '',
            'q_of_life'            => $offer_data['q_of_life'] ?? '',
            'is_best_seller'       => $offer_data['is_best_seller'] ?? '',
            'is_new_arrival'       => $offer_data['is_new_arrival'] ?? '',
            'is_refundable'        => $offer_data['is_refundable'] ?? '',
            'partially_refundable' => $offer_data['partially_refundable'] ?? '',
            'is_star_clinic'       => $offer_data['is_star_clinic'] ?? '',
            'is_cash_back'         => $offer_data['is_cash_back'] ?? '',
            'is_vip'               => $offer_data['is_vip'] ?? '',
            'is_expiring_soon'     => $offer_data['is_expiring_soon'] ?? '',
            'is_clock'             => $offer_data['is_clock'] ?? '',
            'time_remaining'       => $offer_data['time_remaining'] ?? '',
            'is_like_by_me'        => $offer_data['is_like_by_me'] ?? '',
            'doctor_id'            => $offer_data['doctor_id'] ?? '',
            'doctor_name'          => $offer_data['doctor_name'] ?? '',
            'doctor_phone'         => $offer_data['doctor_phone'] ?? '',
            'doctor_appointment_mode' => $offer_data['doctor_appointment_mode'] ?? '',
            'speciality_text'      => $offer_data['speciality_text'] ?? '',
            'hospital_name'        => $offer_data['hospital_name'] ?? '',
            'hospital_address'     => $offer_data['hospital_address'] ?? '',
            'hospital_latitude'    => $offer_data['hospital_latitude'] ?? '',
            'hospital_longitude'   => $offer_data['hospital_longitude'] ?? '',
            'availability'         => $offer_data['availability'] ?? '',
            'opening_time'         => $offer_data['opening_time'] ?? '',
            'closing_time'         => $offer_data['closing_time'] ?? '',
            'clinic_location'      => $offer_data['clinic_location'] ?? '',
            'availability_text'    => $offer_data['availability_text'] ?? '',
            'consultation'         => $offer_data['consultation'] ?? '',
            'session'              => $offer_data['session'] ?? '',
            'is_offer_accept_installment' => $offer_data['is_offer_accept_installment'] ?? '0',
        ];

        foreach ($acf_fields as $key => $value) {
           if (get_field($key, $product_id) !== $value) {
                update_field($key, $value, $product_id);
            }
        }

        // Image handling
        // Check for offer_images or fallback to new_offer_images
        $offer_images = $offer_data['offer_images'] ?? $offer_data['new_offer_images'] ?? null;

        // If neither array exists or is empty, exit early
        if (empty($offer_images) || !is_array($offer_images)) {
            return new WP_REST_Response(['message' => "Offer synced without images."], 200);
        }

        // Use new_offer_images if available, otherwise offer_images
        $new_offer_images = $offer_data['new_offer_images'] ?? $offer_images;
        $cloud_thumbnail = $new_offer_images[0]['image'] ?? $offer_images[0]['cloud_image'] ?? null;

        // Update cloud_thumbnail ACF field
        update_field('cloud_thumbnail', $cloud_thumbnail, $product_id);

        // Set product thumbnail if both product ID and thumbnail exist
        if (!empty($product_id) && !empty($cloud_thumbnail)) {
            set_product_thumbnail_from_cloud($product_id, $cloud_thumbnail);
        } else {
            if (is_wp_error($cloud_thumbnail)) {
                error_log("Failed to set featured image: | Error: " . $cloud_thumbnail->get_error_message());
                return false;
            }
        }

        // Prepare and update cloud_gallery_images field
        $cloud_gallery = [];
        foreach ($offer_images as $image_data) {
            if (!empty($image_data['cloud_image'])) {
                $cloud_gallery[] = ['url' => $image_data['cloud_image']];
            }
        }
        if (!empty($cloud_gallery)) {
            update_field('cloud_gallery_images', $cloud_gallery, $product_id);
        }

        $logger->info("Offer synced: $sku - Action: $action_type (ID: $product_id)", ['source' => $context]);
        return new WP_REST_Response(['message' => "Offer synced successfully."], 200);
    }
}

// Set product thumbnail
if (!function_exists('set_product_thumbnail_from_cloud')) {
    function set_product_thumbnail_from_cloud($product_id, $cloud_thumbnail) {
        if (empty($product_id) || empty($cloud_thumbnail)) return false;

        $image_url = strtok($cloud_thumbnail, '?');
        $filename = basename($image_url);
        $filename = urldecode(sanitize_file_name($filename));

        $existing_attachment = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => 1,
            'post_status' => 'inherit',
            'meta_query' => [[
                'key' => '_wp_attached_file',
                'value' => $filename,
                'compare' => 'LIKE',
            ]],
        ]);

        if (!empty($existing_attachment)) {
            $attachment_id = $existing_attachment[0]->ID;
            return set_post_thumbnail($product_id, $attachment_id);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp_file = download_url($image_url);
        if (is_wp_error($tmp_file)) return false;

        $file_array = ['name' => $filename, 'tmp_name' => $tmp_file];
        $attachment_id = media_handle_sideload($file_array, $product_id);

        return !is_wp_error($attachment_id) && set_post_thumbnail($product_id, $attachment_id);
    }
}