<?php

namespace Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_vipps\Resolver\ChainShippingMethodsResolverInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use zaporylie\Vipps\Model\Payment\ExpressCheckOutPaymentRequest;
use zaporylie\Vipps\Model\Payment\FetchShippingCostAndMethod;
use zaporylie\Vipps\Model\Payment\FetchShippingCostResponse;

/**
 * Provides the Vipps payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "vipps_express",
 *   label = "Vipps Hurtigkasse",
 *   display_label = "Vipps Hurtigkasse",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_vipps\PluginForm\OffsiteRedirect\VippsExpressLandingPageRedirectForm",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class VippsExpress extends Vipps implements SupportsAuthorizationsInterface, SupportsRefundsInterface, SupportsNotificationsInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The shipping method resolver.
   *
   * @var \Drupal\commerce_vipps\Resolver\ChainShippingMethodsResolverInterface
   */
  protected $shippingMethodResolver;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $vipps */
    $vipps = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $vipps->setEventSubscriber($container->get('event_dispatcher'));
    $vipps->setShippingMethodResolver($container->get('commerce_vipps.chain_shipping_methods_resolver'));
    $vipps->setCurrentRouteMatch($container->get('current_route_match'));
    return $vipps;
  }

  /**
   * Service setter.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   *
   * @return $this
   */
  public function setEventSubscriber(EventDispatcherInterface $eventDispatcher) {
    $this->eventDispatcher = $eventDispatcher;
    return $this;
  }

  /**
   * Service setter.
   *
   * @param \Drupal\commerce_vipps\Resolver\ChainShippingMethodsResolverInterface $shippingMethodResolver
   *   The shipping method resolver.
   *
   * @return $this
   */
  public function setShippingMethodResolver(ChainShippingMethodsResolverInterface $shippingMethodResolver) {
    $this->shippingMethodResolver = $shippingMethodResolver;
    return $this;
  }

  /**
   * Service setter.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current route match.
   *
   * @return $this
   */
  public function setCurrentRouteMatch(CurrentRouteMatch $currentRouteMatch) {
    $this->currentRouteMatch = $currentRouteMatch;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'button_style' => 'default',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['button_style'] = [
      '#type' => 'radios',
      '#title' => $this->t('Button style'),
      '#default_value' => $this->configuration['button_style'],
      '#options' => [
        'default' => $this->t('Default'),
        'image_small' => $this->t('Image - small'),
        'image_medium' => $this->t('Image - medium'),
        'image_large' => $this->t('Image - Large'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['button_style'] = $values['button_style'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    switch ($this->currentRouteMatch->getRouteName()) {
      case 'commerce_vipps.notify':
        return $this->doNotify($request);

      case 'commerce_vipps.consents':
        return $this->doConsentRemoval($request);

      case 'commerce_vipps.shipping_details':
        return $this->doShippingDetails($request);
    }

    return new JsonResponse('Unknown callback', Response::HTTP_FORBIDDEN);
  }

  /**
   * Do Consent Removal.
   */
  protected function doConsentRemoval(Request $request) {
    \Drupal::logger('commerce_vipps')->alert('Method not supported');
  }

  /**
   * Notification callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function doNotify(Request $request) {
    try {
      $payment = $this->getPaymentFromRequest($request);
    }
    catch (\Exception $exception) {
      return new JsonResponse('', Response::HTTP_FORBIDDEN);
    }

    /** @var \zaporylie\Vipps\Model\Payment\ExpressCheckOutPaymentRequest $content */
    $content = ExpressCheckOutPaymentRequest::fromString($stringContent = $request->getContent());
    switch ($content->getTransactionInfo()->getStatus()) {
      case 'RESERVE':
        $payment->setAmount(new Price((string) ($content->getTransactionInfo()->getAmount() / 100), $payment->getAmount()->getCurrencyCode()));
        $payment->setState('authorization');
        break;

      case 'SALE':
        $payment->setAmount(new Price((string) ($content->getTransactionInfo()->getAmount() / 100), $payment->getAmount()->getCurrencyCode()));
        $payment->setState('completed');
        break;

      case 'CANCELLED':
      case 'REJECTED':
        // @todo: There is no corresponding state in payment workflow but it's
        // still better to keep the payment with invalid state than delete it
        // entirely.
        $payment->setState('failed');
        $payment->setRemoteState(Xss::filter($content->getTransactionInfo()->getStatus()));
        break;

      default:
        \Drupal::logger('commerce_vipps')->critical('Undefined status: @data', ['@data' => $content->getTransactionInfo()->getStatus(), '@request_body' => $stringContent]);
        return new Response('', Response::HTTP_I_AM_A_TEAPOT);
    }
    $payment->save();

    return new Response('', Response::HTTP_OK);
  }

  /**
   * Shipping details callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   *   JSON response.
   */
  protected function doShippingDetails(Request $request) {
    $incomingData = $request->getContent();
    /** @var \zaporylie\Vipps\Model\Payment\FetchShippingCostAndMethod $incomingData */
    $incomingData = FetchShippingCostAndMethod::fromString($incomingData);
    try {
      $payment = $this->getPaymentFromRequest($request);
    }
    catch (\Exception $exception) {
      // @todo Use dependency injection.
      \Drupal::logger('commerce_vipps')->error($exception->getMessage());
      return new JsonResponse('', Response::HTTP_FORBIDDEN);
    }

    $availableShippingMethods = new FetchShippingCostResponse();
    $availableShippingMethods
      ->setAddressId($incomingData->getAddressId())
      ->setOrderId($payment->getRemoteId())
      ->setShippingDetails($this->shippingMethodResolver->resolve($payment->getOrder(), $incomingData));
    return new Response($availableShippingMethods->toString(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
  }

  /**
   * Retrives specific payment from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface
   *   Payment.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getPaymentFromRequest(Request $request) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway */
    $commerce_payment_gateway = $request->attributes->get('commerce_payment_gateway');

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $request->attributes->get('order');
    if (!$order instanceof OrderInterface) {
      throw new \InvalidArgumentException('Order mismatch');
    }

    // Validate authorization header.
    if ($order->getData('vipps_auth_key') !== $request->headers->get('Authorization')) {
      throw new \InvalidArgumentException('Incorrect Authorization header.');
    }
    $remote_id = $request->attributes->get('remote_id');
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $matching_payments = $payment_storage->loadByProperties([
      'remote_id' => $remote_id,
      'payment_gateway' => $commerce_payment_gateway->id(),
      'order_id' => $order->id(),
    ]);
    if (count($matching_payments) !== 1) {
      throw new \InvalidArgumentException('Multiple or none payment found.');
    }
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $matching_payment */
    return reset($matching_payments);
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $remote_id = $order->getData('vipps_current_transaction');
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $matching_payments = $payment_storage->loadByProperties(['remote_id' => $remote_id, 'order_id' => $order->id()]);
    if (count($matching_payments) !== 1) {
      throw new PaymentGatewayException('More than one or none matching payment found');
    }
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $matching_payment */
    $matching_payment = reset($matching_payments);
    $payment_manager = $this->vippsManager->getPaymentManager($matching_payment->getPaymentGateway()->getPlugin());
    $status = $payment_manager->getOrderStatus($remote_id);
    switch ($status->getTransactionInfo()->getStatus()) {
      case 'RESERVE':
        $matching_payment->setAmount(new Price((string) ($status->getTransactionInfo()->getAmount() / 100), $matching_payment->getAmount()->getCurrencyCode()));
        $matching_payment->setState('authorization');
        $matching_payment->save();
        break;

      case 'SALE':
        $matching_payment->setAmount(new Price((string) ($status->getTransactionInfo()->getAmount() / 100), $matching_payment->getAmount()->getCurrencyCode()));
        $matching_payment->setState('completed');
        $matching_payment->save();
        break;

      case 'RESERVE_FAILED':
      case 'SALE_FAILED':
      case 'CANCEL':
      case 'REJECTED':
      case 'INITIATE':
        // @todo: There is no corresponding state in payment workflow but it's
        // still better to keep the payment with invalid state than delete it
        // entirely.
        $matching_payment->setState('failed');
        $matching_payment->setRemoteState(Xss::filter($status->getTransactionInfo()->getStatus()));
        $matching_payment->save();
        $order->set('payment_gateway', NULL);
        $order->set('payment_method', NULL);
        $order->set('checkout_step', NULL);
        $order->unlock();
        $order->save();

      default:
        throw new NeedsRedirectException('/cart');
    }
  }

}
