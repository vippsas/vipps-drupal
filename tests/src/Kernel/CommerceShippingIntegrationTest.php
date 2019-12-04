<?php

namespace Drupal\Tests\commerce_vipps\Kernel;

use Drupal\commerce_vipps\Resolver\CommerceShippingMethodsResolver;
use Drupal\commerce_vipps\Resolver\ShippingMethodsResolverInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Class CommerceShippingIntegrationTest.
 *
 * @package Drupal\Tests\commerce_vipps\Kernel
 * @group commerce_vipps
 */
class CommerceShippingIntegrationTest extends KernelTestBase {

  protected static $modules = [
    'commerce',
    'commerce_price',
    'commerce_order',
    'commerce_payment',
    'commerce_vipps',
  ];

  /**
   * Checks if commerce_shipping resolver is being added conditionally.
   */
  public function testResolverDiscovery() {
    $shippingResolver = $this->container->get('commerce_vipps.chain_shipping_methods_resolver');
    $resolvers = array_map(function (ShippingMethodsResolverInterface $class) {
      return get_class($class);
    }, $shippingResolver->getResolvers());
    $this->assertNotContains(CommerceShippingMethodsResolver::class, $resolvers);

    $this->enableModules(['commerce_shipping']);
    $shippingResolver = $this->container->get('commerce_vipps.chain_shipping_methods_resolver');
    $this->assertInstanceOf(CommerceShippingMethodsResolver::class, $this->container->get('commerce_vipps.commerce_shipping_methods_resolver'));
    $resolvers = array_map(function (ShippingMethodsResolverInterface $class) {
      return get_class($class);
    }, $shippingResolver->getResolvers());
    $this->assertContains(CommerceShippingMethodsResolver::class, $resolvers);
  }

}
