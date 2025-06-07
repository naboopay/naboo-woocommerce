<?php
/**
 * Plugin Name: Naboopay WordPress Integration
 * Plugin URI: https://naboopay.com/
 * Description: Passerelle de paiement personnalisée pour WooCommerce intégrant Naboopay.
 * Version: 1.0.2
 * Author: Louis Isaac DIOUF
 * Author URI: https://github.com/i2sac
 * License: GPL-2.0+
 * Text Domain: naboopay-gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

// Initialisation du plugin après le chargement des plugins
add_action('plugins_loaded', 'woocommerce_naboopay_init', 0);

// Enregistrement de la route REST pour le webhook
add_action('rest_api_init', 'naboopay_register_webhook_route');

/**
 * Enregistre la route REST pour le webhook Naboopay
 */
function naboopay_register_webhook_route() {
    register_rest_route('naboopay/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'naboopay_handle_webhook',
        'permission_callback' => '__return_true', // Accès public
    ));
}

/**
 * Initialise la passerelle de paiement Naboopay
 */
function woocommerce_naboopay_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Naboopay extends WC_Payment_Gateway {
        // Déclaration explicite des propriétés pour compatibilité PHP 8.2+
        public $api_token;
        public $secret_key;
        public $status_after_payment;
        public $webhook_url;
        
        public function __construct() {
            $this->id = 'naboopay';
            $this->icon = apply_filters('woocommerce_naboopay_icon', plugins_url('assets/images/naboopay.svg', __FILE__));
            $this->has_fields = false;
            $this->method_title = __('Naboopay', 'naboopay-gateway');
            $this->method_description = __('Passerelle de paiement personnalisée pour WooCommerce intégrant Naboopay.', 'naboopay-gateway');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->api_token = $this->get_option('api_token');
            $this->secret_key = $this->get_option('secret_key');
            $this->status_after_payment = $this->get_option('status_after_payment');
            $this->webhook_url = rest_url('naboopay/v1/webhook');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        }

        /**
         * Ajoute les scripts et styles du plugin
         */
        public function enqueue_scripts() {
            // Décommentez si nécessaire
            // wp_enqueue_style('naboopay-style', plugins_url('assets/css/naboopay.css', __FILE__));
            // wp_enqueue_script('naboopay-checkout', plugins_url('assets/js/checkout.js', __FILE__), array('jquery'), '1.0.0', true);
        }

        /**
         * Définit les champs de formulaire pour les paramètres
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activer/Désactiver', 'naboopay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Activer le paiement Naboopay', 'naboopay-gateway'),
                    'default' => 'yes'
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
                    'default' => ''
                ),
                'secret_key' => array(
                    'title' => __('Clé secrète Webhook', 'naboopay-gateway'),
                    'type' => 'text',
                    'description' => __('Clé secrète pour vérifier les signatures des webhooks.', 'naboopay-gateway'),
                    'default' => ''
                ),
                'status_after_payment' => array(
                    'title' => __('Statut après paiement', 'naboopay-gateway'),
                    'type' => 'select',
                    'description' => __('Statut de la commande après un paiement réussi.', 'naboopay-gateway'),
                    'options' => array(
                        'processing' => __('En cours', 'naboopay-gateway'),
                        'completed' => __('Terminé', 'naboopay-gateway'),
                    ),
                    'default' => 'completed'
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

        /**
         * Traite le paiement pour une commande
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
        
            // Préparation des données (votre code existant)
            $products = array();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $products[] = array(
                    'name' => $product->get_name(),
                    'category' => "General",
                    'amount' => intval($product->get_price()),
                    'quantity' => intval($item->get_quantity()),
                    'description' => $product->get_description() ?: "N/A",
                );
            }
        
            $payment_data = array(
                'method_of_payment' => array('WAVE'),
                'products' => $products,
                'is_escrow' => false,
                'is_merchant' => false,
                'success_url' => $this->get_return_url($order),
                'error_url' => wc_get_checkout_url() . '?payment_error=true',
            );
        
            // Création de la transaction
            $response = $this->create_naboopay_transaction($payment_data);
        
            if (is_object($response) && isset($response->checkout_url)) {
                $order->update_meta_data('naboopay_order_id', $response->order_id);
                $order->save();
                return array(
                    'result' => 'success',
                    'redirect' => $response->checkout_url,
                );
            } else {
                $error_message = is_object($response) && isset($response->message) ? $response->message : 'Erreur inconnue lors de la création de la transaction';
                wc_add_notice(__('Erreur de paiement : ', 'naboopay-gateway') . $error_message, 'error');
                return array('result' => 'failure', 'messages' => wc_get_notices('error'));
            }
        }
        /**
         * Crée une transaction via l'API Naboopay
         */
        private function create_naboopay_transaction($payment_data) {
            $api_url = 'https://api.naboopay.com/api/v1/transaction/create-transaction';
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ),
                'body' => json_encode($payment_data),
                'method' => 'PUT',
                'timeout' => 30,
            );
        
            try {
                $response = wp_remote_request($api_url, $args);
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    error_log('Erreur API Naboopay : ' . $error_message);
                    return (object) array('message' => 'Erreur réseau : ' . $error_message);
                }
        
                $http_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                error_log('Réponse API Naboopay (HTTP ' . $http_code . ') : ' . $body);
        
                if ($http_code != 200) {
                    error_log('Erreur HTTP Naboopay : Code ' . $http_code);
                    return (object) array('message' => 'Erreur serveur (HTTP ' . $http_code . ') : ' . $body);
                }
        
                $response_data = json_decode($body);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('Erreur JSON Naboopay : ' . json_last_error_msg());
                    return (object) array('message' => 'Réponse invalide de l\'API');
                }
        
                if (!isset($response_data->checkout_url)) {
                    error_log('Réponse Naboopay invalide : checkout_url manquant');
                    return (object) array('message' => 'URL de paiement non fournie par l\'API');
                }
        
                return $response_data;
            } catch (Exception $e) {
                error_log('Exception dans create_naboopay_transaction : ' . $e->getMessage());
                return (object) array('message' => 'Erreur interne : ' . $e->getMessage());
            }
        }
    }

    add_filter('woocommerce_payment_gateways', 'naboopay_add_gateway');
}

