=== Affiliate Client Integration ===
Contributors: affiliatesystemteam
Donate link: https://affiliate-system.com/donate
Tags: affiliate, discount, validation, client, integration, cross-domain
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seamlessly integrate affiliate discount validation into your WordPress site with secure cross-domain communication.

== Description ==

**Affiliate Client Integration** is a powerful WordPress plugin that enables your website to validate affiliate discount codes through a secure cross-domain system. Perfect for businesses that want to offer affiliate-based discounts while maintaining complete control over their pricing and validation logic.

= Key Features =

* **Secure Code Validation** - Validate affiliate codes through encrypted API communication
* **Flexible Display Options** - Multiple popup styles, inline forms, and custom shortcodes
* **Real-time Verification** - Instant validation with detailed response handling
* **Customizable Forms** - Add custom fields and styling to match your brand
* **Mobile Responsive** - Fully optimized for all device sizes
* **Security First** - Rate limiting, encryption, and secure token validation
* **Easy Integration** - Simple shortcodes and widgets for quick implementation

= Perfect For =

* E-commerce sites offering affiliate discounts
* Membership sites with partner promotions
* Service providers with referral programs
* Any business with affiliate marketing partnerships

= How It Works =

1. **Install & Configure** - Set up your affiliate domain connection and API keys
2. **Add Forms** - Use shortcodes or widgets to add affiliate code forms
3. **Customers Enter Codes** - Users submit their affiliate codes through your forms
4. **Instant Validation** - Codes are validated against your affiliate system
5. **Apply Discounts** - Valid codes trigger discount application on your site

= Shortcodes Available =

* `[affiliate_form]` - Display an affiliate code entry form
* `[affiliate_popup_trigger]` - Add a button that opens a popup form
* `[affiliate_url_parameter]` - Display URL parameter values
* `[affiliate_discount_display]` - Show applied discount information

= Developer Friendly =

* Extensive hooks and filters for customization
* Clean, documented code following WordPress standards
* REST API endpoints for custom integrations
* Comprehensive error handling and logging

= Security & Performance =

* SSL/TLS encryption for all communications
* Rate limiting to prevent abuse
* Caching for improved performance
* Input sanitization and validation
* CSRF protection on all forms

== Installation ==

= Automatic Installation =

1. Go to your WordPress admin dashboard
2. Navigate to Plugins > Add New
3. Search for "Affiliate Client Integration"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Upload it to your `/wp-content/plugins/` directory
3. Extract the zip file
4. Activate the plugin through the 'Plugins' menu in WordPress

= After Installation =

1. Go to **Settings > Affiliate Integration**
2. Enter your affiliate domain URL and API key
3. Test the connection to ensure everything is working
4. Customize your form settings and appearance
5. Add shortcodes to your pages where you want affiliate forms

== Configuration ==

= Basic Setup =

1. **Affiliate Domain**: Enter the URL of your main affiliate system
2. **API Key**: Obtain and enter your API key from the affiliate system
3. **Connection Test**: Use the built-in test to verify connectivity

= Form Settings =

* **Form Style**: Choose between inline, popup, or custom styling
* **Required Fields**: Configure which fields are required for validation
* **Success Actions**: Define what happens after successful validation
* **Error Handling**: Customize error messages and retry behavior

= Advanced Options =

* **Cache Duration**: Set how long to cache validation results
* **Rate Limiting**: Configure request limits to prevent abuse
* **Custom CSS**: Add your own styling to match your theme
* **Webhook URLs**: Set up notifications for successful validations

== Frequently Asked Questions ==

= Do I need a separate affiliate management system? =

Yes, this plugin is designed to work with an existing affiliate management system. It serves as the client-side integration that communicates with your main affiliate platform.

= Is this plugin secure? =

Absolutely. The plugin uses industry-standard security practices including SSL encryption, rate limiting, input sanitization, and secure token validation.

= Can I customize the form appearance? =

Yes! The plugin provides multiple styling options, custom CSS support, and various popup styles. You can make it match your site's design perfectly.

= Will this work with my existing theme? =

Yes, the plugin is designed to work with any properly coded WordPress theme. It includes responsive CSS and follows WordPress coding standards.

= How do I add an affiliate form to my page? =

Simply use the `[affiliate_form]` shortcode on any page or post. You can also use the popup trigger shortcode `[affiliate_popup_trigger]` for a button that opens a popup form.

= What happens when a valid code is entered? =

When a valid affiliate code is entered, the plugin triggers customizable actions such as applying discounts, redirecting to specific pages, or displaying success messages. This is configured in your settings.

= Can I track affiliate code usage? =

Yes, all validation attempts are logged and can be tracked through your main affiliate system. The plugin also provides hooks for custom tracking implementations.

