<?php

namespace Drupal\Tests\acquia_perz_push\Unit\Service;

use Drupal\acquia_perz_push\ExportQueue;
use Drupal\acquia_perz_push\ExportTracker;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\acquia_perz_push\ExportQueue
 * @group acquia_perz
 */
class ExportQueueTest extends UnitTestCase {

  /**
   * The export tracker service.
   *
   * @var \Drupal\acquia_perz_push\ExportTracker
   */
  private $exportTracker;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Export Content Queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The Export Content Queue.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The Queue Worker.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $queueManager;

  /**
   * The messenger object.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The export queue service.
   *
   * @var \Drupal\acquia_perz_push\ExportQueue
   */
  protected $exportQueue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->exportTracker = $this->createMock(ExportTracker::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->queueFactory = $this->createMock(QueueFactory::class);
    $this->queueManager = $this->createMock(QueueWorkerManager::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->config = $this->createMock(Config::class);
  }

  /**
   * Test the getQueueCount method.
   *
   * @covers ::getQueueCount
   */
  public function testGetQueueCount() {
    $this->queue = $this->createMock('Drupal\Core\Queue\QueueInterface');
    $this->queue->method('numberOfItems')->willReturn(100);
    $this->queueFactory->method('get')->with('acquia_perz_push_content_export')->willReturn($this->queue);
    $this->exportQueue = new ExportQueue($this->exportTracker, $this->configFactory, $this->renderer, $this->entityTypeManager, $this->queueFactory, $this->queueManager, $this->messenger);
    $count = $this->exportQueue->getQueueCount();
    $this->assertEquals(100, $count);
  }

  /**
   * Test the getSettingsConfigItem method.
   *
   * @covers ::getSettingsConfigItem
   */
  public function testGetSettingsConfigItem() {
    $this->config
      ->method('get')
      ->with('cim.queue_bulk_max_size')
      ->willReturn(20);

    $this->configFactory->method('get')->with('acquia_perz_push.settings')->willReturn($this->config);
    $this->exportQueue = new ExportQueue($this->exportTracker, $this->configFactory, $this->renderer, $this->entityTypeManager, $this->queueFactory, $this->queueManager, $this->messenger);
    $actual_value = $this->exportQueue->getSettingsConfigItem('cim.queue_bulk_max_size');
    $this->assertEquals(20, $actual_value);
  }

  /**
   * Test the getEntityTypesConfig method.
   *
   * @covers ::getEntityTypesConfig
   */
  public function testGetEntityTypesConfig() {
    $view_modes = [
      'node' => [
        'article' => [
          'default' => 1,
        ],
      ],
    ];
    $this->config
      ->method('get')
      ->willReturn($view_modes);

    $this->configFactory->method('get')->with('acquia_perz.entity_config')->willReturn($this->config);
    $this->exportQueue = new ExportQueue($this->exportTracker, $this->configFactory, $this->renderer, $this->entityTypeManager, $this->queueFactory, $this->queueManager, $this->messenger);
    $actual_value = $this->exportQueue->getEntityTypesConfig();
    $this->assertEquals($view_modes['node']['article']['default'], $actual_value['node']['article']['default']);
  }

}
