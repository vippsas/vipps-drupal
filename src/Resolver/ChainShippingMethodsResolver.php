<?php

namespace Drupal\commerce_vipps\Resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use zaporylie\Vipps\Model\Payment\FetchShippingCostAndMethod;

class ChainShippingMethodsResolver implements ChainShippingMethodsResolverInterface {

  /**
   * The resolvers.
   *
   * @var \Drupal\commerce_vipps\Resolver\ShippingMethodsResolverInterface[]
   */
  protected $resolvers = [];

  /**
   * Constructs a new ChainShippingMethodsResolver object.
   *
   * @param \Drupal\commerce_vipps\Resolver\ShippingMethodsResolverInterface[] $resolvers
   *   The resolvers.
   */
  public function __construct(array $resolvers = []) {
    $this->resolvers = $resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function addResolver(ShippingMethodsResolverInterface $resolver) {
    $this->resolvers[] = $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolvers() {
    return $this->resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OrderInterface $order, FetchShippingCostAndMethod $address) {
    foreach ($this->resolvers as $resolver) {
      $result = $resolver->resolve($order, $address);
      if (isset($result)) {
        return $result;
      }
    }
  }
}
