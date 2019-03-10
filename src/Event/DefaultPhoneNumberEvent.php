<?php

namespace Drupal\commerce_vipps\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

class DefaultPhoneNumberEvent extends Event {

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * @var string|null
   */
  protected $number;

  /**
   * Constructs a new DefaultPhoneNumberEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string|null
   *   Telephone number.
   */
  public function __construct(OrderInterface $order, $number = NULL) {
    $this->order = $order;
    $this->number = $number;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder() {
    return $this->order;
  }

  /**
   * Gets telephone number.
   *
   * @return string|null
   */
  public function getPhoneNumber() {
    return $this->number;
  }

  /**
   * Sets telephone number.
   *
   * @param string|null
   *  Telephone number.
   *
   * @return $this
   */
  public function setPhoneNumber($number) {
    $this->number = $number;
    return $this;
  }

}
