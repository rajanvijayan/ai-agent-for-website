/**
 * AI Agent Admin JavaScript
 */

(function ($) {
    'use strict';

    // Media uploader instance
    let mediaUploader;

    $(document).ready(function () {
        // Test API connection
        $('#aiagent-test-api').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const $result = $('#aiagent-test-result');

            $btn.prop('disabled', true).text('Testing...');
            $result.removeClass('success error').text('');

            $.ajax({
                url: aiagentAdmin.restUrl + 'test',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function (response) {
                    $result.addClass('success').text('✓ Connection successful!');
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Connection failed';
                    $result.addClass('error').text('✗ ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Test Connection');
                },
            });
        });

        // Avatar upload handler
        $('#upload_avatar_btn').on('click', function (e) {
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
                    text: 'Use as Avatar',
                },
                multiple: false,
                library: {
                    type: 'image',
                },
            });

            // When an image is selected
            mediaUploader.on('select', function () {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                const url = attachment.sizes?.thumbnail?.url || attachment.url;

                $('#avatar_url').val(url);
                $('#avatar_preview').html('<img src="' + url + '" alt="Avatar">');
                $('#remove_avatar_btn').show();
                // Update preview
                $('#preview-avatar').html('<img src="' + url + '" alt="Avatar">');
            });

            mediaUploader.open();
        });

        // Remove avatar handler
        $('#remove_avatar_btn').on('click', function (e) {
            e.preventDefault();

            $('#avatar_url').val('');
            $('#avatar_preview').html(
                '<span class="aiagent-avatar-placeholder"><svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></span>'
            );
            $('#preview-avatar').html(
                '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>'
            );
            $(this).hide();
        });

        // Toggle password visibility
        $('.aiagent-toggle-password').on('click', function () {
            const $input = $(this).siblings('input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).text(type === 'password' ? 'Show' : 'Hide');
        });

        // Fetch URL via AJAX
        $('.aiagent-fetch-url').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const url = $btn.data('url');

            $btn.prop('disabled', true).text('Fetching...');

            $.ajax({
                url: aiagentAdmin.restUrl + 'fetch-url',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                data: { url: url },
                success: function (response) {
                    if (response.success) {
                        $btn.text('✓ Added').addClass('button-primary');
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    }
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to fetch';
                    $btn.text('✗ Failed');
                    alert(error);
                },
            });
        });

        // Color picker preview - update widget preview
        $('input[name="primary_color"]').on('input', function () {
            const color = $(this).val();
            $('.aiagent-color-preview').css('background-color', color);
            $('#aiagent-preview-widget').css('--aiagent-primary', color);
        });

        // AI Name preview update
        $('#ai_name').on('input', function () {
            const name = $(this).val() || 'AI Assistant';
            $('#preview-name').text(name);
        });

        // Welcome message preview update
        $('#welcome_message').on('input', function () {
            const message = $(this).val() || 'Hello! How can I help you today?';
            $('#preview-welcome').text(message);
        });

        // AI Suggestion buttons
        $('.aiagent-ai-suggest').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const $status = $btn.siblings('.aiagent-suggest-status');
            const target = $btn.data('target');
            const type = $btn.data('type');
            const $target = $('#' + target);

            $btn.addClass('loading').prop('disabled', true);
            $status.removeClass('success error').text('Generating...');

            $.ajax({
                url: aiagentAdmin.restUrl + 'ai-suggest',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify({ type: type }),
                success: function (response) {
                    if (response.success && response.suggestion) {
                        $target.val(response.suggestion).trigger('input');
                        $status.addClass('success').text('✓ Suggestion applied!');
                        setTimeout(function () {
                            $status.text('');
                        }, 3000);
                    } else {
                        $status.addClass('error').text('✗ No suggestion generated');
                    }
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to generate';
                    $status.addClass('error').text('✗ ' + error);
                },
                complete: function () {
                    $btn.removeClass('loading').prop('disabled', false);
                },
            });
        });

        // Character count for knowledge base text
        $('#kb_text').on('input', function () {
            const count = $(this).val().length;
            $('#kb-char-count').text(count.toLocaleString());
        });

        // Detect pillar pages
        $('#aiagent-detect-pillar').on('click', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const $status = $('.aiagent-detect-status');
            const $results = $('#aiagent-pillar-results');
            const $list = $('#aiagent-pillar-list');

            $btn.prop('disabled', true);
            $status.text('Analyzing your website content...');
            $results.hide();

            $.ajax({
                url: aiagentAdmin.restUrl + 'detect-pillar-pages',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function (response) {
                    if (response.success && response.pages && response.pages.length > 0) {
                        $status.text('');
                        $list.empty();

                        response.pages.forEach(function (page) {
                            const $item = $(
                                '<div class="aiagent-pillar-item">' +
                                    '<input type="checkbox" class="pillar-checkbox" data-url="' +
                                    page.url +
                                    '" checked>' +
                                    '<span class="pillar-title">' +
                                    page.title +
                                    '</span>' +
                                    '<span class="pillar-type">' +
                                    page.type +
                                    '</span>' +
                                    '</div>'
                            );
                            $list.append($item);
                        });

                        $results.show();
                    } else {
                        $status.text('No pillar pages detected.');
                    }
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to detect pages';
                    $status.text('Error: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                },
            });
        });

        // Add selected pillar pages
        $('#aiagent-add-selected-pillar').on('click', function () {
            const $checked = $('.pillar-checkbox:checked');
            addPillarPages($checked);
        });

        // Add all pillar pages
        $('#aiagent-add-all-pillar').on('click', function () {
            const $all = $('.pillar-checkbox');
            addPillarPages($all);
        });

        function addPillarPages($checkboxes) {
            if ($checkboxes.length === 0) {
                alert('No pages selected');
                return;
            }

            const urls = [];
            $checkboxes.each(function () {
                urls.push($(this).data('url'));
            });

            let completed = 0;
            const total = urls.length;
            const $status = $('.aiagent-detect-status');

            $status.text('Adding pages: 0/' + total);

            urls.forEach(function (url) {
                $.ajax({
                    url: aiagentAdmin.restUrl + 'fetch-url',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': aiagentAdmin.nonce,
                    },
                    data: { url: url },
                    complete: function () {
                        completed++;
                        $status.text('Adding pages: ' + completed + '/' + total);

                        if (completed === total) {
                            $status.text('✓ All pages added! Refreshing...');
                            setTimeout(function () {
                                location.reload();
                            }, 1000);
                        }
                    },
                });
            });
        }
    });
})(jQuery);
