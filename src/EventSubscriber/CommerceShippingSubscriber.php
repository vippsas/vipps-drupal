<?php

namespace Drupal\commerce_vipps\EventSubscriber;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\PackerManagerInterface;
use Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent;
use Drupal\commerce_vipps\Event\VippsEvents;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails;

/**
 * Commerce_vipps event subscriber.
 */
class CommerceShippingSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\commerce_shipping\PackerManagerInterface
   */
  protected $packerManager;

  /**
   * CommerceShippingSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\commerce_shipping\PackerManagerInterface $packerManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, PackerManagerInterface $packerManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->packerManager = $packerManager;
  }

  /**
   * Create shipment, update payment.
   *
   * @param \Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent $event
   *   Vipps Express event.
   */
  public function createShipment(ReturnFromVippsExpressEvent $event) {
    $details = $event->getDetails();
    $order = $event->getOrder();
    $payment = $event->getPayment();
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

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->shipments->referencedEntities();
    list($shipments,) = $this->packerManager->packToShipments($order, $profile, $shipments);

    // @todo: Possibly not only the first one.
    $shipment = $shipments[0];

    // Set shipment.
    $shipment->setAmount(new Price((string) $details->getShippingDetails()->getShippingCost(), $order->getTotalPrice()->getCurrencyCode()));
    list($shipping_method_id, $shipping_service_id) = explode('--', $details->getShippingDetails()->getShippingMethodId());
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = $this->entityTypeManager->getStorage('commerce_shipping_method')->load($shipping_method_id);
    $shipment->setPackageType($shipping_method->getPlugin()->getDefaultPackageType());
    $shipment->setShippingMethodId($shipping_method_id);
    $shipment->setShippingProfile($profile);
    $shipment->setShippingService($shipping_service_id);
    $shipment->save();
    $order->set('shipments', [$shipment]);
    $order->setEmail($details->getUserDetails()->getEmail());
    $event->setOrder($order);

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
      VippsEvents::RETURN_FROM_VIPPS_EXPRESS => ['createShipment'],
    ];
  }

}
