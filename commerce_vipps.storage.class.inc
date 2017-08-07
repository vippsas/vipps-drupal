<?php

/**
 * Class CommerceVippsCacheTokenStorageClass
 */
class CommerceVippsCacheTokenStorageClass implements \zaporylie\Vipps\Authentication\TokenStorageInterface {

  const CACHE_BIN = 'commerce_vipps_authorization_token';

  /**
   * {@inheritdoc}
   */
  public function get() {
    if (!$this->has()) {
      throw new InvalidArgumentException('Missing Token');
    }
    return $this->getTokenFromCache();
  }

  /**
   * {@inheritdoc}
   */
  public function set(\zaporylie\Vipps\Model\Authorization\ResponseGetToken $token) {
    cache_set(self::CACHE_BIN, $token, 'cache', $token->getExpiresOn()->getTimestamp());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function has() {
    if (!($this->getTokenFromCache() instanceof \zaporylie\Vipps\Model\Authorization\ResponseGetToken)) {
      return FALSE;
    }

    if ($this->getTokenFromCache()->getExpiresOn()->getTimestamp() < (new \DateTime())->getTimestamp()) {
      $this->delete();
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    cache_clear_all(self::CACHE_BIN);
    drupal_static_reset(self::CACHE_BIN);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->delete();
    return $this;
  }

  /**
   * @return mixed
   */
  private function getTokenFromCache() {
    $static_cache =& drupal_static(self::CACHE_BIN);
    if (isset($static_cache['object']) && $static_cache['object'] instanceof \zaporylie\Vipps\Model\Authorization\ResponseGetToken) {
      return $static_cache['object'];
    }
    $db_cache = cache_get(self::CACHE_BIN);
    if (!isset($db_cache->data)) {
      return FALSE;
    }
    // Save on static cache for future reference.
    $static_cache['object'] = $db_cache->data;
    return $db_cache->data;
  }
}
