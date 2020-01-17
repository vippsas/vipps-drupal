<?php

namespace Drupal\commerce_vipps;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Commerce Vipps Service Provider.
 */
class CommerceVippsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['commerce_shipping'])) {
      $container->register('commerce_vipps.commerce_shipping_methods_resolver', 'Drupal\commerce_vipps\Resolver\CommerceShippingMethodsResolver')
        ->addTag('commerce_vipps.shipping_methods_resolver', ['priority' => -90])
        ->addArgument(new Reference('commerce_shipping.packer_manager'))
        ->addArgument(new Reference('entity_type.manager'));

      $definition = $container->getDefinition('commerce_vipps.commerce_shipping_subscriber')
        ->setAbstract(FALSE);
      $container->setDefinition('commerce_vipps.commerce_shipping_subscriber', $definition);
    }
    if (isset($modules['commerce_cart'])) {
      $definition = $container->getDefinition('commerce_vipps.cart_token_subscriber')
        ->setAbstract(FALSE);
      $container->setDefinition('commerce_vipps.cart_token_subscriber', $definition);
    }
  }

}
