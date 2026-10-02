<?php

defined('ABSPATH') || exit;

function woocommerce_offer_importer_page() {

    // Get saved checkbox value safely
    $test_mode = get_option('enable_test_mode', false);
    ?>
    
    <div class="wrap">

        <h1>Tabib Group API to WooCommerce Sync</h1>

        <!-- Test Mode Setting -->
        <h2>APIs Test Mode</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('offer_importer_settings');
            do_settings_sections('offer-importer');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Test Mode</th>
                    <td>
                        <input type="checkbox" name="enable_test_mode" value="1" <?php checked(1, $test_mode); ?> />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>


        <!-- Offer Logs Section -->
        <h2 style="margin-top:40px;">Manage Logs for Offers</h2>

        <form method="post" style="display:inline-block; margin-bottom:20px;">
            <?php wp_nonce_field('tg_offer_actions', 'tg_offer_nonce'); ?>
            <button type="submit" name="tg_action" value="clear_logs" class="button button-secondary">
                Clear Offers Logs
            </button>
        </form>

        <a href="<?php echo esc_url(site_url('/wp-content/tg-offer-sync.log')); ?>" 
           target="_blank" 
           class="button button-secondary">
           View Offers Logs
        </a>

        <?php
        if (isset($_POST['tg_action']) && check_admin_referer('tg_offer_actions', 'tg_offer_nonce')) {
            if ($_POST['tg_action'] === 'clear_logs') {

                $log_file = WP_CONTENT_DIR . '/tg-offer-sync.log';

                if (file_exists($log_file)) {
                    file_put_contents($log_file, '');
                    echo '<div class="notice notice-success"><p>Offer log file cleared successfully.</p></div>';

                    if (function_exists('tg_log')) {
                        tg_log('Offer log file manually cleared from admin panel.');
                    }

                } else {
                    echo '<div class="notice notice-warning"><p>Offer log file not found.</p></div>';
                }
            }
        }
        ?>


        <!-- Clinic Logs Section -->
        <hr>
        <h2 style="margin-top:40px;">Manage Logs for Clinics</h2>

        <form method="post" style="display:inline-block; margin-bottom:20px;">
            <?php wp_nonce_field('tg_clinic_actions', 'tg_clinic_nonce'); ?>
            <button type="submit" name="tg_clinic_action" value="clear_clinic_logs" class="button button-secondary">
                Clear Clinics Logs
            </button>
        </form>

        <a href="<?php echo esc_url(site_url('/wp-content/tg-clinic-sync.log')); ?>" 
           target="_blank" 
           class="button button-secondary">
           View Clinics Logs
        </a>

        <?php
        if (isset($_POST['tg_clinic_action']) && check_admin_referer('tg_clinic_actions', 'tg_clinic_nonce')) {
            if ($_POST['tg_clinic_action'] === 'clear_clinic_logs') {

                $log_file = WP_CONTENT_DIR . '/tg-clinic-sync.log';

                if (file_exists($log_file)) {
                    file_put_contents($log_file, '');
                    echo '<div class="notice notice-success"><p>Clinic log file cleared successfully.</p></div>';

                    if (function_exists('tg_clinic_log')) {
                        tg_clinic_log('Clinic log file manually cleared from admin panel.');
                    }

                } else {
                    echo '<div class="notice notice-warning"><p>Clinic log file not found.</p></div>';
                }
            }
        }
        ?>

    </div>

    <?php
}