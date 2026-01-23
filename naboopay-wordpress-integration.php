<?php
/**
 * Plugin Name: Naboopay WordPress Integration
 * Plugin URI: https://naboopay.com/
 * Description: Passerelle de paiement personnalisée pour WooCommerce intégrant Naboopay.
 * Version: 2.0.0
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
        public $api_version;
        public $payment_methods;
        public $fees_customer_side;
        public $base_url;
        
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
            $this->api_version = $this->get_option('api_version', 'v2');
            $this->payment_methods = $this->get_option('payment_methods', array('wave', 'orange_money'));
            $this->fees_customer_side = $this->get_option('fees_customer_side', 'no') === 'yes';
            $this->base_url = $this->get_option('base_url', '');
            $this->webhook_url = $this->generate_webhook_url();

            // Mettre à jour le champ webhook_url avec la valeur générée
            $this->update_webhook_url_field();

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
         * Retourne l'URL de base configurée ou l'URL WordPress par défaut
         */
        private function get_base_url() {
            // Vérifier si base_url est défini (peut être appelé avant init_settings)
            $base_url = isset($this->base_url) ? $this->base_url : $this->get_option('base_url', '');
            if (!empty($base_url)) {
                return rtrim($base_url, '/');
            }
            return rtrim(home_url(), '/');
        }

        /**
         * Génère l'URL du webhook
         */
        private function generate_webhook_url() {
            $base = $this->get_base_url();
            return $base . '/wp-json/naboopay/v1/webhook';
        }

        /**
         * Génère l'URL de succès pour une commande
         */
        private function generate_success_url($order) {
            if (!empty($this->base_url)) {
                $base = $this->get_base_url();
                return $base . '/checkout/order-received/' . $order->get_id() . '/?key=' . $order->get_order_key();
            }
            return $this->get_return_url($order);
        }

        /**
         * Génère l'URL d'erreur
         */
        private function generate_error_url() {
            if (!empty($this->base_url)) {
                $base = $this->get_base_url();
                return $base . '/checkout/?payment_error=true';
            }
            return wc_get_checkout_url() . '?payment_error=true';
        }

        /**
         * Met à jour le champ webhook_url avec la valeur générée
         */
        private function update_webhook_url_field() {
            if (isset($this->form_fields['generated_urls'])) {
                $this->form_fields['generated_urls']['description'] = $this->get_generated_urls_html();
            }
        }

        /**
         * Génère le HTML pour afficher les URLs générées
         */
        private function get_generated_urls_html() {
            $base = $this->get_base_url();
            $webhook_url = $this->generate_webhook_url();
            $success_url = $base . '/checkout/order-received/{order_id}/?key={order_key}';
            $error_url = $this->generate_error_url();

            $html = '<table class="form-table naboopay-urls-table" style="margin-top: 10px;">';
            $html .= '<tr><th style="width: 150px; padding: 5px 10px 5px 0;">' . __('URL Webhook', 'naboopay-gateway') . '</th>';
            $html .= '<td><code style="background: #f0f0f0; padding: 5px 10px; display: inline-block; user-select: all;">' . esc_html($webhook_url) . '</code>';
            $html .= '<br><small>' . __('Copiez cette URL dans le tableau de bord Naboopay.', 'naboopay-gateway') . '</small></td></tr>';
            $html .= '<tr><th style="padding: 5px 10px 5px 0;">' . __('URL de succès', 'naboopay-gateway') . '</th>';
            $html .= '<td><code style="background: #f0f0f0; padding: 5px 10px; display: inline-block;">' . esc_html($success_url) . '</code></td></tr>';
            $html .= '<tr><th style="padding: 5px 10px 5px 0;">' . __('URL d\'erreur', 'naboopay-gateway') . '</th>';
            $html .= '<td><code style="background: #f0f0f0; padding: 5px 10px; display: inline-block;">' . esc_html($error_url) . '</code></td></tr>';
            $html .= '</table>';
            $html .= '<p class="description">' . __('Ces URLs sont générées automatiquement à partir de l\'URL de base configurée ci-dessus.', 'naboopay-gateway') . '</p>';

            return $html;
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
                    'default' => __('Payez via Wave, Orange Money ou carte bancaire en toute sécurité', 'naboopay-gateway'),
                ),
                'api_version' => array(
                    'title' => __('Version API', 'naboopay-gateway'),
                    'type' => 'select',
                    'description' => __('Choisissez la version de l\'API Naboopay à utiliser.', 'naboopay-gateway'),
                    'options' => array(
                        'v1' => __('V1 (Legacy)', 'naboopay-gateway'),
                        'v2' => __('V2 (Recommandé)', 'naboopay-gateway'),
                    ),
                    'default' => 'v2'
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
                'payment_methods' => array(
                    'title' => __('Méthodes de paiement', 'naboopay-gateway'),
                    'type' => 'multiselect',
                    'description' => __('Sélectionnez les méthodes de paiement à proposer (V2 uniquement).', 'naboopay-gateway'),
                    'options' => array(
                        'wave' => 'Wave',
                        'orange_money' => 'Orange Money',
                        'bank' => __('Carte bancaire (Visa/Mastercard)', 'naboopay-gateway'),
                    ),
                    'default' => array('wave', 'orange_money'),
                    'class' => 'wc-enhanced-select',
                ),
                'fees_customer_side' => array(
                    'title' => __('Frais côté client', 'naboopay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Ajouter les frais de transaction au total du client (V2 uniquement)', 'naboopay-gateway'),
                    'description' => __('Si activé, les frais sont ajoutés au total du client. Sinon, ils sont déduits du marchand.', 'naboopay-gateway'),
                    'default' => 'no'
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
                'base_url' => array(
                    'title' => __('URL de base du site', 'naboopay-gateway'),
                    'type' => 'text',
                    'description' => __('URL de base pour générer les URLs (webhook, succès, erreur). Laissez vide pour utiliser l\'URL WordPress par défaut. Exemple: https://votre-site.com', 'naboopay-gateway'),
                    'default' => '',
                    'placeholder' => home_url(),
                ),
                'generated_urls' => array(
                    'title' => __('URLs générées', 'naboopay-gateway'),
                    'type' => 'title',
                    'description' => $this->get_generated_urls_html(),
                ),
            );
        }

        /**
         * Traite le paiement pour une commande
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            if ($this->api_version === 'v2') {
                $payment_data = $this->prepare_v2_payment_data($order);
            } else {
                $payment_data = $this->prepare_v1_payment_data($order);
            }

            // Création de la transaction
            $response = $this->create_naboopay_transaction($payment_data);

            if (is_object($response) && isset($response->checkout_url)) {
                $order->update_meta_data('naboopay_order_id', $response->order_id);
                $order->update_meta_data('naboopay_api_version', $this->api_version);
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
         * Prépare les données de paiement pour l'API V1
         */
        private function prepare_v1_payment_data($order) {
            $products = array();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $products[] = array(
                    'name' => $product->get_name(),
                    'category' => 'General',
                    'amount' => intval($product->get_price()),
                    'quantity' => intval($item->get_quantity()),
                    'description' => $product->get_description() ?: 'N/A',
                );
            }

            return array(
                'method_of_payment' => array('WAVE'),
                'products' => $products,
                'is_escrow' => false,
                'is_merchant' => false,
                'success_url' => $this->generate_success_url($order),
                'error_url' => $this->generate_error_url(),
            );
        }

        /**
         * Prépare les données de paiement pour l'API V2
         */
        private function prepare_v2_payment_data($order) {
            $products = array();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $products[] = array(
                    'name' => $product->get_name(),
                    'price' => intval($product->get_price()),
                    'quantity' => intval($item->get_quantity()),
                    'description' => $product->get_description() ?: 'N/A',
                );
            }

            // Méthodes de paiement (lowercase pour V2)
            $methods = is_array($this->payment_methods) ? $this->payment_methods : array('wave', 'orange_money');

            $payment_data = array(
                'method_of_payment' => array_values($methods),
                'products' => $products,
                'is_escrow' => false,
                'is_merchant' => false,
                'fees_customer_side' => $this->fees_customer_side,
                'success_url' => $this->generate_success_url($order),
                'error_url' => $this->generate_error_url(),
            );

            // Ajouter les informations client si disponibles
            $customer_data = array();
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $phone = $order->get_billing_phone();

            if (!empty($first_name)) {
                $customer_data['first_name'] = $first_name;
            }
            if (!empty($last_name)) {
                $customer_data['last_name'] = $last_name;
            }
            if (!empty($phone)) {
                $customer_data['phone'] = $phone;
            }

            if (!empty($customer_data)) {
                $payment_data['customer'] = $customer_data;
            }

            return $payment_data;
        }
        /**
         * Crée une transaction via l'API Naboopay
         */
        private function create_naboopay_transaction($payment_data) {
            if ($this->api_version === 'v2') {
                $api_url = 'https://api.naboopay.com/api/v2/transactions';
                $method = 'POST';
            } else {
                $api_url = 'https://api.naboopay.com/api/v1/transaction/create-transaction';
                $method = 'PUT';
            }

            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ),
                'body' => json_encode($payment_data),
                'method' => $method,
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

        /**
         * Récupère tous les comptes via l'API V2
         */
        public function get_accounts() {
            if ($this->api_version !== 'v2') {
                return (object) array('message' => 'Cette fonctionnalité nécessite l\'API V2');
            }

            $api_url = 'https://api.naboopay.com/api/v2/accounts';
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Accept' => 'application/json',
                ),
                'method' => 'GET',
                'timeout' => 30,
            );

            $response = wp_remote_request($api_url, $args);
            if (is_wp_error($response)) {
                return (object) array('message' => 'Erreur réseau : ' . $response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($http_code != 200) {
                return (object) array('message' => 'Erreur serveur (HTTP ' . $http_code . ')');
            }

            return json_decode($body);
        }

        /**
         * Récupère une transaction par son order_id via l'API V2
         */
        public function get_transaction($order_id) {
            if ($this->api_version !== 'v2') {
                return (object) array('message' => 'Cette fonctionnalité nécessite l\'API V2');
            }

            $api_url = 'https://api.naboopay.com/api/v2/transactions/' . urlencode($order_id);
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Accept' => 'application/json',
                ),
                'method' => 'GET',
                'timeout' => 30,
            );

            $response = wp_remote_request($api_url, $args);
            if (is_wp_error($response)) {
                return (object) array('message' => 'Erreur réseau : ' . $response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($http_code != 200) {
                return (object) array('message' => 'Erreur serveur (HTTP ' . $http_code . ')');
            }

            return json_decode($body);
        }

        /**
         * Annule une transaction via l'API V2
         */
        public function cancel_transaction($order_id) {
            if ($this->api_version !== 'v2') {
                return (object) array('message' => 'Cette fonctionnalité nécessite l\'API V2');
            }

            $api_url = 'https://api.naboopay.com/api/v2/transactions/' . urlencode($order_id);
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Accept' => 'application/json',
                ),
                'method' => 'DELETE',
                'timeout' => 30,
            );

            $response = wp_remote_request($api_url, $args);
            if (is_wp_error($response)) {
                return (object) array('message' => 'Erreur réseau : ' . $response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($http_code != 200) {
                return (object) array('message' => 'Erreur serveur (HTTP ' . $http_code . ')');
            }

            return json_decode($body);
        }

        /**
         * Rembourse une transaction via l'API V2
         */
        public function refund_transaction($order_id) {
            if ($this->api_version !== 'v2') {
                return (object) array('message' => 'Cette fonctionnalité nécessite l\'API V2');
            }

            $api_url = 'https://api.naboopay.com/api/v2/refund/' . urlencode($order_id);
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_token,
                    'Accept' => 'application/json',
                ),
                'method' => 'GET',
                'timeout' => 30,
            );

            $response = wp_remote_request($api_url, $args);
            if (is_wp_error($response)) {
                return (object) array('message' => 'Erreur réseau : ' . $response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($http_code != 200) {
                return (object) array('message' => 'Erreur serveur (HTTP ' . $http_code . ')');
            }

            return json_decode($body);
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
    $api_version = $options['api_version'] ?? 'v2';

    $request_body = $request->get_body();
    $params = $request->get_json_params();

    if (empty($secret_key)) {
        return new WP_REST_Response('Clé secrète du webhook non configurée', 500);
    }

    // Vérification de la signature (supporte les deux formats de header)
    $received_signature = $request->get_header('X-Signature');
    if (empty($received_signature)) {
        $received_signature = $request->get_header('x_signature');
    }

    if (empty($received_signature)) {
        error_log('Naboopay webhook: Signature manquante');
        return new WP_REST_Response('Signature manquante', 401);
    }

    // V2 utilise le JSON compact re-encodé, V1 utilise le body brut
    if ($api_version === 'v2') {
        $payload_for_signature = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        $payload_for_signature = $request_body;
    }

    $expected_signature = hash_hmac('sha256', $payload_for_signature, $secret_key);

    // Essayer aussi avec le body brut si la signature V2 échoue
    if (!hash_equals($expected_signature, $received_signature)) {
        // Fallback: essayer avec le body brut (compatibilité)
        $expected_signature_raw = hash_hmac('sha256', $request_body, $secret_key);
        if (!hash_equals($expected_signature_raw, $received_signature)) {
            error_log('Naboopay webhook: Signature invalide');
            return new WP_REST_Response('Signature invalide', 403);
        }
    }

    if (!isset($params['order_id']) || !isset($params['transaction_status'])) {
        return new WP_REST_Response('Paramètres requis manquants', 400);
    }

    $order_id = $params['order_id'];
    $status = $params['transaction_status'];

    // Log pour debug
    error_log('Naboopay webhook reçu - Order ID: ' . $order_id . ', Status: ' . $status);

    $args = array(
        'meta_key' => 'naboopay_order_id',
        'meta_value' => $order_id,
        'meta_compare' => '=',
        'return' => 'ids'
    );
    $orders = wc_get_orders($args);

    if (empty($orders)) {
        error_log('Naboopay webhook: Commande non trouvée pour order_id: ' . $order_id);
        return new WP_REST_Response('Commande non trouvée', 404);
    }

    foreach ($orders as $wc_order_id) {
        $order = wc_get_order($wc_order_id);

        // Stocker les informations supplémentaires du webhook V2
        if ($api_version === 'v2') {
            if (isset($params['selected_payment_method'])) {
                $order->update_meta_data('naboopay_payment_method', $params['selected_payment_method']);
            }
            if (isset($params['fees'])) {
                $order->update_meta_data('naboopay_fees', $params['fees']);
            }
            if (isset($params['paid_at'])) {
                $order->update_meta_data('naboopay_paid_at', $params['paid_at']);
            }
        }

        // Gestion des statuts (supporte V1 et V2)
        switch ($status) {
            // V2 statuts
            case 'completed':
            case 'paid':
                $order->payment_complete();
                $order->update_status($status_after_payment);
                $order->add_order_note(__('Paiement complété via Naboopay.', 'naboopay-gateway'));
                break;

            case 'paid_and_blocked':
                $order->payment_complete();
                $order->update_status('on-hold', __('Paiement reçu et bloqué (escrow) via Naboopay.', 'naboopay-gateway'));
                break;

            case 'cancelled':
            case 'cancel':
                $order->update_status('cancelled', __('Paiement annulé via Naboopay.', 'naboopay-gateway'));
                break;

            case 'refunded':
                $order->update_status('refunded', __('Paiement remboursé via Naboopay.', 'naboopay-gateway'));
                break;

            case 'pending':
                $order->update_status('pending', __('Paiement en attente via Naboopay.', 'naboopay-gateway'));
                break;

            // V1 statut legacy
            case 'part_paid':
                $order->update_status('on-hold', __('Paiement partiellement payé via Naboopay.', 'naboopay-gateway'));
                break;

            default:
                error_log('Naboopay webhook: Statut inconnu: ' . $status);
                break;
        }

        $order->save();
    }

    return new WP_REST_Response('Webhook reçu', 200);
}
