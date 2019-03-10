<?php

namespace Drupal\commerce_vipps\Event;

final class VippsEvents {

  /**
   * Fired before payment is initiated against Vipps.
   *
   * @Event
   *
   * @see \Drupal\commerce_vipps\Event\DefaultPhoneNumberEvent
   */
  const DEFAULT_PHONE_NUMBER = 'commerce_vipps.default_phone_number';

}
