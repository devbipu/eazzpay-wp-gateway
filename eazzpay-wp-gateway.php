<?php

/**
 * Plugin Name:   EazzPay
 * Plugin URI:    https://www.eazzpay.com
 * Description:   Accept payments via EazzPay on your WordPress WooCommerce website.
 * Version:       1.0.0
 * Author:        EazzPay
 * Author URI:    https://www.eazzpay.com
 * License:       GPL v2 or later
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:   eazzpay
 * Domain Path:   /languages
 */



// If this file is called directly, abort!!!
defined('ABSPATH') || die('Direct access is not allowed.');


// Constant
define('EAZZPAY_VERSION', '1.0.0');
define('EAZZPAY_FILE', __FILE__);
define('EAZZPAY_PATH', plugin_dir_path(EAZZPAY_FILE));
define('EAZZPAY_URL', plugin_dir_url(EAZZPAY_FILE));


//Autoload composer
require_once EAZZPAY_PATH . '/vendor/autoload.php';

final class EazzPay_Plugin
{
  private static $instance = null;

  public $gateways = [];

  public static function instance()
  {
    if (is_null(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct()
  {
    $this->init_hooks();
  }


  private function init_hooks()
  {
    add_action('plugins_loaded', [$this, 'init_plugin']);
  }



  public function init_plugin()
  {
    if (!class_exists('WC_Payment_Gateway')) {
      add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
      return;
    }

    $this->init_gateways();
    $this->init_admin();
  }

  public function woocommerce_missing_notice()
  {
    printf(
      '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
      sprintf(
        esc_html__('%1$s requires %2$s to be installed and activated.', 'uddoktapay-gateway'),
        '<strong>UddoktaPay Gateway</strong>',
        '<strong>WooCommerce</strong>'
      )
    );
  }


  private function init_gateways()
  {
    $this->gateways = [
      new \Devbipu\EazzpayWpGateway\EazzPayGateway(),
    ];

    add_filter('woocommerce_payment_gateways', [$this, 'add_gateways']);
    add_action('woocommerce_after_checkout_form', [$this, 'refresh_checkout']);
  }


  public function add_gateways($gateways)
  {
    foreach ($this->gateways as $gateway) {
      $gateways[] = $gateway;
    }
    return $gateways;
  }

  public function refresh_checkout()
  {
    wc_enqueue_js("
      jQuery('form.checkout').on('change', 'input[name^=\"payment_method\"]', function() {
        jQuery('body').trigger('update_checkout');
      });
    ");
  }

  private function init_admin()
  {
    if (!is_admin()) {
      return;
    }

    add_filter('plugin_action_links_' . plugin_basename(EAZZPAY_FILE), [$this, 'plugin_action_links']);
  }

  public function plugin_action_links($links)
  {
    $plugin_links = [
      sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=wc-settings&tab=checkout&section=eazzpay'),
        __('Payment Settings', 'eazzpay')
      ),

      sprintf(
        '<a href="%s">%s</a>',
        'https://www.eazzpay.com',
        __('<b style="color: green">Purchase License</b>', 'eazzpay')
      ),
    ];

    return array_merge($links, $plugin_links);
  }
}


EazzPay_Plugin::instance();
