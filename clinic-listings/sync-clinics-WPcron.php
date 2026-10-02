<?php
/**
 * Plugin: TabibGroup Clinic Sync (WP-Cron Version)
 * File: TabibGroup-core/clinic-listings/sync-clinics-cron.php
 */

defined('ABSPATH') || exit;

/* ----------------------------------------------------------
   LOGGING HELPER
---------------------------------------------------------- */
if (!function_exists('tg_clinic_log')) {
    function tg_clinic_log($message) {
        $file = WP_CONTENT_DIR . '/tg-clinic-sync.log';
        $time = date('Y-m-d H:i:s');
        $max_size = 0.1 * 1024 * 1024;

        if (file_exists($file) && filesize($file) > $max_size) {
            file_put_contents($file, "[$time] Log cleared automatically (>0.1MB)\n");
        }

        file_put_contents(
            $file,
            mb_convert_encoding("[$time] {$message}\n", 'UTF-8', 'UTF-8'),
            FILE_APPEND | LOCK_EX
        );
    }
}

/* ----------------------------------------------------------
   FETCH PAGINATED CLINICS FROM API
---------------------------------------------------------- */
if (!function_exists('fetch_api_clinics')) {
    function fetch_api_clinics() {

        $all_clinics = [];
        $page = 1;
        $delay = 1;

        tg_clinic_log("Fetching clinics using pagination...");

        while (true) {

            $api_url = tg_api_url('/api/word_press/v1/doctors?page=' . $page);
            tg_clinic_log("Requesting Page {$page}: {$api_url}");

            $response = wp_remote_get($api_url);

            if (is_wp_error($response)) {
                tg_clinic_log("API Request Failed on page {$page}: " . $response->get_error_message());
                break;
            }

            $body = wp_remote_retrieve_body($response);
            $json = json_decode($body, true);

            tg_clinic_log("Json response: {$json}");

            if (!is_array($json) || empty($json['data'])) {
                tg_clinic_log("Invalid or empty JSON on page {$page}");
                break;
            }

            $all_clinics = array_merge($all_clinics, $json['data']);
            tg_clinic_log("Json all_clinics: {$all_clinics}");
            tg_clinic_log("Fetched " . count($json['data']) . " clinics from page {$page}");

            $total_pages = $json['total_pages='] ?? 1;
            if ($page >= $total_pages) {
                tg_clinic_log("Reached last page ({$total_pages}).");
                break;
            }

            tg_clinic_log("Sleeping {$delay}s before next page...");
            sleep($delay);
            $page++;
        }

        tg_clinic_log("Total clinics fetched: " . count($all_clinics));
        return $all_clinics;
    }
}

/* ----------------------------------------------------------
   CHECK EXISTING POST
---------------------------------------------------------- */
if (!function_exists('get_existing_clinic_post_id')) {
    function get_existing_clinic_post_id($doctor_id, $clinic_name) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} pm 
                ON p.ID = pm.post_id AND pm.meta_key = 'doctor_id'
            WHERE p.post_type = 'clinic_listing'
              AND p.post_status != 'trash'
              AND (pm.meta_value = %s OR p.post_title = %s)
            LIMIT 1
        ", $doctor_id, $clinic_name));
    }
}

