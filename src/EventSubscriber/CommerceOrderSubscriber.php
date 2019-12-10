<?php

namespace Drupal\commerce_vipps\EventSubscriber;

use Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent;
use Drupal\commerce_vipps\Event\VippsEvents;
use Drupal\commerce_vipps\VippsManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Commerce Order Subscriber.
 *
 * @package Drupal\commerce_vipps\EventSubscriber
 */
class CommerceOrderSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The Vipps manager.
   *
   * @var \Drupal\commerce_vipps\VippsManagerInterface
   */
  protected $vippsManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * CommerceShippingSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager.
   * @param \Drupal\commerce_vipps\VippsManagerInterface $vippsManager
   *   The vipps manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, VippsManagerInterface $vippsManager, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->vippsManager = $vippsManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
  }

  /**
   * Sets the data collected by VippsExpress back on Order.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function updateOrder(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $order->get('payment_gateway')->entity;
    if (!$payment_gateway) {
      // The order have no payment, so no way to know the plugin either.
      return;
    }
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if ($payment_gateway_plugin->getPluginId() !== 'vipps_express') {
      // Proceed only if Vipps Express payment gateway was selected.
      return;
    }
    $remote_id = $order->getData('vipps_current_transaction');
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $matching_payments = $payment_storage->loadByProperties(['remote_id' => $remote_id, 'order_id' => $order->id()]);
    if (count($matching_payments) !== 1) {
      $this->logger->critical('More than one payment found. Data sync from Vipps to Commerce aborted.');
      return;
    }
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $matching_payment */
    $matching_payment = reset($matching_payments);
    $payment_manager = $this->vippsManager->getPaymentManager($matching_payment->getPaymentGateway()->getPlugin());

    // Get payment details.
    $details = $payment_manager->getPaymentDetails($remote_id);

    // Dispatch the event.
    $e = new ReturnFromVippsExpressEvent($matching_payment, $order, $details);
    $this->eventDispatcher->dispatch(VippsEvents::RETURN_FROM_VIPPS_EXPRESS, $e);

    // Save the payment in case it was modified.
    $matching_payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.place.pre_transition' => ['updateOrder', 50],
    ];
  }

}
