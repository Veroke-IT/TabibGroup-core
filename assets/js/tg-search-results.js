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

let clinicsRendered = false;
let offersRendered = false;

function initSearchResults(page = 1) {

  clinicsRendered = false;
  offersRendered = false;

  let { city, city_id, lat, long } = getUserLocation();
  const baseURL = window.location.origin;
  const params = new URLSearchParams(window.location.search);
  const searchTerm = params.get("search") || "";

  // --- API 1: Doctors ---
  const doctorApiUrl = new URL(`${baseURL}/api/endpoints/mobile/v2/doctors/nearest_doctor.json`);
  doctorApiUrl.searchParams.set("lang", "ar");
  doctorApiUrl.searchParams.set("page", 1);
  doctorApiUrl.searchParams.set("city", city);
  doctorApiUrl.searchParams.set("city_id", city_id);
  doctorApiUrl.searchParams.set("latitude", lat);
  doctorApiUrl.searchParams.set("longitude", long);
  doctorApiUrl.searchParams.set("offer_category_id", '');
  doctorApiUrl.searchParams.set("sub_category_id", '');
  doctorApiUrl.searchParams.set("service", '');
  doctorApiUrl.searchParams.set("doctor_name", searchTerm);
  doctorApiUrl.searchParams.set("date", '');

  fetch(doctorApiUrl)
    .then(res => res.json())
    .then(data => {
      const doctors = data.data.nearest_doctors || [];
      renderClinics(doctors);
      renderClinicHeading(doctors.length || '');      
      clinicsRendered = true;
      checkAndRenderNoResultsMessage();
    })
    .catch(err => console.error("Doctors API error", err));

  // --- API 2: Offers ---
  const offerApiUrl = new URL(`${baseURL}/api/v7/offers/search.json`);
  offerApiUrl.searchParams.set("lang", "ar");
  offerApiUrl.searchParams.set("title", searchTerm);
  offerApiUrl.searchParams.set("doctor_id", '');
  offerApiUrl.searchParams.set("city", city);
  offerApiUrl.searchParams.set("latitude", lat);
  offerApiUrl.searchParams.set("longitude", long);

  fetch(offerApiUrl)
    .then(res => res.json())
    .then(data => {
      const offers = Array.isArray(data.data) ? data.data : [];
      renderOffers(offers);
      renderOfferHeading(offers.length || '');
      renderPagination(data.total_pages || 0, page);
      offersRendered = true;
      checkAndRenderNoResultsMessage();
    })
    .catch(err => console.error("Offers API error", err));

}

function renderClinicHeading(count) {
  const heading = document.querySelector('.clinics-results-area .headings-wrap');
  if (heading) {
    heading.innerHTML = `<h1 id='rxe-fhrepa' class='brxe-heading'>العیادات</h1>
    <span> تم العثور على <span class='clinics-result-count'>${count}</span> نتیجة </span>`;
  }
}

function renderOfferHeading(count) {
  const heading = document.querySelector('.offers-results-area .headings-wrap');
  if (heading) {
    heading.innerHTML = `<h2 id="brxe-tcxnan" class="brxe-heading">العروض</h2>
    <span> تم العثور على <span class="offers-result-count">${count}</span> نتیجة </span>`;
  }
}

