<?php

namespace Drupal\commerce_vipps;

use zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails;
use zaporylie\Vipps\Model\OrderStatus;
use zaporylie\Vipps\Client;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Url;
use zaporylie\Vipps\Exceptions\VippsException;
use zaporylie\Vipps\Model\Error\ErrorInterface;
use zaporylie\Vipps\Vipps;
use Http\Adapter\Guzzle6\Client as GuzzleAdapterClient;
use GuzzleHttp\Client as GuzzleClient;
use zaporylie\Vipps\Client as VippsClient;

/**
 * Class VippsManager.
 *
 * @package Drupal\commerce_vipps
 */
class VippsManager {

  const VIPPS_BASE_URL = '';
  const VIPPS_BASE_URL_TEST = '';

  /**
   * Get payment gateway configuration.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return array
   *   Plugin configuration.
   */
  protected function getPluginConfiguration(OrderInterface $order) {
    $payment_gateway = $order->payment_gateway->entity;
    /** @var \Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway\VippsCheckout $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    return $payment_gateway_plugin->getConfiguration();
  }

  /**
   * Init a vipps transaction.
   *
   * Will return the payment entity with the results.
   * This function may throw checkout errors.
   *
   * @param \Drupal\commerce_payment\Entity\Payment $payment
   *   The order.
   * @param string $number
   *   The number.
   * @param array $settings
   *   The settings (usually from the payment interface itself.)
   *
   * @return \Drupal\commerce_payment\Entity\Payment
   *   The payment.
   */
  public function initVippsPayment(Payment $payment, $number, array $settings) {
    $order = $payment->getOrder();
    if (empty($number)) {
      throw new PaymentGatewayException('Missing phone value');
    }
    $vipps = $this->getClient($settings, $settings['mode']);

    // Initiate vipps payment. Generate things we need.
    try {
      $vipps_payment = $vipps->payment($settings['subscription_key_payment'], $settings['serial_number']);
    }
    catch (\Exception $e) {
      $this->throwCheckoutExceptions($e, $order);
    }
    $internal_url = Url::fromRoute('commerce_payment.notify', ['commerce_payment_gateway' => $payment->getPaymentGatewayId()]);
    $uri = $internal_url->setAbsolute()->toString();

    // Generate a semi-random remote ID.
    $generated_external_id = 'O' . $order->id() . 'T' . rand(100, 999);
    $payment->setRemoteId($generated_external_id);

    // Retrieve a payload.
    try {
      /** @var \zaporylie\Vipps\Model\Payment\ResponseInitiatePayment $payload */
      $payload = $vipps_payment->initiatePayment(
        $payment->getRemoteId(),
        (int) $number,
        (int) round($payment->getAmount()->getNumber(), 2) * 100,
        t('Payment for order %order_id', ['@order_id' => $order->getOriginalId()])->__toString(),
        $uri
      );
    }
    catch (\Exception $e) {
      $this->throwCheckoutExceptions($e, $order);
    }

    // First, dump the payload to the order, primarily for debug purposes.
    $order->setData('commerce_vipps_current_payload', $payload);
    $order->save();

    // Now save the external order ID & status, regardless of what it is.
    $status = $payload->getTransactionInfo()->getStatus();
    $payment->setRemoteState($payload->getTransactionInfo()->getStatus());
    $payment->setRemoteId($payload->getOrderId());
    return $payment;
  }

  /**
   * Load the client.
   *
   * @param mixed $settings
   *   The settings array.
   *
   * @return \zaporylie\Vipps\VippsInterface
   *   The vipps client.
   */
  public function getClient($settings, $environment = 'test') {
    // Initiate client.
    $httpClient = new GuzzleAdapterClient(new GuzzleClient());
    $client = new VippsClient($settings['id'], [
      'http_client' => $httpClient,
      'endpoint' => $environment,
      'token_storage' => new CacheTokenStorage(),
    ]);
    $vipps = new Vipps($client);
    if (!$client->getTokenStorage()->has()) {
      $token = $vipps->authorization($settings['subscription_key_authorization'])->getToken($settings['secret']);
      $client->getTokenStorage()->set($token);
    }
    return $vipps;
  }

  /**
   * Get return url for given type and checkout step.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $type
   *   Return type.
   * @param string $step
   *   Step id.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   Return absolute return url.
   */
  protected function getReturnUrl(OrderInterface $order, $type, $step = 'payment') {
    $arguments = [
      'commerce_order' => $order->id(),
      'step' => $step,
      'commerce_payment_gateway' => 'vipps_checkout',
    ];
    $url = new Url($type, $arguments, [
      'absolute' => TRUE,
    ]);

    return $url->toString();
  }

  /**
   * Get payment's transaction status.
   *
   * @param \Drupal\commerce_payment\Entity\Payment $payment
   *   Commerce Payment Transaction.
   *
   * @return \zaporylie\Vipps\Model\Payment\ResponseGetOrderStatus
   *   Payment status.
   *
   * @throws \Exception
   */
  public function getPaymentStatus($payment) {
    $order = $payment->getOrder();
    $settings = $order->payment_gateway->entity->get('configuration');
    $mode = $settings['mode'];
    $vipps = $this->getClient($settings, $mode);
    $status = $vipps
      ->payment($settings['subscription_key_payment'], $settings['serial_number'])
      ->getOrderStatus($payment->getRemoteId());
    $payment->setRemoteState($status->getTransactionInfo()->getStatus());
    $payment->save();
    return $status;
  }

  /**
   * Get transaction details.
   *
   * @param \Drupal\commerce_payment\Entity\Payment $payment
   *   Commerce Payment Transaction.
   *
   * @return \zaporylie\Vipps\Model\Payment\ResponseGetPaymentDetails
   *   Payment details.
   *
   * @throws \Exception
   *
   * @todo: Check what's affected by new return type.
   */
  public function getTransactionDetails($payment) {
    $order = $payment->getOrder();
    $settings = $order->payment_gateway->entity->get('configuration');
    $mode = $settings['mode'];
    $vipps = $this->getClient($settings, $mode);

    $vipps = $vipps->payment($settings['subscription_key_payment'], $settings['serial_number']);
    $details = $vipps->getPaymentDetails($payment->getRemoteId());
    return $details;
  }

  /**
   * Set local status for payment based on remote.
   *
   * @param \Drupal\commerce_payment\Entity\Payment $payment
   *   Commerce Payment $payment.
   */
  public function setLocalPaymentStatus($payment) {
    switch ($payment->getRemoteState()) {
      case OrderStatus::VOID:
      case OrderStatus::CANCEL:
      case OrderStatus::FAILED:
      case OrderStatus::REJECTED:
        $payment->setState('failed');
        break;

      case OrderStatus::INITIATE:
      case OrderStatus::REGISTER:
        $payment->setState('initiate');
        break;

      case OrderStatus::RESERVE:
        if ($payment->getState() == COMMERCE_VIPPS_STATUS_REGISTERED) {
          $payment->setState('pending');
        }
        elseif ($payment->payload instanceof ResponseGetPaymentDetails && $payment->payload->getTransactionSummary()->getRemainingAmountToRefund() == 0 && $payment->payload->getTransactionSummary()->getRemainingAmountToCapture() == 0) {
          $payment->setState('failed');
        }
        break;
    }
    $payment->save();
  }

  /**
   * Throw exceptions based on whatever we receive.
   *
   * @param \Exception $e
   *   The exception.
   * @param \Drupal\commerce_order\Entity\Order $order
   *   The order.
   */
  public function throwCheckoutExceptions(\Exception $e, OrderInterface $order) {
    // Log exception.
    watchdog_exception('commerce_vipps', $e);
    // Before any errors, let's make sure we have deleted our variable.
    $order->setData(COMMERCE_VIPPS_CURRENT_TRANSACTION, NULL);
    $order->save();
    // Some of the errors should be displayed to customer.
    if ($e instanceof VippsException && $e->getError() instanceof ErrorInterface && $e->getError()->getCode() == 81) {
      drupal_set_message(t('Unable to process payment: @message', ['@message' => t('User not registered with VIPPS')]), 'error');
      throw new PaymentGatewayException('User not registered with VIPPS', 0, $e);
    }
    // User's app is unsupported.
    elseif ($e instanceof VippsException && $e->getError() instanceof ErrorInterface && $e->getError()->getCode() == 82) {
      drupal_set_message(t('Unable to process payment: @message', ['@message' => t('User App Version is not supported')]), 'error');
      throw new PaymentGatewayException('User App Version is not supported', 0, $e);
    }
    // Some of the errors should be displayed to customer.
    elseif ($e instanceof \UnexpectedValueException) {
      drupal_set_message(t('Unable to process payment due to validation errors'), 'error');
      throw new PaymentGatewayException('Unable to process payment due to validation errors', 0, $e);
    }
    else {
      drupal_set_message(t('Unfortunately VIPPS is experiencing technical difficulties at the moment. Try again later or choose another payment method.'), 'error');
      throw new PaymentGatewayException('Unable to process payment due to unkown error', 0, $e);
    }
  }

}
