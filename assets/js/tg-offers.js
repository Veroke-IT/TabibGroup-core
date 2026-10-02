document.addEventListener("DOMContentLoaded", function () {
	const base_url = naviData.baseUrl;
	const offerId = document.querySelector('.sku').textContent;
	const reviewsSection = document.querySelector('.tg-reviews-slider-block');
	// Fetch reviews data
	fetch(`${base_url}/api/v7/offer_reviews/index_by_offer.json?lang=en&offer_id=${offerId}`)
		.then(response => response.json())
		.then(data => {
		if (data && data.data && data.data.length > 0) {
			// Create Swiper structure
			reviewsSection.innerHTML = `
			<div class="tg-reviews-slider bricks-swiper-container swiper-container">
			<div class="swiper-wrapper"></div>
			<div class="swiper-paginations"></div>
			</div>
			<div class="swiper-button-next"></div>
			<div class="swiper-button-prev"></div>
			`;
			const reviewsWrapper = reviewsSection.querySelector('#brxe-kbyykf .swiper-wrapper');
			// Loop through reviews and create HTML for each review
			data.data.forEach(review => {
				const reviewElement = document.createElement('div');
				reviewElement.classList.add('review-block', 'swiper-slide');
				// Format the review content
				reviewElement.innerHTML = `
				<p class="brxe-sub-heading">تم النشر في ${formatDate(review.created_at)}</p>
				<p class="brxe-main-heading">${review.comment && review.comment.trim() !== '' ? review.comment : 'لا توجد تعليقات'}</p>
				<div class="brxe-meta-block">
					<p class="brxe-uname">${review.user_name}</p>
					<div class="brxe-rating-wrap">
						<div class="brxe-star-rating">${generateStars(review.score)}</div>
						<span class="brxe-avg-rating">${(review.score / 2).toFixed(1)}</span>
					</div>
				</div>
				`;
				reviewsWrapper.appendChild(reviewElement);
			});
			
			// Initialize Swiper after adding slides
			new Swiper('.tg-reviews-slider.swiper-container', {
				slidesPerView: 1,
				spaceBetween: 12,
				navigation: {
					nextEl: '.swiper-button-next',
					prevEl: '.swiper-button-prev',
				},
				breakpoints: {
					767: { slidesPerView: 3, spaceBetween: 12 },
					1024: { slidesPerView: 4, spaceBetween: 20 },
				},
			});
		} else {
			reviewsSection.innerHTML = `<p style="margin-right: 15px;">لم يتم العثور على تعليقات.</p>`;
		}
	})
		.catch(error => {
		console.error("Error fetching reviews:", error);
		reviewsSection.innerHTML = `<p style="margin-right: 15px;">فشل تحميل المراجعات.</p>`;
	});
});

// Function to generate star icons based on rating
function generateStars(rating) {
	const filledStar = '<div class="icon full-color">★</div>';
	const emptyStar = '<div class="icon empty-color">☆</div>';
	let stars = '';
	// Calculate the number of full, half, and empty stars
	const fullStars = Math.floor(rating / 2);
	const emptyStars = 5 - fullStars;
	for (let i = 0; i < fullStars; i++) {
		stars += filledStar;
	}
	for (let i = 0; i < emptyStars; i++) {
		stars += emptyStar;
	}
	return stars;
}

// Function to Format Review Date
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('ar-EG', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
		numberingSystem: 'latn'
    }).format(date);
}

// Change main gallery image by clicking thumbnails
function changeImage(imageUrl) {
	const currentImage = document.getElementById("current-image");
	currentImage.src = imageUrl;
	// Highlight the active thumbnail
	const thumbnails = document.querySelectorAll(".custom-image-gallery .thumbnail");
	thumbnails.forEach(thumbnail => thumbnail.classList.remove("active"));
	event.currentTarget.parentElement.classList.add("active");
}

document.addEventListener("DOMContentLoaded", function() {
	new Swiper('.image-thumbnails.swiper-container', {
		slidesPerView: 5,
		spaceBetween: 16,
		navigation: {
			nextEl: '.swiper-button-next',
			prevEl: '.swiper-button-prev',
		},
		breakpoints: {
			1920: {
				spaceBetween: 32,
			}
		},
	});

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

	// related-offers-slider js code
    const wrapper = document.getElementById('related-offers-slider');
    if (!wrapper || wrapper.dataset.loaded === "true") return;
    let { city } = getUserLocation();
	// Get product ID from body class
    let productIdMatch = document.body.className.match(/postid-(\d+)/);
    let productId = productIdMatch ? productIdMatch[1] : '';
    // console.log('Fetching related offers for city:', city, 'and product ID:', productId);
	fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
        action: 'get_related_offers_slider',
        city: city,
		product_id: productId
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
