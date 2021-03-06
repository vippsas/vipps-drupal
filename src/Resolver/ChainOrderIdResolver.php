<?php

namespace Drupal\commerce_vipps\Resolver;

use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Chain Order Id Resolver.
 */
class ChainOrderIdResolver implements ChainOrderIdResolverInterface {

  /**
   * The resolvers.
   *
   * @var \Drupal\commerce_vipps\Resolver\OrderIdResolverInterface[]
   */
  protected $resolvers = [];

  /**
   * Constructs a new ChainOrderIdResolver object.
   *
   * @param \Drupal\commerce_vipps\Resolver\OrderIdResolverInterface[] $resolvers
   *   The resolvers.
   */
  public function __construct(array $resolvers = []) {
    $this->resolvers = $resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function addResolver(OrderIdResolverInterface $resolver) {
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
  public function resolve(PaymentInterface $payment) {
    foreach ($this->resolvers as $resolver) {
      $result = $resolver->resolve($payment);
      if ($result) {
        return $result;
      }
    }
  }

}
