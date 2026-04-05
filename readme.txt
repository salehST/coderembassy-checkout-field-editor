=== CoderEmbassy Checkout Field Editor ===
Contributors: codersaleh
Tags: woocommerce, checkout, custom fields, checkout fields, woocommerce checkout
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add and manage custom fields on your WooCommerce checkout — with customer type visibility, validation, and block checkout support.

== Description ==

CoderEmbassy Checkout Field Editor lets you add custom fields to the WooCommerce checkout page without writing any code.

**Features:**

* Add text, textarea, select, checkbox, radio, number, email, phone, date, and more
* Show or hide fields per customer type (e.g. Private, Company)
* Support for WooCommerce classic checkout and block-based checkout
* Add Block Fields (text, select, checkbox) for the WooCommerce Checkout block
* Required field validation
* Field width control (full, 1/2, 1/3)
* Choose field position on the checkout page
* Show field values on the Thank You page and order emails
* Drag-and-drop field reordering
* Import / Export fields

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Go to **WooCommerce → Checkout Fields** to add and manage your fields
4. Use **Add Classic Field** for classic checkout or **Add Block Field** for the WooCommerce Checkout block

== Frequently Asked Questions ==

= Does this work with the WooCommerce block checkout? =
Yes. Use **Add Block Field** to register fields with the WooCommerce Additional Checkout Fields API. Supported types are text, select, and checkbox.

= Will this work with my theme? =
Yes. The plugin hooks into WooCommerce's standard checkout actions and works with any WooCommerce-compatible theme.

= Are my fields saved to orders? =
Yes. All field values are saved to the order meta and displayed in the order admin screen, order emails, and the Thank You page (if enabled).

= Where can I see field values after checkout? =
Field values appear in WooCommerce → Orders (order details screen), order confirmation emails, and the Thank You page when those options are enabled.

== Screenshots ==

1. Field Builder — list of custom fields with drag-and-drop reordering
2. Add New Field — form to configure a classic or block checkout field
3. WooCommerce checkout — custom fields rendered on the checkout page
4. Order details — field values saved and displayed on the order

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.

