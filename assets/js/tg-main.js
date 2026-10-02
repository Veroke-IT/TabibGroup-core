jQuery(document).ready(function ($) {

    $('.bricks-pagination .next, .bricks-pagination .prev').text('');

    const baseUrl = mainData.baseUrl;
    let cachedCities = null;
    let allowedCities = [];

    // Utility to normalize Arabic characters (e.g., مكة to مكه)
    function normalizeCity(city) {
        return city.replace('ة', 'ه').trim();
    }

    // Load cities (with caching)
    function loadCities(callback) {
        if (cachedCities) {
            callback(cachedCities);
            return;
        }
        $.ajax({
            url: `${baseUrl}/api/v7/cities.json`,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response?.data?.cities) {
                    cachedCities = response.data.cities;
                    allowedCities = cachedCities.map(c => normalizeCity(c.name));
                    callback(cachedCities);
                } else {
                    console.warn('[LoadCities] Invalid city data.');
                    callback([]);
                }
            },
            error: function () {
                console.warn('[LoadCities] Failed to fetch cities.');
                callback([]);
            }
        });
    }

    // Show popup (after loading cities)
    function showPopup() {
        $('#city-popup, #city-popup-overlay').fadeIn();
        loadCities(fetchCities);
    }

    // Populate cities in popup
    function fetchCities(cities) {
        const $cityOptions = $('#city-options');
        $cityOptions.empty();
        $('#city-loading').show();
        $cityOptions.hide();

        if (!cities || cities.length === 0) {
            $('#city-loading').text("تعذر تحميل المدن. الرجاء المحاولة لاحقًا.");
            return;
        }

        cities.forEach(function (city) {
            $cityOptions.append(`
                <div class="city-option">
                    <input type="radio" id="city-${city.id}" name="city" value="${city.id}" data-name="${city.name}">
                    <label for="city-${city.id}">${city.name}</label>
                </div>
            `);
        });

        $('#city-loading').hide();
        $cityOptions.show();

        const stored = localStorage.getItem('user_location');
        if (stored) {
            const userCity = JSON.parse(stored);
            const match = $(`#city-options input[data-name="${userCity.name}"]`);
            if (match.length) {
                match[0].checked = true;
            }
            document.cookie = `selected_city_name=${encodeURIComponent(userCity.name)}; path=/; max-age=86400`;
        }

    }

    // Handle submit
    $('#city-form').on('submit', function (e) {
        e.preventDefault();
        const selectedCity = $('input[name="city"]:checked').val();
        const selectedCityName = $('input[name="city"]:checked').next('label').text();
        if (selectedCity && selectedCityName) {
            const cityData = { id: selectedCity, name: selectedCityName };
            // Store in localStorage
            localStorage.setItem('user_location', JSON.stringify(cityData));
            document.cookie = `selected_city_name=${encodeURIComponent(selectedCityName)}; path=/; max-age=86400`;
            window.location.href = window.location.href;
        }
    });

    // Change location button (delegated event binding)
    $(document).on('click', '#change-location-button', function () {
        showPopup();
    });

    // Auto detect city and show popup
    if (!localStorage.getItem('user_location')) {
        showPopup();
    }

    // Hide popup when overlay clicked
    $(document).on('click', '#city-popup-overlay', function () {
        $('#city-popup, #city-popup-overlay').fadeOut();
    });

    // Clear old location on new session
    if (sessionStorage.getItem('user_session') !== 'active') {
        localStorage.removeItem('user_location');
        sessionStorage.setItem('user_session', 'active');
    }

    if (mainData.is_logged_in) {
        // Fetch cart count via AJAX
        $.ajax({
            url: mainData.ajax_url,
            method: "GET",
            data: {
                action: "get_cart_data",
                _wpnonce: mainData.nonce,
            },
            success: function(response) {
                if (response.success) {
                    const bodyString = response.data.body;
                    const bodyObject = JSON.parse(bodyString);
                    if (bodyObject.data && bodyObject.data.cart) {
                        const cartItems = bodyObject.data.cart.cart_items;
                        $("#cartCount").text(cartItems);
                        //console.log("Cart cartItems:", cartItems);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("Cart data not updated you're not logged in.", error);
            }
        });
    }

    // Change offers claim to thousand format
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + "M";
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + "k";
        }
        return num;
    }

    $(".claims").each(function () {
        let num = parseInt($(this).text().trim(), 10);
        if (!isNaN(num) && num > 999) {
            let formattedNum = formatNumber(num);
            $(this).text(formattedNum);
        }
    });
    
    navigator.geolocation.getCurrentPosition(
        function (position) {
            let latitude = position.coords.latitude;
            let longitude = position.coords.longitude;
            let userLocation = {
                lat: latitude,
                long: longitude
            };
            localStorage.setItem('user_current_location', JSON.stringify(userLocation));
        },
        function (error) {
            console.warn("Geolocation error:", error.message);            
            // Fallback values for location
            let fallbackLatitude = 21.492500;
            let fallbackLongitude = 39.177570;
            let userLocation = {
                lat: fallbackLatitude,
                long: fallbackLongitude
            };
            localStorage.setItem('user_current_location', JSON.stringify(userLocation));
        }
    );

    // Add arrow icons dynamically before each <h3> in FAQs
    $('.sc_fs_faq h3').each(function() {
        $(this).prepend('<span class="faq-arrow"></span>');
    });
    $('.sc_fs_faq > div > div').hide();
    // Accordion behavior
    $(document).on('click', '.sc_fs_faq h3', function () {
        var $this = $(this);
        var $answer = $this.next('div');
        $('.sc_fs_faq h3').removeClass('active');
        $('.sc_fs_faq > div > div').slideUp();
        if (!$answer.is(':visible')) {
            $this.addClass('active');
            $answer.slideDown();
        }
    });
        
});

// Global Loader
window.GlobalLoader = {
  timeoutId: null,
  delayTimer: null,
  loaderVisible: false,
  show: function (timeout = 10000, delay = 50) {
    // Only show loader if delay passes (50ms default)
    this.delayTimer = setTimeout(() => {
      const loader = document.getElementById("global-loader-overlay");
      if (loader) loader.style.display = "flex";
      document.body.style.overflow = "hidden";
      this.loaderVisible = true;
      // Set fail-safe auto timeout
      this.timeoutId = setTimeout(() => {
        this.hide();
      }, timeout);
    }, delay);
  },
  hide: function () {
    clearTimeout(this.delayTimer);
    this.delayTimer = null;
    const loader = document.getElementById("global-loader-overlay");
    if (loader && this.loaderVisible) {
      loader.style.display = "none";
      document.body.style.overflow = "";
      this.loaderVisible = false;
    }
    this.clearTimeout();
  },
  scrollTo: function (selector = ".scrollToPoint") {
    const element = document.querySelector(selector);
    if (element) {
      element.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  },
  clearTimeout: function () {
    if (this.timeoutId) {
      clearTimeout(this.timeoutId);
      this.timeoutId = null;
    }
  }
};

