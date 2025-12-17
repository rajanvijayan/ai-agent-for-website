/**
 * AI Agent Admin JavaScript
 */

(function($) {
    'use strict';

    // Media uploader instance
    let mediaUploader;

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

        // Avatar upload handler
        $('#upload_avatar_btn').on('click', function(e) {
            e.preventDefault();

            // If media uploader exists, open it
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            // Create the media uploader
            mediaUploader = wp.media({
                title: 'Select Avatar Image',
                button: {
                    text: 'Use as Avatar'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // When an image is selected
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                const url = attachment.sizes?.thumbnail?.url || attachment.url;
                
                $('#avatar_url').val(url);
                $('#avatar_preview').html('<img src="' + url + '" alt="Avatar">');
                $('#remove_avatar_btn').show();
            });

            mediaUploader.open();
        });

        // Remove avatar handler
        $('#remove_avatar_btn').on('click', function(e) {
            e.preventDefault();
            
            $('#avatar_url').val('');
            $('#avatar_preview').html('<span class="aiagent-avatar-placeholder"><svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></span>');
            $(this).hide();
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

