# WooCommerce Virtual Try-On

An AI-powered Virtual Try-On plugin for WooCommerce stores powered by OpenAI's `gpt-image-2` image editing API. This plugin allows customers to upload their photo and virtually try on clothing products directly on the product detail page before purchasing.

---

## Features

* **Seamless WooCommerce Integration:** Automatically injects the "Virtual Try On" button on single product pages.
* **Flexible Button Positioning:** Choose where the try-on button appears from the WP Admin panel (Before/After Add to Cart, Beside Quantity, or Summary Footer).
* **Native "Add to Cart" Trigger:** Automatically converts to an **Add to Cart** button after image generation to trigger the store's native cart behavior without page redirects.
* **Custom Product Upload Support:** Allows users to test the try-on feature with the store's default product or upload a custom garment.
* **Self-Contained Architecture:** Operates entirely within WordPress via native AJAX endpoints. No external Node.js or third-party servers required.
* **High Timeout Limit:** Configured with a 120-second execution time limit to handle generative AI models smoothly without timeouts.

---

## Installation

1. **Download the Plugin:**
   * Download the repository as a ZIP file by clicking **Code -> Download ZIP** on GitHub (or download from the [Releases](https://github.com/Saifi-6397/woocommerce-virtual-tryon/releases) page).

2. **Upload to WordPress:**
   * Log in to your WordPress Dashboard.
   * Go to **Plugins -> Add New -> Upload Plugin**.
   * Choose the downloaded `.zip` file and click **Install Now**.
   * Click **Activate Plugin**.

---

## Configuration & Setup

1. Go to **Settings -> Virtual Try-On** in your WordPress Admin Dashboard.
2. **OpenAI API Key:** Enter your store's OpenAI API Key (`sk-proj-...`).
3. **Button Position:** Select your preferred hook location on the single product page:
   * *Before "Add to Cart" Button*
   * *After "Add to Cart" Button*
   * *Beside Quantity Input*
   * *Product Summary Footer*
4. **Button Label:** Customize the display text (Default: `✨ Virtual Try On`).
5. Click **Save Changes**.

---

## How It Works

1. Customer clicks the **Virtual Try On** button on any single product page.
2. A modal overlay opens with the product pre-selected.
3. Customer uploads their photo.
4. Clicking **"Try It On Me!"** sends an AJAX request to the WordPress backend, which calls OpenAI's image editing API with formatted prompts.
5. Upon generation, the preview is displayed inside the modal, and the primary action button seamlessly switches to **"🛒 Add to Cart"**.
6. Clicking **Add to Cart** triggers the theme's native WooCommerce add-to-cart functionality.

---

## Tech Stack & Prerequisites

* **WordPress:** 5.8+
* **WooCommerce:** 6.0+
* **PHP:** 7.4 or 8.x
* **OpenAI API Key:** Active billing account with access to image editing models.

---

## Author

Developed with by **Khaleel Ahmad**
