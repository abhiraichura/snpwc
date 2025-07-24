jQuery(document).ready(function($) {

    // Listen for a click on our specific "Notify Me" button.
    $(document).on('click', '.snpfwc-submit-button', function(e) {
        e.preventDefault();

        var button = $(this);
        var formContainer = button.closest('.snpfwc-subscribe-form');
        var wrapper = button.closest('.snpfwc-subscribe-form-wrapper');
        var emailInput = formContainer.find('.snpfwc-email-input');
        var messageDiv = wrapper.find('.snpfwc-form-message');
        
        var emailValue = emailInput.val();
        
        if (!emailInput.length || emailValue === '') {
            alert('Please enter a valid email address.');
            return;
        }

        var originalButtonText = button.text();
        messageDiv.text('').removeClass('success error').hide();
        button.prop('disabled', true).text('...');

        var formData = {
            action: 'snpfwc_subscribe',
            nonce: snpfwc_ajax.nonce,
            email: emailValue,
            product_id: wrapper.data('product-id'),
            variation_id: wrapper.data('variation-id')
        };
        
        $.post(snpfwc_ajax.ajax_url, formData, function(response) {
            if (response.success) {
                messageDiv.text(snpfwc_ajax.messages.success).addClass('success').show();
                formContainer.slideUp();
            } else {
                var errorMessage = response.data && response.data.message ? response.data.message : snpfwc_ajax.messages.error;
                messageDiv.text(errorMessage).addClass('error').show();
                button.prop('disabled', false).text(originalButtonText);
            }
        }).fail(function() {
            messageDiv.text(snpfwc_ajax.messages.error).addClass('error').show();
            button.prop('disabled', false).text(originalButtonText);
        });
    });

});