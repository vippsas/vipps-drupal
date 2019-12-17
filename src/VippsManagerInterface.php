<?php

namespace Drupal\commerce_vipps;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;

/**
 * Vipps Manager.
 */
interface VippsManagerInterface {

  /**
   * Get Payment Manager.
   *
   * @param \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface $paymentGateway
   *   The payment gateway plugin.
   *
   * @return \zaporylie\Vipps\Api\PaymentInterface
   *   Payment API.
   */
  public function getPaymentManager(PaymentGatewayInterface $paymentGateway);

}
