jQuery(document).ready(function ($) {
  $('#check-reservation-type').on('click', function () {
    const is_logged_in = reservationData.is_logged_in;
    if (is_logged_in) {
      const offerId = reservationData.offer_id;
      const productId = reservationData.product_id;
      // AJAX request to fetch product meta
      $.ajax({
        url: reservationData.ajax_url,
        type: 'POST',
        data: {
          action: 'get_reservation_type',
          offer_id: offerId,
          product_id: productId,
          _wpnonce: reservationData.nonce,
        },
        success: function (response) {
          // console.log(response);
          // console.log(response.session);
          // console.log(response.consultation);
          if (response.session === 'true' && response.consultation === 'true') {
            const modalHTML = `<div id="reservation-modal" style="display: none;">
                <div>
                  <h2>لموعدك الأول</h2>
                  <p>هل تُفضل أن يكون موعدك الأول؟</p>
                  <div id="action-btns">
                    <button id="session-button">جلسة ليزر</button>
                    <button id="consultation-button">كشفية ليزر</button>
                  </div>
                </div>
              </div>
              <div id="reservation-modal-overlay" style="display: none;"></div>`;
            $('body').append(modalHTML);
            $('#reservation-modal, #reservation-modal-overlay').fadeIn();
            $('#reservation-modal-overlay').on('click', closeModal);
          } else if (response.session == 'true') {
            // console.log(response.session);
            navigateToReservationPage('session');
          } else if (response.consultation == 'true') {
            // console.log(response.consultation);
            navigateToReservationPage('consultation');
          } else {
            // console.log("Result:", "Session",  response.session, "Consultation:", response.consultation);
            navigateToReservationPage('');
          }
          $('#session-button').on('click', function () {
            navigateToReservationPage('session');
          });
          $('#consultation-button').on('click', function () {
            navigateToReservationPage('consultation');
          });
        },
        error: function () {
          console.error('Error fetching reservation type.');
        },
      });
      // Function to close the modal and remove it from the DOM
      function closeModal() {
        $('#reservation-modal, #reservation-modal-overlay').fadeOut(function () {
          $(this).remove();
        });
      }
      // Function to navigate to the reservation page
      function navigateToReservationPage(reservationType) {
        if (!reservationData.reservation_page_url) {
        console.error('Reservation page URL is not defined.');
        return;
        }
        const offerId = reservationData.offer_id;
        const reservationURL = `${reservationData.reservation_page_url}?appointment_type=${reservationType}&offer_id=${offerId}`;
        window.location.href = reservationURL;
      }
    } else {
      let currentUrl = window.location.href;
      let loginUrl = '/account/?redirect_to=' + encodeURIComponent(currentUrl);
      window.location.href = loginUrl;
    }
  });
});