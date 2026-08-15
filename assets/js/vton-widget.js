jQuery(document).ready(function ($) {
    let userFile = null;
    let customProductFile = null;
    let currentProductUrl = '';
    let currentProductId = null;
    let generatedImageUrl = ''; // Preserves generated result in session
    let progressTimer = null;
    let maxCredits = parseInt(vton_config.max_credits, 10);
    let currentCredits = Math.min(parseInt(vton_config.user_credits, 10), maxCredits);
    const isLoggedIn = Boolean(vton_config.is_logged_in);

    // Dynamic Top Header Badge
    const creditBadgeHTML = isLoggedIn 
        ? `<span id="vton-credit-badge" style="background: #e0e7ff; color: #3730a3; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; line-height: 17px;">
             Credits: <span id="vton-credit-count">${currentCredits}</span>/${maxCredits}
           </span>`
        : `<span id="vton-credit-badge" style="background: #fef3c7; color: #92400e; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; line-height: 17px;">
             Login Required
           </span>`;

    // 1. Inject Modal HTML into DOM
    const modalHTML = `
        <div id="vton-modal-overlay" class="vton-overlay" style="display:none;">
            <div class="vton-modal-content">
                <button type="button" class="vton-close-btn">&times;</button>
                
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 16px;">
                    ${creditBadgeHTML}
                </div>
                
                <div class="vton-cards-grid">
                    <!-- Card 1: Customer Upload -->
                    <div class="vton-card">
                        <div class="vton-upload-box" id="vton-user-box">
                            <label class="vton-file-label">
                                <span>Upload Your Photo</span>
                                <input type="file" id="vton-user-input" accept="image/*" hidden />
                            </label>
                            <img id="vton-user-preview" class="vton-preview-img" style="display:none;" />
                        </div>
                        <label id="vton-user-change-btn" class="vton-reupload-btn" style="display:none;">
                            Change Photo
                            <input type="file" id="vton-user-reinput" accept="image/*" hidden />
                        </label>
                    </div>

                    <!-- Card 2: Selected Product -->
                    <div class="vton-card">
                        <div class="vton-upload-box" id="vton-product-box">
                            <img id="vton-product-preview" class="vton-preview-img" />
                        </div>
                    </div>
                </div>

                <!-- Action Button Section -->
                <div class="vton-action-section">
                    <p id="vton-error-msg" class="vton-error-msg" style="display:none;"></p>
                    
                    <button type="button" id="vton-generate-btn" class="vton-generate-btn" disabled>
                        Try It On Me!
                    </button>
                </div>

                <!-- Generated Result Section -->
                <div id="vton-result-container" class="vton-result-container" style="display:none;">
                    <img id="vton-result-img" class="vton-result-img" src="" alt="Try On Result" />
                </div>
            </div>
        </div>
    `;

    $('body').append(modalHTML);

    // Helper: Reset Generate Button Appearance
    function resetButtonState($btn) {
        if (progressTimer) clearInterval(progressTimer);
        $btn.css('background', '#111827');
        $btn.text('Try It On Me!');
        
        if (isLoggedIn && currentCredits <= 0) {
            $btn.prop('disabled', true);
        } else if (userFile) {
            $btn.prop('disabled', false);
        }
    }

    // Helper: Start Progress Bar Animation
    function startProgressAnimation($btn) {
        let percent = 5;
        $btn.prop('disabled', true);
        $btn.css('background', `linear-gradient(to right, #4338ca ${percent}%, #111827 ${percent}%)`);
        $btn.text(`Generating... ${percent}%`);

        progressTimer = setInterval(function () {
            if (percent < 95) {
                const step = percent < 60 ? Math.floor(Math.random() * 6) + 4 : Math.floor(Math.random() * 3) + 1;
                percent = Math.min(95, percent + step);
                $btn.css('background', `linear-gradient(to right, #4338ca ${percent}%, #111827 ${percent}%)`);
                $btn.text(`Generating... ${percent}%`);
            }
        }, 900);
    }

    // 2. Open Modal Trigger
    $(document).on('click', '.vton-trigger-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        currentProductUrl = $(this).attr('data-product-img');
        currentProductId = $(this).attr('data-product-id');
        
        $('#vton-error-msg').hide();
        resetButtonState($('#vton-generate-btn'));

        // Retain generated image if already created in current session
        if (generatedImageUrl) {
            $('#vton-result-img').attr('src', generatedImageUrl);
            $('#vton-result-container').show();
        } else {
            $('#vton-result-container').hide();
        }

        if (isLoggedIn && currentCredits <= 0) {
            $('#vton-generate-btn').prop('disabled', true);
            $('#vton-error-msg').text('You have used all your available try-on credits.').show();
        }

        $('#vton-product-preview').attr('src', currentProductUrl).show();
        $('#vton-modal-overlay').fadeIn(200);
    });

    // 3. Close Modal
    $(document).on('click', '.vton-close-btn', function () {
        $('#vton-modal-overlay').fadeOut(200);
    });

    $('#vton-modal-overlay').on('click', function (e) {
        if ($(e.target).is('#vton-modal-overlay')) {
            $(this).fadeOut(200);
        }
    });

    // 4. Handle Photo Upload
    function handleUserImage(file) {
        if (file) {
            userFile = file;
            generatedImageUrl = ''; // Reset cached image on new upload
            const url = URL.createObjectURL(file);
            $('#vton-user-preview').attr('src', url).show();
            $('#vton-user-box .vton-file-label').hide();
            $('#vton-user-change-btn').show();
            
            if (!isLoggedIn || currentCredits > 0) {
                $('#vton-generate-btn').prop('disabled', false);
            }
            
            $('#vton-result-container').hide();
            $('#vton-error-msg').hide();
            resetButtonState($('#vton-generate-btn'));
        }
    }

    $(document).on('change', '#vton-user-input, #vton-user-reinput', function (e) {
        handleUserImage(e.target.files[0]);
    });

    // 5. Submit AJAX Generation
    $(document).on('click', '#vton-generate-btn', function (e) {
        e.preventDefault();

        if (!isLoggedIn) {
            window.location.href = vton_config.login_url;
            return;
        }

        if (!userFile) {
            $('#vton-error-msg').text('Please upload your photo first!').show();
            return;
        }

        if (currentCredits <= 0) {
            $('#vton-error-msg').text('You have used all your try-on credits.').show();
            return;
        }

        const $btn = $(this);
        $('#vton-error-msg').hide();
        startProgressAnimation($btn);

        const formData = new FormData();
        formData.append('action', 'vton_generate_image');
        formData.append('nonce', vton_config.nonce);
        formData.append('user_image', userFile);

        if (customProductFile) {
            formData.append('custom_product_image', customProductFile);
        } else {
            formData.append('product_image_url', currentProductUrl);
        }

        $.ajax({
            url: vton_config.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            timeout: 110000,
            success: function (response) {
                if (response.success && response.data.generated_image_url) {
                    clearInterval(progressTimer);
                    $btn.css('background', 'linear-gradient(to right, #4338ca 100%, #111827 100%)');
                    $btn.text('Completed 100% ✨');

                    generatedImageUrl = response.data.generated_image_url; // Save generated result
                    
                    if (response.data.remaining_credits !== undefined) {
                        currentCredits = parseInt(response.data.remaining_credits, 10);
                        $('#vton-credit-count').text(currentCredits);
                    }

                    $('#vton-result-img').attr('src', generatedImageUrl);
                    $('#vton-result-container').slideDown(300);

                    setTimeout(function () {
                        resetButtonState($btn);
                    }, 1200);

                    setTimeout(function () {
                        const modalContent = document.querySelector('.vton-modal-content');
                        if (modalContent) {
                            modalContent.scrollTop = modalContent.scrollHeight;
                        }
                    }, 300);
                } else {
                    if (response.data && response.data.redirect) {
                        window.location.href = vton_config.login_url;
                        return;
                    }

                    const msg = (response.data && response.data.message) ? response.data.message : 'Failed to generate image.';
                    $('#vton-error-msg').text(msg).show();
                    resetButtonState($btn);
                }
            },
            error: function (xhr, status, error) {
                console.error('VTON AJAX Error:', status, error);
                let errText = 'Server connection error or timeout. Please try again.';
                if (status === 'timeout') {
                    errText = 'Request timed out while waiting for AI generation. Please re-try.';
                }
                $('#vton-error-msg').text(errText).show();
                resetButtonState($btn);
            }
        });
    });
});