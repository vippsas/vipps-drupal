<?php

namespace Drupal\commerce_vipps_test_client;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use Drupal\commerce_vipps\VippsManagerInterface;
use zaporylie\Vipps\Api\PaymentInterface;

/**
 * Mock of a VippsManager.
 *
 * @package Drupal\commerce_vipps_test_client
 */
class VippsManager implements VippsManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getPaymentManager(PaymentGatewayInterface $paymentGateway) {
    return new class implements PaymentInterface {

      /**
       * {@inheritdoc}
       */
      public function initiatePayment(
        $order_id,
        $amount,
        $text,
        $callbackPrefix,
        $fallback,
        $options = []
      ) {
        throw new \RuntimeException('Method is not implemented');
      }

      /**
       * {@inheritdoc}
       */
      public function capturePayment($order_id, $text, $amount = 0) {
        return 'ok';
      }

      /**
       * {@inheritdoc}
       */
      public function cancelPayment($order_id, $text) {
        throw new \RuntimeException('Method is not implemented');
      }

      /**
       * {@inheritdoc}
       */
      public function refundPayment($order_id, $text, $amount = 0) {
        throw new \RuntimeException('Method is not implemented');
      }

      /**
       * {@inheritdoc}
       */
      public function getOrderStatus($order_id) {
        throw new \RuntimeException('Method is not implemented');
      }

      /**
       * {@inheritdoc}
       */
      public function getPaymentDetails($order_id) {
        throw new \RuntimeException('Method is not implemented');
      }

    };
  }

}