function renderClinics(clinics) {
  let { lat, long } = getUserLocation();
  const container = document.getElementById("clinic-results");
  if (!clinics.length) {
    if (container) container.innerHTML = "";
    document.querySelector(".clinics-results-area")?.style.setProperty("display", "none");
    return;
  }
  document.querySelector(".clinics-results-area")?.style.setProperty("margin", "3% 0");

  // Check if we need slider
  const isMobile = window.innerWidth <= 768;
  const enableSlider = isMobile ? clinics.length > 1 : clinics.length > 3;

  const cardsHtml = clinics.map(clinic => {
    const domainUrl = window.location.origin;
    let roundedValue = Math.round(clinic.span * 100) / 100;
    const clinicSlug = clinic.name.replace(/\s+/g, '-').toLowerCase();
    const clinicUrl = `/clinic-listings/${encodeURIComponent(clinicSlug)}/`;
    return `
      <div class="tg-clinic-item brxe-container ${enableSlider ? 'swiper-slide' : ''}">
          <div class="brxe-block clinic-main-block">
          <a href="${clinicUrl}" class="tg-clinic-logo brxe-block">
              <span class="logo-image-wrap">
              <img 
                  src="${domainUrl}/${clinic.image}"
                  class="brxe-image" 
                  alt="${clinic.name}" 
                  onerror="this.onerror=null; this.src='${domainUrl}/wp-content/plugins/TabibGroup-core/assets/images/heart-rate.svg';">
              </span>
          </a>
          <div class="tg-clinic-content-wrap brxe-block heightFix">
              <a href="${clinicUrl}">
                <h3 class="brxe-post-title">${clinic.name}</h3>
              </a>
              <p class="tg-clinic-meta brxe-text-basic" style="margin: 0;">
                <span>عدد العروض: ${clinic.offer_count}</span>
                <span class="separator"></span>
                <span>مسافة: ${roundedValue} كم</span>
              </p>
              <ul class="tg-clinic-location brxe-list">
                <li>
                  <p class="content" style="margin: 0;">
                    <a href="${clinicUrl}">
                      <span class="title"><img src="${domainUrl}/wp-content/plugins/TabibGroup-core/assets/images/location-icon.svg" alt="location"> ${clinic.location}</span>
                    </a>
                  </p>
                </li>
              </ul>
              <a class="tg-clinic-cta brxe-button bricks-button bricks-background-primary circle" href="${clinicUrl}">عرض التفاصيل</a>
          </div>
          </div>
      </div>`;
  }).join("");

  if (enableSlider) {
    container.innerHTML = `
      <div class="brxe-carousel search-results-slider sop-slider">
        <div class="swiper">
          <div class="swiper-wrapper">${cardsHtml}</div>
        </div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
      </div>`;
    setTimeout(() => {
      const swiperEl = document.querySelector(".search-results-slider .swiper");
      if (swiperEl) {
        new Swiper(swiperEl, {
          slidesPerView: 1.2,
          spaceBetween: 12,
          loop: false,
          navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
          },
          breakpoints: {
            1200: { slidesPerView: 3 },
            1024: { slidesPerView: 2.5 },
            768: { slidesPerView: 1.5 }
          }
        });
      }
    }, 100);
  } else {
    container.innerHTML = `${cardsHtml}`;
  }

}

