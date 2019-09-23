<?php

namespace Drupal\commerce_vipps\EventSubscriber;

use Drupal\commerce_price\Price;
use Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent;
use Drupal\commerce_vipps\Event\VippsEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails;

/**
 * Commerce_vipps event subscriber.
 */
class ReturnFromVippsExpressSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * CommerceShippingSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Creates billing profile.
   *
   * @param \Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent $event
   *   Vipps Express event.
   */
  public function createBillingProfile(ReturnFromVippsExpressEvent $event) {
    $details = $event->getDetails();
    $order = $event->getOrder();
    $order->setEmail($details->getUserDetails()->getEmail());
    /** @var \Drupal\profile\Entity\Profile $profile */
    $profile = $this->entityTypeManager->getStorage('profile')->create([
      'type' => 'customer',
      'address' => [
        'given_name' => $details->getUserDetails()->getFirstName(),
        'family_name' => $details->getUserDetails()->getLastName(),
        'country_code' => 'NO',
        'postal_code' => $details->getShippingDetails()->getAddress()->getPostCode(),
        'locality' => $details->getShippingDetails()->getAddress()->getCity(),
        'address_line1' => $details->getShippingDetails()->getAddress()->getAddressLine1(),
        'address_line2' => $details->getShippingDetails()->getAddress()->getAddressLine2(),
      ],
      'uid' => $order->getCustomerId(),
    ]);
    $profile->save();
    $order->setBillingProfile($profile);
    // Amend the payment amount.
    $this->amendPrice($event);
  }

  /**
   * @param \Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent $event.
   */
  public function amendPrice(ReturnFromVippsExpressEvent $event) {
    $details = $event->getDetails();
    $payment = $event->getPayment();
    $payment->setAmount($this->getAmendedPrice($details, $payment->getAmount()));
    $event->setPayment($payment);
  }

  /**
   * Calculates amended price based on ResponseGetPaymentDetails object.
   *
   * @param \zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails $details
   * @param \Drupal\commerce_price\Price $amount
   *
   * @return \Drupal\commerce_price\Price
   */
  public function getAmendedPrice(ResponseGetPaymentDetails $details, Price $amount) {
    return new Price((string) (($details->getTransactionSummary()->getCapturedAmount() + $details->getTransactionSummary()->getRemainingAmountToCapture()) / 100), $amount->getCurrencyCode());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      VippsEvents::RETURN_FROM_VIPPS_EXPRESS => ['createBillingProfile'],
    ];
  }

}
