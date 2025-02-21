<?php

declare(strict_types=1);

namespace Devbipu\EazzpayWpGateway;

use Devbipu\EazzpayWpGateway\Enums\OrderStatus;

// If this file is called directly, abort!!!
defined('ABSPATH') || die('Direct access is not allowed.');

class EazzPayGateway extends \WC_Payment_Gateway
{
  /**
   * API Handler instance
   *
   * @var APIHandler|null
   */
  protected $api = null;

  /**
   * Debug mode
   *
   * @var bool
   */
  protected $debug = false;

  /**
   * API URL
   *
   * @var string
   */
  protected $api_url = '';

  /**
   * API Key
   *
   * @var string
   */
  protected $client_secret = '';

  /**
   * Success URL
   *
   * @var string
   */
  protected $success_url;

  /**
   * Cancel URL
   *
   * @var string
   */
  protected $cancel_url;

  /**
   * Webhook URL
   *
   * @var string
   */
  protected $ipn_url;

  /**
   * Constructor for the gateway.
   */
  public function __construct()
  {
    $this->setup_properties();
    $this->init_form_fields();
    $this->init_settings();


    $this->title = (string) $this->get_option('title', __('EazzPay Payment', 'eazzpay'));
    $this->description = (string) $this->get_option('description', __('Pay securely via Bangladeshi payment methods.', 'eazzpay'));
    $this->client_secret = (string) $this->get_option('client_secret', '');
    $this->debug = $this->get_option('debug') === 'yes';
    $this->api_url = $this->get_option('sandbox') === 'yes' ? 'https://sandbox.eazzpay.com/api/v1' : 'https://pay.eazzpay.com/api/v1';


    if ($this->api === null) {
      APIHandler::$debug = $this->debug;
      APIHandler::$client_secret = $this->client_secret;
      APIHandler::$api_url = $this->api_url;
      $this->api = new APIHandler();
    }

    // Set default URLs
    // $this->success_url = add_query_arg('wc-api', 'eazzpay_success', home_url('/'));
    // $this->cancel_url = add_query_arg('wc-api', 'eazzpay_cancel', home_url('/'));
    // $this->ipn_url = add_query_arg('wc-api', 'eazzpay_ipn', home_url('/'));


    // Hook save settings function
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

    // Hook API handlers
    add_action('woocommerce_api_eazzpay_success', [$this, 'handle_success']);
    add_action('woocommerce_api_eazzpay_cancel', [$this, 'handle_cancel']);
    add_action('woocommerce_api_eazzpay_ipn', [$this, 'handle_ipn']);
  }



  /**
   * Initialize Gateway Settings Form Fields
   *
   * @return void
   */
  public function init_form_fields()
  {
    $currency = get_woocommerce_currency();

    $base_fields = [
      'sandbox' => [
        'title' => __('Test Mode', 'eazzpay'),
        'type' => 'checkbox',
        'label' => __('Enable Test Mode', 'eazzpay'),
        'default' => 'no',
      ],
      'enabled' => [
        'title' => __('Enable/Disable', 'eazzpay'),
        'type' => 'checkbox',
        'label' => __('Enable EazzPay', 'eazzpay'),
        'default' => 'no',
      ],
      'title' => [
        'title' => __('Title', 'eazzpay'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'eazzpay'),
        'default' => __('Bangladeshi Payment', 'eazzpay'),
        'desc_tip' => true,
      ],
      'description' => [
        'title' => __('Description', 'eazzpay'),
        'type' => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'eazzpay'),
        'default' => __('Pay securely via Bangladeshi payment methods.', 'eazzpay'),
        'desc_tip' => true,
      ],
      'client_secret' => [
        'title' => __('Client Secret', 'eazzpay'),
        'type' => 'password',
        'description' => __('Get your Client Secret key from EazzPay Panel.', 'eazzpay'),
      ],

      'physical_product_status' => [
        'title' => __('Physical Product Status', 'eazzpay'),
        'type' => 'select',
        'description' => __('Select status for physical product orders after successful payment.', 'eazzpay'),
        'default' => OrderStatus::PROCESSING,
        'options' => [
          OrderStatus::ON_HOLD => __('On Hold', 'eazzpay'),
          OrderStatus::PROCESSING => __('Processing', 'eazzpay'),
          OrderStatus::COMPLETED => __('Completed', 'eazzpay'),
        ],
      ],
      'digital_product_status' => [
        'title' => __('Digital Product Status', 'eazzpay'),
        'type' => 'select',
        'description' => __('Select status for digital/downloadable product orders after successful payment.', 'eazzpay'),
        'default' => OrderStatus::COMPLETED,
        'options' => [
          OrderStatus::ON_HOLD => __('On Hold', 'eazzpay'),
          OrderStatus::PROCESSING => __('Processing', 'eazzpay'),
          OrderStatus::COMPLETED => __('Completed', 'eazzpay'),
        ],
      ],
    ];

    if ($currency !== 'BDT') {
      $base_fields['exchange_rate'] = [
        'title' => sprintf(__('%s to BDT Exchange Rate', 'eazzpay'), $currency),
        'type' => 'text',
        'desc_tip' => true,
        'description' => __('This rate will be applied to convert the total amount to BDT', 'eazzpay'),
        'default' => '0',
        'custom_attributes' => [
          'step' => '0.01',
          'min' => '0',
        ],
      ];
    }

    $base_fields['debug'] = [
      'title' => __('Debug Log', 'eazzpay'),
      'type' => 'checkbox',
      'label' => __('Enable logging', 'eazzpay'),
      'default' => 'no',
      'description' => sprintf(
        __('Log gateway events inside %s', 'eazzpay'),
        '<code>' . \WC_Log_Handler_File::get_log_file_path('eazzpay') . '</code>'
      ),
    ];

