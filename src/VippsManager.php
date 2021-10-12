<?php

namespace Drupal\commerce_vipps;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Http\ClientFactory;
use Http\Adapter\Guzzle6\Client as GuzzleClient;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use zaporylie\Vipps\Client;
use zaporylie\Vipps\Vipps;

/**
 * Vipps Manager.
 */
class VippsManager implements VippsManagerInterface {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClientFactory;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionsList;

  /**
   * VippsManager constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The http client.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(ClientFactory $http_client_factory, ModuleExtensionList $module_extension_list) {
    $this->httpClientFactory = $http_client_factory;
    $this->moduleExtensionsList = $module_extension_list;
  }

  /**
   * Get Payment Manager.
   */
  public function getPaymentManager(PaymentGatewayInterface $paymentGateway) {
    $settings = $paymentGateway->getConfiguration();
    $vipps = $this->getVippsClient($paymentGateway);

    // Authorize.
    $vipps
      ->authorization($settings['subscription_key_authorization'])
      ->getToken($settings['client_secret']);

    return $vipps->payment($settings['subscription_key_payment'], $settings['serial_number']);
  }

  /**
   * Get Vipps Client.
   */
  protected function getVippsClient(PaymentGatewayInterface $paymentGateway) {
    $settings = $paymentGateway->getConfiguration();

    $headers = [
      'Merchant-Serial-Number' => $settings['serial_number'],
    ];

    // Set commerce version.
    if ($commerce_module = $this->moduleExtensionsList->getExtensionInfo('commerce')) {
      $headers['Vipps-System-Name'] = 'drupal-commerce';
      $headers['Vipps-System-Version'] = $commerce_module['version'] ?? 'unknown';
    }

    // Set plugin version.
    if ($vipps_module = $this->moduleExtensionsList->getExtensionInfo('commerce_vipps')) {
      $headers['Vipps-System-Plugin-Name'] = 'commerce-vipps';
      $headers['Vipps-System-Plugin-Version'] = $vipps_module['version'] ?? 'unknown';
    }

    $client = new Client($settings['client_id'], [
      'http_client' => new GuzzleClient($this->httpClientFactory->fromOptions(
        [
          'headers' => $headers,
        ]
      )),
      'endpoint' => $settings['mode'] === 'live' ? 'live' : 'test',
    ]);
    return new Vipps($client);
  }

}
