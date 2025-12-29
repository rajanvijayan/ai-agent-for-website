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

            $btn.prop('disabled', true);
            $btn.find('.dashicons')
                .removeClass('dashicons-yes-alt')
                .addClass('dashicons-update spin');
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
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons')
                        .removeClass('dashicons-update spin')
                        .addClass('dashicons-yes-alt');
                },
            });
        });

        // Toggle password visibility
        $('.aiagent-toggle-password').on('click', function () {
            const $input = $(this).siblings('input');
            const $icon = $(this).find('.dashicons');
            const isPassword = $input.attr('type') === 'password';

            $input.attr('type', isPassword ? 'text' : 'password');
            $icon
                .removeClass(isPassword ? 'dashicons-visibility' : 'dashicons-hidden')
                .addClass(isPassword ? 'dashicons-hidden' : 'dashicons-visibility');
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

        // Widget position preview update
        $('#widget_position').on('change', function () {
            const position = $(this).val();
            const $preview = $('#aiagent-preview-widget');

            if (position === 'bottom-left') {
                $preview.addClass('position-bottom-left');
            } else {
                $preview.removeClass('position-bottom-left');
            }
        });

        // Initialize position on page load
        (function () {
            const position = $('#widget_position').val();
            if (position === 'bottom-left') {
                $('#aiagent-preview-widget').addClass('position-bottom-left');
            }
        })();

        // Show Powered By toggle - update preview and text field visibility
        $('#show_powered_by').on('change', function () {
            const $powered = $('#preview-powered');
            const $textRow = $('.powered-by-text-row');
            if ($(this).is(':checked')) {
                $powered.removeClass('hidden');
                $textRow.slideDown(200);
            } else {
                $powered.addClass('hidden');
                $textRow.slideUp(200);
            }
        });

        // Powered By text - update preview in real-time
        $('#powered_by_text').on('input', function () {
            const text = $(this).val() || $(this).attr('placeholder');
            $('#preview-powered').text(text);
        });

        // Initialize powered by state on page load
        (function () {
            if (!$('#show_powered_by').is(':checked')) {
                $('#preview-powered').addClass('hidden');
                $('.powered-by-text-row').hide();
            }
        })();

        // Phone field preview toggle (User Info tab)
        $('#require_phone').on('change', function () {
            const $phoneField = $('#phone-preview-field');
            if ($(this).is(':checked')) {
                $phoneField.slideDown(200);
            } else {
                $phoneField.slideUp(200);
            }
        });

        // Phone required toggle (User Info tab)
        $('#phone_required').on('change', function () {
            const $requiredStar = $('#phone-required-star');
            if ($(this).is(':checked')) {
                $requiredStar.show();
            } else {
                $requiredStar.hide();
            }
        });

        // Button Size - update preview
        $('#widget_button_size').on('change', function () {
            const size = $(this).val();
            const $toggle = $('#preview-toggle');
            $toggle.removeClass('size-small size-medium size-large').addClass('size-' + size);
        });

        // Open Animation - update preview
        $('#widget_animation').on('change', function () {
            const animation = $(this).val();
            const $window = $('#preview-window');

            // Remove current animation class
            $window.removeClass('animation-slide animation-fade animation-scale animation-none');

            // Add new animation class with a slight delay to trigger the animation
            setTimeout(function () {
                $window.addClass('animation-' + animation);
            }, 50);
        });

        // Enable Sound - update preview
        $('#widget_sound').on('change', function () {
            const $indicator = $('#preview-sound-indicator');
            if ($(this).is(':checked')) {
                $indicator.fadeIn(200);
            } else {
                $indicator.fadeOut(200);
            }
        });

        // AI Suggestion Modal
        let currentSuggestTarget = null;
        let currentSuggestType = null;
        const $modal = $('#aiagent-suggest-modal');
        const $modalLoading = $('#aiagent-modal-loading');
        const $modalResult = $('#aiagent-modal-result');
        const $modalError = $('#aiagent-modal-error');
        const $modalSuggestion = $('#aiagent-modal-suggestion');
        const $modalTitle = $('#aiagent-modal-title');

        function showModal() {
            $modal.fadeIn(200);
        }

        function hideModal() {
            $modal.fadeOut(200);
            currentSuggestTarget = null;
            currentSuggestType = null;
        }

        function generateSuggestion() {
            $modalLoading.show();
            $modalResult.hide();
            $modalError.hide();
            $('#aiagent-modal-apply, #aiagent-modal-regenerate').prop('disabled', true);

            $.ajax({
                url: aiagentAdmin.restUrl + 'ai-suggest',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify({ type: currentSuggestType }),
                success: function (response) {
                    $modalLoading.hide();
                    if (response.success && response.suggestion) {
                        $modalSuggestion.val(response.suggestion);
                        $modalResult.show();
                        $('#aiagent-modal-apply, #aiagent-modal-regenerate').prop(
                            'disabled',
                            false
                        );
                    } else {
                        $modalError.find('p').text('No suggestion generated. Please try again.');
                        $modalError.show();
                        $('#aiagent-modal-regenerate').prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    $modalLoading.hide();
                    const error = xhr.responseJSON?.message || 'Failed to generate suggestion';
                    $modalError.find('p').text(error);
                    $modalError.show();
                    $('#aiagent-modal-regenerate').prop('disabled', false);
                },
            });
        }

        // Open modal on button click
        $('.aiagent-ai-suggest-btn').on('click', function (e) {
            e.preventDefault();

            currentSuggestTarget = $(this).data('target');
            currentSuggestType = $(this).data('type');

            const title =
                currentSuggestType === 'welcome'
                    ? 'AI Welcome Message Suggestion'
                    : 'AI System Instruction Suggestion';
            $modalTitle.text(title);

            showModal();
            generateSuggestion();
        });

        // Regenerate button
        $('#aiagent-modal-regenerate').on('click', function () {
            generateSuggestion();
        });

        // Apply button
        $('#aiagent-modal-apply').on('click', function () {
            if (currentSuggestTarget) {
                const suggestion = $modalSuggestion.val();
                $('#' + currentSuggestTarget)
                    .val(suggestion)
                    .trigger('input');
            }
            hideModal();
        });

        // Cancel/Close buttons for AI suggestion modal only
        $(document).on(
            'click',
            '#aiagent-modal-cancel, #aiagent-suggest-modal .aiagent-modal-close, #aiagent-suggest-modal .aiagent-modal-overlay',
            function () {
                hideModal();
            }
        );

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

        // ===============================
        // File Upload Functionality
        // ===============================
        const $dropZone = $('#aiagent-file-drop-zone');
        const $fileInput = $('#aiagent-file-input');
        const $progressContainer = $('#aiagent-file-upload-progress');
        const $resultsContainer = $('#aiagent-file-upload-results');

        // Drag and drop handlers
        $dropZone.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        $dropZone.on('dragleave dragend drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        $dropZone.on('drop', function (e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files);
            }
        });

        // File input change handler
        $fileInput.on('change', function () {
            if (this.files.length > 0) {
                handleFileUpload(this.files);
                // Reset input so same file can be selected again
                $(this).val('');
            }
        });

        function handleFileUpload(files) {
            $resultsContainer.empty();

            for (let i = 0; i < files.length; i++) {
                uploadFile(files[i]);
            }
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);

            // Show progress
            const $progressItem = $(`
                <div class="aiagent-file-progress-item">
                    <span class="aiagent-file-name">${escapeHtml(file.name)}</span>
                    <div class="aiagent-progress-bar">
                        <div class="aiagent-progress-fill" style="width: 0%"></div>
                    </div>
                    <span class="aiagent-file-status">0%</span>
                </div>
            `);

            $progressContainer.show().append($progressItem);

            $.ajax({
                url: aiagentAdmin.restUrl + 'upload-file',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener(
                        'progress',
                        function (evt) {
                            if (evt.lengthComputable) {
                                const percent = Math.round((evt.loaded / evt.total) * 100);
                                $progressItem
                                    .find('.aiagent-progress-fill')
                                    .css('width', percent + '%');
                                $progressItem.find('.aiagent-file-status').text(percent + '%');
                            }
                        },
                        false
                    );
                    return xhr;
                },
                success: function (response) {
                    $progressItem.find('.aiagent-progress-fill').css('width', '100%');
                    $progressItem.find('.aiagent-file-status').text('✓').addClass('success');

                    // Add success result
                    const charCount = response.char_count
                        ? response.char_count.toLocaleString()
                        : 'N/A';
                    $resultsContainer.append(`
                        <div class="aiagent-file-result">
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <strong>${escapeHtml(response.filename)}</strong> - ${charCount} characters extracted
                        </div>
                    `);

                    // Remove progress item after delay
                    setTimeout(function () {
                        $progressItem.fadeOut(300, function () {
                            $(this).remove();
                            if (
                                $progressContainer.find('.aiagent-file-progress-item').length === 0
                            ) {
                                $progressContainer.hide();
                            }
                        });
                    }, 1500);
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Upload failed';
                    $progressItem.find('.aiagent-progress-fill').css({
                        width: '100%',
                        background: '#dc3232',
                    });
                    $progressItem.find('.aiagent-file-status').text('✗').addClass('error');

                    // Add error result
                    $resultsContainer.append(`
                        <div class="aiagent-file-result error">
                            <span class="dashicons dashicons-warning"></span>
                            <strong>${escapeHtml(file.name)}</strong> - ${escapeHtml(error)}
                        </div>
                    `);

                    // Remove progress item after delay
                    setTimeout(function () {
                        $progressItem.fadeOut(300, function () {
                            $(this).remove();
                            if (
                                $progressContainer.find('.aiagent-file-progress-item').length === 0
                            ) {
                                $progressContainer.hide();
                            }
                        });
                    }, 2000);
                },
            });
        }

        // Delete file handler
        $(document).on('click', '.aiagent-delete-file', function () {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }

            const $btn = $(this);
            const fileId = $btn.data('file-id');
            const kbIndex = $btn.data('kb-index');

            $btn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: aiagentAdmin.restUrl + 'delete-file',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify({
                    file_id: fileId,
                    kb_index: kbIndex !== undefined ? kbIndex : null,
                }),
                success: function () {
                    $btn.closest('tr').fadeOut(300, function () {
                        $(this).remove();
                    });
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Delete failed';
                    alert(error);
                    $btn.prop('disabled', false).text('Delete');
                },
            });
        });

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ===============================
        // Google Drive Integration
        // ===============================

        // Connect button handler
        $('#aiagent-gdrive-connect').on('click', function () {
            const $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: aiagentAdmin.restUrl + 'gdrive/auth-url',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function (response) {
                    if (response.auth_url) {
                        window.location.href = response.auth_url;
                    }
                },
                error: function (xhr) {
                    alert(xhr.responseJSON?.message || 'Failed to get auth URL');
                    $btn.prop('disabled', false);
                },
            });
        });

        // Disconnect button handler
        $('.aiagent-gdrive-disconnect').on('click', function () {
            if (!confirm('Are you sure you want to disconnect from Google Drive?')) {
                return;
            }

            $.ajax({
                url: aiagentAdmin.restUrl + 'gdrive/disconnect',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function () {
                    location.reload();
                },
            });
        });

        // Google Calendar connect handler
        $('#aiagent-gcalendar-connect').on('click', function () {
            const $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: aiagentAdmin.restUrl + 'gcalendar/auth-url',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function (response) {
                    if (response.auth_url) {
                        window.location.href = response.auth_url;
                    }
                },
                error: function (xhr) {
                    alert(xhr.responseJSON?.message || 'Failed to get auth URL');
                    $btn.prop('disabled', false);
                },
            });
        });

        // Google Calendar disconnect handler
        $('.aiagent-gcalendar-disconnect').on('click', function () {
            if (!confirm('Are you sure you want to disconnect from Google Calendar?')) {
                return;
            }

            $.ajax({
                url: aiagentAdmin.restUrl + 'gcalendar/disconnect',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function () {
                    location.reload();
                },
            });
        });

        // Enable/disable connect button based on credentials
        $('#gcalendar_client_id, #gcalendar_client_secret').on('input', function () {
            const clientId = $('#gcalendar_client_id').val().trim();
            const clientSecret = $('#gcalendar_client_secret').val().trim();
            $('#aiagent-gcalendar-connect').prop('disabled', !clientId || !clientSecret);
        });

        // Load Google Drive files
        function loadGDriveFiles(query = '') {
            const $container = $('#aiagent-gdrive-files');
            $container.html(
                '<p class="aiagent-loading"><span class="spinner is-active"></span> Loading files...</p>'
            );

            $.ajax({
                url: aiagentAdmin.restUrl + 'gdrive/files',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                data: { query: query },
                success: function (response) {
                    if (!response.files || response.files.length === 0) {
                        $container.html('<p class="aiagent-empty">No files found</p>');
                        return;
                    }

                    let html = '<div class="aiagent-file-list">';
                    response.files.forEach(function (file) {
                        const icon = getFileIcon(file.mimeType);
                        html += `
                            <label class="aiagent-file-item">
                                <input type="checkbox" value="${file.id}" data-name="${escapeHtml(file.name)}">
                                <span class="dashicons ${icon}"></span>
                                <span class="aiagent-file-item-name">${escapeHtml(file.name)}</span>
                            </label>
                        `;
                    });
                    html += '</div>';

                    $container.html(html);
                    $('.aiagent-gdrive-actions').show();
                },
                error: function (xhr) {
                    $container.html(
                        '<p class="aiagent-error">Error: ' +
                            (xhr.responseJSON?.message || 'Failed to load files') +
                            '</p>'
                    );
                },
            });
        }

        // Get file icon based on mime type
        function getFileIcon(mimeType) {
            if (mimeType.includes('document') || mimeType.includes('word')) {
                return 'dashicons-media-document';
            } else if (mimeType.includes('spreadsheet') || mimeType.includes('csv')) {
                return 'dashicons-media-spreadsheet';
            } else if (mimeType.includes('pdf')) {
                return 'dashicons-pdf';
            } else if (mimeType.includes('text')) {
                return 'dashicons-text';
            }
            return 'dashicons-media-default';
        }

        // Initialize Google Drive file browser if on knowledge page
        if ($('#aiagent-gdrive-files').length) {
            loadGDriveFiles();
        }

        // Google Drive search
        let gdriveSearchTimeout;
        $('#aiagent-gdrive-search').on('input', function () {
            clearTimeout(gdriveSearchTimeout);
            const query = $(this).val();
            gdriveSearchTimeout = setTimeout(function () {
                loadGDriveFiles(query);
            }, 500);
        });

        // Google Drive refresh
        $('#aiagent-gdrive-refresh').on('click', function () {
            loadGDriveFiles($('#aiagent-gdrive-search').val());
        });

        // Enable/disable import button based on selection
        $(document).on('change', '#aiagent-gdrive-files input[type="checkbox"]', function () {
            const hasSelection = $('#aiagent-gdrive-files input:checked').length > 0;
            $('#aiagent-gdrive-import-selected').prop('disabled', !hasSelection);
        });

        // Import selected Google Drive files
        $('#aiagent-gdrive-import-selected').on('click', function () {
            const $btn = $(this);
            const $status = $btn.siblings('.aiagent-import-status');
            const $checked = $('#aiagent-gdrive-files input:checked');

            if ($checked.length === 0) return;

            $btn.prop('disabled', true);
            $status.text('Importing...');

            let completed = 0;
            let errors = 0;
            const total = $checked.length;

            $checked.each(function () {
                const fileId = $(this).val();
                const fileName = $(this).data('name');

                $.ajax({
                    url: aiagentAdmin.restUrl + 'gdrive/import',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': aiagentAdmin.nonce,
                        'Content-Type': 'application/json',
                    },
                    data: JSON.stringify({ file_id: fileId }),
                    success: function () {
                        completed++;
                        updateImportStatus();
                    },
                    error: function () {
                        completed++;
                        errors++;
                        updateImportStatus();
                    },
                });
            });

            function updateImportStatus() {
                $status.text(
                    `Imported ${completed}/${total}` + (errors > 0 ? ` (${errors} failed)` : '')
                );
                if (completed === total) {
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                }
            }
        });

        // ===============================
        // Confluence Integration
        // ===============================

        // Connect button handler
        $('#aiagent-confluence-connect').on('click', function () {
            const $btn = $(this);
            const $status = $btn.siblings('.aiagent-confluence-status');

            // First save settings, then test connection
            $btn.prop('disabled', true);
            $status.text('Testing connection...');

            // Submit form first to save settings
            $btn.closest('form').submit();
        });

        // Disconnect button handler
        $('.aiagent-confluence-disconnect').on('click', function () {
            if (!confirm('Are you sure you want to disconnect from Confluence?')) {
                return;
            }

            $.ajax({
                url: aiagentAdmin.restUrl + 'confluence/disconnect',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function () {
                    location.reload();
                },
            });
        });

        // Load Confluence spaces
        function loadConfluenceSpaces() {
            const $select = $('#aiagent-confluence-space');

            $.ajax({
                url: aiagentAdmin.restUrl + 'confluence/spaces',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function (response) {
                    $select.empty();
                    $select.append('<option value="">Select a space...</option>');

                    if (response.spaces && response.spaces.length > 0) {
                        response.spaces.forEach(function (space) {
                            $select.append(
                                `<option value="${space.key}">${escapeHtml(space.name)}</option>`
                            );
                        });
                    }
                },
                error: function () {
                    $select.html('<option value="">Failed to load spaces</option>');
                },
            });
        }

        // Initialize Confluence if on knowledge page
        if ($('#aiagent-confluence-space').length) {
            loadConfluenceSpaces();
        }

        // Load pages when space selected
        $('#aiagent-confluence-space').on('change', function () {
            const spaceKey = $(this).val();
            const $container = $('#aiagent-confluence-pages');

            if (!spaceKey) {
                $container.hide().html('<p class="aiagent-empty">Select a space to view pages</p>');
                $('.aiagent-confluence-actions').hide();
                return;
            }

            $container
                .show()
                .html(
                    '<p class="aiagent-loading"><span class="spinner is-active"></span> Loading pages...</p>'
                );

            $.ajax({
                url: aiagentAdmin.restUrl + 'confluence/pages',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                data: { space_key: spaceKey },
                success: function (response) {
                    if (!response.pages || response.pages.length === 0) {
                        $container.html(
                            '<p class="aiagent-empty">No pages found in this space</p>'
                        );
                        return;
                    }

                    let html = '<div class="aiagent-file-list">';
                    response.pages.forEach(function (page) {
                        html += `
                            <label class="aiagent-file-item">
                                <input type="checkbox" value="${page.id}" data-name="${escapeHtml(page.title)}">
                                <span class="dashicons dashicons-media-document"></span>
                                <span class="aiagent-file-item-name">${escapeHtml(page.title)}</span>
                            </label>
                        `;
                    });
                    html += '</div>';

                    $container.html(html);
                    $('.aiagent-confluence-actions').show();
                },
                error: function (xhr) {
                    $container.html(
                        '<p class="aiagent-error">Error: ' +
                            (xhr.responseJSON?.message || 'Failed to load pages') +
                            '</p>'
                    );
                },
            });
        });

        // Enable/disable import button based on selection
        $(document).on('change', '#aiagent-confluence-pages input[type="checkbox"]', function () {
            const hasSelection = $('#aiagent-confluence-pages input:checked').length > 0;
            $('#aiagent-confluence-import-selected').prop('disabled', !hasSelection);
        });

        // Import selected Confluence pages
        $('#aiagent-confluence-import-selected').on('click', function () {
            const $btn = $(this);
            const $status = $btn.siblings('.aiagent-import-status');
            const $checked = $('#aiagent-confluence-pages input:checked');

            if ($checked.length === 0) return;

            $btn.prop('disabled', true);
            $status.text('Importing...');

            let completed = 0;
            let errors = 0;
            const total = $checked.length;

            $checked.each(function () {
                const pageId = $(this).val();

                $.ajax({
                    url: aiagentAdmin.restUrl + 'confluence/import',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': aiagentAdmin.nonce,
                        'Content-Type': 'application/json',
                    },
                    data: JSON.stringify({ page_id: pageId }),
                    success: function () {
                        completed++;
                        updateConfluenceStatus();
                    },
                    error: function () {
                        completed++;
                        errors++;
                        updateConfluenceStatus();
                    },
                });
            });

            function updateConfluenceStatus() {
                $status.text(
                    `Imported ${completed}/${total}` + (errors > 0 ? ` (${errors} failed)` : '')
                );
                if (completed === total) {
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                }
            }
        });

        // ===============================
        // Copy to Clipboard Helper
        // ===============================
        $(document).on('click', '.aiagent-copy-btn', function () {
            const $btn = $(this);
            const targetId = $btn.data('target');
            const $target = $('#' + targetId);

            if ($target.length) {
                const text = $target.text();
                navigator.clipboard.writeText(text).then(function () {
                    $btn.find('.dashicons')
                        .removeClass('dashicons-clipboard')
                        .addClass('dashicons-yes');
                    setTimeout(function () {
                        $btn.find('.dashicons')
                            .removeClass('dashicons-yes')
                            .addClass('dashicons-clipboard');
                    }, 1500);
                });
            }
        });

        // ===============================
        // Integration Configuration Modals
        // ===============================

        // Open integration modal
        $(document).on('click', '.aiagent-integration-configure-btn', function (e) {
            e.preventDefault();
            const modalId = $(this).data('modal');
            const $targetModal = $('#' + modalId);

            if ($targetModal.length) {
                $targetModal.fadeIn(200);
                // Focus first input
                setTimeout(function () {
                    $targetModal.find('input:not([type="hidden"]):first').focus();
                }, 200);
            }
        });

        // Close integration modal - close button and cancel button
        $(document).on(
            'click',
            '.aiagent-integration-modal .aiagent-modal-close, .aiagent-integration-modal .aiagent-modal-cancel',
            function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.aiagent-integration-modal').fadeOut(200);
            }
        );

        // Close integration modal - overlay click
        $(document).on('click', '.aiagent-integration-modal .aiagent-modal-overlay', function () {
            $(this).closest('.aiagent-integration-modal').fadeOut(200);
        });

        // ESC key to close integration modals
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('.aiagent-integration-modal:visible').fadeOut(200);
            }
        });

        // Save integration settings via AJAX
        $(document).on('click', '.aiagent-modal-save', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const integration = $btn.data('integration');
            const $modal = $btn.closest('.aiagent-integration-modal');

            // Disable button and show loading
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Saving...');

            let endpoint = '';
            let data = {};

            // Collect data based on integration type
            switch (integration) {
                case 'groq':
                    endpoint = 'settings/groq';
                    data = {
                        api_key: $modal.find('#api_key').val(),
                    };
                    break;

                case 'gdrive':
                    endpoint = 'settings/gdrive';
                    data = {
                        client_id: $modal.find('#gdrive_client_id').val(),
                        client_secret: $modal.find('#gdrive_client_secret').val(),
                    };
                    break;

                case 'confluence':
                    endpoint = 'settings/confluence';
                    data = {
                        instance_url: $modal.find('#confluence_url').val(),
                        email: $modal.find('#confluence_email').val(),
                        api_token: $modal.find('#confluence_token').val(),
                    };
                    break;

                case 'zapier':
                    endpoint = 'settings/zapier';
                    data = {
                        enabled: $modal.find('#zapier_enabled').is(':checked'),
                        webhook_url: $modal.find('#zapier_webhook_url').val(),
                    };
                    break;

                case 'mailchimp':
                    endpoint = 'settings/mailchimp';
                    data = {
                        enabled: $modal.find('#mailchimp_enabled').is(':checked'),
                        api_key: $modal.find('#mailchimp_api_key').val(),
                        list_id: $modal.find('#mailchimp_list_id').val(),
                    };
                    break;

                case 'woocommerce':
                    endpoint = 'settings/woocommerce';
                    data = {
                        enabled: $modal.find('#woo_enabled').is(':checked'),
                        show_prices: $modal.find('#woo_show_prices').is(':checked'),
                        show_add_to_cart: $modal.find('#woo_show_add_to_cart').is(':checked'),
                        show_related_products: $modal
                            .find('#woo_show_related_products')
                            .is(':checked'),
                        show_product_comparison: $modal
                            .find('#woo_show_product_comparison')
                            .is(':checked'),
                        max_products_display:
                            parseInt($modal.find('#woo_max_products_display').val()) || 6,
                        include_out_of_stock: $modal
                            .find('#woo_include_out_of_stock')
                            .is(':checked'),
                        // Knowledge base sync settings.
                        sync_to_kb: $modal.find('#woo_sync_to_kb').is(':checked'),
                        auto_sync: $modal.find('#woo_auto_sync').is(':checked'),
                        kb_include_descriptions: $modal
                            .find('#woo_kb_include_descriptions')
                            .is(':checked'),
                        kb_include_prices: $modal.find('#woo_kb_include_prices').is(':checked'),
                        kb_include_categories: $modal
                            .find('#woo_kb_include_categories')
                            .is(':checked'),
                        kb_include_attributes: $modal
                            .find('#woo_kb_include_attributes')
                            .is(':checked'),
                        kb_include_stock_status: $modal
                            .find('#woo_kb_include_stock_status')
                            .is(':checked'),
                    };
                    break;

                case 'gcalendar':
                    endpoint = 'settings/gcalendar';
                    // Collect working days
                    const workingDays = [];
                    $modal
                        .find('input[name="gcalendar_working_days[]"]:checked')
                        .each(function () {
                            workingDays.push(parseInt($(this).val()));
                        });

                    data = {
                        enabled: $modal.find('#gcalendar_enabled').is(':checked'),
                        client_id: $modal.find('#gcalendar_client_id').val() || '',
                        client_secret: $modal.find('#gcalendar_client_secret').val() || '',
                        prompt_after_chat: $modal
                            .find('#gcalendar_prompt_after_chat')
                            .is(':checked'),
                        prompt_message: $modal.find('#gcalendar_prompt_message').val() || '',
                        default_duration:
                            parseInt($modal.find('#gcalendar_default_duration').val()) || 30,
                        buffer_time:
                            parseInt($modal.find('#gcalendar_buffer_time').val()) || 15,
                        days_ahead:
                            parseInt($modal.find('#gcalendar_days_ahead').val()) || 14,
                        business_hours_start:
                            $modal.find('#gcalendar_business_hours_start').val() || '09:00',
                        business_hours_end:
                            $modal.find('#gcalendar_business_hours_end').val() || '17:00',
                        working_days: workingDays.length > 0 ? workingDays : [1, 2, 3, 4, 5],
                        event_title_template:
                            $modal.find('#gcalendar_event_title_template').val() || '',
                        event_description_template:
                            $modal.find('#gcalendar_event_description_template').val() || '',
                    };
                    break;

                default:
                    $btn.prop('disabled', false).text(originalText);
                    return;
            }

            $.ajax({
                url: aiagentAdmin.restUrl + endpoint,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify(data),
                success: function (response) {
                    $btn.text('✓ Saved!');
                    setTimeout(function () {
                        $btn.prop('disabled', false).text(originalText);
                        // Close modal and refresh page to show updated status
                        $modal.fadeOut(200);
                        location.reload();
                    }, 1000);
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to save settings';
                    alert('Error: ' + error);
                    $btn.prop('disabled', false).text(originalText);
                },
            });
        });

        // ===============================
        // WooCommerce Sync Button Handler
        // ===============================
        $('#woo_sync_now_btn').on('click', function () {
            const $btn = $(this);
            const $status = $('#woo_sync_status');
            const originalHtml = $btn.html();

            $btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update spin" style="margin-top: 4px;"></span> Syncing...'
            );
            $status.text('');

            $.ajax({
                url: aiagentAdmin.restUrl + 'woocommerce/sync-to-kb',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiagentAdmin.nonce,
                },
                success: function (response) {
                    $btn.html(
                        '<span class="dashicons dashicons-yes-alt" style="margin-top: 4px; color: #46b450;"></span> Synced!'
                    );
                    $status
                        .css('color', '#46b450')
                        .text(response.product_count + ' products synced successfully');

                    setTimeout(function () {
                        $btn.prop('disabled', false).html(originalHtml);
                    }, 3000);
                },
                error: function (xhr) {
                    const error = xhr.responseJSON?.message || 'Sync failed';
                    $btn.html(
                        '<span class="dashicons dashicons-warning" style="margin-top: 4px; color: #dc3232;"></span> Failed'
                    );
                    $status.css('color', '#dc3232').text('Error: ' + error);

                    setTimeout(function () {
                        $btn.prop('disabled', false).html(originalHtml);
                    }, 3000);
                },
            });
        });
    });

    // Add spinning animation for loading state
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .dashicons.spin {
            animation: spin 1s linear infinite;
        }
    `;
    document.head.appendChild(style);
})(jQuery);
