<?php
/**
 * Plugin Name: Naboopay WordPress Integration
 * Plugin URI: https://naboopay.com/
 * Description: Passerelle de paiement personnalisée pour WooCommerce intégrant Naboopay.
 * Version: 1.0.3
 * Author: Louis Isaac DIOUF
 * Author URI: https://github.com/i2sac
 * License: GPL-2.0+
 * Text Domain: naboopay-gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialise la passerelle après le chargement de WooCommerce
 */
add_action('plugins_loaded', 'woocommerce_naboopay_init', 0);

/**
 * Enregistre la route REST pour le webhook Naboopay
 */
add_action('rest_api_init', 'naboopay_register_webhook_route');

function naboopay_register_webhook_route()
{
    register_rest_route(
        'naboopay/v1',
        '/webhook',
        array(
            'methods' => 'POST',
            'callback' => 'naboopay_handle_webhook',
            'permission_callback' => '__return_true',
        )
    );
}

/**
 * Enqueue admin script for copy button (only on the gateway settings section)
 */
add_action('admin_enqueue_scripts', 'naboopay_admin_enqueue_scripts');
function naboopay_admin_enqueue_scripts($hook)
{
    // Load script only on WooCommerce settings page for this gateway
    if (
        isset($_GET['page']) && $_GET['page'] === 'wc-settings'
        && isset($_GET['tab']) && $_GET['tab'] === 'checkout'
        && isset($_GET['section']) && $_GET['section'] === 'naboopay'
    ) {
        wp_enqueue_script(
            'naboopay-admin-copy',
            plugins_url('assets/js/admin-copy.js', __FILE__),
            array(),
            '1.0.0',
            true
        );
    }
}

