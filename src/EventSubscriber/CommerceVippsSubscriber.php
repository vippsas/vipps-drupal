<?php

namespace Drupal\commerce_vipps\EventSubscriber;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_vipps\Event\InitiatePaymentOptionsEvent;
use Drupal\commerce_vipps\Event\VippsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * commerce_vipps event subscriber.
 */
class CommerceVippsSubscriber implements EventSubscriberInterface {

  /**
   * Filter Payment Gateways.
   *
   * @param \Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent $event
   *   Payment gateway event.
   */
  public function filterExpressCheckout(FilterPaymentGatewaysEvent $event) {
    $payment_gateways = $event->getPaymentGateways();
    $payment_gateways = array_filter($payment_gateways, function (PaymentGatewayInterface $payment_gateway) { return $payment_gateway->getPluginId() !== 'vipps_express'; });
    $event->setPaymentGateways($payment_gateways);
  }

  /**
   * Set correct payment method type for express payment.
   *
   * @param \Drupal\commerce_vipps\Event\InitiatePaymentOptionsEvent $event
   */
  public function setPaymentMethod(InitiatePaymentOptionsEvent $event) {
    $plugin = $event->getPayment()->getPaymentGateway()->getPlugin();
    if ($plugin->getPluginId() === 'vipps_express') {
      $event->setOptions($event->getOptions() + [
        'paymentType' => 'eComm Express Payment',
        'shippingDetailsPrefix' => rtrim($plugin->getNotifyUrl()->toString(), '/') . '/' . $event->getPayment()->getOrderId(),
        'consentRemovalPrefix' => rtrim($plugin->getNotifyUrl()->toString(), '/') . '/' . $event->getPayment()->getOrderId()
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PaymentEvents::FILTER_PAYMENT_GATEWAYS => ['filterExpressCheckout'],
      VippsEvents::INITIATE_PAYMENT_OPTIONS => ['setPaymentMethod'],
    ];
  }

}
