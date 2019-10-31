<?php

namespace Drupal\commerce_vipps\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Symfony\Component\EventDispatcher\Event;
use zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails;

/**
 * Return From Vipps Express Event.
 */
class ReturnFromVippsExpressEvent extends Event {

  /**
   * The payment.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Details coming from Vipps API.
   *
   * Details contains many values, including shipping details, user details,
   * etc. Please use it to store data you need on order or payment.
   *
   * @var \zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails
   */
  protected $details;

  /**
   * Constructs a new InitiatePaymentOptionsEvent.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails $details
   *   Vipps payment details.
   */
  public function __construct(PaymentInterface $payment, OrderInterface $order, ResponseGetPaymentDetails $details) {
    $this->payment = $payment;
    $this->order = $order;
    $this->details = $details;
  }

  /**
   * Returns the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder() {
    return $this->order;
  }

  /**
   * Returns payment.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   The payment.
   */
  public function getPayment() {
    return $this->payment;
  }

  /**
   * Returns payment details.
   *
   * @return \zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails
   *   Payment details.
   */
  public function getDetails() {
    return $this->details;
  }

  /**
   * Allows for setting the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return $this
   */
  public function setOrder(OrderInterface $order) {
    $this->order = $order;
    return $this;
  }

  /**
   * Allows for setting the payment.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return $this
   */
  public function setPayment(PaymentInterface $payment) {
    $this->payment = $payment;
    return $this;
  }

}