function woocommerce_naboopay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Naboopay extends WC_Payment_Gateway
    {
        public $api_token;
        public $secret_key;
        public $status_after_payment;
        public $webhook_url;
        public $fees_customer_side;

        public function __construct()
        {
            $this->id = 'naboopay';
            $this->icon = apply_filters('woocommerce_naboopay_icon', plugins_url('assets/images/naboopay.svg', __FILE__));
            $this->has_fields = false;
            $this->method_title = __('Naboopay', 'naboopay-gateway');
            $this->method_description = __('Passerelle de paiement personnalisée pour WooCommerce intégrant Naboopay.', 'naboopay-gateway');

            $this->init_form_fields();
            $this->init_settings();

            // sanitize settings on read
            $this->title = isset($this->settings['title']) ? sanitize_text_field($this->settings['title']) : __('Naboopay', 'naboopay-gateway');
            $this->description = isset($this->settings['description']) ? sanitize_text_field($this->settings['description']) : '';
            $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'no';
            $this->api_token = isset($this->settings['api_token']) ? sanitize_text_field($this->settings['api_token']) : '';
            $this->secret_key = isset($this->settings['secret_key']) ? sanitize_text_field($this->settings['secret_key']) : '';
            $this->status_after_payment = isset($this->settings['status_after_payment']) ? sanitize_text_field($this->settings['status_after_payment']) : 'completed';
            $this->webhook_url = rest_url('naboopay/v1/webhook');
            $this->fees_customer_side = isset($this->settings['fees_customer_side']) ? sanitize_text_field($this->settings['fees_customer_side']) : 'yes';

            // actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activer/Désactiver', 'naboopay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Activer le paiement Naboopay', 'naboopay-gateway'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Titre', 'naboopay-gateway'),
                    'type' => 'text',
                    'description' => __('Titre affiché lors du paiement.', 'naboopay-gateway'),
                    'default' => __('Naboopay', 'naboopay-gateway'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'naboopay-gateway'),
                    'type' => 'textarea',
                    'description' => __('Description affichée lors du paiement.', 'naboopay-gateway'),
                    'default' => __('Payez via WAVE, ORANGE MONEY et FREE MONEY en toute sécurité', 'naboopay-gateway'),
                ),
                'api_token' => array(
                    'title' => __('Jeton API', 'naboopay-gateway'),
                    'type' => 'text',
                    'description' => __('Votre jeton API Naboopay.', 'naboopay-gateway'),
                    'default' => '',
                ),
                'secret_key' => array(
                    'title' => __('Clé secrète Webhook', 'naboopay-gateway'),
                    'type' => 'text',
                    'description' => __('Clé secrète pour vérifier les signatures des webhooks.', 'naboopay-gateway'),
                    'default' => '',
                ),
                'status_after_payment' => array(
                    'title' => __('Statut après paiement', 'naboopay-gateway'),
                    'type' => 'select',
                    'description' => __('Statut de la commande après un paiement réussi.', 'naboopay-gateway'),
                    'options' => array(
                        'processing' => __('En cours', 'naboopay-gateway'),
                        'completed' => __('Terminé', 'naboopay-gateway'),
                    ),
                    'default' => 'completed',
                ),
                'fees_customer_side' => array(
                    'title' => __('Frais à la charge du client', 'naboopay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Facturer les frais au client (fees_customer_side)', 'naboopay-gateway'),
                    'description' => __('Si coché, les frais sont à la charge du client (true).', 'naboopay-gateway'),
                    'default' => 'yes',
                ),
                'webhook_url' => array(
                    'title' => __('URL Webhook', 'naboopay-gateway'),
                    'type' => 'text',
                    'description' => __('Copiez cette URL dans le tableau de bord Naboopay pour les notifications webhook.', 'naboopay-gateway'),
                    'default' => rest_url('naboopay/v1/webhook'),
                    'custom_attributes' => array('readonly' => 'readonly'),
                ),
            );
        }

        public function enqueue_scripts()
        {
            if (function_exists('is_checkout') && is_checkout()) {
                // wp_enqueue_style('naboopay-style', plugins_url('assets/css/naboopay.css', __FILE__));
                // wp_enqueue_script('naboopay-checkout', plugins_url('assets/js/checkout.js', __FILE__), array('jquery'), '1.0.0', true);
            }
        }

        /**
         * Convertit un montant en "minor units" en fonction des décimales configurées dans WooCommerce.
         */
        private function convert_amount_to_minor_units($amount)
        {
            $decimals = intval(wc_get_price_decimals());
            $mult = pow(10, $decimals);
            return (int) round($amount * $mult);
        }

        /**
         * Traite le paiement pour une commande en incluant produits, shipping, fees, taxes et ajustements
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                wc_add_notice(__('Commande introuvable', 'naboopay-gateway'), 'error');
                return array('result' => 'failure');
            }

            $items_payload = array();
            $calculated_sum = 0.0;

            // produits
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }

                $quantity = intval($item->get_quantity());
                $line_total = floatval($item->get_total());
                $unit_price = $quantity ? ($line_total / $quantity) : 0.0;

                $items_payload[] = array(
                    'name' => $product->get_name(),
                    'category' => 'product',
                    'amount' => $this->convert_amount_to_minor_units($unit_price),
                    'quantity' => $quantity,
                    'description' => $product->get_description() ?: 'N/A',
                );

                $calculated_sum += $line_total;
            }

            // shipping
            $shipping_total = floatval($order->get_shipping_total());
            if ($shipping_total != 0.0) {
                $items_payload[] = array(
                    'name' => 'Shipping',
                    'category' => 'shipping',
                    'amount' => $this->convert_amount_to_minor_units($shipping_total),
                    'quantity' => 1,
                    'description' => 'Frais de livraison',
                );
                $calculated_sum += $shipping_total;
            }

            // fees
            foreach ($order->get_items('fee') as $fee_item) {
                $fee_total = floatval($fee_item->get_total());
                $fee_name = $fee_item->get_name() ? $fee_item->get_name() : 'Fee';
                $items_payload[] = array(
                    'name' => $fee_name,
                    'category' => 'fee',
                    'amount' => $this->convert_amount_to_minor_units($fee_total),
                    'quantity' => 1,
                    'description' => 'Frais additionnels',
                );
                $calculated_sum += $fee_total;
            }

            // taxes
            $tax_total = floatval($order->get_total_tax());
            if ($tax_total != 0.0) {
                $items_payload[] = array(
                    'name' => 'Taxes',
                    'category' => 'tax',
                    'amount' => $this->convert_amount_to_minor_units($tax_total),
                    'quantity' => 1,
                    'description' => 'Taxes applicables',
                );
                $calculated_sum += $tax_total;
            }

            // ajustement
            $order_total = floatval($order->get_total());
            $difference = $order_total - $calculated_sum;
            if (abs($difference) > 0.001) {
                $items_payload[] = array(
                    'name' => 'Ajustement (coupons/remises/arrondis)',
                    'category' => 'adjustment',
                    'amount' => $this->convert_amount_to_minor_units($difference),
                    'quantity' => 1,
                    'description' => 'Ajustement pour faire correspondre le total de la commande',
                );
                $calculated_sum += $difference;
            }

            $payment_data = array(
                'method_of_payment' => array('WAVE'),
                'products' => $items_payload,
                'is_escrow' => false,
                'is_merchant' => false,
                'success_url' => $this->get_return_url($order),
                'error_url' => wc_get_checkout_url() . '?payment_error=true',
                'fees_customer_side' => ($this->fees_customer_side === 'yes') ? true : false,
            );

            $response = $this->create_naboopay_transaction($payment_data, $order);

            if (is_object($response) && isset($response->checkout_url)) {
                if (isset($response->order_id)) {
                    $order->update_meta_data('naboopay_order_id', sanitize_text_field((string) $response->order_id));
                    $order->save();
                }

                return array(
                    'result' => 'success',
                    'redirect' => esc_url_raw($response->checkout_url),
                );
            } else {
                $error_message = is_object($response) && isset($response->message) ? $response->message : 'Erreur inconnue lors de la création de la transaction';
                wc_add_notice(__('Erreur de paiement : ', 'naboopay-gateway') . $error_message, 'error');
                return array('result' => 'failure', 'messages' => wc_get_notices('error'));
            }
        }

        private function create_naboopay_transaction($payment_data, $order)
        {
            $api_url = 'https://api.naboopay.com/api/v1/transaction/create-transaction';

            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ),
                'body' => wp_json_encode($payment_data),
                'method' => 'PUT',
                'timeout' => 30,
            );

            $logger = wc_get_logger();

            try {
                $response = wp_remote_request($api_url, $args);

                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    $logger->error('Naboopay API network error: ' . $error_message, array('source' => 'naboopay'));
                    return (object) array('message' => 'Erreur réseau : ' . $error_message);
                }

                $http_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);

                $logger->debug('Réponse API Naboopay (HTTP ' . $http_code . '): ' . $body, array('source' => 'naboopay'));

                if ($http_code < 200 || $http_code >= 300) {
                    $logger->error('Erreur HTTP Naboopay: Code ' . $http_code . ' - ' . $body, array('source' => 'naboopay'));
                    return (object) array('message' => 'Erreur serveur (HTTP ' . $http_code . ') : ' . $body);
                }

                $response_data = json_decode($body);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $logger->error('Erreur JSON Naboopay: ' . json_last_error_msg(), array('source' => 'naboopay'));
                    return (object) array('message' => 'Réponse invalide de l\'API');
                }

                if (!isset($response_data->checkout_url)) {
                    $logger->error('Réponse Naboopay invalide: checkout_url manquant', array('source' => 'naboopay'));
                    return (object) array('message' => 'URL de paiement non fournie par l\'API');
                }

                /* 
                // Récupérer les informations du client
                $billing_phone = $order->get_billing_phone();
                $billing_first_name = $order->get_billing_first_name();
                $billing_last_name = $order->get_billing_last_name();

                // Construire les paramètres d'URL
                $params = array(
                    'prefilled' => 'true',
                    'phone_number' => str_replace(array(' ', '-'), '', $billing_phone),
                    'first_name' => $billing_first_name,
                    'last_name' => $billing_last_name
                );

                // Ajouter les paramètres à l'URL en gérant le "?" ou "&"
                $checkout_url = $response_data->checkout_url;
                $separator = parse_url($checkout_url, PHP_URL_QUERY) ? '&' : '?';
                $response_data->checkout_url = $checkout_url . $separator . http_build_query($params);
                */

                return $response_data;
            } catch (Exception $e) {
                $logger->error('Exception dans create_naboopay_transaction: ' . $e->getMessage(), array('source' => 'naboopay'));
                return (object) array('message' => 'Erreur interne : ' . $e->getMessage());
            }
        }
    }

    add_filter('woocommerce_payment_gateways', 'naboopay_add_gateway');
    function naboopay_add_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Naboopay';
        return $methods;
    }
}

