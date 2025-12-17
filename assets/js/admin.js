/**
 * AI Agent Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Test API connection
        $('#aiagent-test-api').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const $result = $('#aiagent-test-result');
            
            $btn.prop('disabled', true).text('Testing...');
            $result.removeClass('success error').text('');
            
            $.ajax({
                url: aiagentAdmin.restUrl + 'test',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce
                },
                success: function(response) {
                    $result.addClass('success').text('✓ Connection successful!');
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || 'Connection failed';
                    $result.addClass('error').text('✗ ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test Connection');
                }
            });
        });

        // Toggle password visibility
        $('.aiagent-toggle-password').on('click', function() {
            const $input = $(this).siblings('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).text(type === 'password' ? 'Show' : 'Hide');
        });

        // Fetch URL via AJAX
        $('.aiagent-fetch-url').on('click', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const url = $btn.data('url');
            
            $btn.prop('disabled', true).text('Fetching...');
            
            $.ajax({
                url: aiagentAdmin.restUrl + 'fetch-url',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce
                },
                data: { url: url },
                success: function(response) {
                    if (response.success) {
                        $btn.text('✓ Added').addClass('button-primary');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to fetch';
                    $btn.text('✗ Failed');
                    alert(error);
                }
            });
        });

        // Color picker preview
        $('input[name="primary_color"]').on('input', function() {
            const color = $(this).val();
            $('.aiagent-color-preview').css('background-color', color);
        });
    });

})(jQuery);

