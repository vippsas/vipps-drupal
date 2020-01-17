<?php

namespace Drupal\commerce_vipps\EventSubscriber;

use Drupal\commerce_cart\CartSession;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_vipps\PluginForm\OffsiteRedirect\VippsLandingPageRedirectForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cart token subscriber.
 *
 * On request, it checks if the cart token query parameter is available. This
 * ensures cart data is passed to the user's session.
 */
final class CartTokenSubscriber implements EventSubscriberInterface {

  /**
   * The cart session.
   *
   * @var \Drupal\commerce_cart\CartSessionInterface
   */
  private $cartSession;

  /**
   * The tempstore service.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  private $tempStore;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructs a new CartTokenSubscriber object.
   *
   * @param \Drupal\commerce_cart\CartSessionInterface $cart_session
   *   The cart session.
   * @param \Psr\Log\LoggerInterface $logger
   *   The channel logger.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(CartSessionInterface $cart_session, LoggerInterface $logger, SharedTempStoreFactory $tempStoreFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->cartSession = $cart_session;
    $this->logger = $logger;
    $this->tempStore = $tempStoreFactory->get(VippsLandingPageRedirectForm::QUERY_NAME);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    // Run before router_listener so we execute before access checks, and before
    // dynamic_page_cache so we can populate a session. The ensures proper
    // access to CheckoutController.
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];
    return $events;
  }

  /**
   * Loads the token cart data and resets it to the session.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event, which contains the current request.
   */
  public function onRequest(GetResponseEvent $event) {
    $cart_token = $event->getRequest()->query->get(VippsLandingPageRedirectForm::QUERY_NAME);
    $order_id = $this->tempStore->get($cart_token);
    if ($cart_token && $order_id) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);
      if ($this->cartSession->hasCartId($order->id(), CartSessionInterface::COMPLETED)) {
        $this->tempStore->delete($cart_token);
        return;
      }
      if ($order->getData('vipps_auth_key') !== $cart_token) {
        // @todo: Add flood control.
        $this->logger->alert('CartTokenSubscriber was called on order @order_id with outdated vipps_auth_key', ['@order_id' => $order_id]);
        return;
      }
      $this->logger->notice('Cart session attached for order @order_id via CartTokenSubscriber', ['@order_id' => $order_id]);
      // Attach the cart to the current session.
      if (!$this->cartSession->hasCartId($order->id(), CartSession::ACTIVE)) {
        $this->cartSession->addCartId($order->id(), CartSession::ACTIVE);
      }
      if (!$this->cartSession->hasCartId($order->id(), CartSession::COMPLETED)) {
        $this->cartSession->addCartId($order->id(), CartSession::COMPLETED);
      }
      $this->tempStore->delete($cart_token);
    }
  }

}