/**
 * Ajoute un lien vers les paramètres sur la page des plugins
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'naboopay_add_settings_link');
function naboopay_add_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=naboopay">' . __('Paramètres', 'naboopay-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Gère les notifications webhook de Naboopay
 */
function naboopay_handle_webhook(WP_REST_Request $request)
{
    $options = get_option('woocommerce_naboopay_settings', array());
    $secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
    $status_after_payment = isset($options['status_after_payment']) ? $options['status_after_payment'] : 'completed';

    $request_body = $request->get_body();

    // Header variants
    $received_signature = $request->get_header('X-Signature');
    if (empty($received_signature)) {
        $received_signature = $request->get_header('x-signature');
    }
    if (empty($received_signature)) {
        $received_signature = $request->get_header('x_signature');
    }

    $logger = wc_get_logger();

    if (empty($secret_key)) {
        $logger->error('Webhook secret key not configured', array('source' => 'naboopay'));
        return new WP_REST_Response('Clé secrète du webhook non configurée', 500);
    }

    if (empty($received_signature)) {
        $logger->warning('Webhook missing X-Signature header', array('source' => 'naboopay'));
        return new WP_REST_Response('En-tête X-Signature manquant', 400);
    }

    $expected_signature = hash_hmac('sha256', $request_body, $secret_key);

    if (!hash_equals($expected_signature, $received_signature)) {
        $logger->warning('Invalid webhook signature', array('source' => 'naboopay'));
        return new WP_REST_Response('Signature invalide', 403);
    }

    $params = $request->get_json_params();
    if (!isset($params['order_id']) || !isset($params['transaction_status'])) {
        $logger->warning('Webhook missing required fields', array('source' => 'naboopay', 'payload' => $params));
        return new WP_REST_Response('Paramètres requis manquants', 400);
    }

    $order_id = sanitize_text_field((string) $params['order_id']);
    $status = sanitize_text_field((string) $params['transaction_status']);

    $args = array(
        'meta_key' => 'naboopay_order_id',
        'meta_value' => $order_id,
        'meta_compare' => '=',
        'return' => 'ids',
    );
    $orders = wc_get_orders($args);

    if (empty($orders)) {
        $logger->warning('Order not found for naboopay_order_id: ' . $order_id, array('source' => 'naboopay'));
        return new WP_REST_Response('Commande non trouvée', 404);
    }

    foreach ($orders as $wc_order_id) {
        $order = wc_get_order($wc_order_id);
        if (!$order) {
            continue;
        }

        switch ($status) {
            case 'paid':
                $order->payment_complete();
                $order->update_status($status_after_payment);
                $order->add_order_note(__('Paiement complété via Naboopay.', 'naboopay-gateway'));
                break;
            case 'cancel':
                $order->update_status('cancelled', __('Paiement annulé via Naboopay.', 'naboopay-gateway'));
                break;
            case 'pending':
                $order->update_status('pending', __('Paiement en attente via Naboopay.', 'naboopay-gateway'));
                break;
            case 'part_paid':
                $order->update_status('on-hold', __('Paiement partiellement payé via Naboopay.', 'naboopay-gateway'));
                break;
            default:
                $logger->info('Webhook received unknown transaction_status: ' . $status, array('source' => 'naboopay'));
                break;
        }
    }

    return new WP_REST_Response('Webhook reçu', 200);
}
