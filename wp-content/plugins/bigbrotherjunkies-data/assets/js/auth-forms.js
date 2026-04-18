(function($) {
    'use strict';

    // Login Form Handler
    function initLoginForm() {
        const $form = $('#bbjd-login-form');
        if (!$form.length) return;

        $form.on('submit', function(e) {
            e.preventDefault();

            const $btn = $form.find('button[type="submit"]');
            const $error = $('#bbjd-login-error');

            // Disable button and show spinner
            setButtonLoading($btn, true);
            $error.addClass('hidden').empty();

            $.ajax({
                url: bbjdAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bbjd_login',
                    nonce: bbjdAuth.loginNonce,
                    login: $('#bbjd-login').val(),
                    password: $('#bbjd-password').val(),
                    remember: $form.find('input[name="remember"]').is(':checked') ? 1 : 0,
                    redirect_to: $form.find('input[name="redirect_to"]').val() || ''
                },
                success: function(response) {
                    if (response.success) {
                        // Show success and redirect
                        $btn.find('.bbjd-btn-text').text(bbjdAuth.messages.loginSuccess);
                        setTimeout(function() {
                            window.location.href = response.data.redirect || '/';
                        }, 500);
                    } else {
                        $error.removeClass('hidden').text(response.data.message);
                        setButtonLoading($btn, false);
                    }
                },
                error: function() {
                    $error.removeClass('hidden').text(bbjdAuth.messages.error);
                    setButtonLoading($btn, false);
                }
            });
        });
    }

    // Registration Form Handler
    function initRegistrationForm() {
        const $form = $('#bbjd-register-form');
        if (!$form.length) return;

        // Real-time validation
        initFieldValidation($form);

        $form.on('submit', function(e) {
            e.preventDefault();

            // Check honeypot
            if ($('input[name="bbjd_hp_field"]').val()) {
                return false;
            }

            const $btn = $form.find('button[type="submit"]');
            const $error = $('#bbjd-register-error');
            const $success = $('#bbjd-register-success');

            // Client-side validation
            if (!validateRegistrationForm($form)) {
                return;
            }

            // Disable button and show spinner
            setButtonLoading($btn, true);
            $error.addClass('hidden').empty();
            $success.addClass('hidden').empty();

            $.ajax({
                url: bbjdAuth.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bbjd_register',
                    nonce: bbjdAuth.registerNonce,
                    username: $('#bbjd-username').val(),
                    email: $('#bbjd-email').val(),
                    email_confirm: $('#bbjd-email-confirm').val(),
                    password: $('#bbjd-reg-password').val(),
                    password_confirm: $('#bbjd-password-confirm').val(),
                    display_name: $('#bbjd-display-name').val(),
                    first_name: $('#bbjd-first-name').val(),
                    last_name: $('#bbjd-last-name').val(),
                    profile_picture_id: $('#bbjd-profile-picture-id').val(),
                    subscribe_newsletter: $('#bbjd-subscribe').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        $success.removeClass('hidden').text(response.data.message);
                        $form.find('input').prop('disabled', true);
                        $btn.prop('disabled', true);

                        // Redirect after delay
                        setTimeout(function() {
                            window.location.href = response.data.redirect || '/login/';
                        }, 2000);
                    } else {
                        $error.removeClass('hidden').text(response.data.message);

                        // Highlight error field if specified
                        if (response.data.field) {
                            highlightField(response.data.field);
                        }

                        setButtonLoading($btn, false);
                    }
                },
                error: function() {
                    $error.removeClass('hidden').text(bbjdAuth.messages.error);
                    setButtonLoading($btn, false);
                }
            });
        });
    }

    // Client-side validation
    function validateRegistrationForm($form) {
        let valid = true;
        const $error = $('#bbjd-register-error');

        // Username
        const username = $('#bbjd-username').val();
        if (!/^[a-z0-9]+$/.test(username)) {
            $error.removeClass('hidden').text('Username can only contain lowercase letters and numbers.');
            highlightField('username');
            return false;
        }

        // Email match
        const email = $('#bbjd-email').val();
        const emailConfirm = $('#bbjd-email-confirm').val();
        if (email !== emailConfirm) {
            $error.removeClass('hidden').text('Email addresses do not match.');
            highlightField('email_confirm');
            return false;
        }

        // Password match
        const password = $('#bbjd-reg-password').val();
        const passwordConfirm = $('#bbjd-password-confirm').val();
        if (password !== passwordConfirm) {
            $error.removeClass('hidden').text('Passwords do not match.');
            highlightField('password_confirm');
            return false;
        }

        // Password length
        if (password.length < 8) {
            $error.removeClass('hidden').text('Password must be at least 8 characters.');
            highlightField('password');
            return false;
        }

        return true;
    }

    // Highlight error field
    function highlightField(field) {
        const fieldMap = {
            'username': '#bbjd-username',
            'email': '#bbjd-email',
            'email_confirm': '#bbjd-email-confirm',
            'password': '#bbjd-reg-password',
            'password_confirm': '#bbjd-password-confirm'
        };

        const selector = fieldMap[field];
        if (selector) {
            $(selector).addClass('border-red-500 focus:ring-red-500')
                .one('focus', function() {
                    $(this).removeClass('border-red-500 focus:ring-red-500');
                });
        }
    }

    // Real-time field validation
    function initFieldValidation($form) {
        // Username validation
        $('#bbjd-username').on('input', function() {
            const val = $(this).val().toLowerCase();
            $(this).val(val); // Force lowercase
        });

        // Email confirmation
        $('#bbjd-email-confirm').on('blur', function() {
            const email = $('#bbjd-email').val();
            const confirm = $(this).val();
            if (confirm && email !== confirm) {
                $(this).addClass('border-red-500');
            } else {
                $(this).removeClass('border-red-500');
            }
        });

        // Password confirmation
        $('#bbjd-password-confirm').on('blur', function() {
            const password = $('#bbjd-reg-password').val();
            const confirm = $(this).val();
            if (confirm && password !== confirm) {
                $(this).addClass('border-red-500');
            } else {
                $(this).removeClass('border-red-500');
            }
        });
    }

    // Toggle password visibility
    function initPasswordToggle() {
        $('.bbjd-toggle-password').on('click', function() {
            const $btn = $(this);
            const $input = $btn.closest('div').find('input');
            const $eyeOpen = $btn.find('.eye-open');
            const $eyeClosed = $btn.find('.eye-closed');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $eyeOpen.addClass('hidden');
                $eyeClosed.removeClass('hidden');
            } else {
                $input.attr('type', 'password');
                $eyeOpen.removeClass('hidden');
                $eyeClosed.addClass('hidden');
            }
        });
    }

    // Profile picture dropzone
    function initProfileDropzone() {
        const $dropzone = $('#bbjd-profile-dropzone');
        if (!$dropzone.length) return;

        // Check if Dropzone is loaded
        if (typeof Dropzone === 'undefined') {
            // Fallback to simple file input
            initSimpleFileUpload($dropzone);
            return;
        }

        // Disable Dropzone auto-discover
        Dropzone.autoDiscover = false;

        const dropzone = new Dropzone('#bbjd-profile-dropzone', {
            url: bbjdAuth.ajaxUrl,
            paramName: 'profile_picture',
            maxFilesize: 5, // MB
            acceptedFiles: 'image/jpeg,image/png,image/gif',
            maxFiles: 1,
            addRemoveLinks: false,
            clickable: true,
            previewsContainer: false,
            params: {
                action: 'bbjd_upload_profile_picture',
                nonce: bbjdAuth.registerNonce
            },
            init: function() {
                this.on('sending', function() {
                    $('#bbjd-dropzone-placeholder').html('<p class="text-sm text-gray-500">Uploading...</p>');
                });

                this.on('success', function(file, response) {
                    if (response.success) {
                        $('#bbjd-profile-picture-id').val(response.data.attachment_id);
                        $('#bbjd-dropzone-preview img').attr('src', response.data.url);
                        $('#bbjd-dropzone-preview').removeClass('hidden');
                        $('#bbjd-dropzone-placeholder').addClass('hidden');
                    } else {
                        alert(response.data.message);
                        $('#bbjd-dropzone-placeholder').html(getPlaceholderHtml());
                    }
                    this.removeAllFiles();
                });

                this.on('error', function(file, message) {
                    alert(typeof message === 'string' ? message : 'Upload failed');
                    $('#bbjd-dropzone-placeholder').html(getPlaceholderHtml());
                    this.removeAllFiles();
                });
            }
        });

        // Remove picture button
        $('#bbjd-remove-picture').on('click', function(e) {
            e.stopPropagation();
            $('#bbjd-profile-picture-id').val('');
            $('#bbjd-dropzone-preview').addClass('hidden');
            $('#bbjd-dropzone-placeholder').removeClass('hidden');
        });
    }

    // Simple file upload fallback
    function initSimpleFileUpload($dropzone) {
        const $input = $('<input type="file" accept="image/jpeg,image/png,image/gif" class="hidden">');
        $dropzone.append($input);

        $dropzone.on('click', function(e) {
            if (!$(e.target).is('#bbjd-remove-picture')) {
                $input.trigger('click');
            }
        });

        $input.on('change', function() {
            const file = this.files[0];
            if (!file) return;

            // Validate file size
            if (file.size > 5 * 1024 * 1024) {
                alert('File too large. Maximum size is 5MB.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'bbjd_upload_profile_picture');
            formData.append('nonce', bbjdAuth.registerNonce);
            formData.append('profile_picture', file);

            $('#bbjd-dropzone-placeholder').html('<p class="text-sm text-gray-500">Uploading...</p>');

            $.ajax({
                url: bbjdAuth.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#bbjd-profile-picture-id').val(response.data.attachment_id);
                        $('#bbjd-dropzone-preview img').attr('src', response.data.url);
                        $('#bbjd-dropzone-preview').removeClass('hidden');
                        $('#bbjd-dropzone-placeholder').addClass('hidden');
                    } else {
                        alert(response.data.message);
                        $('#bbjd-dropzone-placeholder').html(getPlaceholderHtml());
                    }
                },
                error: function() {
                    alert('Upload failed');
                    $('#bbjd-dropzone-placeholder').html(getPlaceholderHtml());
                }
            });
        });

        // Remove picture button
        $('#bbjd-remove-picture').on('click', function(e) {
            e.stopPropagation();
            $('#bbjd-profile-picture-id').val('');
            $('#bbjd-dropzone-preview').addClass('hidden');
            $('#bbjd-dropzone-placeholder').removeClass('hidden');
            $input.val('');
        });
    }

    function getPlaceholderHtml() {
        return `
            <svg class="mx-auto w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Drag and drop an image, or click to select</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">JPG, PNG, or GIF (max 5MB)</p>
        `;
    }

    // Button loading state
    function setButtonLoading($btn, loading) {
        const $text = $btn.find('.bbjd-btn-text');
        const $spinner = $btn.find('.bbjd-btn-spinner');

        if (loading) {
            $btn.prop('disabled', true);
            $spinner.removeClass('hidden');
        } else {
            $btn.prop('disabled', false);
            $spinner.addClass('hidden');
        }
    }

    // Google Sign-In using Google Identity Services
    function initGoogleSignIn() {
        const $loginBtn = $('#bbjd-google-signin');
        const $signupBtn = $('#bbjd-google-signup');

        // Check if Google is enabled and GIS library is loaded
        if (!bbjdAuth.googleEnabled || typeof google === 'undefined') {
            // Hide Google buttons if not configured
            $loginBtn.hide();
            $signupBtn.hide();
            // Also hide the divider on login form
            $loginBtn.closest('.mb-7').next('.flex.items-center.gap-4').hide();
            $loginBtn.closest('.mb-7').hide();
            return;
        }

        // Initialize Google Identity Services
        google.accounts.id.initialize({
            client_id: bbjdAuth.googleClientId,
            callback: handleGoogleCredentialResponse,
            auto_select: false,
            cancel_on_tap_outside: true
        });

        // Handle button clicks - use Google's popup
        $loginBtn.on('click', function(e) {
            e.preventDefault();
            triggerGoogleSignIn($(this));
        });

        $signupBtn.on('click', function(e) {
            e.preventDefault();
            triggerGoogleSignIn($(this));
        });
    }

    // Trigger Google Sign-In popup
    function triggerGoogleSignIn($btn) {
        // Show loading state
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html(
            '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">' +
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
            '</svg>'
        );

        // Store original HTML for reset
        $btn.data('original-html', originalHtml);

        // Trigger Google One Tap / Sign-In prompt
        google.accounts.id.prompt(function(notification) {
            if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                // Prompt was not displayed or skipped, try popup mode
                // Reset button state
                $btn.prop('disabled', false).html($btn.data('original-html'));

                // Show error message
                const $error = $('#bbjd-login-error, #bbjd-register-error').first();
                if (notification.getNotDisplayedReason() === 'suppressed_by_user') {
                    $error.removeClass('hidden').text('Google Sign-In was blocked. Please enable popups or try email login.');
                } else {
                    // Try using FedCM or redirect approach
                    tryFedCMSignIn($btn);
                }
            }
        });
    }

    // Try FedCM-based sign in
    function tryFedCMSignIn($btn) {
        // Use the prompt method with select_account hint
        google.accounts.id.prompt();
    }

    // Handle the credential response from Google
    function handleGoogleCredentialResponse(response) {
        if (!response.credential) {
            showGoogleError('No credential received from Google.');
            resetGoogleButtons();
            return;
        }

        // Send credential to our backend
        $.ajax({
            url: bbjdAuth.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bbjd_google_signin',
                nonce: bbjdAuth.googleNonce,
                credential: response.credential,
                redirect_to: $('input[name="redirect_to"]').val() || ''
            },
            success: function(result) {
                if (result.success) {
                    // Show success message
                    const $btn = $('#bbjd-google-signin:visible, #bbjd-google-signup:visible').first();
                    if ($btn.length) {
                        $btn.html('<span class="text-green-600">' + result.data.message + '</span>');
                    }

                    // Redirect after short delay
                    setTimeout(function() {
                        window.location.href = result.data.redirect || '/';
                    }, 500);
                } else {
                    showGoogleError(result.data.message);
                    resetGoogleButtons();
                }
            },
            error: function() {
                showGoogleError(bbjdAuth.messages.googleError);
                resetGoogleButtons();
            }
        });
    }

    // Show error message for Google sign-in
    function showGoogleError(message) {
        const $error = $('#bbjd-login-error, #bbjd-register-error').filter(':visible').first();
        if ($error.length) {
            $error.removeClass('hidden').text(message);
        } else {
            // Try to find any error container
            $('#bbjd-login-error, #bbjd-register-error').first().removeClass('hidden').text(message);
        }
    }

    // Reset Google buttons to original state
    function resetGoogleButtons() {
        $('#bbjd-google-signin, #bbjd-google-signup').each(function() {
            const $btn = $(this);
            const originalHtml = $btn.data('original-html');
            if (originalHtml) {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initLoginForm();
        initRegistrationForm();
        initPasswordToggle();
        initProfileDropzone();
        initGoogleSignIn();
    });

})(jQuery);
