<?php 

defined( 'ABSPATH' ) || exit;

add_shortcode('city_popup', 'render_city_popup_shortcode');
function render_city_popup_shortcode() {
    ob_start();
    ?>
    <div id="city-popup-overlay"></div>
    <div id="city-popup" style="display: none;">
        <div class="h2">اختر المدينة</div>
        <form id="city-form">
            <div id="city-options">
                <div id="city-loading" style="text-align:center; padding:20px;">
                    <span>جاري تحميل المدن...</span>
                </div>
            </div>
            <button type="submit" id="continue-button">
				<span class="text">استمرار</span>	
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}