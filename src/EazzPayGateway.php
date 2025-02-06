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
  protected $api_key = '';

  /**
   * Webhook URL
   *
   * @var string
   */
  protected $webhook_url;

  /**
   * Constructor for the gateway.
   */
  public function __construct()
  {

    // Load the settings
    $this->init_form_fields();
    $this->init_settings();
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
      'enabled' => [
        'title' => __('Enable/Disable', 'eazzpay'),
        'type' => 'checkbox',
        'label' => __('Enable UddoktaPay', 'eazzpay'),
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
      'api_key' => [
        'title' => __('API Key', 'eazzpay'),
        'type' => 'password',
        'description' => __('Get your API key from UddoktaPay Panel → Brand Settings.', 'eazzpay'),
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
    $this->webhook_url = (string) add_query_arg('wc-api', $this->id, home_url('/'));
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
    if (empty($this->api_key) || empty($this->api_url)) {
      $this->add_error(__('Eazzpay requires API Key and API URL to be configured.', 'eazzpay'));
      return false;
    }
    return true;
  }
}
