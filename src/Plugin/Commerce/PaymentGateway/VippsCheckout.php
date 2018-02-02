<?php

namespace Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_vipps\VippsManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use zaporylie\Vipps\Model\OrderStatus;
use Drupal\commerce_price\Price;

/**
 * Provides the Vipps payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "vipps_checkout",
 *   label = "Vipps Checkout",
 *   display_label = "Vipps Checkout",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_vipps\PluginForm\OffsiteRedirect\VippsCheckoutForm",
 *   },
 * )
 */
class VippsCheckout extends OffsitePaymentGatewayBase implements SupportsAuthorizationsInterface, SupportsRefundsInterface {

  /**
   * Service used for making API calls using Vipps Checkout library.
   *
   * @var \Drupal\commerce_vipps\VippsManager
   */
  protected $vipps;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * VippsCheckout constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\commerce_vipps\VippsManager $vippsManager
   *   The vipps manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, VippsManager $vippsManager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->vipps = $vippsManager;
    $this->logger = $logger;
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('commerce_vipps.payment_manager'),
      $container->get('logger.factory')->get('commerce_vipps')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'environment' => 'test',
      'serial_number' => '',
      'id' => '',
      'token' => '',
      'cert' => '',
      'mobile' => 'field_phone',

    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['id'] = [
      '#type' => 'textfield',
      '#title' => t('ID'),
      '#required' => TRUE,
      '#description' => t('Client ID'),
      '#default_value' => $this->configuration['id'],
    ];
    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => t('Secret'),
      '#required' => TRUE,
      '#description' => t('Client Secret'),
      '#default_value' => $this->configuration['secret'],
    ];
    $form['serial_number'] = [
      '#type' => 'textfield',
      '#title' => t('Serial Number'),
      '#required' => TRUE,
      '#description' => t('Merchant Serial Number'),
      '#default_value' => $this->configuration['serial_number'],
    ];
    $form['subscription_key_authorization'] = [
      '#type' => 'textfield',
      '#title' => t('Subscription Key - Authorization'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['subscription_key_authorization'],
    ];
    $form['subscription_key_payment'] = [
      '#type' => 'textfield',
      '#title' => t('Subscription Key - Payment'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['subscription_key_payment'],
    ];
    $form['mobile'] = [
      '#type' => 'textfield',
      '#title' => t('Mobile phone field'),
      '#required' => TRUE,
      '#description' => t('Which field stores mobile phone number.'),
      '#default_value' => $this->configuration['mobile'],
    ];
    $form['help'] = [
      '#type' => 'textarea',
      '#title' => t('Help text'),
      '#description' => t('This text will be displayed when user needs to login to Vipps app and finalize payment.'),
      '#default_value' => $this->configuration['help'],
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
      $this->configuration['serial_number'] = $values['serial_number'];
      $this->configuration['id'] = $values['id'];
      $this->configuration['secret'] = $values['secret'];
      $this->configuration['subscription_key_authorization'] = $values['subscription_key_authorization'];
      $this->configuration['subscription_key_payment'] = $values['subscription_key_payment'];
      $this->configuration['mobile'] = $values['mobile'];
      $this->configuration['help'] = $values['help'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // Check that we are dealing with a complete payment.
    $payment_id = $order->getData(COMMERCE_VIPPS_CURRENT_TRANSACTION);
    if (!$payment_id) {
      throw new PaymentGatewayException("No current transaction to validate");
    }
    // Check for actual payment.
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = Payment::load($payment_id);
    if (!$payment_id) {
      throw new PaymentGatewayException("No payment found with ID");
    }

    // Check if payment has correct state.
    if ($payment->getRemoteState() == OrderStatus::RESERVE) {
      $payment->setState('reserve');
      $payment->save();
      // At this point, we should have validated the order properly, so that
      // no one gets here without not actually having completed an order.
      // Go on and delete our variable.
      $order->setData(COMMERCE_VIPPS_CURRENT_TRANSACTION, NULL);
      $order->save();
      return;
    }
    // Cancelled orders.
    $cancelled = [
      OrderStatus::VOID,
      OrderStatus::CANCEL,
      OrderStatus::FAILED,
      OrderStatus::REJECTED,
    ];
    // If payment was cancelled.
    if (in_array($payment->getRemoteState(), $cancelled)) {
      $payment->setState('failed');
      $payment->save();
      $order->setData(COMMERCE_VIPPS_CURRENT_TRANSACTION, NULL);
      $order->save();
      throw new PaymentGatewayException('The order was cancelled.');
    }

    // Anything else to this url cancels the payment.
    throw new PaymentGatewayException("Unknown error.");
  }

  /**
   * Validate the request, check for access & object etc.
   *
   * A HTTP_OK here will require the payment page to reload.
   * The only thing this function actually does isto update the external
   * ID for the payment, so that it canbe further processed by onNotify().
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function validateIncomingRequest(Request $request) {
    $storage = $this->entityTypeManager->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $commerce_order */

    // Let's see if we have an order.
    if (!$commerce_order = $storage->load($request->request->get('commerce_order'))) {
      $this->logger->notice(
        $this->t('Notify callback called for an invalid order @order [@values]', [
          '@order' => $request->request->get('commerce_order'),
          '@values' => print_r($request->request->all(), TRUE),
        ])
      );
      return new Response('', Response::HTTP_OK);
    }
    // Attempt to load the payment details.
    $payment = (int) $request->request->get('commerce_payment_id');
    $current_payment = $commerce_order->getData(COMMERCE_VIPPS_CURRENT_TRANSACTION);
    if ($payment != $current_payment) {
      return new Response('', Response::HTTP_OK);
    }
    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    $payment = Payment::load($payment);
    // If no payment could be retrieved, we've encountered errors.
    if (!$payment) {
      $this->logger->error(
        $this->t('No order details returned from vipps to order @order_id', [
          '@order_id' => $commerce_order->id(),
        ])
      );
      // Fail, reload page.
      return new Response('', Response::HTTP_OK);
    }
    return $commerce_order;
  }

  /**
   * {@inheritdoc}
   *
   * Checks for status changes, and saves it.
   */
  public function onNotify(Request $request) {
    // Validate the incoming data.
    /** @var \Drupal\commerce_order\Entity\OrderInterface $commerce_order */
    $commerce_order = $this->validateIncomingRequest($request);

    // First,load our stuff.
    $payment_id = $commerce_order->getData('commerce_vipps_current_transaction');
    $payment = Payment::load($payment_id);

    // Get & set the transaction details.
    $details = $this->vipps->getTransactionDetails($payment);
    $commerce_order->setData('commerce_vipps_transaction_details', $details);
    $commerce_order->save();

    // Fetch the vipps status and save it to payment.
    $remote_status = $this->vipps->getPaymentStatus($payment);
    $remote_state = $remote_status->getTransactionInfo()->getStatus();

    // Set local status.
    $this->vipps->setLocalPaymentStatus($payment);

    // As long as we have initiate status, we'll continue pinging vipps.
    $initiate = OrderStatus::INITIATE;
    if ($remote_state == $initiate) {
      return new Response('', Response::HTTP_PAYMENT_REQUIRED);
    }
    // If we have any other statuses than INITIATE, reload.
    return new Response('', Response::HTTP_OK);
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {

    // Assert things.
    $this->assertPaymentState($payment, ['reserve']);

    // Load current user.
    $account_name = \Drupal::currentUser()->getAccountName();

    // First, check that we can capture the amount.
    $too_high = $amount->compareTo($payment->getAmount()) == 1;
    if ($too_high) {
      throw new PaymentGatewayException("You cannot capture more than total");
    }
    $too_low = $amount->compareTo($payment->getAmount()) == -1;
    if ($too_low) {
      throw new PaymentGatewayException("Per now, VIPPS only supports capturing the entire amount. You will be able to do part refunds later.");
    }

    $amount = $this->toMinorUnits($amount);
    try {
      // First, attempt to capture amount.
      $settings = $payment->getPaymentGateway()->getPluginConfiguration();
      $string = t('Captured by @user', ['@user' => $account_name])->__toString();
      $request_capture = $this->vipps->getClient($settings)
        ->payment($settings['subscription_key_payment'], $settings['serial_number'])
        ->capturePayment($payment->getRemoteId(), $string, $amount);
    }
    catch (\Exception $e) {
      $text = t('Unable to @action transaction: @message', ['@action' => strtolower(t('Capture')), '@message' => $e->getMessage()]);
      throw new PaymentGatewayException($text);
    }
    // Set the captured amount.
    $payment->setState('captured');
    $payment->save();

    // Before we're done here, save the payment history.
    // Fetch the vipps status and save it to payment.
    $order = $payment->getOrder();
    // Get & set the transaction details.
    $details = $this->vipps->getTransactionDetails($payment);
    $order->setData('commerce_vipps_transaction_details', $details);
    $order->save();

    // @todo Post to order history!
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['reserve']);
    // Perform the void request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();
    $account_name = \Drupal::currentUser()->getAccountName();
    $settings = $payment->getPaymentGateway()->getPluginConfiguration();
    $string = t('Canceled by @user', ['@user' => $account_name])->__toString();
    try {
      $request_void = $this->vipps->getClient($settings)
        ->payment($settings['subscription_key_payment'], $settings['serial_number'])
        ->cancelPayment($payment->getRemoteId(), $string);
    }
    catch (\Exception $e) {
      $text = t('Unable to @action transaction: @message', ['@action' => strtolower(t('Cancel')), '@message' => $e->getMessage()]);
      throw new PaymentGatewayException($text);
    }
    // Set the captured amount.
    $payment->setState('cancelled');
    $payment->save();

    // Before we're done here, save the payment history.
    // Fetch the vipps status and save it to payment.
    $order = $payment->getOrder();
    // Get & set the transaction details.
    $details = $this->vipps->getTransactionDetails($payment);
    $order->setData('commerce_vipps_transaction_details', $details);
    $order->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    // Validate.
    $this->assertPaymentState($payment, ['captured', 'partially_refunded']);

    // Let's do some refunds.
    parent::assertRefundAmount($payment, $amount);

    // Fetch a value that vipps understands.
    $amount_int = (string) $this->toMinorUnits($amount);

    // Call the service.
    try {
      $account_name = \Drupal::currentUser()->getAccountName();
      $settings = $payment->getPaymentGateway()->getPluginConfiguration();
      $string = t('Credited by @user', ['@user' => $account_name])->__toString();
      $request_capture = $this->vipps->getClient($settings)
        ->payment($settings['subscription_key_payment'], $settings['serial_number'])
        ->refundPayment($payment->getRemoteId(), $string, $amount_int);
    }
    catch (\Exception $e) {
      // Get & set the transaction details.
      $order = $payment->getOrder();
      $details = $this->vipps->getTransactionDetails($payment);
      $order->setData('commerce_vipps_transaction_details', $details);
      $order->save();

      $text = t('Unable to @action transaction: @message', ['@action' => strtolower(t('Capture')), '@message' => $e->getMessage()]);
      throw new PaymentGatewayException($text);
    }

    // We can now update the payment.
    $refunded_already = $payment->getRefundedAmount();
    $refunded_now = $amount;
    $new_refund_amount = $refunded_already->add($refunded_now);
    $payment->setRefundedAmount($new_refund_amount);

    // Before we can save, check the balance and see if partial or fully refund.
    $new_state = $payment->getBalance()->isZero() ? 'refunded' : 'partially_refunded';
    $payment->setState($new_state);

    // We're done.
    $payment->save();

    // At last, save the vipps payment history.
    $order = $payment->getOrder();
    // Get & set the transaction details.
    $details = $this->vipps->getTransactionDetails($payment);
    $order->setData('commerce_vipps_transaction_details', $details);
    $order->save();
    // Generate a refund logstorage,
    // $this->logStorage->generate($order, 'order_validated')->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $payment_state = $payment->getState()->value;
    $operations = [];
    $operations['capture'] = [
      'title' => $this->t('Capture'),
      'page_title' => $this->t('Receive payment'),
      'plugin_form' => 'capture-payment',
      'access' => $payment_state == 'reserve',
    ];
    $operations['void'] = [
      'title' => $this->t('Void'),
      'page_title' => $this->t('Cancel payment'),
      'plugin_form' => 'void-payment',
      'access' => $payment_state == 'reserve',
    ];
    $operations['refund'] = [
      'title' => $this->t('Refund'),
      'page_title' => $this->t('Refund payment'),
      'plugin_form' => 'refund-payment',
      'access' => in_array($payment_state, ['captured', 'partially_refunded']),
    ];
    return $operations;
  }

}
