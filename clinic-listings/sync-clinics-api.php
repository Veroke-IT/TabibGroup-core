<?php

if (!defined('ABSPATH')) {
    exit;
}

// Register REST API Endpoint
add_action('rest_api_init', function () {
    register_rest_route('crm/v1', '/sync-clinic', [
        'methods'  => ['POST', 'DELETE'],
        'callback' => 'sync_crm_clinic',
        'permission_callback' => function () {
            $is_valid = clinic_is_valid_crm_basic_auth();
            $logger = wc_get_logger();
            $context = ['source' => 'webhook-sync-clinics'];
            if (!$is_valid) {
                $logger->warning('Webhook called: Authentication failed.', $context);
                return;
            }
            return current_user_can('manage_woocommerce') || $is_valid;
        },
    ]);
});

// Validate Request
if (!function_exists('clinic_is_valid_crm_basic_auth')) {
    function clinic_is_valid_crm_basic_auth()
    {
        $logger = wc_get_logger();
        $context = ['source' => 'webhook-sync-clinics'];
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
        if (hash_equals($expected_key, $consumer_key) && hash_equals($expected_secret, $consumer_secret)) return true;
        $logger->warning(
            "Authorization failed: key/secret mismatch. Received key: $consumer_key",
            $context
        );
        return false;
    }
}

