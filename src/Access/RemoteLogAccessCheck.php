<?php

namespace Drupal\commerce_vipps\Access;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an access checker for payment operations.
 */
class RemoteLogAccessCheck implements AccessInterface {

  /**
   * Checks access to the payment operation on the given route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    $entity = $route_match->getParameter('payment');
    if (empty($entity) || !($entity instanceof PaymentInterface)) {
      return AccessResult::neutral();
    }
    if (!in_array($entity->getPaymentGateway()->getPluginId(), ['vipps', 'vipps_express'])) {
      return AccessResult::neutral();
    }
    return AccessResult::allowedIfHasPermission($account, 'commerce_vipps remote log');
  }

}
