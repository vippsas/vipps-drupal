<?php

namespace Drupal\commerce_vipps\Resolver;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\PackerManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\Profile;
use zaporylie\Vipps\Model\Payment\FetchShippingCostAndMethod;
use zaporylie\Vipps\Model\Payment\ShippingDetails;

/**
 * Returns the site's default remote order id.
 */
class CommerceShippingMethodsResolver implements ShippingMethodsResolverInterface {

  /**
   * The packer manager.
   *
   * @var \Drupal\commerce_shipping\PackerManagerInterface
   */
  protected $packerManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CommerceShippingMethodsResolver constructor.
   *
   * @param \Drupal\commerce_shipping\PackerManagerInterface $packerManager
   *   The Packer Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(PackerManagerInterface $packerManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->packerManager = $packerManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OrderInterface $order, FetchShippingCostAndMethod $address) {
    // Only Norway is supported by Vipps.
    if ($address->getCountry() !== 'Norway') {
      return [];
    }
    $profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'NO',
        'postal_code' => $address->getPostCode(),
        'locality' => $address->getCity(),
        'address_line1' => $address->getAddressLine1(),
        'address_line2' => $address->getAddressLine2(),
      ],
      'uid' => $order->getCustomerId(),
    ]);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->shipments->referencedEntities();
    list($shipments,) = $this->packerManager->packToShipments($order, $profile, $shipments);

    /** @var \Drupal\commerce_shipping\ShippingMethodStorageInterface $shipping_method_storage */
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    $available_shipping_methods = [];

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($shipments as $shipment) {
      $shipping_methods = $shipping_method_storage->loadMultipleForShipment($shipment);
      foreach ($shipping_methods as $shipping_method) {
        $shipping_method_plugin = $shipping_method->getPlugin();
        $shipping_rates = $shipping_method_plugin->calculateRates($shipment);
        foreach ($shipping_rates as $shipping_rate) {
          // @see \Drupal\commerce_shipping\Plugin\Field\FieldWidget\ShippingRateWidget::formElement().
          $service = $shipping_rate->getService();
          $amount = $shipping_rate->getAmount();

          $option_id = $shipping_method->id() . '--' . $service->getId();
          $option_label = $service->getLabel();

          $shipping_details = new ShippingDetails();
          $shipping_details->setShippingCost($amount->getNumber());
          $shipping_details->setShippingMethod((string) $option_label);
          $shipping_details->setShippingMethodId($option_id);
          $shipping_details->setIsDefault(FALSE);
          $shipping_details->setPriority(count($available_shipping_methods));
          $available_shipping_methods[] = $shipping_details;
        }
      }
    }

    return $available_shipping_methods;
  }

}
