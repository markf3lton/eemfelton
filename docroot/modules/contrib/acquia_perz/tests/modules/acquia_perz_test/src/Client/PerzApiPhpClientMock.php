<?php

namespace Drupal\acquia_perz_test\Client;

use Acquia\PerzApiPhp\ObjectFactory;
use Acquia\PerzApiPhp\PerzApiPhpClient;
use Drupal\Component\Serialization\Json;
use Drupal\rest\ModifiedResourceResponse;
use GuzzleHttp\Exception\BadResponseException;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mocks server responses.
 */
class PerzApiPhpClientMock extends PerzApiPhpClient {

  /**
   * {@inheritdoc}
   */
  public function pushEntity($data) {
    $decision_webhook = "{$this->baseUrl}/v3/webhook";
    $request = Request::create(
      $decision_webhook,
      'POST',
      [],
      [],
      [],
      [],
      Json::encode([
        'entity_type_id' => $data['entity_type'],
        'entity_uuid' => $data['entity_uuid'],
        'op' => $data['op'],
      ])
    );
    $request->headers->set('Content-type', 'application/json');
    $request = $this->generateRquestWithSignature($request);
    \Drupal::service('http_kernel')->handle($request);
    return new ModifiedResourceResponse(['sent', 200]);

  }

  /**
   * {@inheritdoc}
   */
  public function putVariations($data) {
    if (empty($data['account_id']) || empty($data['environment'])) {
      throw new BadResponseException('Missing required path parameters.');
    }
    $uri = '/v3/accounts/' . $data['account_id'] . '/environments/' . $data['environment'] . '/contents';
    $query = [
      'origin' => $data['origin'],
    ];

    $decision_webhook = $this->baseUrl . $uri;
    $request = Request::create(
      $decision_webhook,
      'PUT',
      $query,
      [],
      [],
      [],
      Json::encode($data)
    );
    $request->headers->set('Content-type', 'application/json');
    $request = $this->generateRquestWithSignature($request);
    \Drupal::service('http_kernel')->handle($request);
    return new ModifiedResourceResponse(['sent', 200]);
  }

  /**
   * Generates and add HMAC auth signature to repsonse.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   Request signed with hmac
   */
  public function generateRquestWithSignature(Request $request) {
    $auth_id = 'ABCD-123456';
    $auth_key = '1abc8a81d5d1a7a491206bc0a61d51e';
    $key = ObjectFactory::getAuthenticationKey($auth_id, $auth_key);
    $middleware = ObjectFactory::getHmacAuthMiddleware($key);
    $httpMessageFactory = new PsrHttpFactory(new ServerRequestFactory(), new StreamFactory(), new UploadedFileFactory(), new ResponseFactory());
    $psr7_request = $httpMessageFactory->createRequest($request);
    $signed_request = $middleware->signRequest($psr7_request);
    $foundationFactory = new HttpFoundationFactory();
    return $foundationFactory->createRequest($signed_request);
  }

}
