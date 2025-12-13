# FenanPay WooCommerce Gateway

![FenanPay Logo](https://fenanpay.com/logo.png)

**Contributors:** FenanPay  
**Tags:** woocommerce, payment gateway, fenanpay, payment processing  
**Requires at least:** WordPress 5.0, WooCommerce 4.0  
**Tested up to:** WordPress 6.0, WooCommerce 7.0  
**Stable tag:** 0.1.0  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html

Accept payments in your WooCommerce store using FenanPay's secure payment gateway.

## Description

FenanPay WooCommerce Gateway allows you to accept payments through the FenanPay payment processing platform. This plugin provides a seamless integration between your WooCommerce store and FenanPay's payment infrastructure.

### Key Features

- Secure payment processing via FenanPay
- Simple setup and configuration
- Support for all major credit and debit cards
- Webhook support for real-time payment status updates
- Test mode for development and testing
- Detailed transaction logging
- Mobile responsive payment form

## Installation

### Manual Installation

1. Upload the `fenanpay-woocommerce` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce → Settings → Payments → FenanPay
4. Configure your API credentials and other settings

### Requirements

- WordPress 5.0 or later
- WooCommerce 4.0 or later
- PHP 7.4 or later
- cURL extension enabled
- SSL certificate (HTTPS required for live transactions)

## Configuration

1. **Enable the Gateway**
   - Go to WooCommerce → Settings → Payments
   - Click on "Set up" under FenanPay
   - Toggle "Enable/Disable" to enable the payment method

2. **API Credentials**
   - Enter your FenanPay API Key and Secret
   - Set your Merchant ID (provided by FenanPay)
   - Configure the webhook secret for secure callbacks

3. **General Settings**
   - Set the title that appears during checkout
   - Add a description for the payment method
   - Upload a custom icon (optional)
   - Set the order status for successful payments

4. **Test Mode**
   - Enable test mode for development and testing
   - Use test API credentials provided by FenanPay

## Webhook Setup

To receive real-time payment notifications, please configure the following webhook in your FenanPay merchant dashboard:

- **Webhook URL:** `https://your-site.com/wc-api/fenanpay-webhook`
- **Events to receive:** All payment events
- **Authentication:** Enable HMAC verification
- **Secret Key:** [Your webhook secret from plugin settings]

## Frequently Asked Questions

### How do I get API credentials?
Contact FenanPay support at support@fenanpay.com to get your API credentials and merchant ID.

### Is this plugin compatible with my theme?
Yes, the plugin is compatible with any WordPress theme that supports WooCommerce.

### How do I enable debug mode?
Enable debug logging in the plugin settings to log API requests and responses for troubleshooting.

## Support

For support, please contact:
- Email: support@fenanpay.com
- Website: [https://fenanpay.com/support](https://fenanpay.com/support)
- Documentation: [https://fenanpay.com/docs](https://fenanpay.com/docs)

## Changelog

### 0.1.0
- Initial release
- Basic payment processing functionality
- Webhook support for payment status updates
- Test mode for development

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

We welcome contributions to the FenanPay WooCommerce Gateway. Please see our [GitHub repository](https://github.com/fenanpay/woocommerce-gateway-fenanpay) for more information.

---

*FenanPay and the FenanPay logo are registered trademarks of FenanPay.*
