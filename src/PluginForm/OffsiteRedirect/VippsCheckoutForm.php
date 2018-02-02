<?php

namespace Drupal\commerce_vipps\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_payment\Entity\Payment;
use zaporylie\Vipps\Model\OrderStatus;

/**
 * Class VippsCheckoutForm.
 *
 * Handles the initiation of vipps payments.
 */
class VippsCheckoutForm extends BasePaymentOffsiteForm {

  /**
   * The vipps service.
   *
   * @var \Drupal\commerce_vipps\VippsManager
   */
  protected $vipps;

  /**
   * Class constructor.
   */
  public function __construct() {
    $container = \Drupal::getContainer();
    $this->vipps = $container->get('commerce_vipps.payment_manager');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('commerce_vipps.payment_manager')

    );
  }

  /**
   * Get the phone number.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   *
   * @return string
   *   The number.
   *
   * @throws \Exception
   */
  private function getPhoneNumber(\Drupal\commerce_payment\Entity\PaymentInterface $payment) {
    $settings = $payment->getPaymentGateway()->getPluginConfiguration();
    $order = $payment->getOrder();
    $mobile_field_name = $settings['mobile'];
    /** @var \Drupal\profile\Entity\Profile $profile */
    $profile = $order->getBillingProfile();
    $mobile_field = $profile->{$mobile_field_name};
    if (empty($mobile_field)) {
      throw new \Exception('Missing phone field on billing profile.');
    }
    return $this->sanitizeNumber($mobile_field->getString());
  }

  /**
   * Sanitize.
   *
   * @param int|string $number
   *   The number.
   *
   * @return string
   *   The sanitized number.
   */
  private function sanitizeNumber($number) {
    // Remove everything what's not an integer.
    return preg_replace("/[^0-9]/", "", $number);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    // When dumping here, we have a new entity, use that by default.
    $payment = $this->entity;

    // Check if we have one that we already started on.
    $order = $payment->getOrder();
    $id = $order->getData(COMMERCE_VIPPS_CURRENT_TRANSACTION);
    if (!empty($id)) {
      $possible_payment = Payment::load($id);
      if ($possible_payment) {
        // $possible_payment->delete();
        $payment = $possible_payment;
      }
    }

    $is_initiated = !empty($payment->getRemoteId());
    $current_remote_state = $payment->getRemoteState();
    $vaild_for_checkout = [
      OrderStatus::INITIATE,
    ];
    // Any order not in initiate state, we submit for process and redirect.
    $is_somehow_processed = !in_array($current_remote_state, $vaild_for_checkout);
    $remote_is_set = !empty($current_remote_state);
    if ($remote_is_set && $is_somehow_processed) {
      return $this->buildBasicForm($form, $form_state, $payment);
    }

    // If already sent and accepted, return form without doing anything.
    $is_already_sent = in_array($current_remote_state, $vaild_for_checkout);
    if ($is_initiated && $is_already_sent) {
      return $this->buildBasicForm($form, $form_state, $payment);
    }
    // Generate the phone number. We do this only here.
    $phone = $this->getPhoneNumber($payment);

    // We have no valid vipps communication yet, so we initiate a new request.
    $settings = $payment->getPaymentGateway()->getPluginConfiguration();
    try {
      $payment = $this->vipps->initVippsPayment($payment, $phone, $settings);
    }
    catch (\Exception $e) {
      $this->vipps->throwCheckoutExceptions($e, $order);
    }

    // Now, let's check our remote status.
    // If we have a good status, we can continue.
    $current_remote_state = $payment->getRemoteState();
    if ($current_remote_state == OrderStatus::INITIATE) {
      return $this->buildBasicForm($form, $form_state, $payment);
    }
    // If somehow Vipps sent us a weird error unlike normal error handling.
    throw new PaymentGatewayException('Unable to process payment due to unknown error');
  }

  /**
   * Returns the basic form that we have.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\commerce_payment\Entity\Payment $payment
   *   The payment.
   *
   * @return array
   *   The form.
   */
  public function buildBasicForm(array $form, FormStateInterface $form_state, Payment $payment) {
    // Save the payment & order before we continue.
    $payment->save();
    $order = $payment->getOrder();
    $order->setData(COMMERCE_VIPPS_CURRENT_TRANSACTION, $payment->id());
    $order->save();
    // Attempt to add
    // Embed snippet to plugin form (no redirect needed).
    $form['vipps'] = [
      '#theme' => 'commerce_vipps_payment_page',
    ];
    $form['vipps_check'] = [
      '#type' => 'button',
    ];
    return $form;
  }

  /**
   * Builds the URL to the "return" page.
   *
   * @return \Drupal\Core\Url
   *   The "return" page URL.
   */
  protected function buildReturnUrl($order_id) {
    return Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order_id,
      'step' => 'payment',
    ]);
  }

}
