<?php
class WC_Naboo_Gateway extends WC_Payment_Gateway
{
    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id = "naboo";
        $this->method_title = "Naboo Pay";
        $this->method_description = "Pay with Naboo";
        $this->has_fields = false; // No additional fields on checkout

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user-facing settings
        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");

        // Actions
        add_action("woocommerce_update_options_payment_gateways_" . $this->id, [
            $this,
            "process_admin_options",
        ]);
        add_action("woocommerce_api_" . strtolower(get_class($this)), [
            $this,
            "handle_webhook",
        ]);
    }

    /**
     * Initialize gateway settings form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            "enabled" => [
                "title" => "Enable/Disable",
                "type" => "checkbox",
                "label" => "Enable Naboo Pay",
                "default" => "yes",
            ],
            "title" => [
                "title" => "Title",
                "type" => "text",
                "description" =>
                    "This controls the title which the user sees during checkout.",
                "default" => "Naboo Pay",
                "desc_tip" => true,
            ],
            "description" => [
                "title" => "Description",
                "type" => "textarea",
                "description" =>
                    "This controls the description which the user sees during checkout.",
                "default" => "Pay securely with Naboo.",
            ],
            "api_key" => [
                "title" => "API Key",
                "type" => "text",
                "description" => "Enter your Naboo API Key.",
                "default" => "",
            ],
            "secret_key" => [
                "title" => "Secret Key",
                "type" => "password",
                "description" =>
                    "Enter your Naboo Secret Key for webhook verification.",
                "default" => "",
            ],
            "webhook_url" => [
                "title" => "Webhook URL",
                "type" => "text",
                "description" =>
                    "Enter the URL where Naboo will send webhook notifications.",
                "default" => home_url(
                    "/wc-api/" . strtolower(get_class($this))
                ),
                "disabled" => true,
            ],
        ];
    }

    /**
     * Process payment and redirect to Naboo checkout.
     *
     * @param int $order_id The order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Create transaction in Naboo
        $transaction = $this->create_naboo_transaction($order);

        if ($transaction && isset($transaction["checkout_url"])) {
            // Save the Naboo order ID
            update_post_meta(
                $order_id,
                "_naboo_order_id",
                $transaction["order_id"]
            );

            // Redirect to Naboo checkout
            return [
                "result" => "success",
                "redirect" => $transaction["checkout_url"],
            ];
        } else {
            wc_add_notice(
                "Unable to create transaction. Please try again.",
                "error"
            );
            return;
        }
    }

    /**
     * Create a transaction in Naboo.
     *
     * @param WC_Order $order The WooCommerce order.
     * @return array|bool
     */
    private function create_naboo_transaction($order)
    {
        $api_key = $this->get_option("api_key");
        $url = "/api/v1/transaction/create-transaction"; // Adjust based on your server URL

        // Prepare products
        $products = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $products[] = [
                "name" => $product->get_name(),
                "category" => $this->get_product_category($product),
                "amount" => $product->get_price(),
                "quantity" => $item->get_quantity(),
                "description" => $product->get_description(),
            ];
        }

        // Prepare request body
        $body = [
            "method_of_payment" => ["WAVE", "ORANGE_MONEY"], // Adjust as needed
            "products" => $products,
            "success_url" => $this->get_return_url($order),
            "error_url" => wc_get_checkout_url(),
            "fees_customer_side" => true,
            "is_escrow" => false,
            "is_merchant" => false,
        ];

        // Send request to Naboo API
        $response = wp_remote_post($url, [
            "headers" => [
                "Authorization" => "Bearer " . $api_key,
                "Content-Type" => "application/json",
            ],
            "body" => json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        return $response_body;
    }

    /**
     * Get the product category.
     *
     * @param WC_Product $product The WooCommerce product.
     * @return string
     */
    private function get_product_category($product)
    {
        $categories = get_the_terms($product->get_id(), "product_cat");
        if ($categories && !is_wp_error($categories)) {
            return $categories[0]->name;
        }
        return "";
    }

    /**
     * Handle webhook notifications from Naboo.
     */
    public function handle_webhook()
    {
        $payload = json_decode(file_get_contents("php://input"), true);
        $signature = $_SERVER["HTTP_X_SIGNATURE"] ?? "";

        // Verify signature
        $secret_key = $this->get_option("secret_key");
        $expected_signature = hash_hmac(
            "sha256",
            json_encode($payload),
            $secret_key
        );

        if (!hash_equals($expected_signature, $signature)) {
            wp_die("Invalid signature", "Invalid signature", 400);
        }

        // Process payload
        $order_id = $payload["order_id"];
        $transaction_status = $payload["transaction_status"];

        // Find the WooCommerce order
        $orders = wc_get_orders([
            "meta_key" => "_naboo_order_id",
            "meta_value" => $order_id,
        ]);

        if (empty($orders)) {
            wp_die("Order not found", "Order not found", 404);
        }

        $order = $orders[0];

        // Update order status based on transaction status
        switch ($transaction_status) {
            case "paid":
                $order->payment_complete();
                break;
            case "cancel":
                $order->update_status("cancelled");
                break;
            case "pending":
                $order->update_status("pending");
                break;
            case "part_paid":
                $order->update_status("on-hold");
                break;
            default:
                break;
        }

        wp_die("Webhook received", "Webhook received", 200);
    }
}
