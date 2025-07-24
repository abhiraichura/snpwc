jQuery(document).ready(function($) {
    // Initialize the WordPress color picker for our settings fields.
    if ($.fn.wpColorPicker) { // Check if wpColorPicker is available
        $('.snpfwc-color-picker').wpColorPicker();
    }
    
    // Initialize the date picker for our custom product field.
    if ($.fn.datepicker) { // Check if datepicker is available
        $('.snpfwc-datepicker').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }

    // Handle manual email notification
    $('.snpfwc-manual-notify').on('click', function(e) {
        e.preventDefault(); // Prevent the default link/button action

        var $button = $(this);
        var subscriberId = $button.data('subscriber-id'); // Get subscriber ID from data attribute
        var nonce = $button.data('nonce'); // Get nonce from data attribute

        if (!subscriberId || !nonce) {
            alert('Error: Missing subscriber ID or security token on button.');
            return; // Exit if critical data is missing
        }

        var confirmSend = confirm('Are you sure you want to send a manual notification to this subscriber?');

        if (confirmSend) {
            $button.prop('disabled', true).text('Sending...'); // Disable button and show loading text

            var ajaxData = {
                action: 'snpfwc_send_manual_email',
                subscriber_id: subscriberId,
                nonce: nonce
            };

            $.ajax({
                url: ajaxurl, // WordPress AJAX URL
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || 'Email sent successfully!'); // Display success message from PHP
                        $button.text('Sent!'); // Update button text on success
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to send email.')); // Display error message
                        $button.prop('disabled', false).text('Send Manual Email'); // Re-enable button on failure
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('An error occurred while sending the email. Please try again or check server logs.');
                    $button.prop('disabled', false).text('Send Manual Email'); // Re-enable button on AJAX error
                }
            });
        }
    });

    // Handle delete confirmation for single subscriber
    $('.snpfwc-delete-subscriber').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this subscriber? This cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Handle bulk delete confirmation
    $('#doaction').on('click', function(e) {
        var $this = $(this);
        var $action = $('#bulk-action-selector-top').val(); // Get selected bulk action

        if ($action === 'delete') { // Check if the selected action is 'delete'
            if (!$('input[name="subscriber_ids[]"]:checked').length) {
                alert('Please select at least one subscriber to delete.');
                e.preventDefault();
                return;
            }
            if (!confirm('Are you sure you want to delete the selected subscribers? This cannot be undone.')) {
                e.preventDefault();
            }
        }
    });
});