<?php

namespace Drupal\Tests\commerce_vipps\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Class PartialCaptureTest.
 *
 * @package Drupal\Tests\commerce_vipps\Kernel
 * @group commerce_vipps
 */
class CaptureTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce',
    'commerce_price',
    'commerce_order',
    'commerce_payment',
    'commerce_vipps',
    'commerce_vipps_test_client',
  ];

  /**
   * The payment gateway entity.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   */
  protected $paymentGateway;

  /**
   * The user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The order entity.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The payment entity.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig('commerce_payment');

    $user = $this->createUser();
    $this->user = $this->reloadEntity($user);

    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->import('NOK');
    $this->store = $this->createStore('Default store', 'admin@example.com', 'online', TRUE, 'NO', 'NOK');

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = PaymentGateway::create([
      'id' => 'commerce_vipps',
      'label' => 'Commerce Vipps',
      'plugin' => 'vipps',
    ]);
    $payment_gateway->setPluginConfiguration([
      'client_id' => '123',
      'subscription_key_authorization' => '456',
      'client_secret' => '789',
      'subscription_key_payment' => '123',
      'serial_number' => '456',
    ]);
    $payment_gateway->save();
    $this->paymentGateway = $this->reloadEntity($payment_gateway);

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('10', 'NOK'),
    ]);
    $order_item->save();

    $order = Order::create([
      'uid' => $this->user,
      'type' => 'default',
      'state' => 'draft',
      'order_items' => [$order_item],
      'store_id' => $this->store,
    ]);
    $order->save();
    $this->order = $this->reloadEntity($order);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = Payment::create([
      'type' => 'payment_default',
      'payment_gateway' => $payment_gateway->id(),
      'order_id' => $this->order->id(),
      'amount' => new Price('10', 'NOK'),
      'state' => 'authorization',
    ]);
    $payment->save();
    $this->payment = $this->reloadEntity($payment);
    $count = $this->entityTypeManager->getStorage('commerce_payment')->getQuery()->condition('order_id', $this->order->id())->count()->execute();
    $this->assertEqual($count, 1);
  }

  /**
   * Performs full capture (on amount).
   */
  public function testFullCapture() {
    /** @var \Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway\Vipps $plugin */
    $plugin = $this->paymentGateway->getPlugin();
    $plugin->capturePayment($this->payment, Price::fromArray(['number' => 10, 'currency_code' => 'NOK']));
    $count = $this->entityTypeManager->getStorage('commerce_payment')->getQuery()->condition('order_id', $this->order->id())->count()->execute();
    $this->assertEqual($count, 1);
    $this->assertEqual($this->payment->getState()->getId(), 'completed');
  }

  /**
   * Performs partial capture.
   */
  public function testPartialCapture() {
    // Perform partial capture on amount.
    /** @var \Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway\Vipps $plugin */
    $plugin = $this->paymentGateway->getPlugin();
    $plugin->capturePayment($this->payment, Price::fromArray(['number' => 6, 'currency_code' => 'NOK']));
    $count = $this->entityTypeManager->getStorage('commerce_payment')->getQuery()->condition('order_id', $this->order->id())->count()->execute();
    $this->assertEqual($count, 2);
    $this->assertEqual($this->payment->getAmount()->getNumber(), 4);
    $this->assertEqual($this->payment->getState()->getId(), 'authorization');
    $this->assertEqual($this->entityTypeManager->getStorage('commerce_payment')->getQuery()->condition('order_id', $this->order->id())->condition('state', 'completed')->count()->execute(), 1);

    // Perform capture on remaining amount.
    /** @var \Drupal\commerce_vipps\Plugin\Commerce\PaymentGateway\Vipps $plugin */
    $plugin = $this->paymentGateway->getPlugin();
    $plugin->capturePayment($this->payment);
    $count = $this->entityTypeManager->getStorage('commerce_payment')->getQuery()->condition('order_id', $this->order->id())->count()->execute();
    $this->assertEqual($count, 2);
    $this->assertEqual($this->payment->getAmount()->getNumber(), 4);
    $this->assertEqual($this->payment->getState()->getId(), 'completed');
  }

}
