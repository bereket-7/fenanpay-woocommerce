<?php
namespace FenanPay\FenanPay\WC;

if (!defined('ABSPATH')) {
    exit;
}

use WC_Payment_Gateway;

/**
 * FenanPay WooCommerce Gateway
 * - Uses API Key + Secret via Basic Auth to call FenanPay endpoints
 * - Provides a webhook endpoint; optional webhook verification via HMAC using the secret
 */
class WC_FenanPay_Gateway extends WC_Payment_Gateway
{

    protected $api_base;
    protected $api_key;
    protected $api_secret;
    protected $merchant_id;
    protected $webhook_secret;
    protected $notify_url;

    public function __construct()
    {
        $this->id = 'fenanpay';
        $this->icon = apply_filters('fenanpay_icon', plugins_url('src/assets/fenanpay1.png', __FILE__));
        $this->has_fields = false;
        $this->method_title = __('FenanPay', 'fenanpay');
        $this->method_description = __('Pay using FenanPay (external payment flow).', 'fenanpay');

        // Form fields and settings
        $this->init_form_fields();
        $this->init_settings();

        // Map settings to properties
        $this->title = $this->get_option('title', 'FenanPay');
        $this->description = $this->get_option('description', '');
        $this->api_base = rtrim($this->get_option('api_base', 'https://api.fenanpay.com'), '/');
        $this->api_key = $this->get_option('api_key');
        $this->api_secret = $this->get_option('api_secret');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->webhook_secret = $this->get_option('webhook_secret');

        // notify URL (webhook) for FenanPay to call
        $this->notify_url = home_url('/?wc-api=wc_fenanpay');

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Add API endpoint hook for wc-api=wc_fenanpay
        add_action('woocommerce_api_wc_fenanpay', array($this, 'handle_webhook'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'fenanpay'),
                'type' => 'checkbox',
                'label' => __('Enable FenanPay', 'fenanpay'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'fenanpay'),
                'type' => 'text',
                'description' => __('Title shown to customer during checkout.', 'fenanpay'),
                'default' => __('FenanPay', 'fenanpay'),
            ),
            'description' => array(
                'title' => __('Description', 'fenanpay'),
                'type' => 'textarea',
                'default' => __('Pay with FenanPay.', 'fenanpay'),
            ),
            'api_base' => array(
                'title' => __('API Base URL', 'fenanpay'),
                'type' => 'text',
                'description' => __('FenanPay API base url (no trailing slash).', 'fenanpay'),
                'default' => 'https://api.fenanpay.com',
            ),
            'api_key' => array(
                'title' => __('API Key', 'fenanpay'),
                'type' => 'text',
            ),
            'api_secret' => array(
                'title' => __('API Secret', 'fenanpay'),
                'type' => 'password',
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'fenanpay'),
                'type' => 'text',
                'description' => __('Your FenanPay merchant identifier.', 'fenanpay'),
            ),
            'webhook_secret' => array(
                'title' => __('Webhook secret (optional)', 'fenanpay'),
                'type' => 'password',
                'description' => __('If provided, webhook payloads will be verified using HMAC-SHA256 with this secret (header X-Fenanpay-Signature).', 'fenanpay'),
            ),
            'webhook_info' => array(
                'title' => __('Webhook Endpoint', 'fenanpay'),
                'type' => 'title',
                'description' => sprintf(__('Set this URL as your webhook/notification endpoint in FenanPay dashboard: <code>%s</code>', 'fenanpay'), $this->notify_url),
            ),
        );
    }

    /**
     * Process the payment: call FenanPay Initiate endpoint and redirect customer to payment URL.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return array(
                'result' => 'failure',
            );
        }

        // Mark order as pending payment
        $order->update_status('pending', __('Awaiting FenanPay payment', 'fenanpay'));

        // Build payload (adjust fields to FenanPay API)
        $body = array(
            'merchantId' => $this->merchant_id,
            'orderId' => (string) $order_id . bin2hex(random_bytes(4)),
            'amount' => number_format((float) $order->get_total(), 2, '.', ''),
            'currency' => $order->get_currency(),
            'customer' => array(
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            ),
            'successUrl' => $this->get_return_url($order),
            'failureUrl' => add_query_arg('fenanpay_failed', '1', wc_get_checkout_url()),
            'notifyUrl' => $this->notify_url,
        );

        $endpoint = $this->api_base . '/v1/api/v1/payment/intent';

        $auth = base64_encode($this->api_key . ':' . $this->api_secret);

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $order->add_order_note('FenanPay request error: ' . $response->get_error_message());
            wc_add_notice(__('Payment error: could not contact FenanPay. Please try another method.', 'fenanpay'), 'error');
            return array('result' => 'failure');
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        if ($code >= 200 && $code < 300 && !empty($data['url'])) {
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $data['url'],
            );
        } else {
            $order->add_order_note('FenanPay responded with an unexpected response: ' . $body_raw);
            wc_add_notice(__('Payment error: FenanPay did not return a redirect URL. Please try again or contact support.', 'fenanpay'), 'error');
            return array('result' => 'failure');
        }
    }

    /**
     * Standard WooCommerce thank you page content
     */
    public function thankyou_page($order_id)
    {
        // optional instructions output
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /**
     * Handle webhook from FenanPay (registered via wc-api=wc_fenanpay)
     */
    public function handle_webhook()
    {
        // Read raw payload
        $payload = file_get_contents('php://input');
        $signature_header = isset($_SERVER['HTTP_X_FENANPAY_SIGNATURE']) ? wc_clean(wp_unslash($_SERVER['HTTP_X_FENANPAY_SIGNATURE'])) : '';

        // If webhook_secret provided, verify signature (HMAC-SHA256)
        if (!empty($this->webhook_secret)) {
            if (empty($signature_header)) {
                status_header(400);
                echo 'Missing signature';
                exit;
            }
            $computed = hash_hmac('sha256', $payload, $this->webhook_secret);
            if (!hash_equals($computed, $signature_header)) {
                status_header(403);
                echo 'Invalid signature';
                exit;
            }
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            status_header(400);
            echo 'Invalid payload';
            exit;
        }

        $order_ref = isset($data['orderId']) ? $data['orderId'] : '';
        // attempt to extract original order id (assuming we prefixed order id earlier)
        $order_id = intval(preg_replace('/[^0-9].*/', '', $order_ref));
        if ($order_id <= 0) {
            status_header(200);
            echo 'ok';
            exit;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            status_header(200);
            echo 'order not found';
            exit;
        }

        $status = isset($data['status']) ? strtoupper($data['status']) : '';

        if ($status === 'COMPLETED' || $status === 'PAID') {
            // mark as processing/completed depending on product type
            $order->payment_complete();
            $order->add_order_note('FenanPay payment completed (webhook).');
        } elseif ($status === 'FAILED' || $status === 'CANCELLED') {
            $order->update_status('failed', 'FenanPay reported payment failure.');
            $order->add_order_note('FenanPay payment failed or cancelled (webhook).');
        } elseif ($status === 'PENDING') {
            $order->update_status('on-hold', 'FenanPay reported payment pending.');
            $order->add_order_note('FenanPay payment pending (webhook).');
        }

        // Always respond 200
        status_header(200);
        echo 'ok';
        exit;
    }

    /**
     * A helper that can be used by the plugin index rewrite rule to call the webhook directly.
     * Keeps behavior consistent with handle_webhook().
     */
    public function handle_webhook_direct()
    {
        $this->handle_webhook();
    }
}