= Is there support for multiple affiliate programs? =

The plugin is designed to work with one primary affiliate system, but it can handle multiple affiliate codes and programs managed by that system.

= What if the affiliate system is temporarily unavailable? =

The plugin includes fallback mechanisms and error handling. You can configure backup validation methods or graceful degradation when the main system is unavailable.

== Screenshots ==

1. **Settings Page** - Easy configuration of your affiliate system connection
2. **Inline Form** - Clean, responsive affiliate code entry form
3. **Popup Form** - Elegant popup for affiliate code entry
4. **Fullscreen Experience** - Immersive fullscreen validation experience
5. **Mobile Responsive** - Perfect display on all device sizes
6. **Success States** - Clear feedback when codes are validated
7. **Admin Dashboard** - Comprehensive management interface

== Changelog ==

= 1.0.0 - 2025-01-20 =
* Initial release
* Secure affiliate code validation system
* Multiple form display options (inline, popup, fullscreen)
* Comprehensive shortcode system
* Mobile-responsive design
* Rate limiting and security features
* Customizable styling options
* REST API endpoints
* Extensive documentation
* WordPress 6.4 compatibility
* PHP 8.2 compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Affiliate Client Integration. No upgrade needed.

== Third Party Services ==

This plugin communicates with external affiliate management systems to validate discount codes. When users submit affiliate codes through forms created by this plugin:

* Code validation requests are sent to the configured affiliate domain
* Data transmitted includes the affiliate code and optional user information
* Communication is secured using SSL/TLS encryption
* No personal data is stored locally without explicit configuration

**Important**: Ensure you have proper agreements and privacy policies in place with your affiliate system provider.

== Privacy and Data Handling ==

= Data Collection =
* Affiliate codes entered by users (temporarily for validation)
* Optional user information if configured (name, email, etc.)
* IP addresses for rate limiting and security purposes
* Validation timestamps and results for logging

= Data Storage =
* Validation results may be cached temporarily for performance
* User data is only stored if explicitly configured
* All data handling follows WordPress privacy standards
* Support for data export and deletion requests

= Data Sharing =
* Affiliate codes are shared with your configured affiliate system for validation
* Optional user data is shared only if configured and consented to
* No data is shared with third parties beyond your affiliate system
* All communications use secure, encrypted connections

== Support and Documentation ==

= Getting Help =
* **Documentation**: Visit our comprehensive docs at https://docs.affiliate-system.com
* **Support Forum**: Get help from the community
* **Email Support**: support@affiliate-system.com for premium support
* **GitHub Issues**: Report bugs at https://github.com/affiliate-system/client-integration

= Contributing =
We welcome contributions! Please see our contributing guidelines on GitHub.

= Translation =
Help translate this plugin into your language! Translation files are available on WordPress.org.

== Technical Requirements ==

= Minimum Requirements =
* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or MariaDB 10.0
* cURL extension enabled
* OpenSSL extension enabled
* JSON extension enabled

= Recommended =
* WordPress 6.0+
* PHP 8.1+
* MySQL 8.0+ or MariaDB 10.4+
* Redis or Memcached for caching
* SSL certificate for secure communications

= Server Configuration =
* Allow outbound HTTPS connections
* Sufficient memory limit (256MB recommended)
* Maximum execution time of 30 seconds or more
* File upload capability for media management

== Advanced Usage ==

= Custom Integration =
```php
// Validate a code programmatically
$result = ACI_API_Client::validate_code('AFFILIATE123', [
    'user_email' => 'user@example.com'
]);

if ($result['valid']) {
    // Apply discount logic
    apply_affiliate_discount($result['discount']);
}
```

= Hooks and Filters =
```php
// Customize validation response
add_filter('aci_validation_response', function($response, $code) {
    // Modify response before processing
    return $response;
}, 10, 2);

// Custom success action
add_action('aci_code_validated', function($code, $response) {
    // Custom logic after successful validation
}, 10, 2);
```

= REST API Endpoints =
* `GET /wp-json/aci/v1/status` - Check plugin status
* `POST /wp-json/aci/v1/validate` - Validate affiliate code
* `GET /wp-json/aci/v1/settings` - Get public settings

== License ==

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

== Credits ==

= Development Team =
* Lead Developer: Affiliate System Team
* UI/UX Design: Professional design team
* Security Consultant: External security experts
* QA Testing: Comprehensive testing team

= Special Thanks =
* WordPress community for coding standards and best practices
* Beta testers who provided valuable feedback
* Translation contributors for internationalization support
* Open source libraries that make this plugin possible

= Third Party Libraries =
* WordPress HTTP API for secure communications
* jQuery for frontend interactions
* Various WordPress core functions and hooks

For a complete list of dependencies, see the composer.json file included with the plugin.