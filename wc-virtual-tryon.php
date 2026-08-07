<?php
/**
 * Plugin Name: WooCommerce Virtual Try-On Studio
 * Plugin URI:   https://yourdomain.com/wc-virtual-tryon
 * Description: AI-powered Virtual Try-On plugin for WooCommerce using OpenAI.
 * Version:     1.0.0
 * Author:      Khaleel Ahmad
 * Text Domain: wc-vton
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WCVirtualTryOn {

    public function __construct() {
        // Admin Settings Page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Inject Button on Product Page based on selected position
        add_action('wp', array($this, 'attach_tryon_button_hook'));

        // Enqueue Assets (JS & CSS)
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // AJAX Handler for AI Image Generation
        add_action('wp_ajax_vton_generate_image', array($this, 'handle_vton_ajax'));
        add_action('wp_ajax_nopriv_vton_generate_image', array($this, 'handle_vton_ajax'));
    }

    /* ==========================================================================
       1. WP-ADMIN SETTINGS PAGE
       ========================================================================== */

    public function add_admin_menu() {
        add_options_page(
            'Virtual Try-On Settings',
            'Virtual Try-On',
            'manage_options',
            'wc-vton-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        // Correct Option Group binding
        register_setting('vton_settings_group', 'vton_openai_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ''
        ));
        register_setting('vton_settings_group', 'vton_button_position', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'woocommerce_after_add_to_cart_button'
        ));
        register_setting('vton_settings_group', 'vton_button_text', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '✨ Virtual Try On'
        ));
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>✨ WooCommerce Virtual Try-On Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('vton_settings_group');
                do_settings_sections('vton_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input 
                                type="text" 
                                name="vton_openai_api_key" 
                                value="<?php echo esc_attr(get_option('vton_openai_api_key', '')); ?>" 
                                class="regular-text" 
                                placeholder="sk-proj-..." 
                                style="-webkit-text-security: disc;" 
                            />
                            <p class="description">Enter OpenAI API Key for image processing.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Button Position</th>
                        <td>
                            <select name="vton_button_position">
                                <option value="woocommerce_before_add_to_cart_button" <?php selected(get_option('vton_button_position'), 'woocommerce_before_add_to_cart_button'); ?>>Before "Add to Cart" Button</option>
                                <option value="woocommerce_after_add_to_cart_button" <?php selected(get_option('vton_button_position'), 'woocommerce_after_add_to_cart_button'); ?>>After "Add to Cart" Button</option>
                                <option value="woocommerce_after_add_to_cart_quantity" <?php selected(get_option('vton_button_position'), 'woocommerce_after_add_to_cart_quantity'); ?>>Beside Quantity Input</option>
                                <option value="woocommerce_single_product_summary" <?php selected(get_option('vton_button_position'), 'woocommerce_single_product_summary'); ?>>Product Summary Footer</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Button Label Text</th>
                        <td>
                            <input type="text" name="vton_button_text" value="<?php echo esc_attr(get_option('vton_button_text', '✨ Virtual Try On')); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ==========================================================================
       2. DYNAMIC BUTTON INJECTION
       ========================================================================== */

    public function attach_tryon_button_hook() {
        if (is_product()) {
            $position_hook = get_option('vton_button_position', 'woocommerce_after_add_to_cart_button');
            add_action($position_hook, array($this, 'render_tryon_button'));
        }
    }

public function render_tryon_button() {
    global $product;
    if (!$product) return;

    $product_id = $product->get_id(); // Get Product ID
    $product_image_id = $product->get_image_id();
    $product_image_url = wp_get_attachment_image_url($product_image_id, 'full');
    $button_text = get_option('vton_button_text', 'Virtual Try On');

    echo '<div class="vton-button-wrapper" style="margin: 10px 0;">
            <button type="button" class="button vton-trigger-btn" data-product-id="' . esc_attr($product_id) . '" data-product-img="' . esc_url($product_image_url) . '">
                ' . esc_html($button_text) . '
            </button>
          </div>';
}

    /* ==========================================================================
       3. FRONTEND ASSETS & SCRIPT LOCALIZATION
       ========================================================================== */

    public function enqueue_frontend_assets() {
        if (is_product()) {
            wp_enqueue_style('vton-style', plugin_dir_url(__FILE__) . 'assets/css/vton-widget.css', array(), '1.0.0');
            wp_enqueue_script('vton-script', plugin_dir_url(__FILE__) . 'assets/js/vton-widget.js', array('jquery'), '1.0.0', true);

            // Pass WP AJAX URL & Nonce to React/JS
            wp_localize_script('vton-script', 'vton_config', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('vton_nonce')
            ));
        }
    }

    /* ==========================================================================
       4. HIGH-TIMEOUT PHP AJAX HANDLER FOR OPENAI
       ========================================================================== */

    public function handle_vton_ajax() {
        // Prevent script timeout (Allow up to 120 seconds for AI processing)
        @set_time_limit(120);

        check_ajax_referer('vton_nonce', 'nonce');

        $api_key = trim(get_option('vton_openai_api_key', ''));

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'Store owner has not configured the OpenAI API key.'));
        }

        if (empty($_FILES['user_image'])) {
            wp_send_json_error(array('message' => 'User image is missing.'));
        }

        // Handle User Image File
        $user_file = $_FILES['user_image'];
        $user_file_path = $user_file['tmp_name'];

        // Handle Product Image (Custom Upload OR Download from Store URL)
        $product_file_path = '';
        if (!empty($_FILES['custom_product_image'])) {
            $product_file_path = $_FILES['custom_product_image']['tmp_name'];
        } elseif (!empty($_POST['product_image_url'])) {
            $image_url = esc_url_raw($_POST['product_image_url']);
            $temp_img = download_url($image_url);
            if (!is_wp_error($temp_img)) {
                $product_file_path = $temp_img;
            }
        }

        if (empty($product_file_path)) {
            wp_send_json_error(array('message' => 'Product image could not be processed.'));
        }

        // Prepare Prompt Rules
        $prompt = "You are an AI virtual try-on system. Replace the person's current upper clothing shown in Image 1 with the EXACT outfit shown in Image 2. Return ONE photorealistic image preserving face, skin tone, hair, posture and background.";

        // Prepare Multipart Payload for OpenAI
        $boundary = wp_generate_password(24, false);
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
        );

        $body = '';

        // Model field
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\n";
        $body .= 'gpt-image-2' . "\r\n";

        // Prompt field
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="prompt"' . "\r\n\r\n";
        $body .= $prompt . "\r\n";

        // Size field (1024x1024)
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="size"' . "\r\n\r\n";
        $body .= '1024x1024' . "\r\n";

        // Quality field (low / standard)
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="quality"' . "\r\n\r\n";
        $body .= 'low' . "\r\n";

        // Image 1: Person File
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image[]"; filename="person.jpg"' . "\r\n";
        $body .= 'Content-Type: image/jpeg' . "\r\n\r\n";
        $body .= file_get_contents($user_file_path) . "\r\n";

        // Image 2: Garment File
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="image[]"; filename="garment.jpg"' . "\r\n";
        $body .= 'Content-Type: image/jpeg' . "\r\n\r\n";
        $body .= file_get_contents($product_file_path) . "\r\n";

        $body .= '--' . $boundary . '--';

        // Call OpenAI Images Edit API with 90 Second Timeout
        $response = wp_remote_post('https://api.openai.com/v1/images/edits', array(
            'method'    => 'POST',
            'headers'   => $headers,
            'body'      => $body,
            'timeout'   => 90, // Wait up to 90 seconds
        ));

        // Clean temp downloaded product image if created
        if (!empty($_POST['product_image_url']) && file_exists($product_file_path)) {
            @unlink($product_file_path);
        }

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (!empty($data['data'][0]['b64_json'])) {
            wp_send_json_success(array(
                'generated_image_url' => 'data:image/jpeg;base64,' . $data['data'][0]['b64_json']
            ));
        } elseif (!empty($data['data'][0]['url'])) {
            wp_send_json_success(array(
                'generated_image_url' => $data['data'][0]['url']
            ));
        } else {
            $err_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to generate try-on image.';
            wp_send_json_error(array('message' => $err_msg));
        }
    }
}

// Initialize Plugin
new WCVirtualTryOn();