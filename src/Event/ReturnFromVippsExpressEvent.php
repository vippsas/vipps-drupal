<?php

namespace Drupal\commerce_vipps\Event;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Symfony\Component\EventDispatcher\Event;
use zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails;

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
   * @return \Drupal\commerce_order\Entity\OrderInterface
   */
  public function getOrder() {
    return $this->order;
  }

  /**
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   */
  public function getPayment() {
    return $this->payment;
  }

  /**
   * @return \zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails
   */
  public function getDetails() {
    return $this->details;
  }

  /**
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *
   * @return $this
   */
  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  /**
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *
   * @return $this
   */
  public function setPayment($payment) {
    $this->payment = $payment;
    return $this;
  }

}
