<?php

namespace Drupal\commerce_vipps_test_resolvers\Resolver;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_vipps\Resolver\OrderIdResolverInterface;

/**
 * Class TestOrderIdResolver.
 *
 * @package Drupal\commerce_vipps_test_resolvers\Resolver
 */
class TestOrderIdResolver implements OrderIdResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(PaymentInterface $payment) {
    return $payment->getOrderId();
  }

}
