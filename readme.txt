=== CoderEmbassy Checkout Fields Manager ===
Contributors: codersaleh
Tags: woocommerce, checkout, custom fields, checkout fields, woocommerce checkout
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.4
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add and manage custom fields on your WooCommerce checkout — with validation, customer types, and full block checkout support.

== Description ==

CoderEmbassy Checkout Fields Manager lets you add custom fields to the WooCommerce checkout page without writing any code.

It works with both the classic checkout and the newer block-based checkout, which is where most checkout field plugins fall short.

**Features:**

* Add text, textarea, select, radio, checkbox, checkbox group, number, email, phone, date, heading and paragraph fields
* Full support for the WooCommerce Checkout block via the Additional Checkout Fields API
* Works with the classic checkout shortcode too
* Rename, reorder, or switch off WooCommerce's own built-in checkout fields
* Private and Company customer types, with an optional type switcher on checkout
* Show or hide any field depending on the selected customer type
* Hidden fields skip validation entirely, so they never block checkout
* Validation rules: required, email format, and minimum/maximum length or value
* Field width control (full, half) and position on the checkout page
* Drag-and-drop field reordering
* Revision history, so you can roll a field back to an earlier version
* Field values saved to the order and shown on the order screen, order emails, and the Thank You page

== Frequently Asked Questions ==

= Does this work with the WooCommerce block checkout? =
Yes. Use **Add Block Field** to register fields with the WooCommerce Additional Checkout Fields API. Supported types are text, select, and checkbox.

= Will this work with my theme? =
Yes. The plugin hooks into WooCommerce's standard checkout actions and works with any WooCommerce-compatible theme.

= Are my fields saved to orders? =
Yes. All field values are saved to the order meta and displayed in the order admin screen, order emails, and the Thank You page (if enabled).

= What customer types are included? =
Private and Company. Private is always active; Company can be switched on from the Customer Types screen. When both are active a type switcher appears on checkout, and each field can be shown to one type or both.

= Can I create my own customer types? =
Not in the free plugin — it manages the built-in Private and Company pair. The Pro add-on adds unlimited custom types.

= What does the Pro add-on add? =
Unlimited custom customer types, conditional logic with ten rule types and AND/OR groups, conditional required rules, a pricing and fee engine, file upload, multi-select and repeater fields, custom sections for grouping fields, saved templates, import/export, an admin checkout preview, analytics, and WPML support.

The Pro add-on installs alongside this plugin and requires it — your fields and settings are never moved or duplicated, and removing Pro leaves all of them intact.

== Screenshots ==

1. Field Builder — list of custom fields with drag-and-drop reordering
2. Add New Field — form to configure a classic or block checkout field
3. Customer Types — enable the Company type alongside Private
4. WooCommerce checkout — custom fields rendered on the checkout page
5. Order details — field values saved and displayed on the order

== Changelog ==

= 1.0.4 =
* Fixed: none of the plugin's front-end styles were applied, because the stylesheet still used an old class prefix that no longer matched the markup. The customer type switcher, custom sections and field descriptions now render as intended.
* Fixed: switching a built-in field back on had no effect when WooCommerce itself was set to hide it. Company, Address line 2 and Phone are now restored when you enable them.
* Fixed: the customer type list could be missing its default entry, which hid the type switcher on checkout. It is now created automatically.
* Fixed: editing an existing field could fail with a JavaScript error before the form appeared.
* Fixed: custom field tables failed to create on MySQL 8.0.13 and newer, because TEXT columns carried default values that MySQL rejects. Affected installs repair themselves automatically on update.
* Fixed: the plugin only ever checked one of its tables when verifying the schema, so a partial install never healed itself.
* Fixed: developer hooks were registered with an uppercase prefix and never fired. They now use the documented lowercase `cecfm_` names.
* Changed: the plugin is now the base for an optional Pro add-on. Extension points are documented in `src/ExtensionPoints.php`.
* Removed: the readme previously listed Import / Export as a feature; it was never present in the free plugin. It is available in the Pro add-on.

= 1.0.1 =
* Maintenance release.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.4 =
Restores the plugin's checkout styling, which was not being applied at all, and fixes built-in fields not reappearing when switched back on.

== External services ==

This plugin conditionally loads the Google Maps JavaScript API with Places for address autofill. Address autofill assists customers in automatically completing address fields on the checkout page when a Google Maps API Key is provided by the administrator in the plugin settings.

When address autofill is active, the customer's browser loads the Google Maps script directly from Google's servers. As the user types their address, keypress inputs are sent directly to Google APIs to retrieve autocomplete suggestions. No other personal or customer data is sent or processed by this service.

This service is provided by Google:
* Google APIs Terms of Service: https://developers.google.com/maps/terms
* Google Privacy Policy: https://policies.google.com/privacy
