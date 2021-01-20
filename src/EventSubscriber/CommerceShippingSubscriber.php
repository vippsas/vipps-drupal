<?php

namespace Drupal\commerce_vipps\EventSubscriber;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\LateOrderProcessor;
use Drupal\commerce_shipping\PackerManagerInterface;
use Drupal\commerce_shipping\EarlyOrderProcessor;
use Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent;
use Drupal\commerce_vipps\Event\VippsEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Commerce_vipps event subscriber.
 */
class CommerceShippingSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The packer manager.
   *
   * @var \Drupal\commerce_shipping\PackerManagerInterface
   */
  protected $packerManager;

  /**
   * The shipment order processor.
   *
   * @var \Drupal\commerce_shipping\EarlyOrderProcessor
   */
  protected $earlyOrderProcessor;

  /**
   * The shipment order processor.
   *
   * @var \Drupal\commerce_shipping\LateOrderProcessor
   */
  protected $lateOrderProcessor;

  /**
   * CommerceShippingSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_shipping\PackerManagerInterface $packerManager
   *   The packer manager.
   * @param \Drupal\commerce_shipping\EarlyOrderProcessor $earlyOrderProcessor
   *   The shipment order processor.
   * @param \Drupal\commerce_shipping\LateOrderProcessor $lateOrderProcessor
   *   The shipment order processor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, PackerManagerInterface $packerManager, EarlyOrderProcessor $earlyOrderProcessor, LateOrderProcessor $lateOrderProcessor) {
    $this->entityTypeManager = $entityTypeManager;
    $this->packerManager = $packerManager;
    $this->earlyOrderProcessor = $earlyOrderProcessor;
    $this->lateOrderProcessor = $lateOrderProcessor;
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
    $shipment->setData('owned_by_packer', FALSE);
    $shipment->save();
    $order->set('shipments', [$shipment]);

    $this->earlyOrderProcessor->process($order);
    $this->lateOrderProcessor->process($order);
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
