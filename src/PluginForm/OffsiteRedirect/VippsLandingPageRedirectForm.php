<?php

namespace Drupal\commerce_vipps\PluginForm\OffsiteRedirect;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_vipps\Event\InitiatePaymentOptionsEvent;
use Drupal\commerce_vipps\Event\VippsEvents;
use Drupal\commerce_vipps\Resolver\ChainOrderIdResolverInterface;
use Drupal\commerce_vipps\VippsManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class VippsCheckoutForm.
 *
 * Handles the initiation of vipps payments.
 */
class VippsLandingPageRedirectForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {

  /**
   * Used for query name and tempStore collection.
   *
   * @internal
   *   For internal use only!
   */
  const QUERY_NAME = 'commerce_vipps_payment_redirect_key';

  /**
   * The Vipps manager.
   *
   * @var \Drupal\commerce_vipps\VippsManager
   */
  protected $vippsManager;

  /**
   * The Chain Order ID resolver.
   *
   * @var \Drupal\commerce_vipps\Resolver\ChainOrderIdResolverInterface
   */
  protected $chainOrderIdResolver;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Shared temporary storage.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * VippsLandingPageRedirectForm constructor.
   *
   * @param \Drupal\commerce_vipps\VippsManagerInterface $vippsManager
   *   The vipps manager.
   * @param \Drupal\commerce_vipps\Resolver\ChainOrderIdResolverInterface $chainOrderIdResolver
   *   The chain order id resolver.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempStore
   *   The shared temporary storage factory.
   */
  public function __construct(VippsManagerInterface $vippsManager, ChainOrderIdResolverInterface $chainOrderIdResolver, EventDispatcherInterface $eventDispatcher, SharedTempStoreFactory $tempStore) {
    $this->vippsManager = $vippsManager;
    $this->chainOrderIdResolver = $chainOrderIdResolver;
    $this->eventDispatcher = $eventDispatcher;
    $this->tempStore = $tempStore->get(self::QUERY_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_vipps.manager'),
      $container->get('commerce_vipps.chain_order_id_resolver'),
      $container->get('event_dispatcher'),
      $container->get('tempstore.shared')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    // When dumping here, we have a new entity, use that by default.
    $payment = $this->entity;
    /** @var \Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway\Vipps $plugin */
    $plugin = $payment->getPaymentGateway()->getPlugin();
    $settings = $payment->getPaymentGateway()->getPluginConfiguration();

    // Create payment.
    $payment->setRemoteId($settings['prefix'] . $this->chainOrderIdResolver->resolve($payment));

    // Save order.
    $order = $payment->getOrder();
    $order_changed = FALSE;
    if ($order->getData('vipps_auth_key') === NULL) {
      // Generate unique key, retry if key already exists.
      do {
        $order->setData('vipps_auth_key', $this->generateAuthToken());
      } while ($this->tempStore->get($order->getData('vipps_auth_key')));
      $order_changed = TRUE;
    }

    if ($order->getData('vipps_current_transaction') !== $payment->getRemoteId()) {
      $order->setData('vipps_current_transaction', $payment->getRemoteId());
      $order_changed = TRUE;
    }

    $options = [
      'authToken' => $order->getData('vipps_auth_key'),
    ];

    // Set options.
    $event = new InitiatePaymentOptionsEvent($payment, $options);
    $this->eventDispatcher->dispatch(VippsEvents::INITIATE_PAYMENT_OPTIONS, $event);
    $options = $event->getOptions();

    try {
      $url = $this->vippsManager
        ->getPaymentManager($plugin)
        ->initiatePayment(
          $payment->getRemoteId(),
          (int) $payment->getAmount()->multiply(100)->getNumber(),
          $this->t('Payment for order @order_id', ['@order_id' => $payment->getOrderId()]),
          // Get standard payment notification callback and add.
          rtrim($plugin->getNotifyUrl()->toString(), '/') . '/' . $payment->getOrderId(),
          $this->addQueryToUrl($form['#return_url'], [self::QUERY_NAME => $order->getData('vipps_auth_key')]),
          $options
        )
        ->getURL();
    }
    catch (\Exception $exception) {
      throw new PaymentGatewayException($exception->getMessage());
    }

    // If the payment was successfully created at remote host.
    $payment->save();
    if ($order_changed === TRUE) {
      // Order refresh must be disabled at this point.
      $order->setRefreshState(OrderInterface::REFRESH_SKIP);
      $order->save();
    }
    // Add vipps_auth_key to the temp store.
    $this->tempStore->set($order->getData('vipps_auth_key'), $order->id());

    return $this->buildRedirectForm($form, $form_state, $url, []);
  }

  /**
   * Method to generate access token.
   *
   * @return string
   *   Access token.
   */
  private function generateAuthToken() {
    try {
      $randomStr = random_bytes(16);
    }
    catch (\Exception $e) {
      $randomStr = uniqid('', TRUE);
    }
    return bin2hex($randomStr);
  }

  /**
   * Adds custom parameter to string-formed url.
   *
   * @param string $url
   *   Absolute URL.
   * @param array $query
   *   Array of query arguments to be added.
   *
   * @return string
   *   Abslute URL with query arguments.
   */
  private function addQueryToUrl($url, array $query) {
    $parsedUrl = parse_url($url);
    if ($parsedUrl['path'] == NULL) {
      $url .= '/';
    }
    $separator = !isset($parsedUrl['query']) ? '?' : '&';
    return $url .= $separator . http_build_query($query);
  }

}
