jQuery(document).ready(function ($) {
    $('#manual-sync-button').on('click', function (e) {
        e.preventDefault();
		
        $('.notice').remove();
        $(this).prop('disabled', true);
        $('#loader').show();
		
		$.ajax({
			url: manualSyncObj.ajax_url,
			type: 'POST',
			data: {
				action: 'manual_sync',
				nonce: manualSyncObj.nonce,
			},
			success: function(response) {
                let noticeClass = response.success ? 'notice-success' : 'notice-error';
                let message = response.success ? response.data.message : response.data.message;

                // Append notice
                $('#import-msg').prepend(`
                    <div class="notice ${noticeClass}">
                        <p>${message} <a href="/wp-admin/edit.php?post_type=product">Click Here</a> to see the listings.</p>
                    </div>
                `);
            },
            error: function(xhr, status, error) {
                $('#import-msg').prepend(`
                    <div class="notice notice-error">
                        <p>Error: ${error}</p>
                    </div>
                `);
            },
            complete: function() {
                $('#manual-sync-button').prop('disabled', false);
				$('#loader').hide();
				$('#import-msg').show();
            }
		});
    });
});
