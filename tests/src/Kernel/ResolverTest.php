<?php

namespace Drupal\Tests\commerce_vipps\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Class ResolverTest.
 *
 * @package Drupal\Tests\commerce_vipps\Kernel
 * @group commerce_vipps
 */
class ResolverTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce',
    'commerce_price',
    'commerce_order',
    'commerce_payment',
    'commerce_vipps',
  ];

  /**
   * The payment entity.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * Order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
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
    $paymentGateway = $this->reloadEntity($payment_gateway);

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
  }

  /**
   * Tests Vipps Order ID resolver.
   */
  public function testOrderIdResolver() {
    // Test default resolver.
    $resolver = $this->container->get('commerce_vipps.chain_order_id_resolver');
    $this->assertNotEqual($resolver->resolve($this->payment), $resolver->resolve($this->payment));

    // Test custom resolver.
    $this->enableModules(['commerce_vipps_test_resolvers']);
    $resolver = $this->container->get('commerce_vipps.chain_order_id_resolver');
    $this->assertEqual($resolver->resolve($this->payment), $this->payment->getOrderId());
  }

}
