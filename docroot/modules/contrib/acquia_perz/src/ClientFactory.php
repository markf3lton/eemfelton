<?php

namespace Drupal\acquia_perz;

use Acquia\Hmac\Exception\KeyNotFoundException;
use Acquia\Hmac\KeyInterface;
use Acquia\Hmac\KeyLoader;
use Acquia\Hmac\RequestAuthenticator;
use Acquia\Hmac\ResponseSigner;
use Acquia\PerzApiPhp\ObjectFactory;
use Acquia\PerzApiPhp\PerzApiPhpClient;
use Drupal\acquia_connector\Subscription;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Instantiates an Acquia PerzPhp Client object.
 *
 * @see \Acquia\PerzApiPhp
 */
class ClientFactory {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The HTTP kernel service.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Acquia Perz subscription data.
   *
   * @var array
   */
  protected $subscription = [];

  /**
   * The PSR-7 converter.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected $httpMessageFactory;

  /**
   * The httpFoundation factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  protected $httpFoundationFactory;

  /**
   * ClientManagerFactory constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state store.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config Factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list.
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The wrapped HTTP kernel.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The PSR-7 converter.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface $http_foundation_factory
   *   The httpFoundation factory.
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   Acquia Subscription.
   */
  public function __construct(StateInterface $state, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, ModuleExtensionList $module_list, HttpKernelInterface $http_kernel, HttpMessageFactoryInterface $http_message_factory, HttpFoundationFactoryInterface $http_foundation_factory, Subscription $subscription) {
    $this->state = $state;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->moduleList = $module_list;
    $this->httpKernel = $http_kernel;
    $this->httpMessageFactory = $http_message_factory;
    $this->httpFoundationFactory = $http_foundation_factory;
    $subscription_data = $subscription->getSubscription();
    if (isset($subscription_data['acquia_perz'])) {
      $this->subscription = $subscription_data['acquia_perz'];
    }
  }

  /**
   * Get PerzApiPhpClient.
   *
   * @return \Acquia\PerzApiPhp\PerzApiPhpClient|bool
   *   The PerzApiPhp Client
   */
  public function getClient(array $config = []) {
    if (empty($this->subscription)) {
      return FALSE;
    }

    $base_uri = $config['base_url'] ?? $this->subscription['endpoint'];
    $client_user_agent = $config['client-user-agent'] ?? $this->getClientUserAgent();

    $key = ObjectFactory::getAuthenticationKey($this->subscription['api_key'], $this->subscription['secret_key']);
    $middleware = ObjectFactory::getHmacAuthMiddleware($key);
    $config = [
      'client-user-agent' => $client_user_agent,
      'base_url' => $base_uri,
    ];
    return new PerzApiPhpClient($middleware, $config);
  }

  /**
   * Get entities from Personalisation.
   *
   * @param array $data
   *   An array of Entity data.
   *   $data = [
   *     'account_id' => (string) Acquia Account ID. Required.
   *     'origin' => (string) Site hash. Required.
   *     'environment' => (string) Site environment. Required.
   *     'language' => (string) Entity Language. Optional.
   *     'view_mode' => (string) View mode of Entity. Optional.
   *     'q' => (string) Keywords to search. Optional.
   *     'content_type' => (string) Type of the Entity. Oprional.
   *     'tags' =>  (string) Tags to search, Optional.
   *     'all_tags' => (string) All tags to search. Optional.
   *     'date_start' => (datetime) Start date of Entity update. Optional.
   *     'date_end' => (datetime) End date of Entity update. Optional.
   *     'rows' => (integer) Number of rows in result. Default 10. Optional.
   *     'start' => (integer) Page start index. Default 0. Optional.
   *     'sort' => (string) Sort by field. Default modified. Optional.
   *     'sort_order' => (string) Sort order. Default desc. Optional.
   *     'site_hash' => (string) Site hash. Optional.
   *   ].
   *
   * @return \Psr\Http\Message\ResponseInterface|void
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Guzzle Exception.
   */
  public function getEntities(array $data) {
    $client = $this->getClient();
    $response = $client->getEntities($data);
    return json_decode($response->getBody()->getContents());
  }

  /**
   * Push entity to Personalization.
   *
   * @param array $data
   *   An array of Entity data.
   *   $data = [
   *     'account_id' => (string) Acquia Account ID. Required.
   *     'origin' => (string) Site hash. Required.
   *     'environment' => (string) Site environment. Required.
   *     'domain' => (string) Domain of the site. Required.
   *     'op' => (string) View mode of the entity. Required.
   *     'entity_type_id' => (string) Entity Type,
   *     'entity_uuid' => (string) Entity uuid,
   *     'site_hash' => (string) Site hash. Optional.
   *   ].
   *
   * @return \Psr\Http\Message\ResponseInterface|void
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Guzzle Exception.
   */
  public function pushEntity(array $data) {
    $client = $this->getClient();
    return $client->pushEntity($data);
  }

  /**
   * Put entity to Personalization.
   *
   * @param array $data
   *   An array of Entity data.
   *   $data = [
   *     'account_id' => (string) Acquia Account ID. Required.
   *     'origin' => (string) Site hash. Required.
   *     'environment' => (string) Site envireonment. Required.
   *     'entity_variations' => (array) Entity variation data. Required.
   *     'site_hash' => (string) Site hash. Optional.
   *   ].
   *
   * @return \Psr\Http\Message\ResponseInterface|void
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Guzzle Exception.
   */
  public function putVariations(array $data) {
    $client = $this->getClient();
    return $client->putVariations($data);
  }

