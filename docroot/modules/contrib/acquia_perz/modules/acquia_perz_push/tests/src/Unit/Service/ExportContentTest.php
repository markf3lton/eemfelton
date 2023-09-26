<?php

namespace Drupal\Tests\acquia_perz_push\Unit\Service;

use Drupal\acquia_connector\Subscription;
use Drupal\acquia_perz\ClientFactory;
use Drupal\acquia_perz\EntityHelper;
use Drupal\acquia_perz_push\ExportContent;
use Drupal\acquia_perz_push\ExportQueue;
use Drupal\acquia_perz_push\ExportTracker;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\TransferException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @group acquia_perz
 */
class ExportContentTest extends UnitTestCase {

  /**
   * The perz http client service.
   *
   * @var \Drupal\acquia_perz\ClientFactory
   */

  protected $perzHttpClient;

  /**
   * The export queue service.
   *
   * @var \Drupal\acquia_perz_push\ExportQueue
   */
  protected $exportQueue;

  /**
   * The export tracker service.
   *
   * @var \Drupal\acquia_perz_push\ExportTracker
   */
  protected $exportTracker;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The uuid generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Perz entity helper.
   *
   * @var \Drupal\acquia_perz\EntityHelper
   */
  protected $entityHelper;

  /**
   * The ExportContent service.
   *
   * @var \Drupal\acquia_perz_push\ExportContent
   */
  protected $exportContent;

  /**
   * The state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Representation of the current HTTP request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The mocked symphony request.
   *
   * @var Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->perzHttpClient = $this->createMock(ClientFactory::class);
    $this->exportQueue = $this->createMock(ExportQueue::class);
    $this->exportTracker = $this->createMock(ExportTracker::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);
    $this->uuidGenerator = $this->createMock(UuidInterface::class);
    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->entityHelper = $this->createMock(EntityHelper::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->state = $this->createMock(StateInterface::class);
    $this->request = $this->createMock(Request::class);
    $config = $this->createMock(Config::class);

    $config->method('get')
      ->with('api.site_id')
      ->willReturn('TESTSITE');
    $this->configFactory->method('get')
      ->with('acquia_perz.settings')
      ->willReturn($config);

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($this->request);

    $this->request->expects($this->any())
      ->method('getHost')
      ->willReturn('localhost');
    $this->state->expects($this->any())
      ->method('get')
      ->willReturn('PERZTESTv3');

    $site_base_url = 'http://localhost';
    $subscriptionServiceMock = $this->createMock(Subscription::class);
    $subscriptionServiceMock->expects($this->any())
      ->method('getSubscription')
      ->willReturn([
        'acquia_perz' => [
          'api_key' => 'AUTH-TEST-1',
          'secret_key' => 'a491206bc0a61d51e4dfac8a81d5d1a7',
          'account_id' => 'PERZTESTv3',
          'endpoint' => $site_base_url,
        ],
      ]
      );

    $container = new ContainerBuilder();
    $container->set('request_stack', $this->requestStack);
    $container->set('state', $this->state);
    $container->set('acquia_connector.subscription', $subscriptionServiceMock);
    $container->set('config.factory', $this->configFactory);
    \Drupal::setContainer($container);

    $this->exportContent = new ExportContent($this->perzHttpClient, $this->exportQueue, $this->exportTracker, $this->configFactory, $this->entityTypeManager, $this->entityFieldManager, $this->renderer, $this->uuidGenerator, $this->dateFormatter, $this->time, $this->entityHelper);
  }

  /**
   * Tests use cases around normal cis request when entity has been exported.
   */
  public function testSendBulkNormalEndpoint() {
    $response = $this->exportContent->sendBulk([$this->once()]);
    $this->assertEquals(ExportTracker::EXPORTED, $response);
  }

  /**
   * Tests use cases around slow cis request when entity has been exported.
   *
   * @throws \Exception
   */
  public function testSendBulkSlowEndpoint() {
    // Override the sendBulk method and return value.
    $exportContent = $this->createMock(ExportContent::class);
    $exportContent->expects($this->any())
      ->method('sendBulk')
      ->with([$this->once()])
      ->will(
        $this->throwException(new TransferException("Connection timeout."))
      );

    $this->expectException(TransferException::class);
    $this->expectExceptionMessage('Connection timeout.');
    $exportContent->sendBulk([$this->once()]);
  }

}
