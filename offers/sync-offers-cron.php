<?php

defined( 'ABSPATH' ) || exit;

/* Logging helper with auto-clear when file grows too large */
if (!function_exists('tg_log')) {
    function tg_log($message) {
        $file = WP_CONTENT_DIR . '/tg-offer-sync.log';
        $time = date('Y-m-d H:i:s');

        $max_size_mb = 0.1;
        $max_size = $max_size_mb * 1024 * 1024;

        if (file_exists($file) && filesize($file) > $max_size) {
            file_put_contents(
                $file,
                mb_convert_encoding("[$time] Log file auto-cleared due to size limit\n", 'UTF-8', 'UTF-8')
            );
        }

        $log_message = "[{$time}] {$message}\n";

        file_put_contents(
            $file,
            mb_convert_encoding($log_message, 'UTF-8', 'UTF-8'),
            FILE_APPEND | LOCK_EX
        );
    }
}

/* Fetch API Offers */
if (!function_exists('fetch_api_offers')) {
    function fetch_api_offers() {
        global $tabibgroup_base_url;
        $api_url = $tabibgroup_base_url . '/api/word_press/v1/offers.json';

        $response = wp_remote_get($api_url);
        if (is_wp_error($response)) {
            tg_log('API Request Failed: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data)) {
            tg_log('Invalid API response: Data is empty or not an array.');
            return false;
        }

        if (!isset($data['total_pages=']) && !isset($data['total_pages='])) {
            tg_log('Invalid API response: total_pages key missing.');
            return false;
        }

        $total_pages = (int) ($data['total_pages='] ?? $data['total_pages=']);
        $all_offers  = $data['data'] ?? [];

        for ($page = 2; $page <= $total_pages; $page++) {
            $paged_url = $api_url . '?page=' . $page;
            $paged_response = wp_remote_get($paged_url);
            if (is_wp_error($paged_response)) {
                tg_log('API Request Failed for Page ' . $page . ': ' . $paged_response->get_error_message());
                continue;
            }

            $paged_body = wp_remote_retrieve_body($paged_response);
            $paged_data = json_decode($paged_body, true);

            if (isset($paged_data['data']) && is_array($paged_data['data'])) {
                $all_offers = array_merge($all_offers, $paged_data['data']);
            }
        }

        $data['data'] = $all_offers;
        return $data;
    }
}

/* Import offers from API response */
if (!function_exists('import_offers_from_api_response')) {
    function import_offers_from_api_response($api_response) {
        if (!empty($api_response['data']) && is_array($api_response['data'])) {
            foreach ($api_response['data'] as $offer) {
                import_offer_as_woocommerce_product($offer);
            }
        } else {
            tg_log('No offer data found or data is not an array.');
        }
    }
}

/* Republish trashed products */
if (!function_exists('republish_trashed_products')) {
    function republish_trashed_products($valid_skus) {
        if (empty($valid_skus)) return;

        foreach ($valid_skus as $sku) {
            $args = [
                'post_type' => 'product',
                'post_status' => 'trash',
                'meta_query' => [['key' => '_sku', 'value' => $sku]],
                'fields' => 'ids',
                'posts_per_page' => 1,
            ];

            $product_ids = get_posts($args);
            if (!empty($product_ids)) {
                $product_id = $product_ids[0];
                wp_update_post(['ID' => $product_id, 'post_status' => 'publish']);
                tg_log("Republished trashed product ID {$product_id} with SKU {$sku}.");
            }
        }
    }
}

/* Delete WooCommerce products not in API */
if (!function_exists('delete_obsolete_woocommerce_products')) {
    function delete_obsolete_woocommerce_products($valid_skus) {
        if (empty($valid_skus)) return;

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => [['key' => '_sku', 'compare' => 'EXISTS']],
        ];

        $product_ids = get_posts($args);
        foreach ($product_ids as $product_id) {
            $sku = get_post_meta($product_id, '_sku', true);
            if (!in_array($sku, $valid_skus)) {
                wp_update_post(['ID' => $product_id, 'post_status' => 'trash']);
                tg_log("Moved obsolete product ID {$product_id} (SKU {$sku}) to trash.");
            }
        }
    }
}

