<?php

namespace Drupal\commerce_vipps\Resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use zaporylie\Vipps\Model\Payment\FetchShippingCostAndMethod;

/**
 * Defines the interface for shipping methods resolvers.
 */
interface ShippingMethodsResolverInterface {

  /**
   * Resolves available shipping methods.
   *
   * @return \zaporylie\Vipps\Model\Payment\ShippingDetails[]
   */
  public function resolve(OrderInterface $order, FetchShippingCostAndMethod $address);

}
