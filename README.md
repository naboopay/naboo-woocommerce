# Naboo WooCommerce Integration

This plugin integrates the Naboo payment gateway with WooCommerce, allowing customers to pay using Naboo during checkout. It also handles real-time webhook notifications to update order statuses.

## Features
- Seamless integration with WooCommerce checkout.
- Redirects users to the Naboo checkout page for payment.
- Handles webhook notifications for real-time order status updates.
- Secure communication using API key and secret key.

## Requirements
- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher
- A Naboo account with API credentials

## Installation
1. Download the plugin zip file from the [releases page](https://github.com/naboopay/naboo-woocommerce-integration/releases).
2. Go to your WordPress dashboard and navigate to **Plugins > Add New > Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. Activate the plugin.

## Configuration
1. Go to **WooCommerce > Settings > Payments**.
2. Enable the **Naboo Pay** gateway.
3. Enter your Naboo API key and secret key.
4. Note the webhook URL (e.g., `https://app.naboopay.com/security`) and register it in your Naboo dashboard under **Security Settings > Webhooks**.
5. Generate and save a secret key in Naboo for signature verification.

## Usage
1. During checkout, select **Naboo Pay** as the payment method.
2. You will be redirected to the Naboo checkout page to complete the payment.
3. After payment, you will be redirected back to the store, and the order status will be updated automatically via webhooks.

## Webhook Handling
The plugin automatically handles webhook notifications to update order statuses:
- `paid`: Sets the order to "Processing".
- `cancel`: Sets the order to "Cancelled".
- `pending`: Sets the order to "Pending".
- `part_paid`: Sets the order to "On Hold".

## Testing
1. Use the Naboo dashboard to simulate webhook events for testing.
2. Verify that order statuses are updated correctly in WooCommerce.

## Troubleshooting
- **Payment fails to process**: Check your API key and ensure the Naboo API is accessible.
- **Webhook not updating orders**: Verify the webhook URL and secret key in Naboo settings.
- **Currency issues**: Ensure your store's currency is compatible with Naboo (XOF).

## License
This plugin is licensed under the GPL-2.0 License.

## Support
For support, please open an issue on the [GitHub repository](https://github.com/naboopay/naboo-woocommerce-integration/issues).
