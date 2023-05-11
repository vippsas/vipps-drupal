<?php

namespace Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_vipps\VippsManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use zaporylie\Vipps\Exceptions\VippsException;
use Drupal\commerce_price\Price;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Provides the Vipps payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "vipps",
 *   label = "Vipps eComm",
 *   display_label = "Vipps eComm",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_vipps\PluginForm\OffsiteRedirect\VippsLandingPageRedirectForm",
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class Vipps extends OffsitePaymentGatewayBase implements SupportsAuthorizationsInterface, SupportsRefundsInterface, SupportsNotificationsInterface {

  /**
   * Vipps manager.
   *
   * @var \Drupal\commerce_vipps\VippsManager
   */
  protected $vippsManager;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $object = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time')
    );
    $object->vippsManager = $container->get('commerce_vipps.manager');
    $object->lock = $container->get('lock');
    $object->logger = $container->get('logger.channel.commerce_vipps');
    return $object;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'client_id' => '',
      'subscription_key_authorization' => '',
      'client_secret' => '',
      'subscription_key_payment' => '',
      'serial_number' => '',
      'prefix' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#required' => TRUE,
      '#description' => $this->t('Client ID'),
      '#default_value' => $this->configuration['client_id'],
    ];
    $form['subscription_key_authorization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscription Key - Authorization'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['subscription_key_authorization'],
    ];
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client secret'),
      '#required' => TRUE,
      '#description' => $this->t('Client Secret'),
      '#default_value' => $this->configuration['client_secret'],
    ];
    $form['subscription_key_payment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscription Key - Payment'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['subscription_key_payment'],
    ];
    $form['serial_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant Serial Number'),
      '#required' => TRUE,
      '#description' => $this->t("Please note that provided MSN must be for the sales unit that will be used for payments, not the Partner/Supermerchant ID"),
      '#default_value' => $this->configuration['serial_number'],
    ];
    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#description' => $this->t("Add alphanumeric prefix to Order ID in Vipps, in case you're creating Vipps payments from multiple independent systems"),
      '#default_value' => $this->configuration['prefix'],
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
      $this->configuration['client_id'] = $values['client_id'];
      $this->configuration['subscription_key_authorization'] = $values['subscription_key_authorization'];
      $this->configuration['client_secret'] = $values['client_secret'];
      $this->configuration['subscription_key_payment'] = $values['subscription_key_payment'];
      $this->configuration['serial_number'] = $values['serial_number'];
      $this->configuration['prefix'] = $values['prefix'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $remote_id = $order->getData('vipps_current_transaction');
    $paymentGatewayId = $this->parentEntity->id();
    $lockId = $paymentGatewayId . '__' . $remote_id;

    // Keep checking if lock could be acquired for 5 seconds, then start over.
    while ($this->lock->wait($lockId, 5) || !$this->lock->acquire($lockId)) {
      // Looks like lock cannot be acquired, hold for one second and retry.
      sleep(1);
      $this->logger->notice('Waiting for lock @lock to be released', ['@lock' => $lockId]);
    }
    $this->logger->notice('Lock @lock was acquired', ['@lock' => $lockId]);

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $matching_payments = $payment_storage->loadByProperties(['remote_id' => $remote_id, 'order_id' => $order->id()]);
    if (count($matching_payments) !== 1) {
      $this->lock->release($lockId);
      throw new PaymentGatewayException('More than one matching payment found');
    }
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $matching_payment */
    $matching_payment = reset($matching_payments);
    $payment_manager = $this->vippsManager->getPaymentManager($matching_payment->getPaymentGateway()->getPlugin());
    $status = $payment_manager->getOrderStatus($remote_id);

    switch ($status->getTransactionInfo()->getStatus()) {
      case 'RESERVE':
        $matching_payment->setState('authorization');
        $matching_payment->save();
        break;

      case 'SALE':
        $matching_payment->setState('completed');
        $matching_payment->save();
        break;

      // Status INITIATE means that final status has not yet been issued, hence
      // order must be kept locked in payment return state and status check must
      // be retried every 10s.
      // @see https://www.drupal.org/project/commerce_vipps/issues/3106042
      case 'INITIATE':
        sleep(10);
        $this->lock->release($lockId);
        $this->logger->notice('Lock @lock was released', ['@lock' => $lockId]);
        throw new NeedsRedirectException(Url::fromRoute('<current>')->toString());

      case 'RESERVE_FAILED':
      case 'SALE_FAILED':
      case 'CANCEL':
      case 'REJECTED':
        // @todo: There is no corresponding state in payment workflow but it's
        // still better to keep the payment with invalid state than delete it
        // entirely.
        $matching_payment->setState('failed');
        $matching_payment->setRemoteState(Xss::filter($status->getTransactionInfo()->getStatus()));
        $matching_payment->save();

      default:
        $this->lock->release($lockId);
        $this->logger->notice('Lock @lock was released', ['@lock' => $lockId]);
        throw new PaymentGatewayException("Oooops, something went wrong.");
    }
    // Seems like payment went through. Enjoy!
    $this->lock->release($lockId);
    $this->logger->notice('Lock @lock was released', ['@lock' => $lockId]);
  }

  /**
   * Vipps treats onReturn and onCancel in the same way.
   *
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    parent::onReturn($order, $request);
  }

  /**
   * {@inheritdoc}
   *
   * Checks for status changes, and saves it.
   */
  public function onNotify(Request $request) {

    // @todo: Validate order and payment existance.
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway */
    $commerce_payment_gateway = $request->attributes->get('commerce_payment_gateway');
    $payment_gateway_id = $commerce_payment_gateway->id();

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $request->attributes->get('order');
    if (!$order instanceof OrderInterface) {
      return new Response('', Response::HTTP_FORBIDDEN);
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    // Validate authorization header.
    if ($order->getData('vipps_auth_key') !== $request->headers->get('Authorization')) {
      return new Response('', Response::HTTP_FORBIDDEN);
    }

    $content = $request->getContent();

    $remote_id = $request->attributes->get('remote_id');

    $lockId = $payment_gateway_id . '__' . $remote_id;

    // Keep checking if lock could be acquired for 5 seconds, then start over.
    while ($this->lock->wait($lockId, 5) || !$this->lock->acquire($lockId)) {
      // Looks like lock cannot be acquired, hold for one second and retry.
      sleep(1);
      $this->logger->notice('Waiting for lock @lock to be released', ['@lock' => $lockId]);
    }
    $this->logger->notice('Lock @lock was acquired', ['@lock' => $lockId]);

    $matching_payments = $payment_storage->loadByProperties(['remote_id' => $remote_id, 'payment_gateway' => $commerce_payment_gateway->id()]);
    if (count($matching_payments) !== 1) {
      // @todo: Log exception.
      $this->lock->release($lockId);
      $this->logger->notice('Lock @lock was released', ['@lock' => $lockId]);
      return new Response('', Response::HTTP_FORBIDDEN);
    }
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $matching_payment */
    // $old_state = $matching_payment->getState()->getId();
    $matching_payment = reset($matching_payments);

    $content = json_decode($content, TRUE);
    switch ($content['transactionInfo']['status']) {
      case 'RESERVED':
        $matching_payment->setState('authorization');
        break;

      case 'SALE':
        $matching_payment->setState('completed');
        break;

      case 'RESERVE_FAILED':
      case 'SALE_FAILED':
      case 'CANCELLED':
      case 'REJECTED':
        // @todo: There is no corresponding state in payment workflow but it's
        // still better to keep the payment with invalid state than delete it
        // entirely.
        $matching_payment->setState('failed');
        $matching_payment->setRemoteState(Xss::filter($content['transactionInfo']['status']));
        break;

      default:
        $this->logger->critical('Data: @data', ['@data' => $content]);
        $this->lock->release($lockId);
        $this->logger->notice('Lock @lock was released', ['@lock' => $lockId]);
        return new Response('', Response::HTTP_I_AM_A_TEAPOT);
    }
    $matching_payment->save();

    $this->lock->release($lockId);
    $this->logger->notice('Lock @lock was released', ['@lock' => $lockId]);

    return new Response('', Response::HTTP_OK);
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    // Assert things.
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    if ($amount->lessThan($payment->getAmount())) {
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $parent_payment */
      $parent_payment = $payment;
      $payment = $parent_payment->createDuplicate();
    }

    $remote_id = $payment->getRemoteId();
    $number = $amount->multiply(100)->getNumber();
    try {
      $payment_manager = $this->vippsManager->getPaymentManager($payment->getPaymentGateway()->getPlugin());
      // @todo: Pass formatted number.
      $payment_manager->capturePayment($remote_id,
        $this->t('Captured @amount via webshop',
          ['@amount' => $amount->getNumber()]), $number);
    }
    catch (VippsException $exception) {
      if ($exception->getError()->getCode() == 61) {
        // Insufficient funds.
        // Check if order has already been captured and for what amount,.
        foreach ($payment_manager->getPaymentDetails($remote_id)->getTransactionLogHistory() as $item) {
          if (in_array($item->getOperation(), ['CAPTURE', 'SALE']) && $item->getOperationSuccess()) {
            $payment->setAmount(new Price($item->getAmount() / 100, $payment->getAmount()->getCurrencyCode()));
            $payment->setCompletedTime($item->getTimeStamp()->getTimestamp());
            $payment->setState('completed');
            $payment->save();
            // @todo: Sum up all capture transactions - Vipps allow partial
            // capture.
            return;
          }
        }
      }
      throw new DeclineException($exception->getMessage());
    }
    catch (\Exception $exception) {
      throw new DeclineException($exception->getMessage());
    }

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();

    // Update parent payment if one exists.
    if (isset($parent_payment)) {
      $parent_payment->setAmount($parent_payment->getAmount()->subtract($amount));
      if ($parent_payment->getAmount()->isZero()) {
        $parent_payment->setState('authorization_voided');
      }
      $parent_payment->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    $remote_id = $payment->getRemoteId();
    try {
      $payment_manager = $this->vippsManager->getPaymentManager($payment->getPaymentGateway()->getPlugin());
      $payment_manager->cancelPayment($remote_id, $this->t('Canceled via webshop'));
    }
    catch (\Exception $exception) {
      throw new DeclineException($exception->getMessage());
    }

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    // Validate.
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);

    // Let's do some refunds.
    parent::assertRefundAmount($payment, $amount);

    $remote_id = $payment->getRemoteId();
    $number = $amount->multiply(100)->getNumber();
    try {
      $payment_manager = $this->vippsManager->getPaymentManager($payment->getPaymentGateway()->getPlugin());
      // @todo: Pass formatted number.
      $payment_manager->refundPayment($remote_id,
        $this->t('Refunded @amount via webshop',
          ['@amount' => $amount->getNumber()]), $number);
    }
    catch (\Exception $exception) {
      throw new DeclineException($exception->getMessage());
    }

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();

  }

}
