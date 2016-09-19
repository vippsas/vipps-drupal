<?php

/**
 * @file
 * Admin UI for Commerce Payment Transactions.
 */

/**
 * Cancel form.
 */
function commerce_vipps_admin_get($form, &$form_state, $transaction, $order) {
  $uri = commerce_payment_ui_payment_transaction_uri($transaction);
  $form['order'] = array(
    '#type' => 'value',
    '#value' => $order,
  );
  $form['transaction'] = array(
    '#type' => 'value',
    '#value' => $transaction,
  );
  return confirm_form($form, t('Are you sure you want to %action transaction %transaction.', array('%action' => strtolower(t('Synchronize')), '%transaction' => $transaction->transaction_id)), $uri['path']);
}

/**
 * Cancel form: submit handler.
 */
function commerce_vipps_admin_get_submit($form, &$form_state) {
  $transaction = $form_state['values']['transaction'];
  $order = $form_state['values']['order'];
  try {
    module_load_include('inc', 'commerce_vipps');
    _commerce_vipps_get_details_transaction($transaction);
    _commerce_vipps_get_status_transaction($transaction);
    commerce_payment_transaction_save($transaction);
    drupal_set_message(t('Transaction has been successfully %action.', array('%action' => strtolower(t('Synchronized')))));
    $uri = commerce_payment_ui_payment_transaction_uri($transaction);
    $form_state['redirect'] = $uri['path'];
  }
  catch (Exception $e) {
    drupal_set_message(t('Unable to %action transaction: %message', array('%action' => strtolower(t('Synchronize')), '%message' => $e->getMessage())), 'error');
    $form_state['rebuild'] = TRUE;
  }
}

/**
 * Cancel form.
 */
function commerce_vipps_admin_cancel($form, &$form_state, $transaction, $order) {
  $uri = commerce_payment_ui_payment_transaction_uri($transaction);
  $form['order'] = array(
    '#type' => 'value',
    '#value' => $order,
  );
  $form['transaction'] = array(
    '#type' => 'value',
    '#value' => $transaction,
  );
  return confirm_form($form, t('Are you sure you want to %action transaction %transaction.', array('%action' => strtolower(t('Cancel')), '%transaction' => $transaction->transaction_id)), $uri['path']);
}

/**
 * Cancel form: submit handler.
 */
function commerce_vipps_admin_cancel_submit($form, &$form_state) {
  $transaction = $form_state['values']['transaction'];
  $order = $form_state['values']['order'];
  try {
    module_load_include('inc', 'commerce_vipps');
    _commerce_vipps_cancel_transaction($transaction);
    drupal_set_message(t('Transaction has been successfully %action.', array('%action' => strtolower(t('Canceled')))));
    $uri = commerce_payment_ui_payment_transaction_uri($transaction);
    $form_state['redirect'] = $uri['path'];
  }
  catch (Exception $e) {
    drupal_set_message(t('Unable to %action transaction: %message', array('%action' => strtolower(t('Cancel')), '%message' => $e->getMessage())), 'error');
    $form_state['rebuild'] = TRUE;
  }
}

/**
 * Capture form.
 */
function commerce_vipps_admin_capture($form, &$form_state, $transaction, $order) {
  $uri = commerce_payment_ui_payment_transaction_uri($transaction);
  $form['order'] = array(
    '#type' => 'value',
    '#value' => $order,
  );
  $form['transaction'] = array(
    '#type' => 'value',
    '#value' => $transaction,
  );
  $form['amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Amount'),
    '#default_value' => commerce_currency_amount_to_decimal($transaction->amount, $transaction->currency_code),
    '#suffix' => $transaction->currency_code,
    '#element_validate' => array('element_validate_integer_positive'),
  );
  return confirm_form($form, t('Are you sure you want to %action transaction %transaction.', array('%action' => strtolower(t('Capture')), '%transaction' => $transaction->transaction_id)), $uri['path']);
}

/**
 * Capture form: submit handler.
 */
function commerce_vipps_admin_capture_submit($form, &$form_state) {
  $transaction = $form_state['values']['transaction'];
  $order = $form_state['values']['order'];
  $amount = commerce_currency_decimal_to_amount($form_state['values']['amount'], $transaction->currency_code);
  try {
    module_load_include('inc', 'commerce_vipps');
    _commerce_vipps_capture_transaction($transaction, $amount);
    drupal_set_message(t('Transaction has been successfully %action.', array('%action' => strtolower(t('Captured')))));
    $uri = commerce_payment_ui_payment_transaction_uri($transaction);
    $form_state['redirect'] = $uri['path'];
  }
  catch (Exception $e) {
    drupal_set_message(t('Unable to %action transaction: %message', array('%action' => strtolower(t('Capture')), '%message' => $e->getMessage())), 'error');
    $form_state['rebuild'] = TRUE;
  }
}

/**
 * Capture form.
 */
function commerce_vipps_admin_credit($form, &$form_state, $transaction, $order) {
  $uri = commerce_payment_ui_payment_transaction_uri($transaction);
  $form['order'] = array(
    '#type' => 'value',
    '#value' => $order,
  );
  $form['transaction'] = array(
    '#type' => 'value',
    '#value' => $transaction,
  );
  $form['amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Amount'),
    '#default_value' => commerce_currency_amount_to_decimal($transaction->amount, $transaction->currency_code),
    '#suffix' => $transaction->currency_code,
    '#element_validate' => array('element_validate_integer_positive'),
  );
  return confirm_form($form, t('Are you sure you want to %action transaction %transaction.', array('%action' => strtolower(t('Credit')), '%transaction' => $transaction->transaction_id)), $uri['path']);
}

/**
 * Credit form: submit handler.
 */
function commerce_vipps_admin_credit_submit($form, &$form_state) {
  $transaction = $form_state['values']['transaction'];
  $order = $form_state['values']['order'];
  $amount = commerce_currency_decimal_to_amount($form_state['values']['amount'], $transaction->currency_code);
  try {
    module_load_include('inc', 'commerce_vipps');
    _commerce_vipps_credit_transaction($transaction, $amount);
    drupal_set_message(t('Transaction has been successfully %action.', array('%action' => strtolower(t('Credited')))));
    $uri = commerce_payment_ui_payment_transaction_uri($transaction);
    $form_state['redirect'] = $uri['path'];
  }
  catch (Exception $e) {
    drupal_set_message(t('Unable to %action transaction: %message', array('%action' => strtolower(t('Credit')), '%message' => $e->getMessage())), 'error');
    $form_state['rebuild'] = TRUE;
  }
}
