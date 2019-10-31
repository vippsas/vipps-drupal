<?php

namespace Drupal\commerce_vipps;

use Http\Adapter\Guzzle6\Client as GuzzleClient;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use GuzzleHttp\ClientInterface;
use zaporylie\Vipps\Client;
use zaporylie\Vipps\Vipps;

/**
 * Vipps Manager.
 */
class VippsManager {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * VippsManager constructor.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   */
  public function __construct(ClientInterface $httpClient) {
    $this->httpClient = $httpClient;
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
      'http_client' => new GuzzleClient($this->httpClient),
      'endpoint' => $settings['mode'] === 'live' ? 'live' : 'test',
    ]);
    return new Vipps($client);
  }

}