/* ----------------------------------------------------------
   IMPORT SINGLE CLINIC
---------------------------------------------------------- */
if (!function_exists('import_clinic')) {
    function import_clinic($clinic_data) {

        if (empty($clinic_data['doctor_id'])) {
            tg_clinic_log("Skipped clinic: doctor_id missing");
            return false;
        }

        $doctor_id   = trim($clinic_data['doctor_id']);
        $clinic_name = $clinic_data['doctor_name'] ?? "Clinic without Name";
        $slug        = sanitize_title($clinic_name);

        $existing = get_existing_clinic_post_id($doctor_id, $clinic_name);

        $post_data = [
            'post_title'   => wp_strip_all_tags($clinic_name),
            'post_content' => $clinic_data['about_clinic'] ?? "",
            'post_status'  => 'publish',
            'post_type'    => 'clinic_listing',
            'post_name'    => $slug
        ];

        if ($existing) {
            $post_data['ID'] = $existing;
            $post_id = wp_update_post($post_data, true);
            tg_clinic_log("Updating clinic: {$clinic_name} (ID {$existing})");
        } else {
            $post_id = wp_insert_post($post_data);
            tg_clinic_log("Inserting new clinic: {$clinic_name} (ID {$post_id})");
        }

        if (is_wp_error($post_id)) {
            tg_clinic_log("Post insert/update failed: " . $post_id->get_error_message());
            return false;
        }

        /* ACF FIELDS */
        $acf_fields = [
            'clinic_doctor_id'       => $doctor_id,
            'clinic_doctor_phone'    => $clinic_data['doctor_phone'] ?? '',
            'clinic_speciality_text' => $clinic_data['speciality_text'] ?? '',
            'clinic_hospital_name'   => $clinic_data['hospital_name'] ?? '',
            'clinic_hospital_address'=> $clinic_data['hospital_address'] ?? '',
            'clinic_clinic_location' => $clinic_data['clinic_location'] ?? '',
            'clinic_latitude'        => $clinic_data['latitude'] ?? '',
            'clinic_longitude'       => $clinic_data['longitude'] ?? '',
            'clinic_status'          => $clinic_data['status'] ?? '',
            'clinic_doctor_email'    => $clinic_data['doctor_email'] ?? '',
            'clinic_doctor_image'    => $clinic_data['doctor_image'] ?? '',
            'clinic_offers_count'    => $clinic_data['offers_count'] ?? 0,
            'clinic_cities'          =>
                isset($clinic_data['cities']) && is_array($clinic_data['cities'])
                ? implode(', ', array_column($clinic_data['cities'], 'name'))
                : '',
        ];

        foreach ($acf_fields as $key => $value) {
            update_field($key, $value, $post_id);
        }

         /* FEATURED IMAGE */
        if (!empty($clinic_data['doctor_image'])) {
            try {
                $featured_image_url = tg_api_url($clinic_data['doctor_image']);
                set_clinic_featured_image_WPcron($post_id, $featured_image_url);
                tg_clinic_log("Image updated for clinic ID {$post_id}");
            } catch (Throwable $e) {
                tg_clinic_log("Failed setting image for {$post_id}: " . $e->getMessage());
            }
        }

        return $post_id;
    }
}

/* ----------------------------------------------------------
   FEATURED IMAGE
---------------------------------------------------------- */
if (!function_exists('set_clinic_featured_image_WPcron')) {
    function set_clinic_featured_image_WPcron($post_id, $image_url) {
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

/* ----------------------------------------------------------
   MAIN SYNC FUNCTION (USED BY CRON)
---------------------------------------------------------- */
if (!function_exists('tg_run_clinics_sync')) {
    function tg_run_clinics_sync() {

        tg_clinic_log("=== Clinic Sync Started (WP-Cron) ===");

        $clinics = fetch_api_clinics();
        if (!$clinics) {
            tg_clinic_log("No clinics returned — ending process.");
            return;
        }

        foreach ($clinics as $clinic) {
            try {
                import_clinic($clinic);
            } catch (Throwable $e) {
                tg_clinic_log("Error importing clinic: " . $e->getMessage());
            }
        }

        tg_clinic_log("=== Clinic Sync Completed ===");
    }
}

/* ---------------------- Schedule Cron (Daily) ---------------------- */
function tg_schedule_clinics_sync() {
    if (!wp_next_scheduled('tg_daily_clinics_sync')) {
        wp_schedule_event(time(), 'daily', 'tg_daily_clinics_sync');
    }
}
add_action('wp', 'tg_schedule_clinics_sync');
add_action('tg_daily_clinics_sync', 'tg_run_clinics_sync');
