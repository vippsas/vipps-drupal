<?php

namespace Drupal\commerce_vipps;

use zaporylie\Vipps\Authentication\TokenStorageInterface;
use zaporylie\Vipps\Model\Authorization\ResponseGetToken;

/**
 * Class CacheTokenStorage.
 */
class CacheTokenStorage implements TokenStorageInterface {

  const CACHE_BIN = 'commerce_vipps_authorization_token';

  /**
   * {@inheritdoc}
   */
  public function get() {
    if (!$this->has()) {
      throw new \InvalidArgumentException('Missing Token');
    }
    return $this->getTokenFromCache();
  }

  /**
   * {@inheritdoc}
   */
  public function set(ResponseGetToken $token) {
    \Drupal::cache()->set(self::CACHE_BIN, $token, $token->getExpiresOn()->getTimestamp());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function has() {
    if (!($this->getTokenFromCache() instanceof ResponseGetToken)) {
      return FALSE;
    }

    if ($this->getTokenFromCache()->getExpiresOn()->getTimestamp() < (new \DateTime())->getTimestamp()) {
      $this->clear();
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {

    \Drupal::cache()->invalidate(self::CACHE_BIN);
    drupal_static_reset(self::CACHE_BIN);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->clear();
    return $this;
  }

  /**
   * Get token from cache.
   *
   * @return mixed
   *   False if none, array if found.
   */
  private function getTokenFromCache() {
    $static_cache =& drupal_static(self::CACHE_BIN);
    if (isset($static_cache['object']) && $static_cache['object'] instanceof ResponseGetToken) {
      return $static_cache['object'];
    }
    $db_cache = \Drupal::cache()->get(self::CACHE_BIN);
    if (!isset($db_cache->data)) {
      return FALSE;
    }
    // Save on static cache for future reference.
    $static_cache['object'] = $db_cache->data;
    return $db_cache->data;
  }

}