function renderOffers(offers) {
  const container = document.getElementById("offer-results");
  if (!offers.length) {
    if (container) container.innerHTML = "";
    document.querySelector(".offers-results-area")?.style.setProperty("display", "none");
    document.querySelector(".clinics-results-area")?.style.setProperty("margin", "3% 0");
    return;
  }
  document.querySelector(".offers-data-wrap")?.style.setProperty("display", "grid");
  
  container.innerHTML = `
      <div id="brxe-dcezll" class="brxe-container brx-grid customized-grid2x2" style="padding: 0;"> 
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
                          <div class="brxe-xtldrz brxe-text-basic"> رقم العرض: <span class="sku"> ${offer.id} </span></div>
                          <a href="/offers/${offer.id}" style="width: 100%;"><img width="400" height="300" src="${offer.new_offer_images[0]?.image || offer.offer_images[0]?.cloud_image}" class="brxe-dhrodg brxe-image css-filter size-full" alt="${offer.title}"></a>
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
                                  ${offer.clinic_location ? `<li class="repeater-item lg no-link clinic-loc">
                                      <img src="${site_url}/wp-content/plugins/TabibGroup-core/assets/images/location-icon.svg" alt="location"> <span>${offer.clinic_location}</span>
                                  </li>` : ''}
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

  if (offers.length <= 1) {
    const gridElement = document.querySelector('#brxe-dcezll.customized-grid2x2');
    if (gridElement) {
      gridElement.classList.add('adjust-single');
    }
  }

}

function checkAndRenderNoResultsMessage() {
  if (!clinicsRendered || !offersRendered) return;

  const clinicResults = document.getElementById("clinic-results");
  const offerResults = document.getElementById("offer-results");
  const searchContainer = document.getElementById("search-results-container");

  const hasClinicResults = clinicResults && clinicResults.innerHTML.trim() !== "";
  const hasOfferResults = offerResults && offerResults.innerHTML.trim() !== "";

  if (!hasClinicResults && !hasOfferResults && searchContainer) {
    searchContainer.innerHTML = `
      <section class="brxe-section" style="margin-bottom: 30px;">
        <div class="brxe-container">
          <h3>لا یوجد نتائج لعملیة البحث</h3>
        </div>
      </section>`;
  }
}

function renderPagination(totalPages, currentPage) {
    const container = document.getElementById("clinic-pagination-container");
    if (!container) return;

    let html = `<div class="tg-listing-pagination">`;

    // Previous button
    const prevDisabled = currentPage <= 1 ? "disabled" : "";
    const prevPage = currentPage > 1 ? currentPage - 1 : 1;
    html += `<button onclick="initSearchResults(${prevPage})" class="page-numbers prev ${prevDisabled}" data-page="${prevPage}">‹ خلف</button>`;

    // Show page buttons
    let start = Math.max(1, currentPage - 1);
    let end = Math.min(totalPages, currentPage + 1);

    if (start > 1) {
        html += `<button onclick="initSearchResults(1)" class="page-numbers" data-page="1">1</button>`;
        if (start > 2) html += `<span class="dots">...</span>`;
    }

    for (let i = start; i <= end; i++) {
        const activeClass = i === currentPage ? "current" : "";
        html += `<button onclick="initSearchResults(${i})" class="page-numbers ${activeClass}" data-page="${i}">${i}</button>`;
    }

    if (end < totalPages - 1) {
        html += `<span class="dots">...</span>`;
    }

    if (end < totalPages) {
        html += `<button onclick="initSearchResults(${totalPages})" class="page-numbers" data-page="${totalPages}">${totalPages}</button>`;
    }

    // Info text
    html += `<div class="pagination-text">صفحة ${currentPage} ل ${totalPages}</div>`;

    // Next button
    const nextDisabled = currentPage >= totalPages ? "disabled" : "";
    const nextPage = currentPage < totalPages ? currentPage + 1 : totalPages;
    html += `<button onclick="initSearchResults(${nextPage})" class="page-numbers next ${nextDisabled}" data-page="${nextPage}">التالي ›</button>`;

    html += `</div>`;
    container.innerHTML = html;
    
    if ( totalPages <= 1 ) {
      container.innerHTML = '';
    }
}

// ***************** Filters sidebar ***************
function fetchOfferCategories() {
  let { city, lat, long } = getUserLocation();
  const baseURL = window.location.origin;
  const url = new URL(`${baseURL}/api/endpoints/mobile/v3/home.json`);
  url.searchParams.set("lang", "ar");
  url.searchParams.set("city", city);
  url.searchParams.set("latitude", lat);
  url.searchParams.set("longitude", long);

  fetch(url)
    .then(res => res.json())
    .then(data => {
      const categories = data.data.offer_categories || [];
      // console.log("categories 2", data.data.offer_categories);
      renderCategoryFilters(categories);
    })
    .catch(err => {
      console.error("Failed to fetch offer categories:", err);
    });
}

function renderCategoryFilters(categories) {
  const container = document.getElementById("category-filter-list");
  if (!container) return;

  if (!categories.length) {
    container.innerHTML = "<p>لا توجد فئات</p>";
    return;
  }

  container.innerHTML = categories.map(category => {
    return `
      <label>
        <input type="radio" name="category[]" value="${category.id}" class="filter-radio">
        ${category.name}
      </label>`;
  }).join("");
}

// When a category checkbox is selected, fetch its subcategories
document.addEventListener("change", (e) => {
  if (e.target.name === "category[]") {
    let { city_id } = getUserLocation();
    const selectedCategory = e.target.value;

    fetchSubcategories(city_id, selectedCategory);
    fetchServices(city_id, selectedCategory);
  }
});

function fetchSubcategories(cityId, categoryId) {
  const baseURL = window.location.origin;
  const url = new URL(`${baseURL}/api/endpoints/mobile/v3/offer_categories/sub_categories.json`);
  url.searchParams.set("lang", "ar");
  url.searchParams.set("city_id", cityId);
  url.searchParams.set("offer_category_id", categoryId);

  fetch(url)
    .then(res => res.json())
    .then(data => {
      const subcategories = data.data || [];
      // console.log("subcategories", subcategories);
      renderSubcategoryRadios(subcategories);
    })
    .catch(err => {
      console.error("Failed to fetch subcategories:", err);
    });
}

function renderSubcategoryRadios(subcategories) {
  const container = document.getElementById("subcategory-filter-list");
  if (!container) return;

  if (!subcategories.length) {
    container.innerHTML = "<p>لا توجد فئات فرعية</p>";
    return;
  }

  container.innerHTML = subcategories.map(sub => {
    return `
      <label>
        <input type="radio" name="sub_category[]" value="${sub.id}" class="filter-radio">
        ${sub.label}
      </label>`;
  }).join("");
}

function fetchServices(cityId, categoryId) {
  const baseURL = window.location.origin;
  const url = new URL(`${baseURL}/api/endpoints/mobile/v2/service_settings.json`);
  url.searchParams.set("lang", "ar");
  url.searchParams.set("city_id", cityId);
  url.searchParams.set("offer_category_id", categoryId);

  fetch(url)
    .then(res => res.json())
    .then(data => {
      const services = data.data.serivce_settings || [];
      // console.log("services", services);
      renderServiceRadios(services);
    })
    .catch(err => {
      console.error("Failed to fetch services:", err);
    });
}

function renderServiceRadios(services) {
  const container = document.getElementById("service-filter-list");
  if (!container) return;

  if (!services.length) {
    container.innerHTML = "<p>لا توجد خدمات متاحة</p>";
    return;
  }

  container.innerHTML = services.map(service => {
    return `
      <label>
        <input type="radio" name="service[]" value="${service.service}" class="filter-radio">
        ${service.service}
      </label>`;
  }).join("");
}

function filterOffersByFilters() {
  let { city, city_id, lat, long } = getUserLocation();
  const baseURL = window.location.origin;
  const params = new URLSearchParams(window.location.search);
  const searchTerm = params.get("search") || "";

  // Collect selected filter values
  const selectedCategory = document.querySelector('input[name="category[]"]:checked')?.value || "";
  const selectedSubCategory = document.querySelector('input[name="sub_category[]"]:checked')?.value || "";
  const selectedServices = Array.from(document.querySelectorAll('input[name="service[]"]:checked')).map(s => s.nextSibling?.textContent?.trim()).join(',');
  const selectedDate = document.getElementById("appointment-date")?.value || "";
  // const doctorName = new URLSearchParams(window.location.search).get("search") || "";

  const apiUrl = new URL(`${baseURL}/api/word_press/v1/offers/nearest_offers.json`);
  apiUrl.searchParams.set("lang", "ar");
  apiUrl.searchParams.set("page", 1);
  apiUrl.searchParams.set("city", city);
  apiUrl.searchParams.set("city_id", city_id);
  apiUrl.searchParams.set("latitude", lat);
  apiUrl.searchParams.set("longitude", long);
  if (selectedCategory) apiUrl.searchParams.set("offer_category_id", selectedCategory);
  if (selectedSubCategory) apiUrl.searchParams.set("sub_category_id", selectedSubCategory);
  if (selectedServices) apiUrl.searchParams.set("service", selectedServices);
  // if (doctorName) apiUrl.searchParams.set("doctor_name", doctorName);
  // apiUrl.searchParams.set("doctor_name", '');
  if (selectedDate) apiUrl.searchParams.set("date", selectedDate);

  fetch(apiUrl.toString())
    .then(res => res.json())
    .then(data => {
        // console.log("Filtered offer API response:", data);
      const results = Array.isArray(data.data) ? data.data : [];
      // console.log("Filtered offer API results:", results);
      const countEl = document.querySelector(".offers-result-count");
      if (countEl) {
        countEl.textContent = `${results.length}`;
      }
      if (results.length) {
        renderOffers(results);
        renderOfferHeading(results.length);
      } else {
        document.getElementById("offer-results").innerHTML = '<p id="search-no-results">لا یوجد نتائج لعملیة البحث.</p>';
      }

      // Scroll to the search results wrapper
      const scrollTarget = document.querySelector(".offers-data-wrap");
      if (scrollTarget) {
        scrollTarget.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    })
    .catch(err => {
      console.error("Clinic filter API error:", err);
      document.getElementById("offer-results").innerHTML = '<p id="search-no-results">لا یوجد نتائج لعملیة البحث.</p>';
    });
}

document.addEventListener("DOMContentLoaded", () => {

  const offerContainer = document.getElementById("offer-results");
  const clinicContainer = document.getElementById("clinic-results");

  if (!offerContainer || !clinicContainer) {
    console.warn("Required containers are not available in DOM.");
    return;
  }

  initSearchResults();
  fetchOfferCategories();

  // Date Picker
  const dateInput = document.getElementById("appointment-date");

  // Initialize Flatpickr
  flatpickr(dateInput, {
    dateFormat: "Y-m-d",
    locale: "ar",
    minDate: "today"
  });

  // Open picker when clicking label or icon
  document.getElementById("date-label").addEventListener("click", () => {
    dateInput._flatpickr.open();
  });

  document.querySelector(".calendar-icon").addEventListener("click", () => {
    dateInput._flatpickr.open();
  });

  dateInput.addEventListener("change", () => {
    document.getElementById("selected-date").textContent = dateInput.value;
  });

  // Clear all filters
  document.getElementById("clear-filters").addEventListener("click", function (e) {
    e.preventDefault();

    // Uncheck radio
    document.querySelectorAll(".filters-container input[type='radio']").forEach(cb => {
      cb.checked = false;
    });

    // Clear date input via Flatpickr API
    const dateInput = document.getElementById("appointment-date");
    if (dateInput && dateInput._flatpickr) {
      dateInput._flatpickr.clear();
    }

    // Clear dynamically generated subcategories and services
    document.getElementById("subcategory-filter-list").innerHTML = "<p>الرجاء تحديد الفئة أولاً.</p>";
    document.getElementById("service-filter-list").innerHTML = "<p>الرجاء تحديد الفئة أولاً.</p>";

    // Close accordion menus
    document.querySelectorAll(".accordion-content").forEach(content => {
      content.style.display = "none";
    });

    // Optional: Remove 'active' class from accordion toggle buttons
    document.querySelectorAll(".accordion-toggle").forEach(toggle => {
      toggle.classList.remove("active");
    });

    // Disable continue button if needed
    const continueBtn = document.getElementById("filterOffers");
    if (continueBtn) continueBtn.classList.add("disabled");

    // Trigger offer re-fetch with no filters
    initSearchResults();
  });

  // Toggle accordion of filters
  document.querySelectorAll(".accordion-toggle").forEach(toggle => {
    toggle.addEventListener("click", () => {
      const content = toggle.nextElementSibling;
      content.style.display = content.style.display === "block" ? "none" : "block";
    });
  });

  // Enable filter button on inputs select
  document.addEventListener("change", function (e) {
    if (
      e.target.matches(".filter-radio") ||
      e.target.id === "appointment-date"
    ) {
      const hasSelection =
        document.querySelector('input[name="category[]"]:checked') ||
        document.querySelector('input[name="sub_category[]"]:checked') ||
        document.querySelector('input[name="service[]"]:checked') ||
        document.getElementById("appointment-date")?.value;

      const button = document.getElementById("filterOffers");
      if (button) {
        button.classList.toggle("disabled", !hasSelection);
        button.disabled = !hasSelection;
      }
    }
  });

  // Apply filter on button click
  const filterBtn = document.getElementById("filterOffers");
  filterBtn.addEventListener("click", () => {
    filterOffersByFilters();
  });

  // Toggle filters for mobile devices
  const toggleBtn = document.getElementById("toggle-filters");
  const closeBtn = document.getElementById("close-filters");
  const filtersContainer = document.querySelector(".filters-container");

  toggleBtn?.addEventListener("click", () => {
    filtersContainer?.classList.add("active");
    document.documentElement.style.overflow = "hidden";
  });

  closeBtn?.addEventListener("click", () => {
    filtersContainer?.classList.remove("active");
    document.documentElement.style.overflow = "";
  });

});