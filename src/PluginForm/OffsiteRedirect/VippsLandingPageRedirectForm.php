<?php

namespace Drupal\commerce_vipps\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_payment\Entity\Payment;
use zaporylie\Vipps\Client;
use zaporylie\Vipps\Endpoint;
use zaporylie\Vipps\Model\OrderStatus;
use zaporylie\Vipps\Vipps;

/**
 * Class VippsCheckoutForm.
 *
 * Handles the initiation of vipps payments.
 */
class VippsLandingPageRedirectForm extends BasePaymentOffsiteForm {

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
    $payment->setRemoteId(uniqid($settings['prefix']));

    // Save order.
    $order = $payment->getOrder();
    $order_changed = FALSE;
    if ($order->getData('vipps_auth_key') === NULL) {
      $order->setData('vipps_auth_key', $this->generateAuthToken());
      $order_changed = TRUE;
    }

    if ($order->getData('vipps_current_transaction') !== $payment->getRemoteId()) {
      $order->setData('vipps_current_transaction', $payment->getRemoteId());
      $order_changed = TRUE;
    }

    try {
      $url = \Drupal::service('commerce_vipps.manager')
        ->getPaymentManager($plugin)
        ->initiatePayment(
          $payment->getRemoteId(),
          (int) $payment->getAmount()->multiply(100)->getNumber(),
          $this->t('Payment for order @order_id', ['@order_id' => $payment->getOrderId()]),
          // Get standard payment notification callback and add
          rtrim($plugin->getNotifyUrl()->toString(), '/') . '/' . $payment->getOrderId(),
          $form['#return_url'],
          [
            'authToken' => $order->getData('vipps_auth_key'),
          ]
        )
        ->getURL();
    }
    catch (\Exception $exception) {
      throw new PaymentGatewayException();
    }

    // If the payment was successfully created at remote host
    $payment->save();
    if ($order_changed === TRUE) {
      $order->save();
    }

    return $this->buildRedirectForm($form, $form_state, $url, []);
  }

  /**
   * Method to generate access token.
   *
   * @return string
   */
  private function generateAuthToken() {
    try {
      $randomStr = random_bytes(16);
    } catch (\Exception $e) {
      $randomStr = uniqid('', true);
    }
    return bin2hex($randomStr);
  }

}
