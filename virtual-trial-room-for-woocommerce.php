<?php
/**
 * Plugin Name:       Virtual Trial Room for WooCommerce
 * Plugin URI:        https://github.com/Saifi-6397/woocommerce-virtual-tryon
 * Description:       AI-powered Virtual Try-On plugin for WooCommerce using OpenAI.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Khaleel Ahmad
 * Author URI:        https://github.com/Saifi-6397
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       virtual-trial-room-for-woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCVirtualTryOn {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp', array($this, 'attach_tryon_button_hook'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_ajax_vton_generate_image', array($this, 'handle_vton_ajax'));
        add_action('wp_ajax_nopriv_vton_generate_image', array($this, 'handle_vton_ajax'));
    }

    /* ==========================================================================
       1. SETTINGS & ADMIN PAGE
       ========================================================================== */

    public function add_admin_menu() {
        add_options_page(
            __('Virtual Trial Room Settings', 'virtual-trial-room-for-woocommerce'),
            __('Virtual Trial Room', 'virtual-trial-room-for-woocommerce'),
            'manage_options',
            'vton-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('vton_settings_group', 'vton_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'yes',
        ));

        register_setting('vton_settings_group', 'vton_openai_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ));

        register_setting('vton_settings_group', 'vton_max_credits', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3,
        ));

        register_setting('vton_settings_group', 'vton_allowed_categories', array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize_categories_array'),
            'default'           => array(),
        ));

        register_setting('vton_settings_group', 'vton_total_generations_count', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ));

        register_setting('vton_settings_group', 'vton_button_position', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'woocommerce_after_add_to_cart_button',
        ));

        register_setting('vton_settings_group', 'vton_button_text', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __('Virtual Try On', 'virtual-trial-room-for-woocommerce'),
        ));

        register_setting('vton_settings_group', 'vton_login_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url(),
        ));
    }

    public function sanitize_categories_array($input) {
        if (!is_array($input)) {
            return array();
        }
        return array_map('absint', $input);
    }

    public function render_settings_page() {
        $default_login_url  = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        $is_enabled         = get_option('vton_enabled', 'yes');
        $allowed_categories = (array) get_option('vton_allowed_categories', array());
        $total_generations  = (int) get_option('vton_total_generations_count', 0);

        $product_categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Virtual Trial Room for WooCommerce Settings', 'virtual-trial-room-for-woocommerce'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('vton_settings_group');
                do_settings_sections('vton_settings_group');
                ?>
                <table class="form-table" role="presentation">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Virtual Try-On Feature', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="vton_enabled" value="yes" <?php checked($is_enabled, 'yes'); ?> />
                                <?php esc_html_e('Enable Virtual Try-On on product pages', 'virtual-trial-room-for-woocommerce'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Total Try-Ons Generated', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <span style="display:inline-block; font-size: 16px; font-weight:700; color:#4338ca; background:#e0e7ff; padding: 6px 14px; border-radius: 6px;">
                                <?php echo esc_html($total_generations); ?> <?php esc_html_e('Images Processed', 'virtual-trial-room-for-woocommerce'); ?>
                            </span>
                            <p class="description"><?php esc_html_e('Real-time counter tracking total successful try-on images generated across your store.', 'virtual-trial-room-for-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('OpenAI API Key', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <input 
                                type="text" 
                                name="vton_openai_api_key" 
                                value="<?php echo esc_attr(get_option('vton_openai_api_key', '')); ?>" 
                                class="regular-text" 
                                placeholder="sk-proj-..." 
                                style="-webkit-text-security: disc;" 
                            />
                            <p class="description">
                                <?php esc_html_e('Enter OpenAI API Key for image processing.', 'virtual-trial-room-for-woocommerce'); ?>
                                <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer" style="font-weight:600; text-decoration: underline;">
                                    <?php esc_html_e('Get your API Key from OpenAI Dashboard', 'virtual-trial-room-for-woocommerce'); ?> &rarr;
                                </a>
                            </p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Daily Try-on Limit Per User', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <input 
                                type="number" 
                                name="vton_max_credits" 
                                value="<?php echo esc_attr(get_option('vton_max_credits', 3)); ?>" 
                                class="small-text" 
                                min="1" 
                            />
                            <p class="description"><?php esc_html_e('Maximum try-on requests allowed per registered user per calendar day. Balance automatically resets at midnight.', 'virtual-trial-room-for-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Allowed Categories', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <?php if (!empty($product_categories) && !is_wp_error($product_categories)) : ?>
                                <div style="max-height: 160px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 10px; background: #fff; width: 380px; border-radius: 4px;">
                                    <?php foreach ($product_categories as $cat) : ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input 
                                                type="checkbox" 
                                                name="vton_allowed_categories[]" 
                                                value="<?php echo esc_attr($cat->term_id); ?>" 
                                                <?php checked(in_array($cat->term_id, $allowed_categories, true)); ?> 
                                            />
                                            <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description"><?php esc_html_e('Select categories where try-on should be available. If none are selected, try-on will display on all products.', 'virtual-trial-room-for-woocommerce'); ?></p>
                            <?php else : ?>
                                <p><?php esc_html_e('No product categories found.', 'virtual-trial-room-for-woocommerce'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Button Position', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <select name="vton_button_position">
                                <option value="woocommerce_before_add_to_cart_button" <?php selected(get_option('vton_button_position'), 'woocommerce_before_add_to_cart_button'); ?>><?php esc_html_e('Before "Add to Cart" Button', 'virtual-trial-room-for-woocommerce'); ?></option>
                                <option value="woocommerce_after_add_to_cart_button" <?php selected(get_option('vton_button_position'), 'woocommerce_after_add_to_cart_button'); ?>><?php esc_html_e('After "Add to Cart" Button', 'virtual-trial-room-for-woocommerce'); ?></option>
                                <option value="woocommerce_after_add_to_cart_quantity" <?php selected(get_option('vton_button_position'), 'woocommerce_after_add_to_cart_quantity'); ?>><?php esc_html_e('Beside Quantity Input', 'virtual-trial-room-for-woocommerce'); ?></option>
                                <option value="woocommerce_single_product_summary" <?php selected(get_option('vton_button_position'), 'woocommerce_single_product_summary'); ?>><?php esc_html_e('Product Summary Footer', 'virtual-trial-room-for-woocommerce'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Button Label Text', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <input type="text" name="vton_button_text" value="<?php echo esc_attr(get_option('vton_button_text', __('✨ Virtual Try On', 'virtual-trial-room-for-woocommerce'))); ?>" class="regular-text" />
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Login / Signup Page URL', 'virtual-trial-room-for-woocommerce'); ?></th>
                        <td>
                            <input 
                                type="url" 
                                name="vton_login_url" 
                                value="<?php echo esc_url(get_option('vton_login_url', $default_login_url)); ?>" 
                                class="regular-text" 
                                placeholder="https://yourstore.com/my-account/" 
                            />
                            <p class="description"><?php esc_html_e("Guests will be redirected to this URL when clicking 'Try It On Me!' inside the modal.", 'virtual-trial-room-for-woocommerce'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ==========================================================================
       2. HELPER FUNCTIONS
       ========================================================================== */

    public function is_product_allowed($product_id) {
        if ('yes' !== get_option('vton_enabled', 'yes')) {
            return false;
        }

        $allowed_categories = (array) get_option('vton_allowed_categories', array());
        if (empty($allowed_categories)) {
            return true;
        }

        $product_cats = wc_get_product_term_ids($product_id, 'product_cat');
        return (bool) array_intersect($product_cats, $allowed_categories);
    }

    public function get_user_daily_credits($user_id) {
        $max_credits = (int) get_option('vton_max_credits', 3);
        $today       = gmdate('Y-m-d');
        $last_date   = get_user_meta($user_id, 'vton_credits_last_date', true);
        $credits     = get_user_meta($user_id, 'vton_user_credits', true);

        if ($last_date !== $today || $credits === '') {
            $credits = $max_credits;
            update_user_meta($user_id, 'vton_user_credits', $credits);
            update_user_meta($user_id, 'vton_credits_last_date', $today);
        }

        return (int) $credits;
    }

    /* ==========================================================================
       3. FRONTEND HOOKS & ASSETS
       ========================================================================== */

    public function attach_tryon_button_hook() {
        if (function_exists('is_product') && is_product()) {
            if ('yes' !== get_option('vton_enabled', 'yes')) {
                return;
            }
            $position_hook = get_option('vton_button_position', 'woocommerce_after_add_to_cart_button');
            add_action($position_hook, array($this, 'render_tryon_button'));
        }
    }

public function render_tryon_button() {
        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;

        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $product_id = $product->get_id();
        if (!$this->is_product_allowed($product_id)) {
            return;
        }

        $product_image_id  = $product->get_image_id();
        $product_image_url = wp_get_attachment_image_url($product_image_id, 'full');
        $button_text       = get_option('vton_button_text', __('✨ Virtual Try On', 'virtual-trial-room-for-woocommerce'));

        echo '<div class="vton-button-wrapper" style="margin: 10px 0;">
                <button type="button" class="button vton-trigger-btn" data-product-id="' . esc_attr($product_id) . '" data-product-img="' . esc_url($product_image_url) . '">
                    ' . esc_html($button_text) . '
                </button>
              </div>';
    }

    public function enqueue_frontend_assets() {
        if (function_exists('is_product') && is_product()) {
            if ('yes' !== get_option('vton_enabled', 'yes')) {
                return;
            }

            $current_post_id = get_the_ID();
            if ($current_post_id && !$this->is_product_allowed($current_post_id)) {
                return;
            }

            wp_enqueue_style('vton-style', plugin_dir_url(__FILE__) . 'assets/css/vton-widget.css', array(), '1.2.0');
            wp_enqueue_script('vton-script', plugin_dir_url(__FILE__) . 'assets/js/vton-widget.js', array('jquery'), '1.2.0', true);

            $is_logged_in = is_user_logged_in();
            $max_credits  = (int) get_option('vton_max_credits', 3);
            $user_credits = 0;

            if ($is_logged_in) {
                $user_id      = get_current_user_id();
                $user_credits = $this->get_user_daily_credits($user_id);
            }

            $default_login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
            $login_url         = get_option('vton_login_url', $default_login_url);

            wp_localize_script('vton-script', 'vton_config', array(
                'ajax_url'     => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('vton_nonce'),
                'is_logged_in' => $is_logged_in,
                'user_credits' => $user_credits,
                'max_credits'  => $max_credits,
                'login_url'    => esc_url($login_url),
            ));
        }
    }

    /* ==========================================================================
       4. AJAX GENERATION HANDLER
       ========================================================================== */

    public function handle_vton_ajax() {
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
            @set_time_limit(120);
        }

        check_ajax_referer('vton_nonce', 'nonce');

        if ('yes' !== get_option('vton_enabled', 'yes')) {
            wp_send_json_error(array('message' => __('Virtual Try-On is currently disabled by the store owner.', 'virtual-trial-room-for-woocommerce')));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message'  => __('Please log in to continue.', 'virtual-trial-room-for-woocommerce'),
                'redirect' => true,
            ));
        }

        $user_id      = get_current_user_id();
        $max_credits  = (int) get_option('vton_max_credits', 3);
        $user_credits = $this->get_user_daily_credits($user_id);

        if ($user_credits <= 0) {
            wp_send_json_error(array('message' => __('You have reached your daily try-on limit. Please return tomorrow!', 'virtual-trial-room-for-woocommerce')));
        }

        $api_key = trim(get_option('vton_openai_api_key', ''));
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Store owner has not configured the OpenAI API key.', 'virtual-trial-room-for-woocommerce')));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // 1. Process Base64 User Image
        if (empty($_POST['user_image_base64'])) {
            wp_send_json_error(array('message' => __('User image is missing. Please select your photo.', 'virtual-trial-room-for-woocommerce')));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $base64_str = wp_unslash($_POST['user_image_base64']);
        if (preg_match('/^data:image\/(\w+);base64,/', $base64_str, $type)) {
            $base64_str = substr($base64_str, strpos($base64_str, ',') + 1);
            $ext        = strtolower($type[1]);
            if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png', 'webp'), true)) {
                wp_send_json_error(array('message' => __('Invalid image format.', 'virtual-trial-room-for-woocommerce')));
            }
        } else {
            wp_send_json_error(array('message' => __('Invalid image encoding.', 'virtual-trial-room-for-woocommerce')));
        }

        $decoded_image = base64_decode($base64_str);
        if (false === $decoded_image) {
            wp_send_json_error(array('message' => __('Image data decoding failed.', 'virtual-trial-room-for-woocommerce')));
        }

        $filename_user = 'vton_raw_u_' . wp_generate_password(8, false) . '.' . $ext;
        $upload_file   = wp_upload_bits($filename_user, null, $decoded_image);

        if (!empty($upload_file['error'])) {
            wp_send_json_error(array('message' => $upload_file['error']));
        }

        $raw_user_path = $upload_file['file'];

        // 2. Process Product Image
        $raw_product_path      = '';
        $temp_product_download = false;

        $prod_id = !empty($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($prod_id > 0 && function_exists('wc_get_product')) {
            $product_obj = wc_get_product($prod_id);
            if ($product_obj) {
                $image_id = $product_obj->get_image_id();
                if ($image_id) {
                    $local_file = get_attached_file($image_id);
                    if ($local_file && file_exists($local_file)) {
                        $raw_product_path = $local_file;
                    }
                }
            }
        }

        if (empty($raw_product_path) && !empty($_POST['product_image_url'])) {
            $raw_url       = sanitize_text_field(wp_unslash($_POST['product_image_url']));
            $image_url     = esc_url_raw($raw_url);
            $attachment_id = attachment_url_to_postid($image_url);

            if ($attachment_id) {
                $local_file = get_attached_file($attachment_id);
                if ($local_file && file_exists($local_file)) {
                    $raw_product_path = $local_file;
                }
            }

            if (empty($raw_product_path)) {
                $downloaded = download_url($image_url);
                if (!is_wp_error($downloaded)) {
                    $raw_product_path      = $downloaded;
                    $temp_product_download = true;
                }
            }
        }

        if (empty($raw_product_path) || !file_exists($raw_product_path)) {
            wp_delete_file($raw_user_path);
            wp_send_json_error(array('message' => __('Product image could not be processed.', 'virtual-trial-room-for-woocommerce')));
        }

        // 3. Convert Both Images to 1024x1024 Square PNG
        $upload_dir       = wp_upload_dir();
        $user_png_path    = $upload_dir['basedir'] . '/vton_u_' . wp_generate_password(8, false) . '.png';
        $product_png_path = $upload_dir['basedir'] . '/vton_p_' . wp_generate_password(8, false) . '.png';

        $editor_user = wp_get_image_editor($raw_user_path);
        if (is_wp_error($editor_user)) {
            wp_delete_file($raw_user_path);
            if ($temp_product_download && file_exists($raw_product_path)) {
                wp_delete_file($raw_product_path);
            }
            wp_send_json_error(array('message' => __('Could not process user image. Please try another photo.', 'virtual-trial-room-for-woocommerce')));
        }
        $editor_user->resize(1024, 1024, true);
        $editor_user->save($user_png_path, 'image/png');

        $editor_prod = wp_get_image_editor($raw_product_path);
        if (is_wp_error($editor_prod)) {
            wp_delete_file($raw_user_path);
            wp_delete_file($user_png_path);
            if ($temp_product_download && file_exists($raw_product_path)) {
                wp_delete_file($raw_product_path);
            }
            wp_send_json_error(array('message' => __('Could not process product image.', 'virtual-trial-room-for-woocommerce')));
        }
        $editor_prod->resize(1024, 1024, true);
        $editor_prod->save($product_png_path, 'image/png');

        $user_data    = file_get_contents($user_png_path);
        $product_data = file_get_contents($product_png_path);

        wp_delete_file($raw_user_path);
        wp_delete_file($user_png_path);
        wp_delete_file($product_png_path);
        if ($temp_product_download && file_exists($raw_product_path)) {
            wp_delete_file($raw_product_path);
        }

        // 4. Construct Multipart Payload
        $prompt = 'A high quality photorealistic image where the upper clothing of the person in the first image is replaced with the garment shown in the second image. Retain the exact face, skin tone, hair, and pose.';

        $boundary = wp_generate_password(24, false);
        $headers  = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
        );

        $body  = '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\n";
        $body .= 'gpt-image-2' . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="prompt"' . "\r\n\r\n";
        $body .= $prompt . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="size"' . "\r\n\r\n";
        $body .= '1024x1024' . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image[]"; filename="image1.png"' . "\r\n";
        $body .= 'Content-Type: image/png' . "\r\n\r\n";
        $body .= $user_data . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image[]"; filename="image2.png"' . "\r\n";
        $body .= 'Content-Type: image/png' . "\r\n\r\n";
        $body .= $product_data . "\r\n";

        $body .= '--' . $boundary . '--';

        // 5. Send Remote Post to OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/images/edits', array(
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 90,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $response_body = wp_remote_retrieve_body($response);
        $data          = json_decode($response_body, true);

        $generated_url = '';
        if (!empty($data['data'][0]['b64_json'])) {
            $generated_url = 'data:image/jpeg;base64,' . $data['data'][0]['b64_json'];
        } elseif (!empty($data['data'][0]['url'])) {
            $generated_url = $data['data'][0]['url'];
        }

        if (!empty($generated_url)) {
            $new_credits = max(0, $user_credits - 1);
            update_user_meta($user_id, 'vton_user_credits', $new_credits);
            update_user_meta($user_id, 'vton_credits_last_date', gmdate('Y-m-d'));

            $global_count = (int) get_option('vton_total_generations_count', 0);
            update_option('vton_total_generations_count', $global_count + 1);

            wp_send_json_success(array(
                'generated_image_url' => $generated_url,
                'remaining_credits'   => $new_credits,
            ));
        } else {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : __('Failed to generate try-on image.', 'virtual-trial-room-for-woocommerce');
            wp_send_json_error(array('message' => $err_msg));
        }
    }
}

new WCVirtualTryOn();