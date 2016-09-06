<?php

/**
 * @file
 * Payment callbacks and helper functions.
 */


/**
 * Payment method callback: settings form.
 */
function commerce_vipps_settings_form($settings = array()) {
  $form = array();
  return $form;
}

/**
 * Payment method callback: submit form.
 */
function commerce_vipps_submit_form($payment_method, $pane_values, $checkout_pane, $order) {
  $form = array();
  return $form;
}

/**
 * Payment method callback: submit form submission.
 */
function commerce_vipps_submit_form_submit($payment_method, $pane_form, $pane_values, $order, $charge) {
  $order->data['commerce_vipps']['pane_values'] = $pane_values;
}

/**
 * Payment method callback: redirect form.
 */
function commerce_vipps_redirect_form($form, &$form_state, $order, $payment_method) {

  $charge = commerce_payment_order_balance($order);
  $transaction = commerce_payment_transaction_new('commerce_vipps', $order->order_id);
  $transaction->instance_id = $payment_method['instance_id'];
  $transaction->amount = $charge['amount'];
  $transaction->currency_code = $charge['currency_code'];
  $transaction->status = COMMERCE_PAYMENT_STATUS_PENDING;

  if (commerce_payment_transaction_save($transaction)) {
    if (empty($order->data['commerce_vipps']['current_transaction']) || ($transaction->transaction_id != $order->data['commerce_vipps']['current_transaction'])) {
      $order->data['commerce_vipps']['current_transaction'] = $transaction->transaction_id;
      commerce_order_save($order);
    }
    $form['#action'] = url('checkout/' . $order->order_id . '/payment/return/' . $order->data['payment_redirect_key']);
  }
  else {
    $form['#action'] = url('checkout/' . $order->order_id . '/payment/back/' . $order->data['payment_redirect_key']);
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Proceed to Vipps'),
  );
  return $form;
}

/**
 * Payment method callback: redirect form return validation.
 */
function commerce_vipps_redirect_form_validate($order, $payment_method) {
  // @todo: Process transaction.

  return FALSE;
}


/**
 * Cancel transaction.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 * @param bool $skip_save
 *   (optional) Skip transaction save.
 *
 * @throws Exception
 */
function _commerce_vipps_cancel_transaction($transaction, $skip_save = FALSE) {

  // @todo: Do request here, in case of error throw an exception.

  if (!$skip_save) {
    commerce_payment_transaction_save($transaction);
  }
}

/**
 * Capture transaction.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 * @param int|null $amount
 *   (optional) Capture amount.
 * @param bool $skip_save
 *   (optional) Skip transaction save.
 *
 * @throws Exception
 */
function _commerce_vipps_capture_transaction($transaction, $amount = NULL, $skip_save = FALSE) {
  if (!isset($amount)) {
    $amount = $transaction->amount;
  }

  // @todo: Do request here, in case of error throw an exception.

  if (!$skip_save) {
    commerce_payment_transaction_save($transaction);
  }
}

/**
 * Credit transaction.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 * @param int|null $amount
 *   (optional) Credit amount.
 * @param bool $skip_save
 *   (optional) Skip transaction save.
 * 
 * @throws Exception
 */
function _commerce_vipps_credit_transaction($transaction, $amount = NULL, $skip_save = FALSE) {
  if (!isset($amount)) {
    $amount = $transaction->amount;
  }

  // @todo: Do request here, in case of error throw an exception.

  if (!$skip_save) {
    commerce_payment_transaction_save($transaction);
  }
}
