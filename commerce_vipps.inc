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
    '#description' => t('Absolute path to certificate file. Certificate should not be public! If your certificate requires password add it after colon char, ex. vipps.p12:password'),
    '#default_value' => $settings['cert'],
    '#element_validate' => array('commerce_vipps_element_validate_file'),
  );
  $form['mobile'] = array(
    '#type' => 'select',
    '#title' => t('Mobile phone value'),
    '#required' => TRUE,
    '#description' => t('Which field stores mobile phone number.'),
    '#default_value' => $settings['mobile'],
    '#options' => _commerce_vipps_phone_number_properties(),
  );
  $form['base_uri'] = array(
    '#type' => 'textfield',
    '#title' => t('Base URI'),
    '#description' => t('Alternatively you can add base URI to test server'),
    '#default_value' => $settings['base_uri'],
  );
  $form['help'] = array(
    '#type' => 'textarea',
    '#title' => t('Help text'),
    '#description' => t('This text will be displayed when user needs to login to Vipps app and finalize payment.'),
    '#default_value' => $settings['help'],
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

  global $base_url;
  $charge = commerce_payment_order_balance($order);
  $transaction = commerce_payment_transaction_new('commerce_vipps', $order->order_id);
  $transaction->instance_id = $payment_method['instance_id'];
  $transaction->amount = $charge['amount'];
  $transaction->currency_code = $charge['currency_code'];
  $transaction->status = COMMERCE_PAYMENT_STATUS_PENDING;
  commerce_payment_transaction_save($transaction);
  $transaction->remote_id = _commerce_vipps_generate_id($transaction);

  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
  try {
    $wrapper = entity_metadata_wrapper('commerce_order', $order);
    list($entity_type, $bundle, $property, $sub_property) = explode('|', $settings['mobile']);
    if ($bundle == 'billing') {
      $wrapper = $wrapper->commerce_customer_billing;
    }
    elseif ($bundle == 'shipping') {
      $wrapper = $wrapper->commerce_customer_shipping;
    }
    else {
      throw new Exception('Missing phone field.');
    }
    if (!empty($sub_property)) {
      $mobile = $wrapper->{$property}->{$sub_property}->value();
    }
    elseif (!empty($property)) {
      $mobile = $wrapper->{$property}->value();
    }

    if (empty($mobile)) {
      throw new Exception('Missing phone value');
    }

    $vipps = _commerce_vipps_get_client($settings);
    $payment = $vipps->payments();
    $payment
      ->setOrderID($transaction->remote_id)
      ->create(
        $mobile,
        $transaction->amount,
        'Create payment on ' . $base_url,
        url('commerce_vipps/' . $transaction->transaction_id . '/transaction/' . $order->data['payment_redirect_key'], array('absolute' => TRUE))
      );
    $transaction->payload = $payment->getLastResponse();
    commerce_payment_transaction_save($transaction);
  }
  catch (Exception $e) {
    drupal_set_message(t('Unfortunately VIPPS is experiencing technical difficulties at the moment. Try again later or choose another payment method.'), 'error');
    $transaction->message = $e->getMessage();
    $transaction->status = COMMERCE_PAYMENT_STATUS_FAILURE;
    commerce_payment_transaction_save($transaction);
    drupal_goto('checkout/' . $order->order_id . '/payment/back/' . $order->data['payment_redirect_key']);
  }

  // Save current transaction in order.
  if (empty($order->data['commerce_vipps']['current_transaction']) || ($transaction->transaction_id != $order->data['commerce_vipps']['current_transaction'])) {
    $order->data['commerce_vipps']['current_transaction'] = $transaction->transaction_id;
    commerce_order_save($order);
  }

  $form['#action'] = url('checkout/' . $order->order_id . '/commerce_vipps/' . $order->data['payment_redirect_key']);
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Proceed to Vipps'),
  );
  return $form;
}

/**
 * @param object $order
 * @param $payment_redirect_key
 * @return int|string
 */
