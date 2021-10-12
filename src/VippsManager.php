<?php

namespace Drupal\commerce_vipps;

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
   * VippsManager constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The http client.
   */
  public function __construct(ClientFactory $http_client_factory) {
    $this->httpClientFactory = $http_client_factory;
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
    $client = new Client($settings['client_id'], [
      'http_client' => new GuzzleClient($this->httpClientFactory->fromOptions(
        [
          'headers' => [
            'Merchant-Serial-Number' => $settings['serial_number'],
          ],
        ]
      )),
      'endpoint' => $settings['mode'] === 'live' ? 'live' : 'test',
    ]);
    return new Vipps($client);
  }

}
