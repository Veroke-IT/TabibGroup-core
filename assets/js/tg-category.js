 // Track selected subcategory globally
let selectedSubCategoryId = "";
let hasLoadedSubcatFromURL = false;
document.addEventListener("DOMContentLoaded", function () {

    function getUserLocation() {
        let cityData = localStorage.getItem("user_location");
        let locationData = localStorage.getItem("user_current_location");

        let parsedCity = cityData ? JSON.parse(cityData) : { name: "جده", id: 1 };
        let parsedLocation = locationData ? JSON.parse(locationData) : { lat: 21.492500, long: 39.177570 };

        return {
            city: parsedCity.name,
            city_id: parsedCity.id,
            lat: parsedLocation.lat,
            long: parsedLocation.long
        };
    }

    function sanitizeTitle(str) {
        return String(str || '')
        .trim()
        .toLowerCase()
        .replace(/[^\p{L}\p{N}\s-]/gu, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 200);
    }

    function truncateSlug(slug, maxLength = 200) {
        return slug.length > maxLength ? slug.slice(0, maxLength) : slug;
    }

    (async function () {
        const baseURL = window.location.origin;
        const meta = document.getElementById("category-meta");
        const categoryName = meta.textContent.trim();
        // console.log(categoryName);
        if (!categoryName) return;

        let nameMap = localStorage.getItem("categoryNameMap");
        let categoryId = null;

        if (nameMap) {
            try {
                nameMap = JSON.parse(nameMap);
                categoryId = nameMap[categoryName];
            } catch (e) {
                console.warn("Invalid categoryNameMap in localStorage");
            }
        }

        if (!categoryId) {
            let { city, lat, long } = getUserLocation();
            let apiUrl = `${baseURL}/api/endpoints/mobile/v3/home.json?lang=en&city=${encodeURIComponent(city)}&latitude=${lat}&longitude=${long}`;

            try {
                let response = await fetch(apiUrl);
                let json = await response.json();
                const categories = json?.data?.offer_categories || [];
                // console.log("categories", categories);

                const newNameMap = {};
                categories.forEach(cat => {
                    newNameMap[cat.name] = cat.id;
                });
                localStorage.setItem("categoryNameMap", JSON.stringify(newNameMap));
                categoryId = newNameMap[categoryName];
            } catch (error) {
                console.error("Error fetching homepage data:", error);
            }

            let category_api_banners = document.querySelector(".category_api_banners");
            let category_api_subcategories = document.querySelector(".category_api_subcategories");
            let category_api_doctors = document.querySelector(".category_api_doctors");
            let category_api_offers = document.querySelector(".category_api_offers");
            let category_api_pagination = document.querySelector(".category_api_pagination");
            if (category_api_banners) category_api_banners.remove();
            if (category_api_subcategories) category_api_subcategories.remove();
            if (category_api_doctors) category_api_doctors.remove();
            if (category_api_offers) category_api_offers.remove();
            if (category_api_pagination) category_api_pagination.remove();
            return;

        }

        if (categoryId) {
            let categoryOffers = document.querySelector(".category_offers");
            if (categoryOffers) categoryOffers.remove();
            localStorage.setItem("category_id", categoryId);
            
            function buildApiUrl(page = 1, subCategoryId = "") {
                let { city, city_id, lat, long } = getUserLocation();
                const cat_id = localStorage.getItem("category_id") ?? '';
                // const baseURL = window.location.origin;

                const queryString =
                    `lang=ar` +
                    `&page=${page}` +
                    `&offer_category_id=${cat_id}` +
                    `&city=${city}` +
                    `&city_id=${city_id}` +
                    `&filter=` +
                    `&sub_category_id=${subCategoryId}` +
                    `&sort_by=best_selling` +
                    `&min_value=` +
                    `&max_value=` +
                    `&service=` +
                    `&latitude=${lat}` +
                    `&longitude=${long}` +
                    `&date=` +
                    `&num_sessions=` +
                    `&machines=` +
                    `&doctor_id=` +
                    `&sort_direction=` +
                    `&sort_category=`;

                return `${baseURL}/api/endpoints/mobile/v3/offer_categories/offers.json?${queryString}`;
            }

            function updateBanners(banners) {
                let bannersContainer = document.querySelector(".banners");
                if (!bannersContainer) return;
                bannersContainer.innerHTML = "";
                if (!Array.isArray(banners) || banners.length === 0) {
                    let category_api_banners = document.querySelector(".category_api_banners");
                    if (category_api_banners) category_api_banners.remove();
                    return;
                } else {
                    bannersContainer.innerHTML = `
                        <div class="brxe-carousel">
                            <div class="bricks-swiper-container">
                                    <div class="swiper-wrapper">
                                        ${banners.map(banner => {
                                            let bannerUrl = "#";
                                            if (banner.offer_id !== null && banner.has_offer === "true") {
                                                bannerUrl = `/offers/${banner.offer_id}`;
                                            } else if (banner.website_url && banner.has_website_url === "true") {
                                                bannerUrl = banner.website_url;
                                            } else if (banner.doctor_id !== null && banner.has_doctor === "true") {
                                                localStorage.setItem('doctor_id', banner.doctor_id);
                                                bannerUrl = `/offers/?${banner.name}`;
                                            } else if (banner.collection_id) {
                                                localStorage.setItem('collection_id', banner.collection_id);
                                                bannerUrl = `/offers/?${banner.name}`;
                                            } else if (banner.section_id) {
                                                localStorage.setItem('section_id', banner.section_id);
                                                bannerUrl = `/offers/?${banner.name}`;
                                            }
                                            return `
                                                <div class="swiper-slide banner-slide">
                                                    <a href="${bannerUrl}">
                                                        <img src="${banner.cloud_image}" alt="${banner.name}">
                                                    </a>
                                                </div>
                                            `;
                                        }).join("")}
                                    </div>
                                <div class="swiper-pagination-wrap">
                                    <div class="swiper-pagination"></div>
                                </div>
                            </div>
                        </div>
                    `;
                    setTimeout(() => {
                        new Swiper(".banners .bricks-swiper-container", {
                            slidesPerView: 1,
                            spaceBetween: 6,
                            loop: false,
                            speed: 1500,
                            centeredSlides: true,
                            autoplay: {
                                delay: 3500,
                                disableOnInteraction: false,
                            },
                            pagination: {
                                el: ".swiper-pagination",
                                clickable: true,
                            },
                        });
                    }, 100);
                }
            }

            function updateSubCategories(sub_categories) {
                const subCatContainer = document.querySelector(".nearest-sub_categories");
                if (!subCatContainer) return;
                subCatContainer.innerHTML = '';
                if (!Array.isArray(sub_categories) || sub_categories.length === 0) {
                    let category_api_subcategories = document.querySelector(".category_api_subcategories");
                    if (category_api_subcategories) category_api_subcategories.remove();
                    return;
                } else {
                    const site_url = window.location.origin;
                    const isLargeScreen = window.innerWidth >= 768;
                    const useSlider = isLargeScreen && sub_categories.length > 6;

                    if (useSlider) {
                        subCatContainer.innerHTML = `
                        <div class="brxe-carousel home-cat-slider">
                            <div class="swiper">
                                <div class="swiper-wrapper">
                                    ${sub_categories.map(sub_category => {
                                        const isActive = sub_category.id == selectedSubCategoryId ? "active" : "";
                                        return `
                                            <div class="swiper-slide filter-category subcategory ${isActive}" subcat_id="${sub_category.id}">
                                                <div class="cat-image" style="background-image: url(${sub_category.image ? `${sub_category.image}` : `${site_url}/wp-content/plugins/TabibGroup-core/assets/images/tabib-plcholder.png`});"></div>
                                                <p>${sub_category.label}</p>
                                            </div>
                                        `;
                                    }).join("")}
                                </div>
                                <div class="swiper-pagination-wrap">
                                    <div class="swiper-pagination"></div>
                                </div>
                            </div>
                            <div class="swiper-button swiper-button-prev"></div>
                            <div class="swiper-button swiper-button-next"></div>
                        </div>
                        `;

                        // Initialize Swiper
                        new Swiper(".home-cat-slider .swiper", {
                            slidesPerView: 6,
                            spaceBetween: 20,
                            loop: false,
                            navigation: {
                                nextEl: ".swiper-button-next",
                                prevEl: ".swiper-button-prev",
                            },
                        });

                    } else {
                        // Static Grid fallback
                        subCatContainer.innerHTML = `
                            <div id="brxe-ktsxgk">
                                ${sub_categories.map(sub_category => {
                                    const isActive = sub_category.id == selectedSubCategoryId ? "active" : "";
                                    return `
                                        <div class="filter-category subcategory ${isActive}" subcat_id="${sub_category.id}">
                                            <div class="cat-image" style="background-image: url(${sub_category.image ? `${sub_category.image}` : `${site_url}/wp-content/plugins/TabibGroup-core/assets/images/tabib-plcholder.png`});"></div>
                                            <p>${sub_category.label}</p>
                                        </div>
                                    `;
                                }).join("")}
                            </div>
                        `;
                    }
                    if (!hasLoadedSubcatFromURL) {
                        const pathParts = window.location.pathname.split("/").filter(Boolean);
                        const mainCategorySlug = pathParts[pathParts.length - 1] || null;
                        const subcatNameFromUrl = pathParts.length > 2 ? pathParts[pathParts.length - 2] : null;
                        if (subcatNameFromUrl) {
                            hasLoadedSubcatFromURL = true;
                            // Mark active subcategory button
                            document.querySelectorAll(".filter-category").forEach(el => {
                                const subcatSlug = el.textContent.trim().replace(/\s+/g, "-");
                                if (decodeURIComponent(subcatNameFromUrl) === subcatSlug) {
                                    selectedSubCategoryId = el.getAttribute("subcat_id");
                                    el.classList.add("active");
                                } else {
                                    el.classList.remove("active");
                                }
                            });
                            // Fetch with the selected subcategory
                            if (selectedSubCategoryId) {
                                fetchCatPageData(1, {
                                    updateDoctors: false,
                                    updateSubCats: true,
                                    subCategoryId: selectedSubCategoryId,
                                });
                            }
                        }
                    }
                    subCatContainer.querySelectorAll('.filter-category').forEach(el => {
                        el.addEventListener('click', () => {
                            const selectedId = el.getAttribute('subcat_id');
                            selectedSubCategoryId = selectedId;

                            document.querySelector('#brxe-catName')?.classList.remove('scrollToPoint');
                            document.querySelector('.category_api_subcategories')?.classList.add('scrollToPoint');

                            // Update active class
                            subCatContainer.querySelectorAll('.filter-category').forEach(e => e.classList.remove('active'));
                            el.classList.add('active');

                            const pathParts = window.location.pathname.split("/").filter(Boolean);
                            const mainCategorySlug = pathParts[pathParts.length - 1];
                            const subcatSlug = el.textContent.trim().replace(/\s+/g, "-");
                            // Update URL without reloading
                            const newUrl = `/offer-category/${encodeURIComponent(subcatSlug)}/${mainCategorySlug}`;
                            history.pushState({}, "", newUrl);

                            // Fetch API using subcatId
                            fetchCatPageData(1, {
                                updateDoctors: false,
                                updateSubCats: true,
                                subCategoryId: selectedSubCategoryId,
                            });
                        });
                    });
                }

            }

            function updateNearestDoctors(doctors) {
                const doctorsMainContainer = document.querySelector(".nearest-doctors");
                const doctorsContainer = document.querySelector(".nearest-doctors .renderDoctors");
                if (!doctorsContainer) return;                
                doctorsContainer.innerHTML = "";
                if (!Array.isArray(doctors) || doctors.length === 0) {
                    let category_api_doctors = document.querySelector(".category_api_doctors");
                    if (category_api_doctors) category_api_doctors.remove();
                    return;
                } else {
                    // Get base url
                    const site_url = window.location.origin;
                    if (doctors.length > 6) {
                        // Get base url
                        const site_url = window.location.origin;
                        doctorsContainer.innerHTML = `
                            <div class="bricks-swiper-container">
                                <div class="swiper-wrapper">
                                    ${doctors.map(doctor => {
                                        const clinicSlug = doctor.name.replace(/\s+/g, '-').toLowerCase();
                                        const clinicUrl = `/clinic-listings/${encodeURIComponent(clinicSlug)}/`;
                                        return `
                                            <div class="swiper-slide">
                                                <a href="${clinicUrl}" data-clinic-id="${doctor.id}">
                                                    <img src="${site_url}/${doctor.image}" 
                                                        onerror="this.onerror=null; this.src='/wp-content/plugins/TabibGroup-core/assets/images/tabib-plcholder.png';" 
                                                        alt="${doctor.name}">
                                                </a>
                                            </div>
                                        `;
                                    }).join("")}
                                </div>
                            </div>
                        `;
                        setTimeout(() => {
                            new Swiper(".nearest-doctors .renderDoctors .bricks-swiper-container", {
                                slidesPerView: 2.5,
                                spaceBetween: 12,
                                loop: true,
                                speed: 1000,
                                autoplay: {
                                    delay: 2500,
                                    disableOnInteraction: false,
                                },
                                breakpoints: {
                                    768: { slidesPerView: 3.5 },
                                    1024: { slidesPerView: 4 },
                                    1280: { slidesPerView: 6 },
                                }
                            });
                        }, 100);
                    } else if (doctorsMainContainer) {
                        doctorsMainContainer.classList.add("withoutSlider");
                        doctorsContainer.innerHTML = doctors.map(doctor => {
                            const clinicSlug = doctor.name.replace(/\s+/g, '-').toLowerCase();
                            const clinicUrl = `/clinic-listings/${encodeURIComponent(clinicSlug)}/`;

                            return `
                                <div class="doctor-item">
                                    <a href="${clinicUrl}" data-clinic-id="${doctor.id}">
                                        <img src="${site_url}/${doctor.image}" 
                                            onerror="this.onerror=null; this.src='/wp-content/plugins/TabibGroup-core/assets/images/tabib-plcholder.png';" 
                                            alt="${doctor.name}">
                                    </a>
                                </div>
                            `;
                        }).join("");
                    }
                }
            }

            function renderOffers(offers) {
                const offersContainer = document.querySelector(".offers-container");
                if (!offersContainer) return;
                offersContainer.innerHTML = '';
                if (!Array.isArray(offers) || offers.length === 0) {
                    let category_api_offers = document.querySelector(".category_api_offers");
                    if (category_api_offers) category_api_offers.remove();
                    let category_api_pagination = document.querySelector(".category_api_pagination");
                    if (category_api_pagination) category_api_pagination.remove();
                    return;
                } else {
                    offersContainer.innerHTML = `
                        <div id="brxe-dcezll" class="brxe-container brx-grid" style="padding: 0;"> 
                            ${offers.map(offer => {
                                // Get base url
                                const site_url = window.location.origin;
                                // Calculate discount percentage
                                let discountPercentage = offer.old_price > offer.new_price 
                                    ? Math.round(((offer.old_price - offer.new_price) / offer.old_price) * 100) 
                                    : 0;
                                const offerUrl = `/offers/${offer.id}`;
                                const clinicSlug = offer.doctor_name.replace(/\s+/g, '-').toLowerCase();
                                const clinicUrl = `/clinic-listings/${encodeURIComponent(clinicSlug)}/`;
                                return `
                                    <div class="brxe-fwicvy brxe-container offer-item">
                                        <div class="brxe-bdbprb brxe-block">
                                            <p class="brxe-xtldrz brxe-text-basic"> رقم العرض: <span class="sku"> ${offer.id} </span></p>
                                            <a href="${offerUrl}" style="width: 100%;"><img width="400" height="300" src="${offer.new_offer_images[0]?.image || offer.offer_images[0]?.cloud_image}" class="brxe-dhrodg brxe-image css-filter size-full" alt="${offer.title}"></a>
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
                                                    ${discountPercentage > 0 ? `<div class="brxe-fnzfzv brxe-shortcode"><span class="badge onsale" dir="ltr">-${discountPercentage}%</span></div>` : ''}
                                                </div>
                                            </div>
                                            <div class="brxe-lmslci brxe-divider horizontal">
                                                <div class="line"></div>
                                            </div>
                                            ${(offer.bold_title || offer.light_title) ? `
                                                <a href="${offerUrl}">
                                                    <h3 class="brxe-opulje brxe-post-title">${offer.bold_title ?? ''} ${offer.light_title ?? ''}</h3>                                                   
                                                </a>
                                            ` : ''}
                                            <div class="brxe-ygksdl brxe-block">
                                                <ul class="brxe-enxkja brxe-social-icons">
                                                    <li class="repeater-item lg no-link">
                                                        <a href="${clinicUrl}"><p>${offer.doctor_name}</p></a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="brxe-ygksdl brxe-block mb-24">
                                                <ul class="brxe-enxkja brxe-social-icons">
                                                    <li class="repeater-item lg no-link clinic-loc">
                                                        <img src="${site_url}/wp-content/plugins/TabibGroup-core/assets/images/location-icon.svg" alt="location">
                                                        <p>${offer.clinic_location}</p>
                                                    </li>
                                                    <li class="repeater-item no-link">
                                                        <i class="ti-layout-line-solid icon"></i>
                                                    </li>
                                                </ul>
                                                <ul class="brxe-bsxmbc brxe-social-icons">
                                                    <li class="repeater-item no-link clinic-rating">
                                                        <img src="${site_url}/wp-content/plugins/TabibGroup-core/assets/images/rating-star.svg" alt="rating">
                                                        ${offer.avg_rating != "not rated" ? `<span>${offer.avg_rating}</span>` : ''}
                                                    </li>
                                                </ul>
                                                <ul class="brxe-gnowdx brxe-social-icons">
                                                    <li class="repeater-item no-link clinic-claims">
                                                        <p> (<span class="claims">${offer.claimed_count}</span> تعليق) </p>
                                                    </li>
                                                </ul>
                                            </div>
                                            <a class="brxe-sdwdzz brxe-button bricks-button xl bricks-background-primary circle" href="${offerUrl}">احجز الان</a>
                                        </div>
                                    </div>
                                `;
                            }).join("")}
                        </div>
                    `;
                }
            }

            function updatePagination(totalPages, currentPage) {
                const container = document.getElementById("clinic-pagination-container");
                if (!container) return;

                let html = `<div class="tg-listing-pagination">`;
                const prevDisabled = currentPage <= 1 ? "disabled" : "";
                const prevPage = currentPage > 1 ? currentPage - 1 : 1;
                html += `<button onclick="handlePaginationClick(${prevPage})" class="page-numbers prev ${prevDisabled}" data-page="${prevPage}">‹ خلف</button>`;

                let start = Math.max(1, currentPage - 1);
                let end = Math.min(totalPages, currentPage + 1);

                if (start > 1) {
                    html += `<button onclick="handlePaginationClick(1)" class="page-numbers" data-page="1">1</button>`;
                    if (start > 2) html += `<span class="dots">...</span>`;
                }

                for (let i = start; i <= end; i++) {
                    const activeClass = i === currentPage ? "current" : "";
                    html += `<button onclick="handlePaginationClick(${i})" class="page-numbers ${activeClass}" data-page="${i}">${i}</button>`;
                }

                if (end < totalPages - 1) {
                    html += `<span class="dots">...</span>`;
                }

                if (end < totalPages) {
                    html += `<button onclick="handlePaginationClick(${totalPages})" class="page-numbers" data-page="${totalPages}">${totalPages}</button>`;
                }

                html += `<div class="pagination-text">صفحة ${currentPage} ل ${totalPages}</div>`;

                const nextDisabled = currentPage >= totalPages ? "disabled" : "";
                const nextPage = currentPage < totalPages ? currentPage + 1 : totalPages;
                html += `<button onclick="handlePaginationClick(${nextPage})" class="page-numbers next ${nextDisabled}" data-page="${nextPage}">التالي ›</button>`;

                html += `</div>`;
                container.innerHTML = html;
            }

            async function fetchCatPageData(page = 1, options = { updateDoctors: true, updateSubCats: true, subCategoryId: "" }) {
                GlobalLoader.show();
                try {
                    const apiUrl = buildApiUrl(page, options.subCategoryId || selectedSubCategoryId);
                    const response = await fetch(apiUrl);
                    const result = await response.json();

                    if (result.status === "success") {
                        if (options.updateSubCats) updateSubCategories(result.sub_categories || []);
                        // if (options.updateDoctors) updateNearestDoctors(result.nearest_doctors || []);
                        renderOffers(result.data || []);
                        updatePagination(result["total_pages="] || 1, page);
                        updateBanners(result.banners || []);
                    }

                    GlobalLoader.scrollTo(".scrollToPoint");
                } catch (error) {
                    console.error("Error fetching offers:", error);
                } finally {
                    GlobalLoader.hide();
                }
            }

            function handlePaginationClick(page = 1) {
                fetchCatPageData(page, { updateDoctors: false, updateSubCats: false });
            }
            window.handlePaginationClick = handlePaginationClick;

            // On initial load
            fetchCatPageData();

            // Watch for location change in localStorage
            window.addEventListener("storage", function (event) {
                if (event.key === "user_location" || event.key === "user_current_location") {
                    fetchCatPageData(1);
                }
            });
        }

    })();

});

document.addEventListener("DOMContentLoaded", () => {
    const subCatContainer = document.querySelector(".nearest-sub_categories");
    if (!subCatContainer) return;
    const pathParts = window.location.pathname.split("/").filter(Boolean);
    if (pathParts.length < 2) return;
    const slugFromUrl = decodeURIComponent(pathParts[pathParts.length - 1]);
    const readableName = slugFromUrl.replace(/-/g, " ").trim();
    const observer = new MutationObserver(() => {
        const items = subCatContainer.querySelectorAll(".filter-category");
        if (items.length === 0) return;
        for (let el of items) {
            const pText = el.querySelector("p")?.textContent.trim() || "";
            if (pText === readableName) {
                el.classList.add("active");
                observer.disconnect();
                return;
            }
        }
    });
    observer.observe(subCatContainer, { childList: true, subtree: true });
});
