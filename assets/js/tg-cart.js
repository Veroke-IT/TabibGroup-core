jQuery(document).ready(function ($) {

    // Handle "Go Back" buttons
    $('.ab-go-back').on('click', function () {
        if (document.referrer) {
            window.location.href = document.referrer;
        } else {
            window.location.href = '/offers';
        }
    });

    // Fetch cart items via AJAX
    $.ajax({
        url: cartData.ajax_url,
        method: "GET",
        data: {
            action: "tg_get_cart_items",
            _wpnonce: cartData.nonce,
        },
        success: function(response) {
            if (response.success) {
                $("#cart-items-container").html(response.data.html);
            } else {
                const domainUrl = window.location.origin;
                $(".cart-wrapper").empty();
                $('.cart-wrapper').html(`<div class="appointment-success-section"><div class="appointment-success"><div class="apointemnt-success-wrapper"><img src="${domainUrl}/wp-content/plugins/TabibGroup-core/assets/images/shopping.png" alt="empty cart"><h2 class="as-title">تنبيه!</h2><p class="as-txt">سلة التسوق الخاصة بك فارغة يرجى إضافة العروض إلى سلة التسوق الخاصة بك.</p><a href="/" class="as-browse-more-btn">تصفح المزيد من العروض</a></div></div></div>`);
            }
        },
        error: function() {
            $('.cart-wrapper').html("<p>An error occurred while loading the cart items.</p>");
        }
    });

    // Handle the "Delete" button in the cart item
    let cartItemIdToDelete = null;
    let modalHtml = `<div id="reservation-modal" style="display: none;">
        <div>
            <h2>تنبيه!</h2>
            <p>هل تريد فعلاً حذف هذا العرض من سلة المشتريات ؟</p>
            <div id="action-btns">
                <button id="continue-deletion" class="dialog-button">استمرار</button>
                <button id="cancel-deletion" class="dialog-button">إلغاء</button>
            </div>
        </div>
    </div>
    <div id="reservation-modal-overlay" style="display: none;"></div>`;

    // Show the modal when "Delete" button is clicked
    $(document).on('click', '.csi-remove-btn', function () {
        cartItemIdToDelete = $(this).attr('cart-item');
        if (cartItemIdToDelete) {
            if ($('#reservation-modal').length === 0) {
                $('body').append(modalHtml);
            }
            $('#reservation-modal, #reservation-modal-overlay').fadeIn();
        }
    });

    // Handle the "Continue" button in the dialog
    $(document).on('click', '#continue-deletion', function () {
        if (cartItemIdToDelete) {
            $.ajax({
                url: cartData.ajax_url,
                method: 'POST',
                data: {
                    action: "tg_delete_cart_item",
                    cartItemIdToDelete: cartItemIdToDelete,
                    _wpnonce: cartData.nonce,
                },
                success: function (response) {
                    if (response.success) {
                        $(`.csi-remove-btn[cart-item="${cartItemIdToDelete}"]`).closest('.csi-main-div').remove();
                        cartItemIdToDelete = null;
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    alert('Failed to remove the item. Please try again.', error);
                },
            });
            $('#reservation-modal, #reservation-modal-overlay').fadeOut();
        }
    });

    $(document).on('click', '#cancel-deletion', function () {
        $('#reservation-modal, #reservation-modal-overlay').fadeOut();
        cartItemIdToDelete = null;
    });

    $(document).on('click', '#reservation-modal-overlay', function () {
        $('#reservation-modal, #reservation-modal-overlay').fadeOut();
        cartItemIdToDelete = null;
    });

    // Handle "Checkout" buttons click
    $('.cart-pay-full-btn').on('click', function (e) {
        let invalidOffers = false;
        $('.csi-main-div').each(function () {
            let reservedTime = $(this).attr('reserved-time');
            let patientAvailabilityCheck = $(this).attr('patient-availability');
            if (reservedTime === 'null' && patientAvailabilityCheck === 'false') {
                invalidOffers = true;
                return false;
            }
        });
        if (invalidOffers) {
            let expiredOfferHtml = `
            <div id="expired-offer-modal" style="display: none;">
                <div>
                    <h2>تنبيه!</h2>
                    <p>لديك عرض في سلة الشراء لم يتم تحديد موعده ، الرجاء تحديد موعد لكل العروض المختارة.</p>
                    <button id="close-expired-offer" class="dialog-button">إغلاق</button>
                </div>
            </div>
            <div id="expired-offer-overlay" style="display: none;"></div>`;
            if ($('#expired-offer-modal').length === 0) {
                $('body').append(expiredOfferHtml);
            }
            $('#expired-offer-modal, #expired-offer-overlay').fadeIn();
            $(document).on('click', '#close-expired-offer', function () {
                $('#expired-offer-modal, #expired-offer-overlay').fadeOut();
            });
            $(document).on('click', '#expired-offer-overlay', function () {
                $('#expired-offer-modal, #expired-offer-overlay').fadeOut();
            });
            return;
        } else {
            sendCartCheckoutApiRequest();
        }
    });

    $('.cart-partial-pay-btn').on('click', function (e) {
        let invalidOffers = false;
        $('.csi-main-div').each(function () {
            if ($('.csi-main-div[accept-instalments="true"]').length > 0) {
                $('#checkOutType').val(1);
            }
            let reservedTime = $(this).attr('reserved-time');
            let patientAvailabilityCheck = $(this).attr('patient-availability');
            if (reservedTime === 'null' && patientAvailabilityCheck === 'false') {
                invalidOffers = true;
                return false;
            }
        });

        if (invalidOffers) {
            let expiredOfferHtml = `<div id="expired-offer-modal" style="display: none;">
                <div>
                    <h2>تنبيه!</h2>
                    <p>لديك عرض في سلة الشراء لم يتم تحديد موعده ، الرجاء تحديد موعد لكل العروض المختارة.</p>
                    <button id="close-expired-offer" class="dialog-button">إغلاق</button>
                </div>
            </div>
            <div id="expired-offer-overlay" style="display: none;"></div>`;
            if ($('#expired-offer-modal').length === 0) {
                $('body').append(expiredOfferHtml);
            }
            $('#expired-offer-modal, #expired-offer-overlay').fadeIn();
            $(document).on('click', '#close-expired-offer', function () {
                $('#expired-offer-modal, #expired-offer-overlay').fadeOut();
            });
            $(document).on('click', '#expired-offer-overlay', function () {
                $('#expired-offer-modal, #expired-offer-overlay').fadeOut();
            });
            return;
        } else {
            $.ajax({
                url: cartData.ajax_url,
                method: "GET",
                data: {
                    action: "tg_get_cart_items_count",
                    check_out_type: $('#checkOutType').val(),
                    _wpnonce: cartData.nonce,
                },
                success: function (response) {
                    if (response.success) {
                        let modalHtml = response.data.html;
                        if (Number($('#cartItemsCount').val()) === 1) {
                            if ($('#reservation-modal').length === 0) {
                                $('body').append(modalHtml);
                            }
                            $('#reservation-modal, #reservation-modal-overlay').fadeIn();
                            $(document).on('click', '#continue-shopping', function () {
                                $('#reservation-modal, #reservation-modal-overlay').fadeOut();
                                window.location.href = '/offers';
                            });
                        } else {
                            sendCartCheckoutApiRequest();
                        }
                    }
                },
                error: function () {
                    console.error("Cart count API not working!");
                }
            });
        }
    });
    
    // Handle "Go to Payment" button click
    $(document).on('click', '#go-to-payment', function () {
        sendCartCheckoutApiRequest();
    });

    function sendCartCheckoutApiRequest() {
        $.ajax({
            url: cartData.ajax_url,
            method: "POST",
            data: {
                action: "tg_cart_checkout",
                cart_id: $('#cartId').val(),
                check_out_type: $('#checkOutType').val(),
                _wpnonce: cartData.nonce
            },
            success: function (response) {
                if (response.success) {
                    const apiResponse = response.data.data;
                    if ( apiResponse.status !== 'error') {
                        const cartDetails = apiResponse.data ? apiResponse.data.cart : null;
                        if (cartDetails) {
                            let checkOutType = $('#checkOutType').val();
                            const checkoutUrl = `checkout?cart_id=${cartDetails.cart_id}&bill_id=${cartDetails.bill_id}&check_out_type=${checkOutType}`;
                            window.location.href = checkoutUrl;
                        } else {
                            alert('No cart data returned.');
                        }
                    } else if ( apiResponse.status === 'error' && apiResponse.message === 'Cart is already checked out' ) {
                        // If cart is already checked out, fetch the bill details
                        $.ajax({
                            url: cartData.ajax_url,
                            method: "POST",
                            data: {
                                action: "tg_get_bill",
                                _wpnonce: cartData.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    const apiResponseBill = response.data.data;
                                    if ( apiResponseBill.status === 'error' ) {
                                        alert(apiResponseBill.message);
                                    } else {
                                        const billDetails = apiResponseBill.data ? apiResponseBill.data.bill : null;
                                        if (billDetails) {
                                            let checkOutType = $('#checkOutType').val();
                                            const checkoutUrl = `checkout?cart_id=${billDetails.cart_id}&bill_id=${billDetails.bill_id}&check_out_type=${checkOutType}`;
                                            window.location.href = checkoutUrl;
                                        } else {
                                            alert('No bill details returned.');
                                        }
                                    }                
                                } else {
                                    console.error('API Request Failed:', response.data.message);
                                }
                            },
                            error: function (error) {
                                console.error("AJAX request failed:", error);
                            }
                        });
                    } else if ( apiResponse.status === 'error' && apiResponse.message === 'Cart does not exist' ) {
                        alert(apiResponse.message);
                    } else {
                        alert(apiResponse.message);
                    }
                } else {
                    console.error('API Request Failed:', response.data.message);
                }
            },
            error: function (error) {
                console.error("AJAX request failed:", error);
            }
        });
    }    
});