    $this->form_fields = $base_fields;
  }


  /**
   * Setup general properties for the gateway
   *
   * @return void
   */
  protected function setup_properties()
  {
    $this->id = 'eazzpay';
    $this->icon = (string) apply_filters('woocommerce_eazzpay_icon', '');
    $this->has_fields = false;
    $this->method_title = __('Eazzpay', 'eazzpay');
    $this->method_description = sprintf(
      '%s<br/><a href="%s" target="_blank">%s</a>',
      __('Accept payments via multiple gateways including bKash, Nagad, Rocket and more.', 'eazzpay'),
      esc_url('https://eazzpay.com'),
      __('Sign up for Eazzpay account', 'eazzpay')
    );
    $this->ipn_url = (string) add_query_arg('wc-api', $this->id, home_url('/'));
    $this->supports = [
      'products',
    ];
  }



  /**
   * Check if gateway is valid for use
   *
   * @return bool
   */
  protected function is_valid_for_use()
  {
    if (empty($this->client_secret) || empty($this->api_url)) {
      $this->add_error(__('Eazzpay requires API Key and API URL to be configured.', 'eazzpay'));
      return false;
    }
    return true;
  }


  public function process_payment($order_id)
  {
    //

    // wc_add_notice($this->success_url, 'error');
    // wc_add_notice($this->cancel_url, 'error');
    // wc_add_notice($this->ipn_url, 'error');
    // try {
    $order = wc_get_order($order_id);
    if (!$order) {
      throw new \Exception(__('Invalid order', 'eazzpay'));
    }


    $this->success_url = add_query_arg([
      'wc-api'   => 'eazzpay_success',
      'order_id' => $order_id,
    ], home_url('/'));

    $this->cancel_url = add_query_arg([
      'wc-api'   => 'eazzpay_cancel',
      'order_id' => $order_id,
    ], home_url('/'));

    $this->ipn_url = add_query_arg([
      'wc-api'   => 'eazzpay_ipn',
      'order_id' => $order_id,
    ], home_url('/'));

    $products = [];

    foreach ($order->get_items() as $item_id => $item) {
      $product_id = $item->get_product_id();
      $product_name = $item->get_name();
      $product_variation_id = $item->get_variation_id();

      $products[] = [
        'id'   => $product_id,
        'name' => $product_name,
        'product_variation_id' => $product_variation_id,
      ];
    }

    $billing_address = [
      'address_1'  => $order->get_billing_address_1(),
      'address_2'  => $order->get_billing_address_2(),
      'city'       => $order->get_billing_city(),
      'state'      => $order->get_billing_state(),
      'postcode'   => $order->get_billing_postcode(),
      'country'    => $order->get_billing_country(),
    ];

    $metadata = [
      'order_id' => $order->get_id(),
      'email'    => $order->get_billing_email(),
      'phone'    => $order->get_billing_phone(),
      'products' => $products,
      'billing_address' => $billing_address,
      'redirect_url' => $this->get_return_url($order),
    ];

    //Request payment init to API

    $response = $this->api->create_payment(
      $order->get_total(),
      $order->get_currency(),
      $order->get_billing_first_name(),
      $this->success_url,
      $this->cancel_url,
      $this->ipn_url,
      'POST', //IPN Method
      null, // $metadata,
      $this->get_option('exchange_rate')
    );


    if (empty($response->data->eazzpay_url)) {
      throw new \Exception($response->message ?? __('Payment URL not received', 'eazzpay'));
    }

    // Mark as pending payment
    $order->update_status(
      OrderStatus::PENDING,
      __('Awaiting EazzPay Payment', 'eazzpay')
    );

    // Empty cart
    // WC()->cart->empty_cart();

    return [
      'result' => 'success',
      'redirect' => $response->data->eazzpay_url,
    ];
    // } catch (\Exception $e) {
    //   throw new \Exception($e->getMessage());
    // }
  }


  // Handle successful payment
  public function handle_success()
  {
    $order_id = $_GET['order_id'] ?? null;
    if ($order_id) {
      $order = wc_get_order($order_id);
      if ($order) {
        $order->payment_complete();
        $order->update_status('completed', __('Payment received', 'woocommerce'));
        wp_redirect($this->get_return_url($order));
        exit;
      }
    }
    wp_redirect(home_url());
    exit;
  }

  // Handle payment cancellation
  public function handle_cancel()
  {
    $order_id = $_GET['order_id'] ?? null;
    if ($order_id) {
      $order = wc_get_order($order_id);
      if ($order) {
        $order->update_status('cancelled', __('Payment cancelled', 'woocommerce'));
        wc_add_notice(__('Payment was cancelled.', 'woocommerce'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
      }
    }
    wp_redirect(home_url());
    exit;
  }

  // Handle IPN (Webhook) for order updates
  public function handle_ipn()
  {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    if (!isset($data['reference'])) {
      status_header(400);
      echo json_encode(['error' => 'Invalid IPN data']);
      exit;
    }

    $order_id = $data['reference'];
    $status   = $data['status'];

    $order = wc_get_order($order_id);
    if ($order) {
      if ($status === 'COMPLETED') {
        $order->payment_complete();
        $order->update_status('completed', __('Payment received via EazzPay.', 'woocommerce'));
      } elseif ($status === 'FAILED') {
        $order->update_status('failed', __('Payment failed.', 'woocommerce'));
      } elseif ($status === 'PENDING') {
        $order->update_status('pending', __('Payment pending.', 'woocommerce'));
      } elseif ($status === 'CANCELED') {
        $order->update_status('canceled', __('Payment canceled.', 'woocommerce'));
      }
    }

    echo json_encode(['message' => 'IPN processed']);
    exit;
  }
}
