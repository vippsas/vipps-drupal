<?php

/**
 * Implements hook_drush_command().
 */
function commerce_vipps_drush_command() {
  $items = array();
  $items['vipps-process'] = array(
    'callback' => 'commerce_vipps_drush_process',
    'description' => 'Process tasks in commerce vipps queue table.',
  );
  return $items;
}

/**
 * Run
 */
function commerce_vipps_drush_process() {
  $lock = 'commerce_vipps_process';
  if (!lock_acquire($lock, 86400.0)) {
    drush_log('Already running..');
    return;
  }
  module_load_include('inc', 'commerce_vipps');
  _commerce_vipps_process(200);
  lock_release($lock);
}
