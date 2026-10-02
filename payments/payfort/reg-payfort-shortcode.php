<?php 

defined( 'ABSPATH' ) || exit;

add_shortcode('payfort_checkout', 'payfort_checkout_shortcode');
function payfort_checkout_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="message error"><span class="text">يرجى تسجيل الدخول أولا إلى حسابك.</span></div>';
    }

    if (!isset($_GET['bill_id']) || !isset($_GET['amount']) || !isset($_GET['payment_method'])) {
        return '<p>طلب الدفع غير صالح.</p>';
    }

    if ( isset($_GET['bill_id']) && isset($_GET['amount']) ) {
        global $tabibgroup_base_url;
        $current_user_id = get_current_user_id();
        $access_token = get_user_meta($current_user_id, 'api_access_token', true);
        $client = get_user_meta($current_user_id, 'api_client', true);
        $uid = get_user_meta($current_user_id, 'api_uid', true);
        
        $bill_id = sanitize_text_field($_GET['bill_id']);
        $amount = sanitize_text_field($_GET['amount']);
        $payment_method = sanitize_text_field($_GET['payment_method']);
        
        // API URL to fetch PayFort checkout details
        $api_url = $tabibgroup_base_url . '/api/word_press/v1/payfort/payfort_checkout?bill_id='. $bill_id .'&amount='.$amount;
        
        $response = wp_remote_get($api_url, [
            'headers' => [
                'access-token' => $access_token,
                'client' => $client,
                'uid' => $uid,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return '<p>فشل في جلب تفاصيل الدفع.</p>';
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || $data['status'] !== 'success') {
            return '<p>خطأ في جلب تفاصيل الدفع.</p>';
        }

        if ( $payment_method === 'Apple Pay' ) {
            $checkout_url = esc_url('/thank-you/?bill_id='.$bill_id.'&payment_method='.$payment_method.'');
        } else {
            $checkout_url = esc_url($data['data']['checkout_url']);
        }
        $params = $data['data']['payfort_params'];

        $requestParams = array(
            'command' => esc_attr($params['command']),
            'access_code' => esc_attr($params['access_code']),
            'merchant_identifier' => esc_attr($params['merchant_identifier']),
            'merchant_reference' => esc_attr($params['merchant_reference']),
            'amount' => esc_attr($params['amount']),
            'currency' => esc_attr($params['currency']),
            'language' => esc_attr($params['language']),
            'customer_email' => esc_attr($params['customer_email']),
            'return_url' => esc_attr($params['return_url']),
            'signature' => esc_attr($params['signature']),
            'order_description' => esc_attr($params['order_description'])
        );

        ob_start();
        ?>
        <h3 style="text-align: center; margin-top: 30px;">يرجى الانتظار، نحن نقوم بتوجيهك إلى المحتوى الذي طلبته...</h3>
        <form action="<?php echo $checkout_url; ?>" method="post" id="frm" name="frm">
            <?php foreach ($requestParams as $key => $value) : ?>
                <input type="hidden" name="<?php echo htmlentities($key); ?>" value="<?php echo htmlentities($value); ?>">
            <?php endforeach; ?>
        </form>
        <script type="text/javascript">
            document.frm.submit();
        </script>
        <?php
        return ob_get_clean();
    } else {
        wp_send_json_error(array('message' => 'No payment data received.'));
    }
}
?>
