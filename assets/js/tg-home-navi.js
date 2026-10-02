function getUserLocation() {
    let cityData = localStorage.getItem("user_location");
    let locationData = localStorage.getItem("user_current_location");

    let parsedCity = cityData ? JSON.parse(cityData) : { name: "جده" };
    let parsedLocation = locationData ? JSON.parse(locationData) : { lat: 21.492500, long: 39.177570 };

    return {
        city: parsedCity.name,
        lat: parsedLocation.lat,
        long: parsedLocation.long
    };
}

async function fetchOffersData(url) {
    const loader = document.querySelector("#brxe-Loader");
    const bricksOffers = document.querySelector("#brxe-Offers");

    try {
        loader.style.display = "block";
        bricksOffers.style.display = "none";

        let response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
        
        let data = await response.json();
        if (!data || !data.data) {
            throw new Error("Invalid API response: offers not found");
        }
        
        let offers = [];
        if (data.data?.specialist && Array.isArray(data.data?.offers)) {
            // Specialist offer API
            offers = data.data.offers;
        } else if (Array.isArray(data.data)) {
            // General offers API
            offers = data.data;
        } else {
            throw new Error("No offers found");
        }
        // Optional: Pass specialist if available
        const specialist = data.data?.specialist || null;
        loader.style.display = "none";
         if (offers.length > 0) {
            updateOffersSection(offers, specialist);
        } else {
            bricksOffers.style.display = "grid";
        }

    } catch (error) {
        console.error("Error fetching offers data:", error);
        document.querySelector("#brxe-Loader").style.display = "none";
        document.querySelector("#brxe-Offers").style.display = "grid";
    }
}

function updateOffersSection(offers, specialist = null) {
    let offersContainer = document.querySelector("#brxe-OffersWrap");
    if (!offersContainer) return;

    // If specialist is available, use their info at the top
    if (specialist) {
        // Render specialist header info
        // console.log("Specialist Name:", specialist.name);
    }

    offersContainer.innerHTML = `
        <div id="brxe-dcezll" class="brxe-container brx-grid" style="padding: 0;"> 
            ${offers.map(offer => {
                // Get base url
                const site_url = window.location.origin;
                const clinicSlug = offer.doctor_name.replace(/\s+/g, '-').toLowerCase();
                const clinicUrl = `/clinic-listings/${encodeURIComponent(clinicSlug)}/`;
                // Calculate discount percentage
                let discountPercentage = offer.old_price > offer.new_price 
                    ? Math.round(((offer.old_price - offer.new_price) / offer.old_price) * 100) 
                    : 0;
                return `
                    <div class="brxe-fwicvy brxe-container offer-item">
                        <div class="brxe-bdbprb brxe-block">
                            <p class="brxe-xtldrz brxe-text-basic"> رقم العرض: <span class="sku"> ${offer.id} </span></p>
                            <a href="/offers/${offer.id}"><img width="400" height="300" src="${offer.new_offer_images[0]?.image || offer.offer_images[0]?.cloud_image}" class="brxe-dhrodg brxe-image css-filter size-full" alt="${offer.title}"></a>
                            ${offer.is_offer_accept_installment ? `
                            <div class="payment-options">
                                <img width="34" height="34" src="${site_url}/wp-content/uploads/image-2.png" class="brxe-ifgfhj brxe-image css-filter size-full" alt="">
                                <img width="34" height="34" src="${site_url}/wp-content/uploads/image-3.png" class="brxe-uqlgkb brxe-image css-filter size-full" alt="">
                            </div>
                            ` : ''}
                        </div>
                        <div class="brxe-efrjnz brxe-block heightFix">
                            <div class="brxe-hckunf brxe-block">
                                <div class="brxe-corhph brxe-block">
                                    <div class="brxe-dxsckk brxe-esjbri brxe-product-price">
                                        <p class="price">
                                            ${offer.old_price > offer.new_price ? `
                                                <del aria-hidden="true">
                                                    <span class="woocommerce-Price-amount amount">
                                                        <bdi>${offer.old_price}<span class="woocommerce-Price-currencySymbol">ريال</span></bdi>
                                                    </span>
                                                </del>
                                            ` : ''}
                                            <ins aria-hidden="true">
                                                <span class="woocommerce-Price-amount amount">
                                                    <bdi>${offer.new_price}<span class="woocommerce-Price-currencySymbol">ريال</span></bdi>
                                                </span>
                                            </ins>
                                        </p>
                                    </div>
                                </div>
                                <div class="brxe-riteak brxe-block">
                                    <p class="brxe-fnzfzv brxe-shortcode">${discountPercentage > 0 ? `<span class="badge onsale" dir="ltr">-${discountPercentage}%</span>` : ''}</p>
                                </div>
                            </div>
                            <div class="brxe-lmslci brxe-divider horizontal">
                                <div class="line"></div>
                            </div>
                            ${(offer.bold_title || offer.light_title) ? `
                                <a href="/offers/${offer.id}">
                                    <h3 class="brxe-opulje brxe-post-title">${offer.bold_title ?? ''} ${offer.light_title ?? ''}</h3>                                                   
                                </a>
                            ` : ''}
                            <div class="brxe-ygksdl brxe-block">
                                <ul class="brxe-enxkja brxe-social-icons">
                                    <li class="repeater-item lg no-link">
                                        <a href="${clinicUrl}"><span>${offer.doctor_name}</span></a>
                                    </li>
                                </ul>
                            </div>
                            <div class="brxe-ygksdl brxe-block mb-24">
                                <ul class="brxe-enxkja brxe-social-icons">
                                    <li class="repeater-item lg no-link clinic-loc">
                                        <img src="${site_url}/wp-content/plugins/TabibGroup-core/assets/images/location-icon.svg" alt="location-icon"><span>${offer.clinic_location}</span>
                                    </li>
                                    <li class="repeater-item no-link mr-12">
                                        <i class="ti-layout-line-solid icon"></i>
                                    </li>
                                </ul>
                                <ul class="brxe-bsxmbc brxe-social-icons">
                                    <li class="repeater-item no-link clinic-rating">
                                        <img src="${site_url}/wp-content/plugins/TabibGroup-core/assets/images/rating-star.svg" alt="rating">
                                        <span>${offer.avg_rating}</span>
                                    </li>
                                </ul>
                                <ul class="brxe-gnowdx brxe-social-icons">
                                    <li class="repeater-item no-link clinic-claims">
                                        <span> (<span class="claims">${offer.claimed_count}</span> تعليق) </span>
                                    </li>
                                </ul>
                            </div>
                            <a class="brxe-sdwdzz brxe-button bricks-button xl bricks-background-primary circle" href="/offers/${offer.id}/">احجز الان</a>
                        </div>
                    </div>
                `;
            }).join("")}
        </div>
    `;

}

