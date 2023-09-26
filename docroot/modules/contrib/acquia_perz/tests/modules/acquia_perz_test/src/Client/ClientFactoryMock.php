<?php

namespace Drupal\acquia_perz_test\Client;

use Acquia\PerzApiPhp\ObjectFactory;
use Drupal\acquia_perz\ClientFactory;

/**
 * Mocks the client factory service.
 */
class ClientFactoryMock extends ClientFactory {

  /**
   * {@inheritdoc}
   */
  public function pushEntity($data) {
    $client = $this->getClient();
    return $client->pushEntity($data);
  }

  /**
   * {@inheritdoc}
   */
  public function putVariations($data) {
    $client = $this->getClient();
    return $client->putVariations($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getClient($config = []) {
    if (empty($this->subscription)) {
      return FALSE;
    }
    $base_uri = $config['base_url'] ?? $this->subscription['endpoint'];
    $client_user_agent = $config['client-user-agent'] ?? $this->getClientUserAgent();

    $auth_id = $this->subscription['api_key'];
    $auth_key = $this->subscription['secret_key'];
    $key = ObjectFactory::getAuthenticationKey($auth_id, $auth_key);
    $middleware = ObjectFactory::getHmacAuthMiddleware($key);
    $config = [
      'client-user-agent' => $client_user_agent,
      'base_url' => $base_uri,
    ];

    return new PerzApiPhpClientMock($middleware, $config);
  }

}
