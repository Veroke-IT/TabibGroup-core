jQuery(document).ready(function ($) {
    const baseUrl = availabilityData.baseUrl;
    const params = new URLSearchParams(window.location.search);
    const offerId = params.get('offer_id');
    const reservationType = params.get('appointment_type');

    if (!offerId) {
        alert('Invalid offer ID.');
        return;
    }
	
	const reviewsCountElement = document.querySelector('.count-reviews');
	// Fetch reviews data
	fetch(`${baseUrl}/api/v7/offer_reviews/index_by_offer.json?lang=en&offer_id=${offerId}`)
		.then(response => response.json())
		.then(data => {
		if (data && data.data && data.data.length > 0) {
			let reviewCount = 0;
			data.data.forEach(review => {
				reviewCount++;
			});
			reviewsCountElement.textContent = reviewCount;
		} else {
			reviewsCountElement.textContent = "0";
		}
	})
		.catch(error => {
		reviewsCountElement.textContent = "0";
	});
	
	// Fetch offer details dynamically
    function fetchOfferDetails(offerId) {
        $.ajax({
            url: availabilityData.ajax_url,
            type: 'GET',
            data: {
                action: 'get_offer_data',
                offer_id: offerId,
                _wpnonce: availabilityData.nonce,
            },
            success: function (response) {
                if (response.success) {
                    const offer = response.data.data.offer;
                    const clinicSlug = offer.doctor_name.replace(/\s+/g, '-').toLowerCase();
                    const clinicUrl = `/clinic-listings/${encodeURIComponent(clinicSlug)}/`;
                    $('.abai-featured-img').attr('src', offer.new_offer_images[0].image || '');
					$('.offer_id').text(offer.id || 'N/A');
                    $('.abai-title').text(offer.bold_title + ' ' + offer.light_title || 'لا يوجد عنوان متاح');
                    // $('.abai-excerpt').text(offer.light_title || 'لا يوجد وصف متاح');
					$('.abai-category').text(offer.doctor_name || 'لا يوجد اسم المستشفى متاح');
					$('.abai-location span').text(offer.clinic_location || 'لا يوجد موقع متاح');
                    $('.avg-rating').text(offer.avg_rating || 'N/A');
					// $('.patient_availability_check').val(offer.preffered_time || '');
					$('.doctor_id').val(offer.doctor_id || '');
                    $('.abai-body .content').attr('href', clinicUrl);
                } else {
                    alert('Error fetching offer data:', response.message);
                }
            },
            error: function () {
                alert('Error fetching offer details.');
            },
        });
    }

    let calendarData = {};
    let currentYear, currentMonth;

    // Fetch calendar slots dynamically
    function fetchCalendar(offerId, reservationType) {
        $.ajax({
            url: availabilityData.ajax_url,
            type: 'GET',
            data: {
                action: 'get_calendar_data',
                offer_id: offerId,
                appointment_type: reservationType,
                _wpnonce: availabilityData.nonce,
            },
            success: function (response) {
                if (response.success) {
                    const slotLists = response.data.data.slotLists;
                    if (Array.isArray(slotLists) && slotLists.length > 0) {
                        calendarData = groupDatesByYearAndMonth(slotLists);
                        initializeCalendar();
                    } else {
                        $('.ab-calendar').html('<h4>لا توجد فترات زمنية متاحة لهذا العرض، الاستمرار في صفحة سلة التسوق.</h4>');
                        $('#confirm-reservation').removeClass('abai-disabled-btn');
                    }
                } else {
                    alert('Unable to fetch availability data. Please try again later.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again later.');
            },
        });
    }

    // Group dates by year and month
    function groupDatesByYearAndMonth(slotLists) {
        return slotLists.reduce((acc, slot) => {
            const [year, month, day] = slot.date.split('-');
            if (!acc[year]) acc[year] = {};
            if (!acc[year][month]) acc[year][month] = [];
            acc[year][month].push({ day, timeSlots: slot.timeSlots });
            return acc;
        }, {});
    }

    // Initialize calendar with the first available year and month
    function initializeCalendar() {
        const years = Object.keys(calendarData).sort();
        if (years.length === 0) return;

        const today = new Date();
        const thisYear = today.getFullYear().toString();
        const thisMonth = (today.getMonth() + 1).toString().padStart(2, '0');

        if (calendarData[thisYear] && calendarData[thisYear][thisMonth]) {
            // Start at current month if available
            currentYear = thisYear;
            currentMonth = thisMonth;
        } else {
            // Fallback: find the first available month that is within one month from today
            for (let year of years) {
                const months = Object.keys(calendarData[year]).sort();
                for (let month of months) {
                    const testDate = new Date(parseInt(year), parseInt(month) - 1, 1);
                    const oneMonthFromToday = new Date(today);
                    oneMonthFromToday.setMonth(today.getMonth() + 1);
                    if (testDate >= today && testDate <= oneMonthFromToday) {
                        currentYear = year;
                        currentMonth = month;
                        break;
                    }
                }
                if (currentYear && currentMonth) break;
            }
        }

        if (currentYear && currentMonth) {
            renderCalendar(currentYear, currentMonth);
            updateNavButtons();
        } else {
            $('.ab-calendar').html('<h4>لا توجد فترات زمنية متاحة خلال الشهر القادم.</h4>');
        }
    }
	
	// Set time slots for the calendar
	function populateTimeSlots(timeSlots, selectedDate) {
		const timeSlotsContainer = $('.time-slot-wrapper');
		timeSlotsContainer.empty();

		if (!Array.isArray(timeSlots) || timeSlots.length === 0) {
			timeSlotsContainer.append('<p>لا توجد فترات زمنية متاحة لهذا التاريخ.</p>');
			return;
		}

		// Iterate through the time slots and create buttons
		timeSlots.forEach(time => {
			// Parse the time to check if it's AM or PM
			const [hour, minute] = time.split(':').map(Number);
			const period = hour < 12 ? 'AM' : 'PM';
			const formattedHour = hour % 12 || 12;

			// Format the time as HH:MM AM/PM
			const formattedTime = `${formattedHour}:${minute.toString().padStart(2, '0')} ${period}`;

			const timeElement = $('<button>')
				.addClass('time-slot')
				.attr('data-time', time)
				.text(formattedTime)
				.on('click', function () {
					$('.time-slot').removeClass('selected');
					$(this).addClass('selected');
					$('#confirm-reservation').removeClass('abai-disabled-btn');
				});

			timeSlotsContainer.append(timeElement);
		});
	}

    function renderCalendar(year, month) {
        const daysInMonth = new Date(year, month, 0).getDate();
        const firstDayOfMonth = new Date(year, month - 1, 1).getDay();
        const calendarDayNames = $('#dt-calendarDayNames');
        const calendarContainer = $('#dt-calendarDays');
        const monthYearDisplay = $('#dt-monthYear');
    
        // Clear previous content
        calendarDayNames.empty();
        calendarContainer.empty();
        $('.dtSlots').hide();
    
        // Set month and year display (in Arabic)
        const monthName = new Date(year, month - 1).toLocaleString('ar-EG', { month: 'long' });
        monthYearDisplay.text(`${monthName} ${year}`);
    
        // Arabic day names
        const daysOfWeek = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        daysOfWeek.forEach(day => {
            calendarDayNames.append(`<div class="day-name">${day}</div>`);
        });
    
        // Blank spaces before first of the month
        for (let i = 0; i < firstDayOfMonth; i++) {
            calendarContainer.append('<div class="dt-day empty"></div>');
        }
    
        const today = new Date();
        const oneMonthFromToday = new Date(today);
        oneMonthFromToday.setMonth(today.getMonth() + 1);
    
        // Loop through each day in month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateObj = new Date(year, month - 1, day);
            const formattedDate = `${day.toString().padStart(2, '0')}/${month.toString().padStart(2, '0')}/${year}`;
            const isAvailable = calendarData[year]?.[month]?.find(d => parseInt(d.day) === day);
    
            // Check if date is in allowed range
            const isInRange = dateObj >= today && dateObj <= oneMonthFromToday;
    
            const dayElement = $('<div>')
                .addClass('dt-day')
                .html(`<span>${day}</span>`)
                .attr('data-date', formattedDate)
                .toggleClass('available', !!isAvailable && isInRange)
                .toggleClass('unavailable', !isAvailable || !isInRange)
                .on('click', function () {
                    if (isAvailable && isInRange) {
                        $('.dt-day').removeClass('selected');
                        $(this).addClass('selected');
                        $('.dtDates').hide();
                        $('.dtSlots').show();
                        populateTimeSlots(isAvailable.timeSlots, `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`);
                    }
                });
    
            calendarContainer.append(dayElement);
        }
    }    

    function updateNavButtons() {
        const today = new Date();
        const oneMonthFromToday = new Date();
        oneMonthFromToday.setMonth(today.getMonth() + 1);

        const currentDate = new Date(parseInt(currentYear), parseInt(currentMonth) - 1, 1);

        $('#dt-nextMonth').prop(
            'disabled',
            currentDate >= new Date(oneMonthFromToday.getFullYear(), oneMonthFromToday.getMonth(), 1)
        );

        $('#dt-prevMonth').prop(
            'disabled',
            currentDate <= new Date(today.getFullYear(), today.getMonth(), 1)
        );
    }
    
    // Get today's date and one month from today
    const today = new Date();
    const oneMonthFromToday = new Date();
    oneMonthFromToday.setMonth(today.getMonth() + 1);

    // Handle month navigation
    $('#dt-nextMonth').on('click', function () {
        const nextMonthDate = new Date(parseInt(currentYear), parseInt(currentMonth) - 1 + 1, 1);

        if (nextMonthDate > oneMonthFromToday) {
            return;
        }

        if (!calendarData[currentYear]) return;

        const months = Object.keys(calendarData[currentYear]);
        const currentMonthIndex = months.indexOf(currentMonth);

        if (currentMonthIndex < months.length - 1) {
            currentMonth = months[currentMonthIndex + 1];
        } else {
            const nextYear = (parseInt(currentYear) + 1).toString();
            if (calendarData[nextYear]) {
                const nextYearFirstMonth = Object.keys(calendarData[nextYear])[0];
                const testDate = new Date(parseInt(nextYear), parseInt(nextYearFirstMonth) - 1, 1);
                if (testDate <= oneMonthFromToday) {
                    currentYear = nextYear;
                    currentMonth = nextYearFirstMonth;
                } else {
                    return;
                }
            }
        }

        renderCalendar(currentYear, currentMonth);
        updateNavButtons();
    });

    $('#dt-prevMonth').on('click', function () {
        const prevMonthDate = new Date(parseInt(currentYear), parseInt(currentMonth) - 1 - 1, 1);

        if (prevMonthDate < new Date(today.getFullYear(), today.getMonth(), 1)) {
            return;
        }

        if (!calendarData[currentYear]) return;

        const months = Object.keys(calendarData[currentYear]);
        const currentMonthIndex = months.indexOf(currentMonth);

        if (currentMonthIndex > 0) {
            currentMonth = months[currentMonthIndex - 1];
        } else {
            const prevYear = (parseInt(currentYear) - 1).toString();
            if (calendarData[prevYear]) {
                const prevYearMonths = Object.keys(calendarData[prevYear]);
                const lastMonth = prevYearMonths[prevYearMonths.length - 1];
                const testDate = new Date(parseInt(prevYear), parseInt(lastMonth) - 1, 1);
                if (testDate >= new Date(today.getFullYear(), today.getMonth(), 1)) {
                    currentYear = prevYear;
                    currentMonth = lastMonth;
                } else {
                    return;
                }
            }
        }

        renderCalendar(currentYear, currentMonth);
        updateNavButtons();
    });

    // Handle "Go Back" buttons
	$('.ab-go-back').on('click', function () {
		if (document.referrer) {
            window.location.href = document.referrer;
        } else {
            window.location.href = '/offers';
        }
    });
	
    $('#dt-gotoDate').on('click', function () {
        $('.dtSlots').hide();
        $('.dtDates').show();
    });

    // Fetch offer details and calendar slots on page load
    fetchOfferDetails(offerId);
    fetchCalendar(offerId, reservationType);

    document.getElementById('confirm-reservation').addEventListener('click', function () {

        const params = new URLSearchParams(window.location.search);
        const offerId = params.get('offer_id');
        const reservationType = params.get('appointment_type');
        const isCartUpdate = params.get('action') === 'cartUpdate';
        const cartItemId = params.get('cart_item_id');
        
        if (!offerId) return;
    
        const selectedDateElement = $('.dt-day.selected');
        const selectedTimeElement = $('.time-slot.selected');
        const patientAvailabilityCheck = $('.patient_availability_check').val();
        const doctorId = $('.doctor_id').val();
        const cartId = $('.cart_id').val();
    
        if (!cartId) {
            $('body:not(.logged-in) .error-message').show();
            return;
        }
    
        const selectedDate = selectedDateElement.attr('data-date') || '';
        const selectedTime = selectedTimeElement.attr('data-time') || '';
    
        const requestBody = {
            reserved_time: selectedTime,
            cart_id: cartId,
            doctor_id: doctorId,
            patient_availability: '',
            appointment_type: reservationType,
            reserved_date: selectedDate,
            offer_id: offerId,
            patient_availability_check: patientAvailabilityCheck,
        };
    
        // Determine whether to POST or PUT
        if (isCartUpdate && cartItemId) {
            // AJAX call to your custom WordPress action
            $.ajax({
                url: availabilityData.ajax_url,
                method: 'POST',
                data: {
                    action: 'update_cart_item_info',
                    cart_item_id: cartItemId,
                    cart_id: cartId,
                    reserved_date: selectedDate,
                    reserved_time: selectedTime,
                    _wpnonce: availabilityData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const domainUrl = window.location.origin;
                        $('.ab-section').html(`
                            <div class="appointment-success-section">
                                <div class="appointment-success">
                                    <div class="apointemnt-success-wrapper">
                                        <img src="${domainUrl}/wp-content/plugins/TabibGroup-core/assets/images/tick-circle.svg" alt="tick circle" class="as-img">
                                        <h1 class="as-title">تهانينا</h1>
                                        <p class="as-txt">تهانينا، لقد أكملت حجزك بنجاح، فلننتقل الآن إلى الخطوات التالية.</p>
                                        <a href="/" class="as-browse-more-btn">تصفح المزيد من العروض</a>
                                        <a href="/cart" class="as-cart-btn"><span>الذهاب الى السلة</span> 
                                            <img src="${domainUrl}/wp-content/plugins/TabibGroup-core/assets/images/lr-arrow.svg" alt="arrow">
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `);
                        $('html, body').animate({
                            scrollTop: $('.ab-section').offset().top
                        }, 800);
                    } else {
                        $('.error-message').show().html(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX error:', error);
                    $('.error-message').show().html(error);
                }
            });
        } else {
            fetch('/wp-admin/admin-ajax.php?action=add_to_cart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody),
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(handleSuccess)
            .catch(handleError);
        }
    
        function handleSuccess(data) {
            if (data.success) {
                $.ajax({
                    url: availabilityData.ajax_url,
                    method: "GET",
                    data: {
                        action: "update_cart_data",
                        _wpnonce: availabilityData.nonce,
                    },
                    success: function (response) {
                        const bodyString = response.data.body;
                        const bodyObject = JSON.parse(bodyString);
                        if (bodyObject.data && bodyObject.data.cart) {
                            const cartItems = bodyObject.data.cart.cart_items;
                            $("#cartCount").text(cartItems);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert("Error in cart data update.", error);
                    }
                });
    
                const domainUrl = window.location.origin;
                $('.ab-section').html(`
                    <div class="appointment-success-section">
                        <div class="appointment-success">
                            <div class="apointemnt-success-wrapper">
                                <img src="${domainUrl}/wp-content/plugins/TabibGroup-core/assets/images/tick-circle.svg" alt="tick circle" class="as-img">
                                <h1 class="as-title">تهانينا</h1>
                                <p class="as-txt">تهانينا، لقد أكملت حجزك بنجاح، فلننتقل الآن إلى الخطوات التالية.</p>
                                <a href="/" class="as-browse-more-btn">تصفح المزيد من العروض</a>
                                <a href="/cart" class="as-cart-btn"><span>الذهاب الى السلة</span> 
                                    <img src="${domainUrl}/wp-content/plugins/TabibGroup-core/assets/images/lr-arrow.svg" alt="arrow">
                                </a>
                            </div>
                        </div>
                    </div>
                `);
                $('html, body').animate({
                    scrollTop: $('.ab-section').offset().top
                }, 800);
            } else {
                $('.error-message').show();
            }
        }
    
        function handleError(error) {
            alert('An error occurred while processing your reservation. Please try again.', error);
        }
    
    });    

});

(function () {
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
        const response = await originalFetch(...args);
        // Check if the request is adding to the cart
        if (args[0].includes("action=add_to_cart")) {
            let cartTimestamps = JSON.parse(localStorage.getItem("cart_added_times")) || [];
            cartTimestamps.push(Date.now());
            localStorage.setItem("cart_added_times", JSON.stringify(cartTimestamps));
        }
        return response;
    };
})();