document.addEventListener("DOMContentLoaded", function () {
    function determineApiUrl() {
        const getSection_id = naviData.query_vars?.section_id || '';
        const getCollection_id = naviData.query_vars?.collection_id || '';
        const getDoctor_id = naviData.query_vars?.doctor_id || '';
        const getSpecialist_id = naviData.query_vars?.specialist_id || '';
        let { city, lat, long } = getUserLocation();
        const base_url = naviData.baseUrl;

        if (getSection_id) {
            return `${base_url}/api/endpoints/mobile/v1/sections/${getSection_id}.json?lang=ar&page=1&city=${encodeURIComponent(city)}`;
        }
        if (getCollection_id) {
            return `${base_url}/api/endpoints/mobile/v1/offer_collections/${getCollection_id}.json?lang=ar&page=1&city=${encodeURIComponent(city)}&filter=&min_value=&max_value`;
        }
        if (getDoctor_id) {
            return `${base_url}/api/endpoints/mobile/v2/offers/doctor_offers.json?lang=ar&category=&doctor_id=${getDoctor_id}&service=&machines=&latitude=${lat}&longitude=${long}&date=&sub_category=&city=${encodeURIComponent(city)}&filter=&min_value=&max_value=&sort_by=&num_sessions=&num_body_parts=&sort_direction=&sort_category=&page=1`;
        }
        if (getSpecialist_id) {
            return `${base_url}/api/endpoints/mobile/v1/specialists/${getSpecialist_id}.json?lang=ar&page=1&city=${encodeURIComponent(city)}&filter=&min_value=&max_value`;
        }

        return null;
    }

    let apiUrl = determineApiUrl();
    if (apiUrl) {
        fetchOffersData(apiUrl);
    } else {
        document.querySelector("#brxe-Loader").style.display = "none";
        document.querySelector("#brxe-Offers").style.display = "grid";
    }
});