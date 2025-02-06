<?php

declare(strict_types=1);

namespace Devbipu\EazzpayWpGateway;

// If this file is called directly, abort!!!
defined('ABSPATH') || die('Direct access is not allowed.');

/**
 * API Handler
 *
 * @since 1.0.0
 */
class APIHandler
{

  /** @var bool Debug mode */
  public static $debug;

  /** @var string API URL */
  public static $api_url;

  /** @var string API Key */
  public static $api_key;

  /** @var array Endpoints */
  private static $endpoints = [
    'API' => 'payments/execute',
    'VERIFY' => 'verify-payment',
  ];

  /**
   * Log debug messages
   *
   * @param string $message Message to log
   * @param string $level Log level (default: debug)
   * @return void
   */
  private static function log($message, $level = 'debug')
  {
    if (self::$debug) {
      if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
      }
      wc_get_logger()->log($level, $message, ['source' => 'eazzpay']);
    }
  }

  /**
   * Send request to API
   *
   * @param array $params Request parameters
   * @param string $method HTTP method (default: POST)
   * @param string $type API endpoint type
   * @return object|WP_Error Response object or WP_Error
   */
  public static function send_request($params = [], $method = 'POST', $type = 'API')
  {
    try {
      self::validate_credentials();

      $url = self::build_api_url($type);
      $args = self::prepare_request_args($params, $method);

      self::log([
        'url' => $url,
        'args' => $args,
        'params' => $params,
      ]);

      $response = wp_remote_request($url, $args);

      if (is_wp_error($response)) {
        throw new \Exception($response->get_error_message());
      }

      $body = wp_remote_retrieve_body($response);
      $data = json_decode($body);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON response from API');
      }

      self::log([
        'response' => $data,
      ]);

      return $data;
    } catch (\Exception $e) {
      self::log($e->getMessage(), 'error');
      return (object) [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Validate API credentials
   *
   * @throws Exception if credentials are invalid
   * @return void
   */
  private static function validate_credentials()
  {
    if (empty(self::$api_url) || empty(self::$api_key)) {
      throw new \Exception('API credentials are not configured');
    }
  }

  /**
   * Build API URL
   *
   * @param string $type Endpoint type
   * @return string Complete API URL
   */
  private static function build_api_url($type)
  {
    if (!isset(self::$endpoints[$type])) {
      throw new \Exception('Invalid API endpoint type');
    }

    $baseURL = rtrim(self::$api_url, '/');
    $apiSegmentPosition = strpos($baseURL, '/api');

    if ($apiSegmentPosition !== false) {
      $baseURL = substr($baseURL, 0, $apiSegmentPosition + 4);
    }

    return $baseURL . '/' . self::$endpoints[$type];
  }

  /**
   * Prepare request arguments
   *
   * @param array $params Request parameters
   * @param string $method HTTP method
   * @return array Request arguments
   */
  private static function prepare_request_args($params, $method)
  {
    $args = [
      'method' => $method,
      'timeout' => 45,
      'headers' => [
        'RT-UDDOKTAPAY-API-KEY' => self::$api_key,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
    ];

    if (in_array($method, ['POST', 'PUT'])) {
      $args['body'] = wp_json_encode($params);
    }

    return $args;
  }

  /**
   * Create a new payment
   *
   * @param float $amount Amount to charge
   * @param string $currency Currency code
   * @param string $full_name Customer name
   * @param string $email Customer email
   * @param array $metadata Additional metadata
   * @param string $redirect Redirect URL
   * @param string $cancel Cancel URL
   * @param string $webhook_url Webhook URL
   * @param float $exchange_rate Exchange rate
   * @return object Response object
   */
  public static function create_payment($amount = null, $currency = null, $full_name = null, $email = null, $metadata = null, $redirect = null, $cancel = null, $webhook_url = null, $exchange_rate = 120)
  {
    try {
      if (empty($currency)) {
        throw new \Exception('Currency is required');
      }

      $args = self::prepare_payment_args(
        $amount,
        $currency,
        $full_name,
        $email,
        $metadata,
        $redirect,
        $cancel,
        $webhook_url,
        $exchange_rate,
        'BDT'
      );

      return self::send_request($args, 'POST', 'API');
    } catch (\Exception $e) {
      self::log($e->getMessage(), 'error');
      return (object) [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Create an international payment
   *
   * @param float $amount Amount to charge
   * @param string $currency Currency code
   * @param string $full_name Customer name
   * @param string $email Customer email
   * @param array $metadata Additional metadata
   * @param string $redirect Redirect URL
   * @param string $cancel Cancel URL
   * @param string $webhook_url Webhook URL
   * @param float $exchange_rate Exchange rate
   * @return object Response object
   */
  public static function create_payment_international($amount = null, $currency = null, $full_name = null, $email = null, $metadata = null, $redirect = null, $cancel = null, $webhook_url = null, $exchange_rate = 120)
  {
    try {
      if (empty($currency)) {
        throw new \Exception('Currency is required');
      }

      $args = self::prepare_payment_args(
        $amount,
        $currency,
        $full_name,
        $email,
        $metadata,
        $redirect,
        $cancel,
        $webhook_url,
        $exchange_rate,
        'USD'
      );

      return self::send_request($args, 'POST', 'API_INTERNATIONAL');
    } catch (\Exception $e) {
      self::log($e->getMessage(), 'error');
      return (object) [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Prepare payment arguments
   *
   * @param float $amount Amount to charge
   * @param string $currency Currency code
   * @param string $full_name Customer name
   * @param string $email Customer email
   * @param array $metadata Additional metadata
   * @param string $redirect Redirect URL
   * @param string $cancel Cancel URL
   * @param string $webhook_url Webhook URL
   * @param float $exchange_rate Exchange rate
   * @param string $base_currency Base currency for conversion
   * @return array Payment arguments
   */
  private static function prepare_payment_args($amount = null, $currency = null, $full_name = null, $email = null, $metadata = null, $redirect = null, $cancel = null, $webhook_url = null, $exchange_rate = 120, $base_currency)
  {
    $args = [];

    // Process amount with currency conversion if needed
    $args['amount'] = !empty($amount) ? $amount : 0;
    if ($currency !== $base_currency) {
      $args['amount'] = $base_currency === 'USD' ?
        $amount / $exchange_rate :
        $amount * $exchange_rate;
    }

    // Set customer information
    $args['full_name'] = !empty($full_name) ? sanitize_text_field($full_name) : 'Unknown';
    $args['email'] = !empty($email) ? sanitize_email($email) : 'unknown@gmail.com';

    // Add optional parameters
    if (!is_null($metadata)) {
      $args['metadata'] = $metadata;
    }

    if (!is_null($redirect)) {
      $args['redirect_url'] = esc_url_raw($redirect);
    }

    if (!is_null($cancel)) {
      $args['cancel_url'] = esc_url_raw($cancel);
    }

    if (!is_null($webhook_url)) {
      $args['webhook_url'] = esc_url_raw($webhook_url);
    }

    $args['return_type'] = 'GET';

    return $args;
  }

  /**
   * Verify payment status
   *
   * @param string $invoice_id Invoice ID to verify
   * @return object Response object
   */
  public static function verify_payment($invoice_id)
  {
    try {
      if (empty($invoice_id)) {
        throw new \Exception('Invoice ID is required');
      }

      return self::send_request(
        ['invoice_id' => sanitize_text_field($invoice_id)],
        'POST',
        'VERIFY'
      );
    } catch (\Exception $e) {
      self::log($e->getMessage(), 'error');
      return (object) [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }
  }

  /**
   * Get payment status
   *
   * @param string $invoice_id Invoice ID
   * @return string Payment status
   */
  public static function get_payment_status($invoice_id)
  {
    $result = self::verify_payment($invoice_id);
    return isset($result->status) ? $result->status : 'UNKNOWN';
  }
}
