<?php 

defined( 'ABSPATH' ) || exit;

// PHP function to Retrieve Product Meta
add_action('wp_ajax_get_reservation_type', 'get_reservation_type');
function get_reservation_type() {
    check_ajax_referer('get_reservation_type_nonce', 'security');
    if (!is_user_logged_in()) {        
        wp_send_json_error(['message' => 'User not logged in.']);
    }
    if (isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        $session = get_post_meta($product_id, 'session', true);
        $consultation = get_post_meta($product_id, 'consultation', true);
        wp_send_json([
            'session' => $session,
            'consultation' => $consultation,
        ]);
    }
    wp_send_json_error('Offer ID not found to get reservation type.');
}