/**
 * Ajoute Naboopay aux méthodes de paiement WooCommerce
 */
function naboopay_add_gateway($methods) {
    $methods[] = 'WC_Gateway_Naboopay';
    return $methods;
}

/**
 * Ajoute un lien vers les paramètres sur la page des plugins
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'naboopay_add_settings_link');
function naboopay_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=naboopay">' . __('Paramètres', 'naboopay-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Gère les notifications webhook de Naboopay
 */
function naboopay_handle_webhook(WP_REST_Request $request) {
    $options = get_option('woocommerce_naboopay_settings', array());
    $secret_key = $options['secret_key'] ?? '';
    $status_after_payment = $options['status_after_payment'] ?? 'completed';

    $request_body = $request->get_body();
    $received_signature = $request->get_header('x_signature');

    if (empty($secret_key)) {
        return new WP_REST_Response('Clé secrète du webhook non configurée', 500);
    }

    $expected_signature = hash_hmac('sha256', $request_body, $secret_key);
    if (!hash_equals($expected_signature, $received_signature)) {
        return new WP_REST_Response('Signature invalide', 403);
    }

    $params = $request->get_json_params();
    if (!isset($params['order_id']) || !isset($params['transaction_status'])) {
        return new WP_REST_Response('Paramètres requis manquants', 400);
    }

    $order_id = $params['order_id'];
    $status = $params['transaction_status'];

    $args = array(
        'meta_key' => 'naboopay_order_id',
        'meta_value' => $order_id,
        'meta_compare' => '=',
        'return' => 'ids'
    );
    $orders = wc_get_orders($args);

    if (empty($orders)) {
        return new WP_REST_Response('Commande non trouvée', 404);
    }

    foreach ($orders as $wc_order_id) {
        $order = wc_get_order($wc_order_id);

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
        }
    }

    return new WP_REST_Response('Webhook reçu', 200);
}
