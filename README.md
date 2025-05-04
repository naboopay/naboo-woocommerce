# Naboopay WooCommerce Integration

This plugin integrates the Naboopay payment gateway with WooCommerce, enabling customers to pay using Naboopay-supported methods (WAVE, ORANGE_MONEY, FREE_MONEY, BANK) during checkout. It also supports real-time webhook notifications to update order statuses automatically.

## Features
- Seamless integration with WooCommerce checkout process.
- Redirects users to the Naboopay checkout page for secure payment processing.
- Supports multiple payment methods: WAVE, ORANGE_MONEY and FREE_MONEY.
- Handles webhook notifications for real-time order status updates.
- Secure API communication using an API token and webhook secret key.
- Configurable order status after successful payment ("Processing" or "Completed").
- Detailed error logging for troubleshooting.

## Requirements
- WordPress 5.6 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher (compatible with PHP 8.2+)
- A Naboopay account with API credentials (API token and webhook secret key)

## Installation
1. Download the plugin zip file from the [releases page](https://github.com/naboopay/naboopay-woocommerce-integration/releases) (update this link if you have a GitHub repo).
2. In your WordPress dashboard, go to **Plugins > Add New > Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. Activate the plugin via the **Plugins** menu.

## Configuration
1. Navigate to **WooCommerce > Settings > Payments** in your WordPress admin panel.
2. Locate **Naboopay** in the payment methods list and click **Manage**.
3. Enable the gateway by checking **Enable Naboopay**.
4. Enter your **API Token** provided by Naboopay.
5. Enter your **Webhook Secret Key** for signature verification (generate this in your Naboopay dashboard).
6. Choose the **Status After Payment** ("Processing" or "Completed") to set the order status after a successful payment.
7. Copy the **Webhook URL** (e.g., `https://yoursite.com/wp-json/naboopay/v1/webhook`) and register it in your Naboopay dashboard under **Security Settings > Webhooks**.
8. Save the settings.

## Usage
1. During checkout, customers can select **Naboopay** as their payment method.
2. They will be redirected to the Naboopay checkout page to complete the payment using WAVE, ORANGE_MONEY, FREE_MONEY, or BANK.
3. Upon successful payment, customers are redirected back to your store, and the order status updates automatically via webhooks.

## Webhook Handling
The plugin processes Naboopay webhook notifications to update WooCommerce order statuses:
- `paid`: Sets the order to the configured status ("Processing" or "Completed").
- `cancel`: Sets the order to "Cancelled".
- `pending`: Sets the order to "Pending".
- `part_paid`: Sets the order to "On Hold".

## Testing
1. Use test credentials from Naboopay (if available) to simulate payments.
2. Trigger webhook events from the Naboopay dashboard to test status updates.
3. Enable WordPress debugging (`WP_DEBUG` and `WP_DEBUG_LOG`) to log API responses and errors:
   - Edit `wp-config.php` and add:
     ```php
     define('WP_DEBUG', true);
     define('WP_DEBUG_LOG', true);
     define('WP_DEBUG_DISPLAY', false);
     ```
   - Check logs in `wp-content/debug.log`.

## Troubleshooting
- **Payment fails with "Erreur serveur (HTTP 422)"**: Ensure `method_of_payment` values are correctly formatted (`WAVE`, `ORANGE_MONEY`, `FREE_MONEY`, `BANK`) without spaces.
- **Webhook not updating orders**: Verify the webhook URL is correctly registered in Naboopay and the secret key matches.
- **API connection issues**: Check your API token and ensure your server can reach `https://api.naboopay.com`.
- **Order created but payment fails**: Review `debug.log` for detailed error messages and validate product data (e.g., `amount` must be a valid number).

## Supported Currencies
- The plugin assumes compatibility with Naboopay-supported currencies (e.g., XOF). Confirm with Naboopay support if your store uses a different currency.

## License
This plugin is licensed under the [GPL-2.0 License](https://www.gnu.org/licenses/gpl-2.0.html).

## Support
For assistance, open an issue on the [GitHub repository](https://github.com/naboopay/naboopay-woocommerce-integration/issues) (update this link if applicable) or contact Naboopay support.

## Contributing
Contributions are welcome! Fork the repository, make your changes, and submit a pull request.

## Changelog
- **1.0.5**: Fixed `method_of_payment` validation for API compatibility (replaced spaces with underscores).
- **1.0.4**: Added PHP 8.2 compatibility with explicit property declarations.
- **1.0.0**: Initial release with basic payment and webhook functionality.