function commerce_vipps_checkout_page($order, $payment_redirect_key) {

  // Check if there's still need to check.
  $transaction = commerce_payment_transaction_load($order->data['commerce_vipps']['current_transaction']);
  if ($transaction->remote_status !== COMMERCE_VIPPS_REMOTE_INITIATE) {
    drupal_goto('checkout/' . $order->order_id . '/payment/return/' . $payment_redirect_key);
  }
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();

  // Build spinner page.
  $build = array();
  $build['#attached']['css'] = array(
    drupal_get_path('module', 'commerce_vipps') . '/assets/commerce_vipps.css',
  );
  $build['#attached']['js'] = array(
    drupal_get_path('module', 'commerce_vipps') . '/assets/commerce_vipps.js',
    array(
      'data' => array(
        'commerce_vipps' => array(
          'transaction' => $transaction->transaction_id,
          'payment_redirect_key' => $payment_redirect_key,
        ),
      ),
      'type' => 'setting'
    ),
  );
  $build['help'] = array(
    '#type' => 'container',
    '#attributes' => array(
      'class' => array('commerce-vipps-help'),
    ),
    'text' => array(
      '#markup' => $settings['help'],
    ),
  );
  $build['spinner'] = array(
    '#theme' => 'image',
    '#path' => drupal_get_path('module', 'commerce_vipps') . '/assets/spinner.gif',
    '#attributes' => array(
      'class' => array('commerce-vipps-spinner'),
    )
  );
  $build['vipps-logo'] = array(
    '#theme' => 'image',
    '#path' => drupal_get_path('module', 'commerce_vipps') . '/assets/logo.png',
    '#attributes' => array(
      'class' => array('commerce-vipps-logo'),
    )
  );
  return $build;
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

  _commerce_vipps_get_status_transaction($transaction);
  if ($transaction->remote_status === COMMERCE_VIPPS_REMOTE_RESERVED) {
    commerce_payment_transaction_save($transaction);
    return TRUE;
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
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
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
function _commerce_vipps_capture_transaction($transaction, $amount = 0, $skip_save = FALSE) {
  global $user;
  if (!isset($amount)) {
    $amount = $transaction->amount;
  }
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
  $payment = _commerce_vipps_get_client($settings)->payments()->setOrderID($transaction->remote_id);
  $payment->capture(t('Captured by @user', array('@user' => $user->name)), $amount);
  _commerce_vipps_get_status_transaction($transaction);
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
function _commerce_vipps_credit_transaction($transaction, $amount = 0, $skip_save = FALSE) {
  if (!isset($amount)) {
    $amount = $transaction->amount;
  }
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
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
 * @return object
 *   Payment status.
 *
 * @throws Exception
 */
function _commerce_vipps_get_status_transaction($transaction) {
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
  $vipps = _commerce_vipps_get_client($settings);
  $payment = $vipps->payments();
  $status = $payment->setOrderID($transaction->remote_id)->getStatus();
  $transaction->remote_status = $status->transactionInfo->status;
  return $status;
}

/**
 * Get transaction details.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 *
 * @return object
 *   Payment details.
 *
 * @throws Exception
 */
function _commerce_vipps_get_details_transaction($transaction) {
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
  $vipps = _commerce_vipps_get_client($settings);
  $payment = $vipps->payments();
  $details = $payment->setOrderID($transaction->remote_id)->getDetails();
  $transaction->payload = $details;
  return $details;
}

/**
 * @param $settings
 * @return /Vipps/Vipps
 */
function _commerce_vipps_get_client($settings) {

  // Get certificate.
  list($path, $password) = explode(':', $settings['cert']);
  if (isset($password)) {
    $cert = array($path, $password);
  }
  else {
    $cert = $path;
  }

  // Add certificate.
  $options = array(
    'cert' => $cert,
  );

  // Add base uri.
  if ($settings['base_uri']) {
    $options['base_uri'] = $settings['base_uri'];
  }

  // Initiate client.
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
    'mobile' => NULL,
    'help' => t('Open VIPPS app on your phone and confirm payment.'),
  );
}

/**
 * Generate remote transaction ID for transaction.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 *
 * @return string
 *   Remote ID.
 */
function _commerce_vipps_generate_id($transaction) {
  return 'O' . $transaction->order_id . 'T' . $transaction->transaction_id;
}

function _commerce_vipps_phone_number_properties($flatten = FALSE) {
  $array = array();
  $bundles = array(
    array(
      'entity_type' => 'commerce_customer_profile',
      'bundle' => 'billing'
    ),
    array(
      'entity_type' => 'commerce_customer_profile',
      'bundle' => 'shipping'
    ),
  );
  foreach ($bundles as $bundle) {
    $bundle_array =& $array[$bundle['entity_type'] . ' - ' . $bundle['bundle']];
    // Get properties.
    $info = entity_get_property_info($bundle['entity_type']);
    $properties = $info['properties'];
    if (isset($info['bundles'][$bundle['bundle']])) {
      $properties += $info['bundles'][$bundle['bundle']]['properties'];
    }
    // Build select list.
    foreach ($properties as $key => $property) {
      $type = isset($property['type']) ? entity_property_extract_innermost_type($property['type']) : 'text';
      if (isset($property['field']) && $property['field'] && !empty($property['property info'])) {
        $bundle_array[$bundle['entity_type'] . '|' . $bundle['bundle'] . '|' . $key] = $property['label'];
        foreach ($property['property info'] as $sub_key => $sub_prop) {
          $bundle_array[$bundle['entity_type'] . '|' . $bundle['bundle'] . '|' . $key . '|' . $sub_key] = $property['label'] . ' - ' . $sub_prop['label'];
        }
      }
      else {
        $bundle_array[$bundle['entity_type'] . '|' . $bundle['bundle'] . '|' . $key] = $property['label'];
      }
    }
  }
  return $flatten ? options_array_flatten($array) : $array;
}
