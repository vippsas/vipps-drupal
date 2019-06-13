<?php

namespace Drupal\commerce_vipps;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class CommerceVippsServiceProvider extends ServiceProviderBase {
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['commerce_shipping'])) {
      $definition = $container->getDefinition('commerce_vipps.commerce_shipping_methods_resolver')
        ->setAbstract(FALSE);
      $container->setDefinition('commerce_vipps.commerce_shipping_methods_resolver', $definition);

      $definition = $container->getDefinition('commerce_vipps.commerce_shipping_subscriber')
        ->setAbstract(FALSE);
      $container->setDefinition('commerce_vipps.commerce_shipping_subscriber', $definition);
    }
  }
}
