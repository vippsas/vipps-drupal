<?php

namespace Drupal\commerce_vipps\Event;

final class VippsEvents {

  /**
   * Fired before payment is initiated against Vipps.
   *
   * @Event
   *
   * @see \Drupal\commerce_vipps\Event\InitiatePaymentOptionsEvent
   */
  const INITIATE_PAYMENT_OPTIONS = 'commerce_vipps.initiate_payment_options';

  /**
   * Fired after user returns back from Vipps Hurtigkasse.
   *
   * @Event
   *
   * @see \Drupal\commerce_vipps\Event\ReturnFromVippsExpressEvent
   */
  const RETURN_FROM_VIPPS_EXPRESS = 'commerce_vipps.return_from_vipps_express';

}
