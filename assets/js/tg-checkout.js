jQuery(document).ready(function ($) {
    const $tabHeaders = $('.co-pm-tab-header');
    $tabHeaders.on('click', function() {
        $tabHeaders.removeClass('active');
        $(this).addClass('active');
    });
    $tabHeaders.first().addClass('active');

	if (typeof ApplePaySession !== 'undefined' && ApplePaySession.canMakePayments()) {
		$('.unsupportedBrowserMessage').hide();
		if (ApplePaySession.canMakePaymentsWithActiveCard('merchant.come.ADDS.DrDizzy')) {
			$('.applePayButtonContainer').show();
			$('.configureWalletMessage').hide();
			console.info('Startup Check: Device is capable of making Apple Pay payments with Active Card.');
		} else {
			console.error('Startup Check: Device is NOT capable of making Apple Pay payments with Active Card.');
			$('.configureWalletMessage').show();
			$('.applePayButtonContainer').hide();
		}
	} else {
		console.error('Startup Check: Device is not capable of making Apple Pay payments.');
		$('.unsupportedBrowserMessage').show();
		$('.applePayButtonContainer').hide();
	}

    function fetchWalletBalance() {
        return $.ajax({
            url: checkoutData.ajax_url,
            type: 'POST',
            data: { 
                action: 'apply_wallet_deduction',
                security: checkoutData.nonce
            },
            dataType: 'json'
        });
    }

    function updateUI(walletBalance) {
        const $walletCheckbox = $('#cp-sw-toggle-switch');
        const $invoiceAmountEl = $('#invoice-amount');        
        const $remainingAmountEl = $('#remaining-amount');
        const $walletDeductionEl = $('#wallet-deduction');
        const $walletDeductionLabel = $('#wallet-deduction-label');

        parseFloat($('#wallet-amount').text(walletBalance)) || 0;
        let totalDiscountEl = parseFloat($('#total-discount').text()) || 0;
        let totalDiscountedAmount = parseFloat($('#discounted-amount').text()) || 0;
        let totalInvAmount = parseFloat($('#total-amount').text()) || 0;
        let remainingAmount = parseFloat($('#remaining-amount').text()) || 0;
        let invoiceAmount = parseFloat($('#invoice-amount').text()) || 0;
        $walletDeductionEl.text("0.00");
        $walletDeductionLabel.hide();

        if ($walletCheckbox.length) {
            $walletCheckbox.on('change', function () {
                if ($(this).is(':checked') && walletBalance > 0) {
                    $('.co-pm-tab-header.active').removeClass('active');
                    let deduction = Math.min(walletBalance, totalDiscountedAmount);

                    let newInvoiceAmount = invoiceAmount - deduction;
                    let remainingAfterWallet = Math.max(0, totalDiscountedAmount - deduction);

                    $invoiceAmountEl.text(newInvoiceAmount.toFixed(2));
                    $walletDeductionEl.text(deduction.toFixed(2));
                    $walletDeductionLabel.show();
        
                    if (walletBalance >= deduction && remainingAfterWallet <= 0) {
                        $('.co-pm-tab-item').hide();
                        $('.co-pm-tab-header.active').removeClass('active');
                    } else {
                        $('.co-pm-tab-item').show();
                        $('.co-pm-tab-header[data-tab="1"]').addClass('active');
                    }
                } else {
                    $invoiceAmountEl.text(invoiceAmount.toFixed(2));
                    $remainingAmountEl.text(remainingAmount.toFixed(2));
                    $walletDeductionEl.text("0.00");
                    $walletDeductionLabel.hide();        
                    $('.co-pm-tab-item').show();
                }
            });
        
            if (walletBalance === 0) {
                $walletCheckbox.prop('disabled', true);
            }
        }        
    }

    fetchWalletBalance().done(function (response) {
        if (response.success) {
            updateUI(parseFloat(response.data.wallet_balance) || 0);
        } else {
            console.error('Failed to fetch wallet balance:', response.message);
        }
    }).fail(function (error) {
        console.error('AJAX error:', error);
    });

    $(document).on('click', '#checkout-button-btn', function () {
        const baseUrl = checkoutData.baseUrl;
        const urlParams = new URLSearchParams(window.location.search);
        const cart_id = urlParams.get('cart_id');
        const bill_id = urlParams.get('bill_id');
		const paymentCoverage = urlParams.get('check_out_type') === "0" ? "Full Amount" : "Partial Amount";
        const walletUsed = $('#cp-sw-toggle-switch').is(":checked");
        const paymentMethod = $('.co-pm-tab-header.active').attr('payment-method') || 'Wallet';
        const paymentMethodStr = paymentMethod.replace(/\+/g, ' ');
        const patientName = $("#currentUser").val();
		const userID = $("#currentUserID").val();
        const patientNameStr = patientName.replace(/\+/g, ' ');
        const discount = parseFloat($("#total-discount").text());
        const totalAmount = parseFloat($("#total-amount").text());
        let totalPrice = urlParams.get('check_out_type') === "0" ? parseFloat($("#invoice-amount").text()) : parseFloat($("#discounted-amount").text());
        const partialAmount = parseFloat($("#discounted-amount").text());
        const vatCharge = parseFloat($("#VAT-percentage").text());
        const walletAmount = parseFloat($("#wallet-deduction").text());
		if (walletUsed)	{
			totalPrice = totalPrice - walletAmount;
		}
        if ( paymentMethodStr === 'Credit Card' || paymentMethodStr === 'Mada' || paymentMethodStr === 'Apple Pay' || paymentMethodStr === 'Wallet' ) {
			if (paymentMethodStr === 'Apple Pay') {

				// Apple Pay logic starts
				if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
					console.warn('Apple Pay not supported or no active card.');
					return;
				}

				// Define ApplePayPaymentRequest
				var request = {
					countryCode: 'SA',
					currencyCode: 'SAR',
					merchantIdentifier: 'merchant.come.ADDS.DrDizzy',
					supportedNetworks: ['visa', 'masterCard', 'amex', 'discover' , 'mada'],
					merchantCapabilities: ['supports3DS'],
					total: {
						label: 'Apple Pay',
						amount: totalPrice
					}
				};

				var session = new ApplePaySession(6, request);

				function getSession(url, bill) {
					return new Promise((resolve, reject) => {
						const requestUrl = `${baseUrl}/api/word_press/v1/apple_pay/verify?bill_id=${bill}&url=${url}`;
						fetch(requestUrl, {
							method: "POST",
							redirect: "follow"
						})
							.then(response => {
							if (!response.ok) {
								return response.text().then(text => {
									reject({
										status: response.status,
										statusText: response.statusText,
										body: text
									});
								});
							}
							return response.json();
						})
							.then(data => resolve(data))
							.catch(error => {
							reject({
								status: "network_error",
								message: error.message || "Unknown error",
							});
						});
					});
				}

				function sendtopayfort(data) {
					return new Promise((resolve, reject) => {
						const requestUrl = `${baseUrl}/api/word_press/v1/apple_pay/checkout_session`;
						fetch(requestUrl, {
							method: "POST",
							headers: {
								"Content-Type": "application/json"
							},
							body: JSON.stringify(Object.assign({
								bill_id: bill_id,
								user_id: userID,
								amount: totalPrice
							}, data)),
							redirect: "follow"
						})
							.then(response => {
							if (!response.ok) {
								return response.text().then(text => {
									reject({
										status: response.status,
										statusText: response.statusText,
										body: text
									});
								});
							}
							return response.json();
						})
							.then(data => resolve(data))
							.catch(error => {
							reject({
								status: "network_error",
								message: error.message || "Unknown error",
							});
						});
					});
				}

				session.onvalidatemerchant = (event) => {
					const validationURL = event.validationURL;
					getSession(validationURL, bill_id).then((responses) => {
						session.completeMerchantValidation(responses);
					}).catch((error) => {
						console.error("Merchant validation failed:", error);
						session.abort();
					});
				};

				session.onpaymentauthorized = event => {
					const token = event.payment;
					sendtopayfort(token).then(function(response) {
						var responseMessage = response.response.response_message;
						if (responseMessage == 'Success') {
							session.completePayment(ApplePaySession.STATUS_SUCCESS);
							book_appointment();
						} else {
							session.completePayment(ApplePaySession.STATUS_FAILURE);
						}
					});
				};
				session.oncancel = event => {
					// Payment canceled by WebKit
					console.log("Payment Cancelled.", event);
				};
				session.begin();
			}
			else {
				book_appointment();
			}
			function book_appointment() {
				// Fetch appointment data dynamically from backend
				$.ajax({
					url: `${baseUrl}/api/endpoints/mobile/v2/cart_items.json?lang=en&cart_id=${cart_id}`,
					method: "GET",
					beforeSend: function() {
						$(this).prop('disabled', true).text('يعالج...');
					},
					success: function(response) {
						if (response && response.data && response.data.cart_items) {
							// Collect dynamic data from frontend
							let appointmentData = {
								"bill_id": String(bill_id),
								"transaction_id": "",
								"payment_person_name": '',
								"cod_charge": '',
								"payment_person_number": '',
								"promo_code": '',
								"payment_person_address": '',
								"iqama_number": '',
								"payment_method_ref_no": '',
								"payment_method": String(paymentMethodStr),
								"appointment_data": [],
								"discount": String(discount),
								"final_price": String(totalAmount),
								"is_wallet_used": String(walletUsed),
								"partial_amount": String(partialAmount - discount),
								"total_price": String(totalPrice),
								"vat_charge": String(vatCharge),
								"wallet_amount": String(walletAmount)
							};

							localStorage.setItem("payfortData", JSON.stringify(appointmentData));

							// Loop through the fetched cart items to populate appointment_data array
							response.data.cart_items.forEach(item => {
								appointmentData.appointment_data.push({
									"applied_vat_percentage": item.applied_vat_percentage ? item.applied_vat_percentage : '0' ,
									"appointment_type": String(item.appointment_type),
									"cart_id": String(cart_id),
									"cod_charge": '',
									"date": item.reserved_date != null ? String(item.reserved_date) : '',
									"date_of_birth": '',
									"discount": String(item.discount),
									"doctor_id": String(item.doctor_id),
									"final_price": String(item.new_price - item.discount),
									"gender": '',
									"is_other_patient": '',
									"is_prime_discount_availed": item.is_prime_discount_availed,
									"offer_claim_id": '',
									"offer_id": String(item.offer_id),
									"partial_amount": String(item.discounted_price),
									"patient_address": '',
									"patient_availability": '',
									"patient_name": String(patientNameStr),
									"payment_method": '',
									"payment_person_address": '',
									"payment_person_name": '',
									"payment_person_number": '',
									"payment_coverage": String(paymentCoverage),
									"promo_code": '',
									"relation": '',
									"shipping_fee": '',
									"time": item.reserved_time != null ? String(item.reserved_time) : '',
									"total_price": String(item.new_price),
									"transaction_id": '',
									"vat_charge": ''
								});
							});

							// localStorage.setItem("data", JSON.stringify(data));
							// localStorage.setItem("payfortData", JSON.stringify(payfortData));
							// console.log("Stored appointmentData:", JSON.parse(localStorage.getItem("appointmentData")));

							// Send the dynamic data to the backend API
							// console.log("Final data sent:", JSON.stringify(appointmentData));
							$.ajax({
								url: checkoutData.ajax_url,
								method: "POST",
								cache: false,
								data: {
									action: "tg_update_bill_checkout",
									security: checkoutData.nonce,
									tg_bill: JSON.stringify(appointmentData)
								},
								success: function (response) {
									// if (response.success) {
									//console.log(response);
									// if ( paymentMethodStr === 'Apple Pay' || paymentMethodStr === 'Wallet' ) {
									if ( paymentMethodStr === 'Wallet' ) {
										const redirectUrl = `/thank-you/?bill_id=${bill_id}&payment_method=${paymentMethodStr}`;
										window.location.replace(redirectUrl);
									} else {
										const redirectUrl = `/process-payment?bill_id=${bill_id}&amount=${appointmentData.total_price}&payment_method=${paymentMethodStr}`;
										window.location.href = redirectUrl;
									}
									// } else {
									//     console.error("Error: ", response);
									// }
								},
								error: function () {
									console.error("Checkout API not working!");
								}
							});
						} else {
							console.error("Error fetching cart items:", response.message);
						}
					},
					error: function (xhr, status, error) {
						console.error("Checkout API failed!", xhr.responseText, status, error);
					},
					complete: function() {
						$(this).prop('disabled', false).text('الدفع');
					}
				});
			}
        } else if ( paymentMethodStr === 'Tabby' || paymentMethodStr === 'Tamara' ) {
            const tabbyData = {
                "bill_id": bill_id,
                "payment_method": paymentMethodStr
            };
            //console.log(tabbyData);
            // Send the dynamic data to the backend API
            $.ajax({
                url: checkoutData.ajax_url,
                method: "POST",
                data: {
                    action: "tg_update_bill_tabby_tamara_checkout",
                    security: checkoutData.nonce,
                    tabby_bill_data: JSON.stringify(tabbyData)
                },
                success: function (response) {
                    //console.log(response);
                    if (response.success) {
                        //console.log(response.data.message);
                        if ( paymentMethodStr === 'Tabby' ) {
                            // Send Tabby 2nd API request
                            $.ajax({
                                url: checkoutData.ajax_url,
                                method: "POST",
                                data: {
                                    action: "tg_tabby_checkout",
                                    security: checkoutData.nonce,
                                    amount: totalPrice
                                },
                                success: function (response) {
                                    if (response.success) {
                                        window.location.href = response.data.web_url;
                                    } else {
                                        $('#checkout-button-btn').addClass('disabled');
                                        $('.checkout-btn-wrapper').append(`<p>${response.data.message}</p>`);
                                        console.error('Error: ' + response.data.message);
                                    }
                                },
                                error: function () {
                                    console.error("Tabby Checkout API not working!");
                                }
                            });
                        } else {
                            // Send Tamara 2nd API request
                            $.ajax({
                                url: checkoutData.ajax_url,
                                method: "POST",
                                data: {
                                    action: "tg_tamara_checkout",
                                    security: checkoutData.nonce,
                                    amount: totalPrice
                                },
                                success: function (response) {
                                    if (response.success) {
                                        window.location.href = response.data.checkout_url;
                                    } else {
                                        $('#checkout-button-btn').addClass('disabled');
                                        $('.checkout-btn-wrapper').append(`<p>${response.data.message}</p>`);
                                        console.error('Error: ' + response.data.message);
                                    }
                                },
                                error: function () {
                                    console.error("Tamara Checkout API not working!");
                                }
                            });
                        }
                    } else {
                        console.error("Error: ", response.data.message);
                    }
                },
                error: function () {
                    console.error("Checkout API not working!");
                }
            });
        } else {
            alert("طريقة الدفع هذه غير مدعومة.");
        }
    });

});