  /**
   * Delete entities from Personalization.
   *
   * @param array $data
   *   An array of Entity data.
   *   $data = [
   *     'account_id' => (string) Acquia Account ID. Required.
   *     'origin' => (string) Site hash. Required.
   *     'environment' => (string) Site environment. Required.
   *     'content_uuid' => (string) UUID of the entity. Optional.
   *     'language' => (string) UUID of the entity. Optional.
   *     'view_mode' => (string) UUID of the entity. Optional.
   *     'site_hash' => (string) Site hash. Optional. Optional.
   *   ].
   *
   * @return \Psr\Http\Message\ResponseInterface|void
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Guzzle Exception.
   */
  public function deleteEntities(array $data) {
    $client = $this->getClient();
    return $client->deleteEntities($data);
  }

  /**
   * Returns Client's user agent.
   *
   * @return string
   *   User Agent.
   */
  public function getClientUserAgent() {
    // Find out the module version in use.
    $module_info = $this->moduleList->getExtensionInfo('acquia_perz');
    $module_version = (isset($module_info['version'])) ? $module_info['version'] : '0.0.0';
    $drupal_version = (isset($module_info['core'])) ? $module_info['core'] : '0.0.0';

    return 'AcquiaPerzApiPhp/' . $drupal_version . '-' . $module_version;
  }

  /**
   * Makes a call to get a client response based on the client name.
   *
   * Note, this receives a Symfony request, but uses a PSR7 Request to Auth.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Acquia\Hmac\KeyInterface|bool
   *   Controller Key, FALSE otherwise.
   */
  public function authenticate(Request $request) {
    if (!$this->subscription) {
      return FALSE;
    }

    $keys = [$this->subscription['api_key'] => $this->subscription['secret_key']];
    $keyLoader = new KeyLoader($keys);
    $authenticator = new RequestAuthenticator($keyLoader);
    $psr7_request = $this->httpMessageFactory->createRequest($request);
    try {
      return $authenticator->authenticate($psr7_request);
    }
    catch (KeyNotFoundException $exception) {
      $this->loggerFactory
        ->get('acquia_perz')
        ->debug('HMAC validation failed. [authorization_header = %authorization_header]', [
          '%authorization_header' => $request->headers->get('authorization'),
        ]);
    }

    return FALSE;
  }

  /**
   * Generates and add HMAC auth signature to repsonse.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   Response.
   * @param \Acquia\Hmac\KeyInterface $key
   *   Controller Key.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Controller Key, FALSE otherwise.
   */
  public function generateResponseWithSignature(Request $request, Response $response, KeyInterface $key) {
    $psr7_request = $this->httpMessageFactory->createRequest($request);
    $psr7Response = $this->httpMessageFactory->createResponse($response);
    $signer = new ResponseSigner($key, $psr7_request);
    $signedResponse = $signer->signResponse($psr7Response);
    return $this->httpFoundationFactory->createResponse($signedResponse);
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
  public function generateRequestWithSignature(Request $request) {
    $key = ObjectFactory::getAuthenticationKey($this->subscription['api_key'], $this->subscription['secret_key']);
    $middleware = ObjectFactory::getHmacAuthMiddleware($key);
    $psr7_request = $this->httpMessageFactory->createRequest($request);
    $signed_request = $middleware->signRequest($psr7_request);
    return $this->httpFoundationFactory->createRequest($signed_request);
  }

  /**
   * Delete content from CIS.
   *
   * @return \Psr\Http\Message\ResponseInterface|void
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Guzzle Exception.
   */
  public function deleteContentFromCis() {
    $data = [
      'account_id' => PerzHelper::getAccountId(),
      'origin' => PerzHelper::getSiteId(),
      'environment' => PerzHelper::getSiteEnvironment(),
      'site_hash' => PerzHelper::getSiteHash(),
    ];
    return $this->deleteEntities($data);
  }

  /**
   * Push variations to Personalization.
   *
   * @param string|null $account_id
   *   Account ID.
   * @param string|null $site_hash
   *   Site hash.
   * @param string|null $site_env
   *   Site environment.
   * @param array $entity_variations
   *   Entity variations.
   * @param string $op
   *   Operation.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function pushDataToPersonalization(?string $account_id, ?string $site_hash, ?string $site_env, array $entity_variations, string $op = 'normal') {
    if (!empty($account_id) && !empty($site_hash) && !empty($site_env)) {
      $data = [
        'account_id' => $account_id,
        'origin' => $site_hash,
        'environment' => $site_env,
        'entity_variations' => $entity_variations,
        'site_hash' => PerzHelper::getSiteHash(),
      ];
      $this->putVariations($data);
    }
  }

  /**
   * Delete All contents from CIS.
   *
   * @return \Psr\Http\Message\ResponseInterface|void
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Guzzle Exception.
   */
  public function deleteAllContentsFromCis() {
    $data = [
      'account_id' => PerzHelper::getAccountId(),
      'environment' => PerzHelper::getSiteEnvironment(),
    ];
    return $this->deleteEntities($data);
  }

}
