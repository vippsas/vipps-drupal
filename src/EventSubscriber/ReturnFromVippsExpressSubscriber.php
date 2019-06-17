<?php

namespace Drupal\commerce_vipps\EventSubscriber;

use Drupal\commerce_shipping\PackerManagerInterface;
use Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent;
use Drupal\commerce_vipps\Event\VippsEvents;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
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
    $event->setOrder($order);
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
