<?php

namespace Drupal\commerce_vipps\Resolver;

use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Returns the site's default remote order id.
 */
class DefaultOrderIdResolver implements OrderIdResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(PaymentInterface $payment) {
    return uniqid();
  }

}
