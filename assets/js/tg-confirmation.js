jQuery(document).ready(function ($) {
    $('#brxe-confirmUser').on('click', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $responseDiv = $('#brxe-respDiv');


        // Disable the button to prevent multiple clicks
        $button.addClass('disabled').text('انتظر من فضلك...');

        // Step 1: Fetch registration data dynamically
        $.ajax({
            url: confirmationObj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_registration_data',
            },
            success: function (dataResponse) {
                if (dataResponse.success) {
                    var registrationData = dataResponse.data.registration_data;

                    // Step 2: Proceed with the confirm_user_registration API call
                    $.ajax({
                        url: confirmationObj.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'confirm_user_registration',
                            _wpnonce: confirmationObj.nonce,
                            id: registrationData.id,
                            confirmation_code: registrationData.confirmation_code,
                            password: registrationData.password,
                            password_confirmation: registrationData.password_confirmation,
                            name: registrationData.name,
                            phone: registrationData.phone,
                            email: registrationData.email,
                        },
                        success: function (response) {
                            if (response.success) {
                                $responseDiv.html('تم تأكيد إعادة التوجيه إلى صفحة تسجيل الدخول بنجاح');
                                $responseDiv.show();
                                setTimeout(function () {
                                    const urlParams = new URLSearchParams(window.location.search);
                                    const redirectTo = urlParams.get("redirect_to");
                                    let loginUrl = '/account/';
                                    if (redirectTo) {
                                        loginUrl += '?redirect_to=' + encodeURIComponent(redirectTo);
                                    }
                                    window.location.href = loginUrl;
                                }, 2000);
                            } else {
                                $button.removeClass('disabled').text('اﻟﺗﺎﻟﻲ');
                                $responseDiv.html(response.message || 'حدث خطأ. جارٍ إعادة التحميل...');
                                $responseDiv.show();
                                setTimeout(function () {
                                    location.reload();
                                }, 2000);
                            }
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.error('AJAX request failed:', textStatus, errorThrown);
                            console.error('Response:', jqXHR.responseText);
                        },
                    });
                } else {
                    // Handle case where registration data is not found
                    $button.removeClass('disabled').text('اﻟﺗﺎﻟﻲ');
                    $responseDiv.html('تعذر العثور على بيانات التسجيل. الرجاء إعادة المحاولة.');
                    $responseDiv.show();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Failed to fetch registration data:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);
                $button.removeClass('disabled').text('اﻟﺗﺎﻟﻲ');
                $responseDiv.html('خطأ أثناء استرداد بيانات التسجيل. الرجاء إعادة المحاولة.');
                $responseDiv.show();
            },
        });
    });
});
