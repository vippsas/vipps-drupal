<?php

namespace Drupal\commerce_vipps;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;

/**
 * Vipps Manager.
 */
interface VippsManagerInterface {

  /**
   * Get Payment Manager.
   */
  public function getPaymentManager(PaymentGatewayInterface $paymentGateway);

}
