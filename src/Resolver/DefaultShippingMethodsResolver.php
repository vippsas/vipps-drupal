<?php

namespace Drupal\commerce_vipps\Resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use zaporylie\Vipps\Model\Payment\FetchShippingCostAndMethod;
use zaporylie\Vipps\Model\Payment\ShippingDetails;

/**
 * Returns the site's default remote order id.
 */
class DefaultShippingMethodsResolver implements ShippingMethodsResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(OrderInterface $order, FetchShippingCostAndMethod $address) {
    return [];
  }

}
