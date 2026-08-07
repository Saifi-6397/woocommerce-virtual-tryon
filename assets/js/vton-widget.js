jQuery(document).ready(function ($) {
    let userFile = null;
    let customProductFile = null;
    let currentProductUrl = '';
    let currentProductId = null;

    // 1. Inject Modal HTML into DOM on page load
    const modalHTML = `
        <div id="vton-modal-overlay" class="vton-overlay" style="display:none;">
            <div class="vton-modal-content">
                <button type="button" class="vton-close-btn">&times;</button>
                
                <div class="vton-cards-grid">
                    <!-- Card 1: Customer Upload -->
                    <div class="vton-card">
                         <!-- <h3>1. Upload Your Photo</h3> -->
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
                         <!-- <h3>2. Selected Product</h3> -->
                        <div class="vton-upload-box" id="vton-product-box">
                            <img id="vton-product-preview" class="vton-preview-img" />
                        </div>
                        <!--
                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <label class="vton-reupload-btn">
                                <span id="vton-prod-upload-label">Upload Custom Product</span>
                                <input type="file" id="vton-custom-product-input" accept="image/*" hidden />
                            </label>
                            <span id="vton-reset-prod-btn" class="vton-reupload-btn" style="color: #dc2626; cursor: pointer; display: none;">
                                Reset
                            </span>
                        </div>
                        -->
                    </div>
                </div>

                <!-- Action Button Section -->
                <div class="vton-action-section">
                    <p id="vton-error-msg" class="vton-error-msg" style="display:none;"></p>
                    
                    <!-- Primary Generate Button -->
                    <button type="button" id="vton-generate-btn" class="vton-generate-btn" disabled>
                        Try It On Me!
                    </button>

                    <!-- Add to Cart Button (Hidden initially, visible after generation) -->
                    <button type="button" id="vton-add-to-cart-btn" class="vton-generate-btn" style="display:none; background-color: #16a34a;">
                        Add To Cart
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

    // 2. Open Modal when clicking Trigger Button
    $(document).on('click', '.vton-trigger-btn', function (e) {
        e.preventDefault();
        currentProductUrl = $(this).attr('data-product-img');
        currentProductId = $(this).attr('data-product-id');
        
        // Reset UI Buttons
        $('#vton-generate-btn').show();
        $('#vton-add-to-cart-btn').hide().text('🛒 Add To Cart').prop('disabled', false);
        $('#vton-result-container').hide();

        $('#vton-product-preview').attr('src', currentProductUrl).show();
        $('#vton-modal-overlay').fadeIn(200);
    });

    // 3. Close Modal
    $(document).on('click', '.vton-close-btn', function () {
        $('#vton-modal-overlay').fadeOut(200);
    });

    // Close on clicking overlay background
    $('#vton-modal-overlay').on('click', function (e) {
        if ($(e.target).is('#vton-modal-overlay')) {
            $(this).fadeOut(200);
        }
    });

    // 4. Handle Customer Image Selection
    function handleUserImage(file) {
        if (file) {
            userFile = file;
            const url = URL.createObjectURL(file);
            $('#vton-user-preview').attr('src', url).show();
            $('#vton-user-box .vton-file-label').hide();
            $('#vton-user-change-btn').show();
            $('#vton-generate-btn').prop('disabled', false);
            $('#vton-result-container').hide();
            $('#vton-error-msg').hide();
            
            // Reset buttons view
            $('#vton-generate-btn').show();
            $('#vton-add-to-cart-btn').hide();
        }
    }

    $(document).on('change', '#vton-user-input, #vton-user-reinput', function (e) {
        handleUserImage(e.target.files[0]);
    });

    // 5. Handle Custom Product Image Selection
    $(document).on('change', '#vton-custom-product-input', function (e) {
        const file = e.target.files[0];
        if (file) {
            customProductFile = file;
            const url = URL.createObjectURL(file);
            $('#vton-product-preview').attr('src', url);
            $('#vton-prod-upload-label').text('Change Product Photo');
            $('#vton-reset-prod-btn').show();
            $('#vton-result-container').hide();
            
            // Reset buttons
            $('#vton-generate-btn').show();
            $('#vton-add-to-cart-btn').hide();
        }
    });

    // 6. Reset Product Image
    $(document).on('click', '#vton-reset-prod-btn', function () {
        customProductFile = null;
        $('#vton-product-preview').attr('src', currentProductUrl);
        $('#vton-prod-upload-label').text('Upload Custom Product');
        $(this).hide();
    });

    // 7. Submit AJAX Request to WordPress Backend
    $(document).on('click', '#vton-generate-btn', function () {
        if (!userFile) {
            $('#vton-error-msg').text('Please upload your photo first!').show();
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('Generating Try-On (30-60s)...');
        $('#vton-error-msg').hide();

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
                    const imgUrl = response.data.generated_image_url;
                    $('#vton-result-img').attr('src', imgUrl);
                    $('#vton-highres-link').attr('href', imgUrl);
                    $('#vton-result-container').slideDown(300);

                    // HIDE "Try It On Me!" Button & SHOW "Add To Cart" Button
                    $('#vton-generate-btn').hide();
                    $('#vton-add-to-cart-btn').fadeIn(200);

                    // Auto scroll to result inside modal
                    setTimeout(function () {
                        const modalContent = document.querySelector('.vton-modal-content');
                        if (modalContent) {
                            modalContent.scrollTop = modalContent.scrollHeight;
                        }
                    }, 300);
                } else {
                    const msg = (response.data && response.data.message) ? response.data.message : 'Failed to generate image.';
                    $('#vton-error-msg').text(msg).show();
                    $btn.prop('disabled', false).text('Try It On Me!');
                }
            },
            error: function (xhr, status, error) {
                console.error('VTON AJAX Error:', status, error);
                let errText = 'Server connection error or timeout. Please try again.';
                if (status === 'timeout') {
                    errText = 'Request timed out while waiting for AI generation. Please re-try.';
                }
                $('#vton-error-msg').text(errText).show();
                $btn.prop('disabled', false).text('Try It On Me!');
            }
        });
    });

    // 8. Trigger Native WooCommerce Add To Cart Button
    $(document).on('click', '#vton-add-to-cart-btn', function () {
        // Target standard WooCommerce Single Product Add to Cart button
        const $originalCartBtn = $('form.cart .single_add_to_cart_button');

        if ($originalCartBtn.length > 0) {
            // Close modal smoothly
            $('#vton-modal-overlay').fadeOut(200);
            
            // Trigger theme's native Add to Cart button
            $originalCartBtn.trigger('click');
        } else {
            // Fallback if form not found
            alert('Could not locate Add to Cart form on page.');
        }
    });
});