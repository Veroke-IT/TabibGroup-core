<?php

defined( 'ABSPATH' ) || exit;

function get_user_profile_shortcode() {
    if( !is_user_logged_in() ) {
        return 'Please log in to view your profile.';
    }

	global $tabibgroup_base_url;
    $current_user_id = get_current_user_id();
    $access_token = get_user_meta($current_user_id, 'api_access_token', true);
    $client = get_user_meta($current_user_id, 'api_client', true);
    $uid = get_user_meta($current_user_id, 'api_uid', true);

    // API URL
	$api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v1/users/get_profile';

    $response = wp_remote_get($api_url, [
        'headers' => [
            'access-token' => $access_token,
            'client' => $client,
            'uid' => $uid,
        ],
    ]);

    // HTML for the user profile display and edit form
    $output = '<div id="user-profile-container">
        <div class="profile-details">
            <form id="edit-profile-form">
                <div class="row">
                    <div class="col-50">
                        <label for="edit-name">الاسم الكامل</label>
                        <input type="text" id="edit-name" name="name" value="" />
                    </div>
                    <div class="col-50">
                        <label for="edit-email">عنوان البريد الإلكتروني</label>
                        <input type="email" id="edit-email" name="email" value="" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-50">
                        <label for="edit-phone">رقم الهاتف</label>
                        <input type="text" id="edit-phone" name="phone" value="" />
                    </div>
                    <div class="col-50">
                        <label for="edit-dob">تاريخ الميلاد</label>
                        <input type="date" id="edit-dob" name="dob" value="" />
                    </div>
                </div>
                <div class="col-50">
                    <label for="edit-gender">جنس</label>                    
                    <select id="edit-gender" name="gender">
						<option value="" selected>جنس</option>
                        <option value="M">مذكر</option>
                        <option value="F">نسائي</option>
                    </select>
                </div>
                <input type="submit" id="save_profile" value="يحفظ" />
            </form>
        </div>
        </div>';

    // Check if the API request was successful
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const apiResponse = <?php echo json_encode($data); ?>;
                // Set form fields with data
                if (apiResponse && apiResponse.data) {
                    document.querySelector('#edit-name').value = apiResponse.data.name || '';
                    document.querySelector('#edit-email').value = apiResponse.data.email || '';
                    document.querySelector('#edit-phone').value = apiResponse.data.phone || '';
                    document.querySelector('#edit-dob').value = apiResponse.data.date_of_birth || '';
                    var genderSelect = document.querySelector('#edit-gender');
                    if (genderSelect) {
                        if (apiResponse.data.gender === "M" || apiResponse.data.gender === "F" || apiResponse.data.gender === "") {
                            genderSelect.value = apiResponse.data.gender;
                        }
                    } 
                }
            });
        </script>
        <?php
    } else {
        echo '<script>console.error("Failed to fetch profile data.");</script>';
    }
    $output .= '
    <script>
        jQuery(document).ready(function($) {        
             // Initially make all form fields readonly and hide the save button
            $("form#edit-profile-form input, form#edit-profile-form select")
                .prop("readonly", true)
                .prop("disabled", true)
                .addClass("readonly");
            $("#save_profile, .cancel-update-btn").hide();

            // Handle Edit button click
            $(".edit-profile-btn").on("click", function() {
                // Make fields editable
                $("form#edit-profile-form input, form#edit-profile-form select")
                    .prop("readonly", false)
                    .prop("disabled", false)
                    .removeClass("readonly");
                $("form#edit-profile-form #edit-phone")
                    .prop("readonly", true)
                    .prop("disabled", true)
                    .addClass("readonly");                
                $(this).hide();
                // Show the save button
                $("#save_profile, .cancel-update-btn").show();
            });

            // Handle Cancel button click
            $(".cancel-update-btn").on("click", function() {
                // Make fields readonly again
                $("form#edit-profile-form input, form#edit-profile-form select")
                    .prop("readonly", true)
                    .prop("disabled", true)
                    .addClass("readonly");
                // Hide the save button
                $(this).hide();
                $("#save_profile").hide();
                $(".edit-profile-btn").show();
            });

            // Update profile form submission
            $("#edit-profile-form").submit(function(e) {
                e.preventDefault();

                // Disable the save button and indicate loading
                $(".edit-profile-btn").prop("disabled", true).val("ادخار...");

                // Format date_of_birth to YYYY-MM-DD
                let rawDate = $("#edit-dob").val();
                let formattedDate = rawDate ? new Date(rawDate).toISOString().split("T")[0] : "";

                // Prepare data
                var formData = {
                    name: $("#edit-name").val(),
                    email: $("#edit-email").val(),
                    date_of_birth: formattedDate,
                    gender: $("#edit-gender").val(),
                };

                $.ajax({
                    url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                    type: "POST",
                    data: {
                        action: "update_user_profile",
                        form_data: formData
                    },
                    success: function (response) {
                        if (response.success) {
                            $("#save_profile, .cancel-update-btn").hide();
                            $("#update-status").html("تم تحديث الملف الشخصي بنجاح.");                            
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $("#update-status").html("حدث خطأ أثناء التحديث.");
                        }
                    },
                    error: function (xhr) {
                        console.log("An error occurred in profile update: " + xhr.responseText);
                    },
                    complete: function () {
                        $(".edit-profile-btn").prop("disabled", false).val("يحفظ");
                    },
                });
            });
        });
    </script>';

    return $output;
}
add_shortcode('user_profile_form', 'get_user_profile_shortcode');

// AJAX callback function (action)
function update_user_profile() {
    if( isset($_POST['form_data']) ) {
        global $tabibgroup_base_url;
        // Get current user meta
        $current_user_id = get_current_user_id();
        $access_token = get_user_meta($current_user_id, 'api_access_token', true);
        $client = get_user_meta($current_user_id, 'api_client', true);
        $uid = get_user_meta($current_user_id, 'api_uid', true);

        $form_data = $_POST['form_data'];

        // Prepare the JSON body in the required format
        $request_body = json_encode(array(
            'user' => array(
                //"interested_categories" => "categories_value",
                "date_of_birth" => $form_data['date_of_birth'] ?? '',
                'gender'        => $form_data['gender'] ?? '',
                'email'         => $form_data['email'] ?? '',
                'name'          => $form_data['name'] ?? '',
            )
        ));

        // API URL
	    $api_url = $tabibgroup_base_url . '/api/endpoints/mobile/v1/users/update_profile';

        $response = wp_remote_post($api_url, array(
            'method'    => 'POST',
            'headers'   => array(
                'Content-Type' => 'application/json',
                'access-token' => $access_token,
                'client'       => $client,
                'uid'          => $uid,
            ),
            'body'      => $request_body
        ));

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $status = $response_body['status'] ?? '';

        if ($response_body && $response_code === 200 && $status === 'success') {
            wp_send_json_success(array('message' => 'Profile updated successfully.'));
            wp_safe_redirect('/account');
            exit;
        } else {
            // Error log API request failure
            $error_message_API_failure = $response_body['message'] ?? 'API request was unsuccessful.';
            error_log("Profile Update API Response: $error_message_API_failure");
        }
    } else {
        wp_send_json_error(array('message' => 'Form data not set.'));
    }
    wp_die();
}
add_action('wp_ajax_update_user_profile', 'update_user_profile');