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
  $form['environment'] = array(
    '#type' => 'radios',
    '#title' => t('Environment'),
    '#required' => TRUE,
    '#description' => t('Choose between live and test environment'),
    '#default_value' => $settings['environment'],
    '#options' => array(
      'test' => t('Test'),
      'live' => t('Live'),
    ),
  );
  $form['id'] = array(
    '#type' => 'textfield',
    '#title' => t('ID'),
    '#required' => TRUE,
    '#description' => t('Client ID'),
    '#default_value' => $settings['id'],
  );
  $form['secret'] = array(
    '#type' => 'textfield',
    '#title' => t('Secret'),
    '#required' => TRUE,
    '#description' => t('Client Secret'),
    '#default_value' => $settings['secret'],
  );
  $form['serial_number'] = array(
    '#type' => 'textfield',
    '#title' => t('Serial Number'),
    '#required' => TRUE,
    '#description' => t('Merchant Serial Number'),
    '#default_value' => $settings['serial_number'],
  );
  $form['subscription_key_authorization'] = array(
    '#type' => 'textfield',
    '#title' => t('Subscription Key - Authorization'),
    '#required' => TRUE,
    '#default_value' => $settings['subscription_key_authorization'],
  );
  $form['subscription_key_payment'] = array(
    '#type' => 'textfield',
    '#title' => t('Subscription Key - Payment'),
    '#required' => TRUE,
    '#default_value' => $settings['subscription_key_payment'],
  );
  $form['mobile'] = array(
    '#type' => 'select',
    '#title' => t('Mobile phone value'),
    '#required' => TRUE,
    '#description' => t('Which field stores mobile phone number.'),
    '#default_value' => $settings['mobile'],
    '#options' => _commerce_vipps_phone_number_properties(),
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

  $transaction = _commerce_vipps_get_current_transaction($order, $payment_method);
  $payment_redirect_key = $order->data['payment_redirect_key'];
  // Payment doesn't exist.
  if (!$transaction) {
    commerce_payment_redirect_pane_previous_page($order);
    drupal_goto(commerce_checkout_order_uri($order));
  }

  // Payment has been processed already.
  if ($transaction->remote_status !== COMMERCE_VIPPS_REMOTE_INITIATE) {
    drupal_goto('checkout/' . $order->order_id . '/payment/return/' . $payment_redirect_key);
  }

  // We need settings.
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();

  // Wait for payment confirmation.
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
  $build['vipps-help'] = array(
    '#type' => 'container',
    '#attributes' => array(
      'class' => array('commerce-vipps-help'),
    ),
    'text' => array(
      '#markup' => $settings['help'],
    ),
  );
  $build['vipps-spinner'] = array(
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
  $form['vipps'] = $build;
  return $form;
}

/**
 * Get OR create new VIPPS payment.
 *
 * @param object $order
 *   Commerce Order.
 * @param array $payment_method
 *   Payment method instance definition.
 *
 * @return FALSE|object
 */
function _commerce_vipps_get_current_transaction($order, $payment_method) {
  $transaction_id = empty($order->data['commerce_vipps']['current_transaction']) ? NULL : $order->data['commerce_vipps']['current_transaction'];
  if ($transaction_id && $transaction = commerce_payment_transaction_load($transaction_id)) {
    return $transaction;
  }

  // We would need base url.
  global $base_url;

  // And settings array (with default values).
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();

  // Get charge.
  $charge = commerce_payment_order_balance($order);

  // Create transaction.
  $transaction = commerce_payment_transaction_new($payment_method['method_id'], $order->order_id);
  $transaction->status = COMMERCE_VIPPS_STATUS_REGISTERED;
  $transaction->instance_id = $payment_method['instance_id'];
  $transaction->amount = $charge['amount'];
  $transaction->currency_code = $charge['currency_code'];

  // Save to create transaction ID.
  commerce_payment_transaction_save($transaction);

  // Set transaction ID.
  $transaction->remote_id = _commerce_vipps_generate_id($transaction);
  commerce_payment_transaction_save($transaction);

  // Save current transaction in order.
  $order->data['commerce_vipps']['current_transaction'] = $transaction->transaction_id;
  commerce_order_save($order);

  try {
    // Get order wrapper.
    $wrapper = entity_metadata_wrapper('commerce_order', $order);

    // Get path to phone field.
    list($entity_type, $bundle, $property, $sub_property) = array_pad(explode('|', $settings['mobile']), 4, NULL);

    // Get phone parent entity wrapper.
    if ($bundle == 'billing') {
      $wrapper = $wrapper->commerce_customer_billing;
    }
    elseif ($bundle == 'shipping') {
      $wrapper = $wrapper->commerce_customer_shipping;
    }
    else {
      throw new Exception('Missing phone field.');
    }

    // Get phone value.
    if (!empty($sub_property)) {
      $mobile = $wrapper->{$property}->{$sub_property}->value();
    }
    elseif (!empty($property)) {
      $mobile = $wrapper->{$property}->value();
    }
    else {
      $mobile = '';
    }

    // Remove everything what's not an integer.
    $mobile = preg_replace("/[^0-9]/", "", $mobile);

    // Throw exception in case phone field does not exist.
    if (empty($mobile)) {
      throw new Exception('Missing phone value');
    }

    $vipps = _commerce_vipps_get_client($settings);
    $payment = $vipps->payments();
    $payment
      ->setOrderID($transaction->remote_id)
      ->create(
        (int) $mobile,
        (int) $transaction->amount,
        t('Payment for order @order_id', array('@order_id' => $wrapper->getIdentifier())),
        url('commerce_vipps/' . $transaction->transaction_id . '/transaction/' . $order->data['payment_redirect_key'], array('absolute' => TRUE, 'https' => TRUE))
      );

    // Save transaction.
    $transaction->remote_status = COMMERCE_VIPPS_REMOTE_INITIATE;
    $transaction->payload = $payment->getLastResponse();
    commerce_payment_transaction_save($transaction);
  }
  catch (Exception $e) {

    // Some of the errors should be displayed to customer.
    if ($e instanceof \Vipps\Exceptions\VippsException && $e->getErrorCode() == 81) {
      drupal_set_message(t('Unable to process payment: @message', array('@message' => t('User not registered with VIPPS'))), 'error');
    }
    // User's app is unsupported.
    elseif ($e instanceof \Vipps\Exceptions\VippsException && $e->getErrorCode() == 82) {
      drupal_set_message(t('Unable to process payment: @message', array('@message' => t('User App Version is not supported'))), 'error');
    }
    // Some of the errors should be displayed to customer.
    elseif ($e instanceof \UnexpectedValueException) {
      drupal_set_message(t('Unable to process payment due to validation errors'), 'error');
    }
    else {
      drupal_set_message(t('Unfortunately VIPPS is experiencing technical difficulties at the moment. Try again later or choose another payment method.'), 'error');
    }

    // Log exception.
    watchdog_exception('commerce_vipps', $e);

    // Change status for transaction.
    $transaction->message = $e->getMessage();
    $transaction->status = COMMERCE_PAYMENT_STATUS_FAILURE;
    $transaction->remote_id = NULL;
    commerce_payment_transaction_save($transaction);

    // Unset current transaction.
    $order->data['commerce_vipps']['current_transaction'] = NULL;
    commerce_order_save($order);

    // Return with error.
    return FALSE;
  }

  // Return transaction.
  return $transaction;
}

/**
 * Payment method callback: redirect form return validation.
 */
function commerce_vipps_redirect_form_validate($order, $payment_method) {

  // Should never happen but catch it anyway.
  if (empty($order->data['commerce_vipps']['current_transaction']) || !($transaction = commerce_payment_transaction_load($order->data['commerce_vipps']['current_transaction']))) {
    watchdog('commerce_vipps', 'Missing current transaction id for order @order', array('@order' => $order->order_id), WATCHDOG_CRITICAL);
    return FALSE;
  }

  // @todo: Should be wrapped as vipps api can fail.
  try {
    _commerce_vipps_get_status_transaction($transaction);
  }
  catch (\Vipps\Exceptions\ConnectionException $e) {
    watchdog_exception('commerce_vipps', $e);
    watchdog('commerce_vipps', 'Networking issue while getting transaction status: @message', array('@message_id' => $e->getMessage()), WATCHDOG_ERROR);
    // Assuming data was already synced and status is reserved we can let user
    // pass (optimistic scenario).
    if ($transaction->remote_status !== COMMERCE_VIPPS_REMOTE_RESERVE) {
      // Delay.
      sleep(10);
      // Try again.
      drupal_goto('checkout/' . $order->order_id . '/payment/return/' . $order->data['payment_redirect_key']);
    }
  }
  catch (Exception $e) {
    watchdog_exception('commerce_vipps', $e);
    watchdog('commerce_vipps', 'Unknown error when getting transaction status: @message', array('@message_id' => $e->getMessage()), WATCHDOG_CRITICAL);
  }

  // Check if remote status is "Reserved".
  if ($transaction->remote_status === COMMERCE_VIPPS_REMOTE_RESERVE) {
    _commerce_vipps_set_local_status($transaction);
    $transaction->message = 'Reserved';
    commerce_payment_transaction_save($transaction);
    return TRUE;
  }
  elseif (in_array($transaction->remote_status, array(COMMERCE_VIPPS_REMOTE_INITIATE, COMMERCE_VIPPS_REMOTE_REGISTER))) {
    // Can't be right, try again.
    watchdog('commerce_vipps', 'Order is looped in status @remote_status', array('@remote_status' => $transaction->remote_status), WATCHDOG_ERROR);
    // Delay.
    sleep(10);
    // Try again.
    drupal_goto('checkout/' . $order->order_id . '/payment/return/' . $order->data['payment_redirect_key']);
  }

  // It seems like transaction failed.
  $transaction->status = COMMERCE_PAYMENT_STATUS_FAILURE;
  $transaction->message = 'Payment failed: ' . $transaction->remote_status;
  commerce_payment_transaction_save($transaction);

  // Unset current transaction.
  unset($order->data['commerce_vipps']['current_transaction']);
  commerce_order_save($order);

  // Return error.
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
  global $user;
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
  $payment = _commerce_vipps_get_client($settings)->payments()->setOrderID($transaction->remote_id);
  $payment->cancel(t('Canceled by @user', array('@user' => $user->name)));
  $transaction->message = 'Canceled';
  $transaction->message_variables = array();
  _commerce_vipps_get_status_transaction($transaction);
  _commerce_vipps_get_details_transaction($transaction);
  _commerce_vipps_set_local_status($transaction);

  if (!$skip_save) {
    commerce_payment_transaction_save($transaction);
  }
}

/**
 * Capture transaction.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 * @param int $amount
 *   (optional) Capture amount.
 * @param bool $skip_save
 *   (optional) Skip transaction save.
 *
 * @throws Exception
 */
function _commerce_vipps_capture_transaction($transaction, $amount = 0, $skip_save = FALSE) {
  global $user;
  if (empty($amount)) {
    $amount = (int) $transaction->amount;
  }
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
  $payment = _commerce_vipps_get_client($settings)->payments()->setOrderID($transaction->remote_id);
  $payment->capture(t('Captured by @user', array('@user' => $user->name)), $amount);
  $transaction->message = 'Captured @amount';
  $transaction->message_variables = array('@amount' => $amount);
  _commerce_vipps_get_status_transaction($transaction);
  _commerce_vipps_get_details_transaction($transaction);
  $transaction->status = COMMERCE_PAYMENT_STATUS_SUCCESS;
  $transaction->amount = $transaction->payload->transactionSummary->capturedAmount - $transaction->payload->transactionSummary->refundedAmount;
  if (!$skip_save) {
    commerce_payment_transaction_save($transaction);
  }
}

/**
 * Credit transaction.
 *
 * @param object $transaction
 *   Commerce Payment Transaction.
 * @param int $amount
 *   (optional) Credit amount.
 * @param bool $skip_save
 *   (optional) Skip transaction save.
 *
 * @throws Exception
 */
function _commerce_vipps_credit_transaction($transaction, $amount = 0, $skip_save = FALSE) {
  global $user;
  if (empty($amount)) {
    $amount = (int) $transaction->amount;
  }
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $settings = $payment_method['settings'] + _commerce_vipps_default_settings();
  $payment = _commerce_vipps_get_client($settings)->payments()->setOrderID($transaction->remote_id);
  $payment->refund(t('Credited by @user', array('@user' => $user->name)), $amount);
  $transaction->message = 'Credited @amount';
  $transaction->message_variables = array('@amount' => $amount);
  _commerce_vipps_get_status_transaction($transaction);
  _commerce_vipps_get_details_transaction($transaction);
  $transaction->amount = $transaction->payload->transactionSummary->capturedAmount - $transaction->payload->transactionSummary->refundedAmount;
  _commerce_vipps_set_local_status($transaction);
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
 * Set local status for transaction based on remote.
 *
 * @param object $transaction
 *   Commerce Payment Transaction
 */
function _commerce_vipps_set_local_status($transaction) {
  switch ($transaction->remote_status) {
    case COMMERCE_VIPPS_REMOTE_VOID:
    case COMMERCE_VIPPS_REMOTE_CANCEL:
    case COMMERCE_VIPPS_REMOTE_FAILED:
      $transaction->status = COMMERCE_PAYMENT_STATUS_FAILURE;
      break;

    case COMMERCE_VIPPS_REMOTE_INITIATE:
    case COMMERCE_VIPPS_REMOTE_REGISTER:
      $transaction->status = COMMERCE_VIPPS_STATUS_REGISTERED;
      break;

    case COMMERCE_VIPPS_REMOTE_RESERVE:
      if ($transaction->status == COMMERCE_VIPPS_STATUS_REGISTERED) {
        $transaction->status = COMMERCE_PAYMENT_STATUS_PENDING;
      }
      elseif ($transaction->payload->transactionSummary->remainingAmountToRefund == 0 && $transaction->payload->transactionSummary->remainingAmountToCapture == 0) {
        $transaction->status = COMMERCE_PAYMENT_STATUS_FAILURE;
      }
      break;
  }
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
 * @return /Vipps/VippsInterface
 * @throws LogicException
 */
function _commerce_vipps_get_client($settings) {
  // Initiate client.
  $httpClient = new \Http\Adapter\Guzzle6\Client(new \GuzzleHttp\Client());
  $client = new \zaporylie\Vipps\Client($settings['id'], [
    'http_client' => $httpClient,
    'environment' => $settings['environment'],
    'token_storage' => new CommerceVippsCacheTokenStorageClass(),
  ]);
  $vipps = new \zaporylie\Vipps\Vipps($client);
  if (!$client->getTokenStorage()->has()) {
    $token = $vipps->authorization($settings['subscription_key_authorization'])->getToken($settings['secret']);
    $client->getTokenStorage()->set($token);
  }
  return $vipps;
}

/**
 * Default settings.
 *
 * @return array
 */
function _commerce_vipps_default_settings() {
  return array(
    'environment' => 'test',
    'serial_number' => '',
    'id' => '',
    'secret' => '',
    'subscription_key_authorization' => '',
    'subscription_key_payment' => '',
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
 *
 * @todo: Generate random key.
 */
function _commerce_vipps_generate_id($transaction) {
  return 'O' . $transaction->order_id . 'T' . $transaction->transaction_id;
}

/**
 * @param bool $flatten
 * @return array
 */
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

/**
 * @param $limit
 */
function _commerce_vipps_process($limit = 200) {
  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'commerce_order')
    ->propertyCondition('changed', strtotime('-5 minutes'), '<')
    ->propertyCondition('status', 'checkout_payment')
    ->propertyOrderBy('changed', 'DESC')
    ->range(0, $limit);

  $result = $query->execute();
  if (!isset($result['commerce_order'])) {
    watchdog('commerce_vipps', 'Found 0 orders', array(), WATCHDOG_NOTICE);
    return;
  }
  watchdog('commerce_vipps', 'Found @count orders', array('@count' => count($result['commerce_order'])), WATCHDOG_DEBUG);
  foreach (array_keys($result['commerce_order']) as $order_id) {
    $order = entity_load_single('commerce_order', $order_id);

    // Ignore if payment method is different than vipps.
    $instance = commerce_payment_method_instance_load($order->data['payment_method']);
    if ($instance['method_id'] !== 'commerce_vipps') {
      watchdog('commerce_vipps', 'Incorrect payment method: @method', array('@method' => $instance['method_id']), WATCHDOG_DEBUG);
      continue;
    }

    try {
      // Ensure order status is correct.
      if ($order->status !== 'checkout_payment') {
        throw new Exception('Wrong status: ' . $order->status);
      }

      // Get transaction.
      if (empty($order->data['commerce_vipps']['current_transaction'])) {
        throw new Exception('Unable to find referenced transaction');
      }
      $transaction = commerce_payment_transaction_load($order->data['commerce_vipps']['current_transaction']);
      if (!$transaction) {
        throw new Exception('Unable to load referenced transaction');
      }

      _commerce_vipps_get_status_transaction($transaction);

      switch ($transaction->remote_status) {
        case COMMERCE_VIPPS_REMOTE_VOID:
        case COMMERCE_VIPPS_REMOTE_CANCEL:
        case COMMERCE_VIPPS_REMOTE_FAILED:
          $transaction->message = 'Transaction cancelled: @remote_status';
          $transaction->message_variables = array('@remote_status' => $transaction->remote_status);
          _commerce_vipps_set_local_status($transaction);
          commerce_payment_transaction_save($transaction);
          unset($order->data['commerce_vipps']['current_transaction']);
          commerce_payment_redirect_pane_previous_page($order, 'Commerce Vipps: Transaction cancelled');
          watchdog('commerce_vipps', 'Payment @transaction_id cancelled due to remote status @reason by Drush.', array('@transaction_id' => $transaction->transaction_id, '@reason' => $transaction->remote_status), WATCHDOG_WARNING);
          break;

        case COMMERCE_VIPPS_REMOTE_INITIATE:
          $transaction->message = 'Time out';
          $transaction->status = COMMERCE_PAYMENT_STATUS_FAILURE;
          commerce_payment_transaction_save($transaction);
          unset($order->data['commerce_vipps']['current_transaction']);
          commerce_payment_redirect_pane_previous_page($order, 'Commerce Vipps: Timeout');
          watchdog('commerce_vipps', 'Payment @transaction_id cancelled due to @reason by Drush.', array('@transaction_id' => $transaction->transaction_id, '@reason' => 'timeout'), WATCHDOG_WARNING);
          break;

        case COMMERCE_VIPPS_REMOTE_RESERVE:
          _commerce_vipps_get_details_transaction($transaction);
          _commerce_vipps_set_local_status($transaction);
          $transaction->message = 'Reserved';
          commerce_payment_transaction_save($transaction);
          commerce_checkout_complete($order);
          watchdog('commerce_vipps', 'Payment @transaction_id fulfilled by Drush.', array('@transaction_id' => $transaction->transaction_id), WATCHDOG_WARNING);
          break;

        default:
          throw new Exception('Unknown transaction status: ' . $transaction->remote_status);
      }

    }
    catch (Exception $e) {
      watchdog('commerce_vipps', 'Order @order_id could not be processed: @message', array('@order_id' => $order_id, '@message' => $e->getMessage()), WATCHDOG_WARNING);
    }
  }
}
