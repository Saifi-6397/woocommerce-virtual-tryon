# Virtual Trial Room for WooCommerce

Contributors: Saifi-6397
Donate link: https://github.com/Saifi-6397
Tags: woocommerce, virtual try on, ai, fashion, clothing
Tested up to: 7.1
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered Virtual Trial Room for WooCommerce stores using OpenAI image editing API.

---

## Description

Virtual Trial Room for WooCommerce allows customers to upload their photo and virtually preview clothing items directly on single product pages. The plugin runs entirely on the store's WordPress environment via native AJAX handlers and includes user credit controls and guest authentication redirects.

---

## Features

* **WooCommerce Integration:** Injects a try-on button directly on single product pages using native hooks.
* **Flexible Placement:** Configure button placement (Before/After Add to Cart, Beside Quantity, Summary Footer).
* **Credit Limit System:** Store owners can define per-user try-on credits from the settings page.
* **Guest Authentication:** Redirects guest users directly to a designated login or signup URL on try-on submission.
* **Live Progress Feedback:** Real-time percentage progress bar animation during image processing.
* **Self-Contained:** Native WordPress AJAX endpoint without external server dependencies.

---

## Installation

1. **Download the Plugin:**
   * Download the repository ZIP file via GitHub Releases or direct archive download.
2. **Upload to WordPress:**
   * Navigate to **Plugins -> Add New -> Upload Plugin** in your WordPress Admin Dashboard.
   * Upload the `.zip` archive and click **Install Now**.
   * Click **Activate Plugin**.

---

## Configuration & Setup

1. Open **Settings -> Virtual Try-On** in the WordPress Dashboard.
2. **OpenAI API Key:** Paste your valid OpenAI API Key (`sk-proj-...`).
3. **Allowed Credits Per User:** Set the maximum try-on attempts permitted for registered users.
4. **Login / Signup Page URL:** Enter your store's account or login page URL (defaults to `/my-account/`).
5. **Button Position:** Select your preferred WooCommerce template hook.
6. **Button Label:** Customize the front-end button text.
7. Click **Save Changes**.

---

## How It Works

1. The customer selects **Virtual Try On** on a product page to launch the modal studio.
2. The modal pre-selects the product garment and prompts for a user photo upload.
3. Submitting **Try It On Me!** executes the authentication and credit checks.
4. An AJAX request transmits the image data to OpenAI's image edits endpoint.
5. The processed try-on image renders in the modal preview with session persistence.

---

## Tech Stack & Prerequisites

* **WordPress:** 5.8+
* **WooCommerce:** 6.0+
* **PHP:** 7.4 or 8.x
* **OpenAI API Key:** Active OpenAI account with access to image editing models.

---

## Author

Developed by **Khaleel Ahmad**  
GitHub: [@Saifi-6397](https://github.com/Saifi-6397)