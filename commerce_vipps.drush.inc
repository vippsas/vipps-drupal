<?php

/**
 * Implements hook_drush_command().
 */
function commerce_vipps_drush_command() {
  $items = array();
  $items['vipps-process'] = array(
    'callback' => 'commerce_vipps_drush_process',
    'description' => 'Process tasks in commerce vipps queue table.',
    'options' => array(
      'limit' => 'The maximum number of orders to process.',
    ),
  );
  return $items;
}

/**
 * Run
 */
function commerce_vipps_drush_process() {
  $limit = drush_get_option('limit', 200);
  $lock = 'commerce_vipps_process';
  if (!lock_acquire($lock, 86400.0)) {
    drush_log('Already running..');
    return;
  }
  module_load_include('inc', 'commerce_vipps');
  _commerce_vipps_process($limit);
  lock_release($lock);
}