/* Smart Category Sync */
if (!function_exists('sync_product_categories_cron')) {
    function sync_product_categories_cron($product_id, $offer_data) {
        $parent_name = trim($offer_data['category'] ?? '');
        $sub_name    = trim($offer_data['sub_category'] ?? '');
        if (empty($parent_name)) {
            tg_log("sync_product_categories_cron: No parent category provided for product {$product_id}");
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

/* Find existing product by SKU/title */
if (!function_exists('get_existing_product_id')) {
    function get_existing_product_id($offer_id, $offer_title) {
        global $wpdb;
        $offer_id = trim($offer_id);
        $offer_title = sanitize_title($offer_title);
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type = 'product' AND p.post_status != 'trash'
            AND (pm.meta_value = %s OR p.post_title = %s OR p.post_name = %s)
            LIMIT 1
        ", $offer_id, $offer_title, $offer_title));
        return $product_id ? (int) $product_id : 0;
    }
}

// Import/Update offer as woocommerce product
if (!function_exists('import_offer_as_woocommerce_product')) {
    function import_offer_as_woocommerce_product($offer_data) {
        // Use default values if fields are missing
        $sku   = !empty($offer_data['id']) ? trim($offer_data['id']) : 'no-id-' . uniqid();
        $title = !empty($offer_data['title']) ? trim($offer_data['title']) : 'عرض بلا عنوان';
        $announcement = !empty($offer_data['announcement']) ? $offer_data['announcement'] : '';

        // Build product title from bold/light parts or fallbacks
        $product_title = trim(($offer_data['bold_title'] ?? '') . ' ' . ($offer_data['light_title'] ?? ''));
        // Generate slug safely
        $slug = sanitize_title($product_title);
        if (empty($product_title)) {
            $product_title = $title;
            $slug = sanitize_title($title);
        }

        $existing_product_id = get_existing_product_id($sku, $title);

        // Prepare common product data
        $product_data = [
            'post_title'   => wp_strip_all_tags($product_title),
            'post_content' => $announcement,
            'post_status'  => 'publish',
            'post_type'    => 'product',
            'post_name'    => $slug,
        ];

        // Log if fallback values were used (for debugging)
        if (strpos($sku, 'no-id-') === 0) {
            tg_log("Offer had no ID. Assigned temporary SKU: {$sku}");
        }
        if ($title === 'عرض بلا عنوان') {
            tg_log("Offer had no title. Using fallback title.");
        }
        if (empty($announcement)) {
            tg_log("Offer had no announcement text.");
        }

        if ($existing_product_id && get_post_status($existing_product_id)) {
            // Update existing product
            $product_data['ID'] = $existing_product_id;
            $product_id = wp_update_post($product_data, true);

            if (is_wp_error($product_id)) {
                tg_log("Update failed for Product ID {$existing_product_id}: " . $product_id->get_error_message());
                return;
            }

            tg_log("Product updated successfully: ID {$product_id}");
        } else {
            // Insert new product
            $product_id = wp_insert_post($product_data);

            if (is_wp_error($product_id)) {
                tg_log("Insert failed: " . $product_id->get_error_message());
                return;
            }

            tg_log("New product inserted: ID {$product_id}");
        }

        // Update common fields
        update_post_meta($product_id, '_sku', $sku);
        update_post_meta($product_id, '_regular_price', $offer_data['old_price']);
        update_post_meta($product_id, '_sale_price', $offer_data['new_price']);
        update_post_meta($product_id, '_price', $offer_data['new_price']);


        // Set product categories
        if (isset($offer_data['category'], $offer_data['sub_category'])) {
            sync_product_categories_cron($product_id, $offer_data);
        }

        // Convert rating out of 10 to out of 5
        if (!function_exists('convert_ratings')) {
            function convert_ratings($rating) {
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
            'avg_rating'           => convert_ratings($offer_data['avg_rating'] ?? ''),
            'avg_score_doctor'     => convert_ratings($offer_data['avg_score_doctor'] ?? ''),
            'avg_score_app'        => convert_ratings($offer_data['avg_score_app'] ?? ''),
            'avg_score_clinic'     => convert_ratings($offer_data['avg_score_clinic'] ?? ''),
            'avg_score_price'      => convert_ratings($offer_data['avg_score_price'] ?? ''),
            'avg_score_service'    => convert_ratings($offer_data['avg_score_service'] ?? ''),
            'avg_score_location'   => convert_ratings($offer_data['avg_score_location'] ?? ''),
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
            'consultation'         => $offer_data['consultation'] ?? '',
            'is_offer_accept_installment' => $offer_data['is_offer_accept_installment'] ?? '0',
        ];

        foreach ($acf_fields as $key => $value) {
            if (get_field($key, $product_id) !== $value) {
                update_field($key, $value, $product_id);
            }
        }

        // Image handling
        if (!empty($offer_data['offer_images'])) {
            $offer_images = $offer_data['offer_images'];
            $new_offer_images = $offer_data['new_offer_images'] ?? $offer_images;
            $cloud_thumbnail = $new_offer_images[0]['image'] ?? $offer_images[0]['cloud_image'];

            update_field('cloud_thumbnail', $cloud_thumbnail, $product_id);

            if (!empty($product_id) && !empty($cloud_thumbnail)) {
                set_product_thumbnails($product_id, $cloud_thumbnail);
            } else {
                if (is_wp_error($cloud_thumbnail)) {
                    tg_log("Failed to set featured image: | Error: " . $cloud_thumbnail->get_error_message());
                    return false;
                }
            }

            $cloud_gallery = [];
            foreach ($offer_images as $image_data) {
                if (!empty($image_data['cloud_image'])) {
                    $cloud_gallery[] = ['url' => $image_data['cloud_image']];
                }
            }
            if (!empty($cloud_gallery)) {
                update_field('cloud_gallery_images', $cloud_gallery, $product_id);
            }
        }

        return $product_id;
    }
}

// Set product thumbnail from cloud
if (!function_exists('set_product_thumbnails')) {
    function set_product_thumbnails($product_id, $cloud_thumbnail) {
        if (empty($product_id) || empty($cloud_thumbnail)) {
            tg_log("Thumbnail setup skipped: Missing product_id or thumbnail URL.");
            return false;
        }

        $image_url = strtok($cloud_thumbnail, '?');
        $filename = basename($image_url);
        $filename = urldecode(sanitize_file_name($filename));

        // Check if attachment with same filename exists
        $existing_attachment = get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => 1,
            'post_status'    => 'inherit',
            'meta_query'     => [
                [
                    'key'     => '_wp_attached_file',
                    'value'   => $filename,
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        if (!empty($existing_attachment)) {
            $attachment_id = $existing_attachment[0]->ID;

            if (get_post_thumbnail_id($product_id) === $attachment_id) {
                return true;
            }
            return set_post_thumbnail($product_id, $attachment_id);
        }

        // Use WP temp file and media_handle_sideload
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp_file = download_url($image_url);

        if (is_wp_error($tmp_file)) {
            tg_log("Failed to download image: {$image_url} | Error: " . $tmp_file->get_error_message());
            return false;
        }

        // Prepare the array mimicking a $_FILES array
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp_file,
        ];

        // Upload and attach to product
        $attachment_id = media_handle_sideload($file_array, $product_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp_file);
            tg_log("Failed to sideload image for Product {$product_id}: " . $attachment_id->get_error_message());
            return false;
        }

        set_post_thumbnail($product_id, $attachment_id);
        return true;
    }
}


/* ==========================================================
   BATCHED CRON SYSTEM (Prevents 502 timeout)
   ========================================================== */
/* Daily event scheduler */
add_action('wp', 'woo_api_sync_schedule_event');
if (!function_exists('woo_api_sync_schedule_event')) {
    function woo_api_sync_schedule_event() {
        if (!wp_next_scheduled('TG_api_offers_sync_event')) {
            wp_schedule_event(time(), 'daily', 'TG_api_offers_sync_event');
        }
    }
}

/* Clear event on deactivation */
register_deactivation_hook(__FILE__, 'TG_api_offers_clear_event');
if (!function_exists('TG_api_offers_clear_event')) {
    function TG_api_offers_clear_event() {
        $timestamp = wp_next_scheduled('TG_api_offers_sync_event');
        if ($timestamp) wp_unschedule_event($timestamp, 'TG_api_offers_sync_event');
    }
}

/* Main Sync Starter (fetch once, then batch) */
add_action('TG_api_offers_sync_event', 'woo_api_sync_start_batch_process');
if (!function_exists('woo_api_sync_start_batch_process')) {
    function woo_api_sync_start_batch_process() {
        tg_log('Starting API offer sync batch process...');
        // Mark sync as in progress and set start time
        update_option('tg_offer_batch_in_progress', true);
        update_option('tg_offer_batch_start_time', time());
        update_option('tg_offer_batch_index', 0);
        // Fetch offers from API
        $api_response = fetch_api_offers();
        if (!$api_response || empty($api_response['data'])) {
            tg_log('API returned empty or invalid data: ' . json_encode($api_response));
            update_option('tg_offer_batch_in_progress', false);
            return;
        }
        // Prepare offer data
        $offers = $api_response['data'];
        $total_offers = count($offers);
        $chunks = array_chunk($offers, 500);
        // Clear old chunks first (if any)
        $old_chunk_count = (int) get_option('tg_offer_chunk_count', 0);
        for ($i = 0; $i < $old_chunk_count; $i++) {
            delete_option("tg_offer_chunk_{$i}");
        }
        // Store new chunks
        foreach ($chunks as $i => $chunk) {
            update_option("tg_offer_chunk_{$i}", $chunk, false);
        }
        update_option('tg_offer_chunk_count', count($chunks), false);
        tg_log("Saved {$total_offers} offers across " . count($chunks) . " chunks.");
        // Schedule first batch
        wp_schedule_single_event(time() + 60, 'TG_api_offers_batch_process');
        tg_log('First batch scheduled to start in 60 seconds...');
    }
}

/* Batch processor for syncing offers from API */
add_action('TG_api_offers_batch_process', 'woo_api_process_offers_batch');
if (!function_exists('woo_api_process_offers_batch')) {
    function woo_api_process_offers_batch() {
        tg_log("Starting offer batch process...");
        // Mark as in progress
        update_option('tg_offer_batch_in_progress', true);
        update_option('tg_offer_batch_start_time', time());
        // Retrieve all offers (from transient or stored option)
        $offers = get_option('tg_all_offers_data');
        $chunk_count = (int) get_option('tg_offer_chunk_count', 0);
        // Fallback: merge chunked offers if single option missing
        if (empty($offers) && $chunk_count > 0) {
            $offers = [];
            for ($i = 0; $i < $chunk_count; $i++) {
                $offers = array_merge($offers, (array) get_option("tg_offer_chunk_{$i}", []));
            }
        }
        if (empty($offers)) {
            tg_log("No offers found in options. Batch stopped.");
            update_option('tg_offer_batch_in_progress', false);
            return;
        }
        $batch_size  = 200;
        $batch_index = (int) get_option('tg_offer_batch_index', 0);
        $total       = count($offers);
        $offset      = $batch_index * $batch_size;
        $batch       = array_slice($offers, $offset, $batch_size);
        tg_log("Loaded total offers: {$total}");
        tg_log("Processing batch #" . ($batch_index + 1) . " ({$offset} - " . ($offset + count($batch)) . ")");
        if (empty($batch)) {
            // Compute valid SKUs from the full offers list
            $valid_offer_skus = [];
            if (!empty($offers) && is_array($offers)) {
                foreach ($offers as $offer_item) {
                    if (!empty($offer_item['id'])) $valid_offer_skus[] = (string) $offer_item['id'];
                }
            }
            // Run deletion & republish only once, using the computed valid SKUs
            if (!empty($valid_offer_skus)) {
                delete_obsolete_woocommerce_products($valid_offer_skus);
                republish_trashed_products($valid_offer_skus);
                error_log('Woo API Sync: Performed deletion and republish actions after final batch.');
            } else {
                error_log('Woo API Sync: Could not determine valid SKUs for deletion/republish (transient missing).');
            }
            // All done — cleanup
            tg_log("Woo API Sync completed all {$total} offers successfully.");
            delete_option('tg_all_offers_data');
            delete_option('tg_offer_chunk_count');
            delete_option('tg_offer_batch_index');
            delete_option('tg_offer_batch_in_progress');
            delete_option('tg_offer_batch_start_time');
            tg_log("Cleanup completed. Flags and data cleared.");
            return;
        }
        // Import offers in this batch
        try {
            import_offers_from_api_response(['data' => $batch]);
            tg_log("Batch #" . ($batch_index + 1) . " imported successfully.");
        } catch (Exception $e) {
            tg_log("Error in batch #" . ($batch_index + 1) . ": " . $e->getMessage());
            // Stop and mark not in progress for retry
            update_option('tg_offer_batch_in_progress', false);
            return;
        }
        // Prepare next batch
        update_option('tg_offer_batch_index', $batch_index + 1);
        update_option('tg_offer_batch_in_progress', false);
        tg_log("Scheduling next batch #" . ($batch_index + 2) . " in 60 seconds...");
        // Schedule the next batch
        wp_schedule_single_event(time() + 60, 'TG_api_offers_batch_process');
        // Safety: clear if stuck for >1 hour
        $batch_start = get_option('tg_offer_batch_start_time');
        if ($batch_start && (time() - $batch_start) > HOUR_IN_SECONDS) {
            tg_log("Sync stuck for more than 1 hour. Forcing reset.");
            delete_option('tg_offer_batch_in_progress');
        }
        tg_log("Batch #" . ($batch_index + 1) . " completed successfully at " . date('Y-m-d H:i:s'));
    }
}