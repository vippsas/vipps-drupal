<?php

namespace Drupal\commerce_vipps\Resolver;

use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Defines the interface for order id resolvers.
 */
interface OrderIdResolverInterface {

  /**
   * Resolves the remote order id.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   Payment entity. Note that payment hasn't been saved at that time hence
   *   it doesn't have an ID. Please consider entity immutable and do not save.
   *
   * @return string
   *   Resolved remote order ID.
   */
  public function resolve(PaymentInterface $payment);

}
