<?php

namespace Drupal\commerce_vipps\Resolver;

/**
 * Defines the interface for order id resolvers.
 */
interface OrderIdResolverInterface {

  /**
   * Resolves the remote order id.
   *
   * @return string
   *   Resolved remote order ID.
   */
  public function resolve();

}
