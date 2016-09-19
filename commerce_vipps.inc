<?php

/**
 * @file
 * Payment callbacks and helper functions.
 */


/**
 * Payment method callback: settings form.
 */
function commerce_vipps_settings_form($settings = array()) {
  $settings += _commerce_vipps_default_settings();
  $form = array();
  $form['serial_number'] = array(
    '#type' => 'textfield',
    '#title' => t('Serial Number'),
    '#required' => TRUE,
    '#description' => t('Merchant Serial Number'),
    '#default_value' => $settings['serial_number'],
  );
  $form['id'] = array(
    '#type' => 'textfield',
    '#title' => t('ID'),
    '#required' => TRUE,
    '#description' => t('Merchant ID'),
    '#default_value' => $settings['id'],
  );
  $form['token'] = array(
    '#type' => 'textfield',
    '#title' => t('Token'),
    '#required' => TRUE,
    '#description' => t('Merchant Token'),
    '#default_value' => $settings['token'],
  );
  $form['cert'] = array(
    '#type' => 'textfield',
    '#title' => t('Certificate'),
    '#required' => TRUE,
    '#description' => t('Absolute path to certificate file. Certificate should not be public!'),
    '#default_value' => $settings['cert'],
    '#element_validate' => array('commerce_vipps_element_validate_file'),
  );
  $form['base_uri'] = array(
    '#type' => 'textfield',
    '#title' => t('Base URI'),
    '#description' => t('Alternatively you can add base URI to test server'),
    '#default_value' => $settings['base_uri'],
  );
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
  // Should never happen but catch it anyway.
  if (empty($order->data['commerce_vipps']['current_transaction']) || !($transaction = commerce_payment_transaction_load($order->data['commerce_vipps']['current_transaction']))) {
    watchdog('commerce_vipps', 'Missing current transaction id for order @order', array('@order' => $order->order_id), WATCHDOG_CRITICAL);
    return FALSE;
  }

  $status = _commerce_vipps_get_status_transaction($transaction);
  if ($status) {

  }

  $transaction->status = COMMERCE_PAYMENT_STATUS_FAILURE;
  commerce_payment_transaction_save($transaction);
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
  $settings = commerce_payment_method_instance_load($transaction->instance_id) + _commerce_vipps_default_settings();
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
  $settings = commerce_payment_method_instance_load($transaction->instance_id) + _commerce_vipps_default_settings();
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
  $settings = commerce_payment_method_instance_load($transaction->instance_id) + _commerce_vipps_default_settings();
  // @todo: Do request here, in case of error throw an exception.

  if (!$skip_save) {
    commerce_payment_transaction_save($transaction);
  }
}

/**
 * Get transaction status.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 *
 * @throws Exception
 */
function _commerce_vipps_get_status_transaction($transaction) {
  $settings = commerce_payment_method_instance_load($transaction->instance_id) + _commerce_vipps_default_settings();
  $vipps = _commerce_vipps_get_client($settings);
  $payment = $vipps->payments();
  return $payment->setOrderID($transaction->remote_id)->getStatus();
}

/**
 * Get transaction details.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 *
 * @throws Exception
 */
function _commerce_vipps_get_details_transaction($transaction) {
  $settings = commerce_payment_method_instance_load($transaction->instance_id) + _commerce_vipps_default_settings();
  $vipps = _commerce_vipps_get_client($settings);
  $payment = $vipps->payments();
  return $payment->setOrderID($transaction->remote_id)->getDetails();
}

/**
 * @param $settings
 * @return /Vipps/Vipps
 */
function _commerce_vipps_get_client($settings) {
  $options = array(
    'cert' => [
      $settings['cert'],
      'test',
    ]
  );
  if ($settings['base_uri']) {
    $options['base_uri'] = $settings['base_uri'];
  }
  $client = new \GuzzleHttp\Client($options);
  $vipps = new \Vipps\Vipps($client);
  return $vipps->setToken($settings['token'])->setMerchantSerialNumber($settings['serial_number'])->setMerchantID($settings['id']);
}

/**
 * Default settings.
 *
 * @return array
 */
function _commerce_vipps_default_settings() {
  return array(
    'serial_number' => '',
    'id' => '',
    'token' => '',
    'cert' => '',
    'base_uri' => '',
  );
}
