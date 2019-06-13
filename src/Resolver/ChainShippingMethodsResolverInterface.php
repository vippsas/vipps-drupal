<?php

namespace Drupal\commerce_vipps\Resolver;

/**
 * Runs the added resolvers one by one until one of them returns the available
 * shipping methods.
 *
 * Each resolver in the chain can be another chain, which is why this interface
 * extends the order id resolver one.
 */
interface ChainShippingMethodsResolverInterface extends ShippingMethodsResolverInterface {

  /**
   * Adds a resolver.
   *
   * @param \Drupal\commerce_vipps\Resolver\ShippingMethodsResolverInterface $resolver
   *   The resolver.
   */
  public function addResolver(ShippingMethodsResolverInterface $resolver);

  /**
   * Gets all added resolvers.
   *
   * @return \Drupal\commerce_vipps\Resolver\ShippingMethodsResolverInterface[]
   *   The resolvers.
   */
  public function getResolvers();

}
