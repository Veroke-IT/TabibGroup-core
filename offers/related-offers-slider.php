<?php

defined( 'ABSPATH' ) || exit;

add_action('wp_ajax_get_related_offers_slider', 'tg_get_related_offers_slider');
add_action('wp_ajax_nopriv_get_related_offers_slider', 'tg_get_related_offers_slider');
function tg_get_related_offers_slider() {
    $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
    $current_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if (!$current_product_id) {
        wp_send_json_error('Product ID missing.');
    }

    // --- Get the product categories ---
    $terms = wp_get_post_terms($current_product_id, 'product_cat');

    if (empty($terms) || is_wp_error($terms)) {
        wp_send_json_error('No categories found.');
    }

    // Separate parent and child category slugs
    $parent_cat_slugs = [];
    $child_cat_slugs = [];

    foreach ($terms as $term) {
        if ($term->parent == 0) {
            $parent_cat_slugs[] = $term->slug;
        } else {
            $child_cat_slugs[] = $term->slug;
        }
    }

    ob_start();

    echo '<section class="offer-slider-block">';    
    echo '<div class="slider-header">';
        echo "<h2>عروض مشابھة</h2>";
        echo "<p><a href='/عروض-طبيب/' class='show-all'>إظهار الكل</a></p>";
    echo '</div>';

    // --- Build the WP_Query ---
    $tax_query = ['relation' => 'AND'];

    if (!empty($parent_cat_slugs)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $parent_cat_slugs,
        ];
    }

    if (!empty($child_cat_slugs)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $child_cat_slugs,
        ];
    }

    $meta_query = [];

    if (!empty($city)) {
        $meta_query[] = [
            'key'     => 'cities',
            'value'   => $city,
            'compare' => 'LIKE',
        ];
    }

    $args = [
        'post_type'      => 'product',
        'posts_per_page' => 8,
        'post__not_in'   => [$current_product_id],
        'tax_query'      => $tax_query,
        'meta_query'     => $meta_query,
    ];

    $products = new WP_Query($args);

    if ($products->have_posts()) {
        echo '<div class="brxe-carousel sop-slider">';
        echo '<div class="swiper offer-swiper bricks-swiper-container">';
        echo '<div class="swiper-wrapper">';

        while ($products->have_posts()) {
            $products->the_post();
            global $product;
            $sku = $product->get_sku();
            $offer_url = !empty($sku) ? "/offers/{$sku}" : get_permalink($product->get_id());
            $accept_installment = get_field('is_offer_accept_installment', $product->get_id());
            $bold_title = get_field('bold_title', $product->get_id());
            $light_title = get_field('light_title', $product->get_id());
            $doctor_name = get_field('doctor_name', $product->get_id());
            $doctor_id = get_field('doctor_id', $product->get_id());
            $clinic_location = get_field('clinic_location', $product->get_id());
            $avg_rating = get_field('avg_rating', $product->get_id());
            $claims_count = get_field('claimed_count', $product->get_id());
            ?>
            <div class="brxe-fwicvy brxe-container swiper-slide">
                <div class="brxe-bdbprb brxe-block">
                    <p class="brxe-xtldrz brxe-text-basic"> رقم العرض: <span class="sku"> <?php echo esc_html($product->get_sku()); ?> </span></p>
                    <a href="<?php echo esc_url($offer_url); ?>"><?php echo $product->get_image('full', ['class' => 'brxe-dhrodg brxe-image css-filter size-full']); ?></a>
                    <?php if ($accept_installment === '1') : ?>
                    <div class="payment-options">
                        <img width="34" height="34" src="<?php echo site_url(); ?>/wp-content/uploads/image-2.png" alt="Tabby" class="brxe-ifgfhj brxe-image css-filter size-full">
                        <img width="34" height="34" src="<?php echo site_url(); ?>/wp-content/uploads/image-3.png" alt="تمارا" class="brxe-uqlgkb brxe-image css-filter size-full">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="brxe-efrjnz brxe-block">
                    <div class="brxe-hckunf brxe-block">
                        <div class="brxe-corhph brxe-block">
                            <div class="brxe-dxsckk brxe-esjbri brxe-product-price">
                                <p class="price"><?php echo $product->get_price_html(); ?></p>
                            </div>
                        </div>
                        <div class="brxe-riteak brxe-block">
                            <?php if ($product->is_on_sale()) { ?>
                                <p class="brxe-fnzfzv brxe-shortcode"><span dir="ltr"><?php echo do_shortcode('[sale_percentage]'); ?></span></p>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="brxe-lmslci brxe-divider horizontal"><div class="line"></div></div>
                    <a href="<?php echo esc_url($offer_url); ?>">
                        <h3 class="brxe-opulje brxe-post-title"><?php echo esc_html($bold_title); ?> <?php echo esc_html($light_title); ?></h3>
                    </a>
                    <div class="brxe-ygksdl brxe-block">
                        <ul class="brxe-enxkja brxe-social-icons">
                            <li class="repeater-item lg no-link">
                                <a href="/clinic-listings/<?php echo esc_html($doctor_id); ?>"><p><?php echo esc_html($doctor_name); ?></p></a>
                            </li>
                        </ul>
                    </div>
                    <div class="brxe-ygksdl brxe-block mb-24">
                        <ul class="brxe-enxkja brxe-social-icons">
                            <li class="repeater-item lg no-link clinic-loc">
                                <img src="<?php echo plugins_url('../assets/images/location-icon.svg', __FILE__); ?>" alt="location-icon">
                                <span><?php echo esc_html($clinic_location); ?></span>
                            </li>
                            <li class="repeater-item no-link">
                                <i class="ti-layout-line-solid icon"></i>
                            </li>
                        </ul>
                        <ul class="brxe-bsxmbc brxe-social-icons">
                            <li class="repeater-item no-link clinic-rating">
                                <img src="<?php echo plugins_url('../assets/images/rating-star.svg', __FILE__); ?>" alt="rating">
                                <span><?php echo esc_html($avg_rating); ?></span>
                            </li>
                        </ul>
                        <ul class="brxe-gnowdx brxe-social-icons">
                            <li class="repeater-item no-link clinic-claims">
                                <span>(<span class="claims"><?php echo esc_html($claims_count); ?></span> تعليق)</span>
                            </li>
                        </ul>
                    </div>
                    <a class="brxe-sdwdzz brxe-button bricks-button xl bricks-background-primary circle" href="<?php echo esc_url($offer_url); ?>">احجز الان</a>
                </div>
            </div>
            <?php
        }

        echo '</div>'; // swiper-wrapper
        echo '<div class="swiper-pagination-wrap"><div class="swiper-pagination"></div></div>';
        echo '</div>'; // swiper
        echo '<div class="swiper-button swiper-button-prev"></div><div class="swiper-button swiper-button-next"></div>';
        echo '</div>'; // sop-slider
    } else {
        echo '<p class="no-related">لا توجد عروض مشابهة في نفس المدينة.</p>';
    }

    wp_reset_postdata();
    echo '</section>';

    wp_send_json_success(ob_get_clean());
}