/* ---------------------- Sync CRM Clinic ---------------------- */
if (!function_exists('sync_crm_clinic')) {
    function sync_crm_clinic(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        $method = $request->get_method();
        $logger  = wc_get_logger();
        $context = ['source' => 'webhook-sync-clinics'];
        $logger->info("CRM Clinic Webhook Received: " . json_encode($data), $context);
        // Validate doctor_id
        if (empty($data['doctor_id'])) {
            $logger->warning("doctor_id not recieved by API response", $context);
            return new WP_REST_Response(['message' => 'Missing doctor_id'], 400);
        }
        $doctor_id  = intval($data['doctor_id']);
        $logger->warning("doctor_id received: $doctor_id", $context);
        $is_active  = (isset($data['status']) && $data['status'] === "true");
        $clinic_name = sanitize_text_field($data['doctor_name'] ?? 'Clinic without Name');
        $slug        = sanitize_title($clinic_name);
        // -----------------------------------------------------
        // Check if clinic already exists
        // -----------------------------------------------------
        if (!empty($doctor_id)) {
            global $wpdb;
            $clinic_id = $wpdb->get_var($wpdb->prepare("
                SELECT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                WHERE p.post_type = 'clinic_listing'
                AND pm.meta_key = 'clinic_doctor_id'
                AND pm.meta_value = %s
                AND p.post_status != 'auto-draft'
                LIMIT 1
            ", $doctor_id));
            $clinic_id = $clinic_id ? (int) $clinic_id : 0;
            $logger->warning("clinic_id found: $clinic_id", $context);
        }
        // -----------------------------------------------------
        // DELETE Handler (CRM deleted clinic)
        // -----------------------------------------------------
        if ($method === 'DELETE') {
            if ($clinic_id) {
                wp_trash_post($clinic_id);
                $logger->warning("Clinic deleted via webhook: Doctor ID $doctor_id", $context);
                return new WP_REST_Response(['message' => "Clinic deleted"], 200);
            }
            return new WP_REST_Response(['message' => "Clinic not found"], 404);
        }
        // -----------------------------------------------------
        // ACTIVE / INACTIVE → manage publish / draft / trash
        // -----------------------------------------------------
        // if ($clinic_id && isset($data['status'])) {
        //     if ($data['status'] === 'true') {
        //         wp_update_post(['ID' => $clinic_id, 'post_status' => 'publish']);
        //         update_field('clinic_status', 'true', $clinic_id);
        //         $status_message = "Clinic marked active: $doctor_id";

        //     } elseif ($data['status'] === 'false') {
        //         wp_trash_post($clinic_id);
        //         update_field('clinic_status', 'false', $clinic_id);
        //         $status_message = "Clinic marked inactive: $doctor_id";
        //     }
        // }

        // -----------------------------------------------------
        // CREATE or UPDATE clinics if needed
        // -----------------------------------------------------
        if (!$clinic_id) {
            // Insert new clinic
            $clinic_id = wp_insert_post([
                'post_type'    => 'clinic_listing',
                'post_title'   => wp_strip_all_tags($clinic_name),
                'post_content' => $data['about_clinic'] ?? '',
                'post_status'  => 'publish',
                'post_name'    => $slug,
            ]);
            $logger->info("New clinic created: $clinic_name (Doctor ID: $doctor_id) - (Clinic ID: $clinic_id)", $context);
        } else {
            // Update existing clinic
            wp_update_post([
                'ID'          => $clinic_id,
                'post_title'  => wp_strip_all_tags($clinic_name),
                'post_status' => 'publish',
                'post_name'   => $slug,
            ]);
            // Update post_content only if empty
            if (empty(get_post_field('post_content', $clinic_id)) && !empty($data['about_clinic'])) {
                wp_update_post([
                    'ID'           => $clinic_id,
                    'post_content' => $data['about_clinic'],
                ]);
            }
            $logger->info("Clinic updated: $clinic_name (Doctor ID: $doctor_id) - (Clinic ID: $clinic_id)", $context);
        }

        /* ---------------------- Update ACF Fields ---------------------- */
        $acf_fields = [
            'clinic_doctor_id'       => $doctor_id,
            'clinic_doctor_phone'    => $data['doctor_phone'] ?? '',
            'clinic_speciality_text' => $data['speciality_text'] ?? '',
            'clinic_hospital_name'   => $data['hospital_name'] ?? '',
            'clinic_hospital_address' => $data['hospital_address'] ?? '',
            'clinic_clinic_location' => $data['clinic_location'] ?? '',
            'clinic_latitude'        => $data['latitude'] ?? '',
            'clinic_longitude'       => $data['longitude'] ?? '',
            'clinic_status'          => $data['status'] ?? '',
            'clinic_doctor_email'    => $data['doctor_email'] ?? '',
            'clinic_doctor_image'    => $data['doctor_image'] ?? '',
            'clinic_offers_count'    => $data['offers_count'] ?? 0,
            'clinic_cities'          =>
            isset($data['cities']) && is_array($data['cities'])
                ? implode(', ', array_column($data['cities'], 'name'))
                : '',
        ];
        foreach ($acf_fields as $key => $value) {
            update_field($key, $value, $clinic_id);
        }
        if (!empty($data['doctor_image'])) {
            try {
                $featured_image_url = home_url(ltrim($clinic_data['doctor_image'], '/'));
                set_sync_clinic_featured_image($post_id, $featured_image_url);
            } catch (Exception $e) {
                $logger->error("Failed image for clinic $clinic_id: " . $e->getMessage(), $context);
            }
        }
        return new WP_REST_Response([
            'message' => $status_message ?? "Clinic updated: $doctor_id",
        ], 200);
    }
}

/* ---------------------- Set Clinic Featured Image ---------------------- */
if (!function_exists('set_sync_clinic_featured_image')) {
    function set_sync_clinic_featured_image($post_id, $image_url)
    {
        if (empty($image_url) || empty($post_id)) return false;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $clean_url = strtok($image_url, '?');
        $extension = pathinfo($clean_url, PATHINFO_EXTENSION);
        if (!$extension) $extension = 'jpg';
        $filename = 'clinic-' . $post_id . '.' . $extension;
        $tmp_file = download_url($image_url);
        if (is_wp_error($tmp_file)) {
            throw new Exception($tmp_file->get_error_message());
        }
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp_file,
        ];
        $attachment_id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp_file);
            throw new Exception($attachment_id->get_error_message());
        }
        set_post_thumbnail($post_id, $attachment_id);
    }
}
