document.addEventListener("DOMContentLoaded", function () {
    function createSlug(name) {
        return name
            .toString()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\u0600-\u06FFa-zA-Z0-9\-]/g, '')
            .replace(/-+/g, '-')
            .toLowerCase();
    }

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

    async function fetchHomePageData() {
        const base_url = homeData.baseUrl;
        let { city, lat, long } = getUserLocation();
        let apiUrl = `${base_url}/api/endpoints/mobile/v3/home.json?lang=en&city=${encodeURIComponent(city)}&latitude=${lat}&longitude=${long}`;

        try {
            let response = await fetch(apiUrl);
            let data = await response.json();

            if (data.status === "success") {
                updateHomepageContent(data.data);
            }
        } catch (error) {
            console.error("Error fetching homepage data:", error);
        }
    }

    function updateHomepageContent(data) {
        updateSlider(data.banners);
        updateCategories(data.offer_categories);
        updateSpecialist(data.specialist_group);
        updateCollections(data.collections);
        updateSections(data.sections);
        updateOffers(data.home_offers);
        // updateNearestDoctors(data.nearest_doctors);
    }

    function updateSlider(banners) {
        let bannersContainer = document.querySelector(".banners");
        if (!bannersContainer) return;
        bannersContainer.innerHTML = "";
        if (!Array.isArray(banners) || banners.length === 0) {
            bannersContainer.innerHTML = `
                <div class="banner-slide placeholder-slide">
                    <img src="/wp-content/plugins/TabibGroup-core/assets/images/tabib-plcholder.png" alt="tabib placeholder">
                </div>
            `;
        } else {
            bannersContainer.innerHTML = `
                <div class="brxe-carousel">
                    <div class="bricks-swiper-container">
                            <div class="swiper-wrapper">
                                ${banners.map(banner => {
                                    const isTrue = val => String(val) === "true";
                                    const safeName = createSlug(banner.name);

                                    if (banner.offer_id && isTrue(banner.has_offer)) {
                                        return `
                                            <div class="swiper-slide banner-slide">
                                                <a href="/offers/${banner.offer_id}">
                                                    <img src="${banner.cloud_image}" alt="${banner.name}">
                                                </a>
                                            </div>
                                        `;
                                    }
                                    if (banner.website_url && isTrue(banner.has_website_url)) {
                                        return `
                                            <div class="swiper-slide banner-slide">
                                                <a href="${banner.website_url}">
                                                    <img src="${banner.cloud_image}" alt="${banner.name}">
                                                </a>
                                            </div>
                                        `;
                                    }
                                    if (banner.doctor_id && isTrue(banner.has_doctor)) {
                                        return `
                                            <div class="swiper-slide banner-slide">
                                                <a href="/offers/doctor/${banner.doctor_id}/${safeName}" title="${banner.name}" id="${banner.doctor_id}" 
                                                data-type="doctor_id" class="dynamicNavigate">
                                                    <img src="${banner.cloud_image}" alt="${banner.name}">
                                                </a>
                                            </div>
                                        `;
                                    }
                                    if (banner.collection_id) {
                                        return `
                                            <div class="swiper-slide banner-slide">
                                                <a href="/offers/collection/${banner.collection_id}/${safeName}" title="${banner.name}" id="${banner.collection_id}" 
                                                data-type="collection_id" class="dynamicNavigate">
                                                    <img src="${banner.cloud_image}" alt="${banner.name}">
                                                </a>
                                            </div>
                                        `;
                                    }
                                    if (banner.section_id) {
                                        return `
                                            <div class="swiper-slide banner-slide">
                                                <a href="/offers/section/${banner.section_id}/${safeName}" title="${banner.name}" id="${banner.section_id}" 
                                                data-type="section_id" class="dynamicNavigate">
                                                    <img src="${banner.cloud_image}" alt="${banner.name}">
                                                </a>
                                            </div>
                                        `;
                                    }
                                    return "";
                                }).join("")}
                            </div>
                        <div class="swiper-pagination-wrap">
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            `;
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
        }
    }

    function updateCategories(categories) {
        // Get base url
        const site_url = window.location.origin;
        let activeCategories = categories.filter(category => category.is_active === true);
        if (activeCategories.length === 0) return;
        const categoriesContainer = document.getElementById("categories-wrapper");
        if (!categoriesContainer) return;
        const isMobile = window.innerWidth <= 768;
        const handleClickStorage = (id) => `localStorage.setItem('category_id', '${id}')`;
        if (isMobile) {
            const itemsPerColumn = 2;
            const itemsPerPage = itemsPerColumn * 4;
            // Render mobile grid layout
            categoriesContainer.innerHTML = `
                <div class="categories-mobile-wrapper">
                    <div id="cat-scroll" class="categories-scroll-area">
                        ${activeCategories.map(category => {
                            const nameSlug = category.name.replace(/\s+/g, '-');
                            return `
                                <a href="/offer-category/${nameSlug}" class="category-box" onclick="${handleClickStorage(category.id)}">
                                    <img 
                                        src="${category.image}"
                                        alt="${category.name}" 
                                        onerror="this.onerror=null;this.src='${site_url}/wp-content/plugins/TabibGroup-core/assets/images/tabib-plcholder.png';" 
                                    />
                                    <span>${category.name}</span>
                                </a>
                            `;
                        }).join("")}
                    </div>
                    <div class="category-dots" id="cat-dots"></div>
                </div>
            `;
            const scrollArea = document.getElementById('cat-scroll');
            const dotsContainer = document.getElementById('cat-dots');

            // Wait until DOM fully renders
            setTimeout(() => {
                const totalPages = Math.ceil(activeCategories.length / itemsPerPage);
                if (scrollArea.scrollWidth > scrollArea.clientWidth && totalPages > 1) {
                    for (let i = 0; i < totalPages; i++) {
                        const dot = document.createElement("span");
                        dot.className = "dot" + (i === 0 ? " active" : "");
                        dotsContainer.appendChild(dot);
                    }

                    scrollArea.addEventListener("scroll", () => {
                        const pageWidth = scrollArea.clientWidth;
                        const scrollLeft = scrollArea.scrollLeft;
                        // Normalize scrollLeft for RTL
                        const isRTL = getComputedStyle(scrollArea).direction === 'rtl';
                        const normalizedScroll = isRTL
                            ? scrollArea.scrollWidth - scrollArea.clientWidth - scrollLeft
                            : scrollLeft;
                        const pageIndex = Math.round(normalizedScroll / pageWidth);
                        dotsContainer.querySelectorAll(".dot").forEach((d, i) =>
                            d.classList.toggle("active", i === pageIndex)
                        );
                    });
                }
            }, 100);
        } else {
            // Render Swiper for desktop
            categoriesContainer.innerHTML = `
                <div class="brxe-carousel home-cat-slider sop-slider">
                    <div class="swiper">
                        <div class="swiper-wrapper">
                            ${activeCategories.map(category => {
                                const nameSlug = category.name.replace(/\s+/g, '-');
                                return `
                                    <div class="swiper-slide">
                                        <a href="/offer-category/${nameSlug}" class="slider-content" onclick="${handleClickStorage(category.id)}">
                                            <img src="${category.image}" alt="${category.name}" 
                                                onerror="this.onerror=null;this.src='${site_url}/wp-content/plugins/TabibGroup-core/assets/images/tabib-plcholder.png';" 
                                                class="attachment-thumbnail size-thumbnail" decoding="async">
                                            <p>${category.name}</p>
                                        </a>
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
            new Swiper(".home-cat-slider .swiper", {
                slidesPerView: 2,
                spaceBetween: 12,
                loop: false,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
                breakpoints: {
                    768: { slidesPerView: 3 },
                    1024: { slidesPerView: 4 },
                    1280: { slidesPerView: 6 },
                }
            });
        }
    }

    function updateSpecialist(specialist_group) {
        let specialistContainer = document.querySelector(".specialist_group");
        if (!specialistContainer) return;
        if (specialist_group === null || specialist_group.status !== "active") return;
        let activeSpecialists = specialist_group.specialists.filter(specialist => specialist.status === "active");
        if (activeSpecialists.length === 0) return;
        if (activeSpecialists.length > 4) {
            specialistContainer.innerHTML = `
                <div id="brxe-specialists" class="brxe-container">
                    <h2 id="brxe-title" class="brxe-heading">${specialist_group.title}</h2>
                    <p><a href="/%d8%b9%d8%b1%d9%88%d8%b6-%d8%b7%d8%a8%d9%8a%d8%a8/">إظهار الكل</a></p>
                </div>
                <div class="brxe-carousel sop-slider">
                    <div class="bricks-swiper-container specialist_slider">
                        <div class="swiper-wrapper">
                            ${activeSpecialists.map(specialist => {
                                let safeSlug = createSlug(specialist.name);
                                return `
                                    <div class="swiper-slide">
                                        <a href="/offers/specialist/${specialist.id}/${safeSlug}">
                                            <div class="specialist-card">
                                                <img src="${specialist.profile_pic}" alt="${specialist.name}">
                                                <h3>${specialist.name}</h3>
                                                <h4>${specialist.clinic_name}</h4>
                                            </div>
                                        </a>
                                    </div>
                                `;
                            }).join("")}
                        </div>
                        <div class="swiper-pagination-wrap">
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                    <div class="swiper-button swiper-button-prev bricks-swiper-button-prev"></div>
                    <div class="swiper-button swiper-button-next bricks-swiper-button-next"></div>
                </div>
            `;
            setTimeout(() => {
                new Swiper(".specialist_group .specialist_slider", {
                    slidesPerView: 1.5,
                    spaceBetween: 12,
                    loop: false,
                    pagination: {
                        el: ".swiper-pagination",
                        clickable: true,
                    },
                    navigation: {
                        nextEl: ".bricks-swiper-button-next",
                        prevEl: ".bricks-swiper-button-prev",
                    },
                    breakpoints: {
                        768: { slidesPerView: 2.5, spaceBetween: 12 },
                        1024: { slidesPerView: 4, spaceBetween: 16 },
                    }
                });
            }, 100);
        } else {
            specialistContainer.innerHTML = `
                <div id="brxe-specialists" class="brxe-container">
                    <h2 id="brxe-title" class="brxe-heading">${specialist_group.title}</h2>
                    <p><a href="/%d8%b9%d8%b1%d9%88%d8%b6-%d8%b7%d8%a8%d9%8a%d8%a8/">إظهار الكل</a></p>
                </div>
                <div class="specialists_items">
                    ${activeSpecialists.map(specialist => {
                        let safeSlug = createSlug(specialist.name);
                        return `
                            <a href="/offers/specialist/${specialist.id}/${safeSlug}">
                                <div class="specialist-card">
                                    <img src="${specialist.profile_pic}" alt="${specialist.name}">
                                    <h3>${specialist.name}</h3>
                                    <h4>${specialist.clinic_name}</h4>
                                </div>
                            </a>
                        `;
                    }).join("")}
                </div>
            `;
        }
    }

    function updateCollections(collections) {
        let collectionsContainer = document.querySelector(".home-collections");
        if (!collectionsContainer) return;
    
        let hasShownFirstVisibleGrid = false;
    
        const filteredCollections = collections.filter((collection, index) => {
            const isGridTile = collection.display_type === "Grid Tile";
            const isFlatTile = collection.display_type === "Flat Tile";
    
            if (isFlatTile && collection.visibility === "true") {
                return true;
            }
    
            if (isGridTile) {
                if (collection.visibility === "true") {
                    hasShownFirstVisibleGrid = true;
                    return true;
                } else if (hasShownFirstVisibleGrid) {
                    return true;
                }
            }
    
            return false;
        });
    
        if (filteredCollections.length === 0) return;
    
        collectionsContainer.innerHTML = filteredCollections.map(collection => {
            let formattedClass = collection.display_type.replace(/\s+/g, '_').toLowerCase();
            let layoutClass = formattedClass.includes("grid") ? "grid_tile" : "flat_tile";
            let safeSlug = createSlug(collection.name);
    
            return `
                <a href="/offers/collection/${collection.id}/${safeSlug}" class="collection-item ${layoutClass}">
                    <img src="${collection.tile_image}" alt="${collection.name}" onerror="this.parentNode.remove();" loading="lazy">
                </a>
            `;
        }).join("");
    }    

    function updateSections(sections) {
        let sectionsContainer = document.querySelector(".home-sections");
        if (!sectionsContainer) return;
        let activeSections = sections.filter(section => section.is_active === "true");
        if (activeSections.length === 0) return;
        if (activeSections.length > 0) {
            sectionsContainer.innerHTML = `
            <div class="tg-api-section">
                ${activeSections.map(section => {
                    let safeSlug = createSlug(section.label);
                    return `
                        <a href="/offers/section/${section.id}/${safeSlug}" class="brxe-button bricks-button bricks-background-primary circle bricks-color-light">
                            ${section.label}
                        </a>
                    `;
                }).join("")}
            </div>`;
        }
    }
    
    function updateOffers(offers) {
        let offersContainer = document.querySelector(".home-offers");
        if (!offersContainer) return;
        offersContainer.innerHTML = `
            <div id="brxe-kspgjm" data-script-id="kspgjm" class="brxe-carousel sop-slider">
                <div class="bricks-swiper-container">
                    <div class="swiper-wrapper">
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
                                <div class="repeater-item swiper-slide brxe-fwicvy">
                                    <p class="brxe-xtldrz brxe-text-basic"> رقم العرض: <span class="sku"> ${offer.id} </span></p>
                                    <img src="${offer.new_offer_images[0]?.image || offer.offer_images[0]?.cloud_image}" class="image" alt="${offer.title}">
                                    ${offer.is_offer_accept_installment ? `
                                    <div class="payment-options" style="position: relative;">
                                        <img width="34" height="34" src="${site_url}/wp-content/uploads/image-2.png" class="brxe-ifgfhj brxe-image css-filter size-full" alt="">
                                        <img width="34" height="34" src="${site_url}/wp-content/uploads/image-3.png" class="brxe-uqlgkb brxe-image css-filter size-full" alt="">
                                    </div>
                                    ` : ''}
                                    <div class="content-wrapper middle center">
                                        <div class="dynamic" data-field-id="goglbc">
                                            ${discountPercentage > 0 ? `<span class="badge onsale">-${discountPercentage}%</span>` : ''}
                                        </div>                                        
                                        <a href="${clinicUrl}"><p class="dynamic" data-field-id="bgrrwe">${offer.doctor_name}</p></a>
                                        ${(offer.bold_title || offer.light_title || offer.title) ? `
                                        <h3 class="dynamic" data-field-id="oujfpi">
                                            <a href="/offers/${offer.id}/">
                                            ${offer.bold_title ?? offer.title}
                                            ${offer.light_title ? ` ${offer.light_title}` : ''}
                                            </a>
                                        </h3>
                                        ` : ''}
                                        <p class="dynamic" data-field-id="tpxxwh">
                                            ${offer.old_price > offer.new_price ? `
                                              <del aria-hidden="true">
                                                <span class="woocommerce-Price-amount amount">${offer.old_price}
                                                    <span class="woocommerce-Price-currencySymbol">ريال</span>
                                                </span>
                                              </del>
                                          ` : ''}
                                            <ins aria-hidden="true">
                                                <span class="woocommerce-Price-amount amount">${offer.new_price}
                                                    <span class="woocommerce-Price-currencySymbol">ريال</span>
                                                </span>
                                            </ins>
                                        </p>
                                    </div>
                                </div>
                            `;
                        }).join("")}
                    </div>
                    <div class="swiper-pagination-wrap">
                        <div class="swiper-pagination"></div>
                    </div>
                </div>
                <div class="swiper-button swiper-button-prev bricks-swiper-button-prev"></div>
                <div class="swiper-button swiper-button-next bricks-swiper-button-next"></div>
            </div>
        `;
        setTimeout(() => {
            new Swiper(".home-offers .bricks-swiper-container", {
                slidesPerView: 1,
                spaceBetween: 12,
                autoHeight: true,
                loop: false,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
                breakpoints: {
                    768: { slidesPerView: 3, spaceBetween: 12 },
                    1024: { slidesPerView: 4, spaceBetween: 16 },
                }
            });
        }, 100);
    }

    function updateNearestDoctors(doctors) {
        let doctorsContainer = document.querySelector(".nearest-doctors");
        if (!doctorsContainer) return;
        doctorsContainer.innerHTML = "";  
        const site_url = window.location.origin;
        if (doctors.length > 6) {
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
                new Swiper(".nearest-doctors .bricks-swiper-container", {
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
            }, 0);
        } else {
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
    
    fetchHomePageData();

    window.addEventListener("storage", function (event) {
        if (event.key === "user_location" || event.key === "user_current_location") {
            fetchHomePageData();
        }
    });

    // home-offer-sliders js code
    const wrapper = document.getElementById('home-offer-sliders');
    if (!wrapper || wrapper.dataset.loaded === "true") return;
    let { city } = getUserLocation();
    fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
        action: 'get_home_offer_sliders',
        city: city
        })
    })
    .then(res => res.json())
    .then(data => {
    if (data.success && data.data) {
        wrapper.innerHTML = data.data;
        wrapper.dataset.loaded = "true";
        document.querySelectorAll('.offer-swiper').forEach(swiperEl => {
            const slideCount = swiperEl.querySelectorAll('.swiper-slide').length;
            const screenWidth = window.innerWidth;
            let slidesVisible = 1;
            if (screenWidth >= 1200) {
                slidesVisible = 3;
            } else if (screenWidth >= 768) {
                slidesVisible = 2;
            }
            const swiperBlock = swiperEl.closest('.offer-slider-block .sop-slider');
            if (slideCount <= slidesVisible) {
                if (swiperBlock) {
                swiperBlock.classList.add('no-swiper');
                // Remove navigation buttons & pagination
                const nextBtn = swiperBlock.querySelector('.swiper-button-next');
                const prevBtn = swiperBlock.querySelector('.swiper-button-prev');
                if (nextBtn) nextBtn.remove();
                if (prevBtn) prevBtn.remove();
                const paginationEl = swiperEl.querySelector('.swiper-pagination');
                    if (paginationEl) paginationEl.remove();
                }
                // Convert to flex layout (non-swiper)
                const wrapper = swiperEl.querySelector('.swiper-wrapper');
                wrapper.classList.remove('swiper-wrapper');
                wrapper.classList.add('simple-flex-wrapper');
                swiperEl.querySelectorAll('.swiper-slide').forEach(slide => {
                slide.classList.remove('swiper-slide');
                slide.classList.add('simple-flex-item');
                });
                return;
            }

            // Init Swiper
            new Swiper(swiperEl, {
                slidesPerView: 1,
                spaceBetween: 0,
                autoHeight: true,
                loop: false,
                pagination: {
                el: swiperEl.querySelector('.swiper-pagination'),
                clickable: true,
                },
                navigation: {
                nextEl: swiperBlock.querySelector('.swiper-button-next'),
                prevEl: swiperBlock.querySelector('.swiper-button-prev'),
                },
                breakpoints: {
                768: { slidesPerView: 2 },
                1200: { slidesPerView: 3 },
                }
            });
        });
    } else {
        wrapper.innerHTML = '<p class="offers-no-result">لم يتم العثور على عروض.</p>';
    }
    })
    .catch(() => {
        wrapper.innerHTML = '<p class="offers-no-result">فشل تحميل العروض.</p>';
    });

});
