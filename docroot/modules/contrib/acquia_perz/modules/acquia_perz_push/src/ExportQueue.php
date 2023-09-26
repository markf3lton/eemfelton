<?php

namespace Drupal\acquia_perz_push;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Exception\TransferException;

/**
 * Implements an Export Queue for CIS.
 */
class ExportQueue {

  use StringTranslationTrait;
  use DependencySerializationTrait;

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
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ExportTracker $export_tracker,
                              ConfigFactoryInterface $config_factory,
                              RendererInterface $renderer,
                              EntityTypeManagerInterface $entity_type_manager,
                              QueueFactory $queue_factory,
                              QueueWorkerManager $queue_manager,
                              MessengerInterface $messenger) {
    $this->exportTracker = $export_tracker;
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->queue = $queue_factory->get('acquia_perz_push_content_export');
    $this->queueManager = $queue_manager;
    $this->messenger = $messenger;
  }

  /**
   * Obtains the number of items in the export queue.
   *
   * @return mixed
   *   The number of items in the export queue.
   */
  public function getQueueCount() {
    return $this->queue->numberOfItems();
  }

  /**
   * Add entities to the Export Queue.
   *
   * @param string $action
   *   The action.
   * @param array $entities
   *   Entities that should be exported.
   *   Format:
   *   [
   *    entity_type: '',
   *    entity_id: '',
   *    entity_uuid: ''
   *   ].
   * @param string $langcode
   *   Language code of the entity translation that should be exported.
   *   'all' value means that all entity translations should be exported.
   */
  public function addBulkQueueItem($action, array $entities, $langcode = 'all') {
    $this->queue->createItem([
      'action' => $action,
      'entities' => $entities,
      'langcode' => $langcode,
    ]);
  }

  /**
   * Remove all the export queue items.
   */
  public function purgeQueue() {
    $this->queue->deleteQueue();
  }

  /**
   * Get config item from the perz settings.
   *
   * @param string $config_item
   *   The config item name.
   *
   * @return array|mixed|null
   *   Returns The config item value.
   */
  public function getSettingsConfigItem($config_item) {
    $settings = $this->configFactory->get('acquia_perz_push.settings');
    return $settings->get($config_item);
  }

  /**
   * Get entity types from Entity configuration form.
   *
   * @return array|mixed|null
   *   Returns list of entity types > bundle > view modes
   *   from Entity configuration form.
   */
  public function getEntityTypesConfig() {
    return $this->configFactory
      ->get('acquia_perz.entity_config')
      ->get('view_modes');
  }

  /**
   * Enqueue content (bulk).
   */
  public function rescanContentBulk($use_batch = TRUE) {
    if ($this->getQueueCount() > 0) {
      $this->purgeQueue();
    }
    $queue_bulk_max_size = $this->getSettingsConfigItem('cis.queue_bulk_max_size');
    $batch = [
      'title' => $this->t("Rescan Content Bulk Process"),
      'operations' => [],
      'finished' => [[$this, 'rescanBatchFinished'], []],
    ];
    $entity_types = $this->getEntityTypesConfig();
    $bulk = [];
    foreach ($entity_types as $entity_type_id => $bundles) {
      $entity_ids = $this->getRescannedEntities($entity_type_id, $bundles);
      foreach ($entity_ids as $entity_id) {
        $entity = $this->entityTypeManager
          ->getStorage($entity_type_id)
          ->load($entity_id);
        $bulk[] = [
          'entity_type_id' => $entity_type_id,
          'entity_id' => $entity_id,
          'entity_uuid' => $entity->uuid(),
        ];
        if (count($bulk) === $queue_bulk_max_size) {
          if ($use_batch) {
            $batch['operations'][] = [
              [$this, 'rescanBatchBulkProcess'],
              [$bulk],
            ];
          }
          else {
            $this->rescanBatchBulkProcess($bulk);
          }
          $bulk = [];
        }
      }
    }
    if ($use_batch) {
      // Send the rest if it's present.
      if (!empty($bulk)) {
        $batch['operations'][] = [
          [$this, 'rescanBatchBulkProcess'],
          [$bulk],
        ];
      }
      // Adds the batch sets.
      batch_set($batch);
      return;
    }
    if (!empty($bulk)) {
      $this->rescanBatchBulkProcess($bulk);
    }
  }

  /**
   * Returns entity ids by entity type id and passed bundles.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param array $bundles
   *   List of bundles of entity type.
   */
  protected function getRescannedEntities($entity_type_id, array $bundles) {
    // Check only bundles with at least one view mode activated
    // besides 'acquia_perz_push_preview_image' view mode.
    $available_bundles = [];
    foreach ($bundles as $bundle => $view_modes) {
      $view_modes = array_keys($view_modes);
      if (count($view_modes) === 1
        && in_array('acquia_perz_push_preview_image', $view_modes)) {
        continue;
      }
      $available_bundles[] = $bundle;
    }
    // Skip entity type without activated bundles.
    if (empty($available_bundles)) {
      return [];
    }
    $bundle_property_name = $this
      ->entityTypeManager
      ->getStorage($entity_type_id)
      ->getEntityType()
      ->getKey('bundle');
    $query = $this
      ->entityTypeManager
      ->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(TRUE);
    // For single-bundle entity types like 'user'
    // we don't use bundle related property.
    if (!empty($bundle_property_name)) {
      $query = $query->condition($bundle_property_name, $available_bundles, 'IN');
    }
    return $query->execute();
  }

  /**
   * Rescan content batch bulk processing callback.
   *
   * @param array $entities
   *   The list of entities.
   *   Format:
   *   [
   *    [
   *      entity_type_id: 'node'
   *      entity_id: 5
   *      entity_uuid: '...'
   *    ],
   *    ...
   *   ].
   */
  public function rescanBatchBulkProcess(array $entities) {
    $this->addBulkQueueItem('insert_or_update', $entities);
  }

  /**
   * Rescan content batch finished callback.
   *
   * @param bool $success
   *   Whether the batch process succeeded or not.
   * @param array $results
   *   The results array.
   * @param array $operations
   *   An array of operations.
   */
  public function rescanBatchFinished($success, array $results, array $operations) {
    if ($success) {
      $this->messenger->addMessage($this->t("The contents are successfully rescanned."));
    }
    else {
      $error_operation = reset($operations);
      $this->messenger->addMessage($this->t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]
      ));
    }
    // Providing a report on the items processed by the queue.
    $elements = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#items' => $results,
    ];
    $queue_report = $this->renderer->render($elements);
    $this->messenger->addMessage($queue_report);
  }

  /**
   * Process all queue items with batch API (bulk).
   */
  public function exportBulkQueueItems() {
    // Create batch which collects all the specified queue items and process
    // them one after another.
    $batch = [
      'title' => $this->t("Process Export Queue"),
      'operations' => [],
      'finished' => [[$this, 'exportBatchFinished'], []],
      'progressive' => FALSE,
    ];

    // Count number of the items in this queue, create enough batch operations.
    for ($i = 0; $i < $this->getQueueCount(); $i++) {
      // Create batch operations.
      $batch['operations'][] = [[$this, 'exportBulkBatchProcess'], []];
    }

    // Adds the batch sets.
    batch_set($batch);
  }

  /**
   * Export bulk batch processing callback for all operations.
   *
   * @param mixed $context
   *   The context array.
   */
  public function exportBulkBatchProcess(&$context) {

    $queue_worker = $this->queueManager->createInstance('acquia_perz_push_content_export_bulk');
    // Get a queued item.
    if ($item = $this->queue->claimItem()) {
      try {
        // Generating a list of entities.
        $msg_label = $this->t('(@entities)', [
          '@entities' => serialize($item->data['entities']),
        ]);

        // Process item.
        $bulks_processed = $queue_worker->processItem($item->data);
        if ($bulks_processed == FALSE) {
          // Indicate that the item could not be processed.
          if ($bulks_processed === FALSE) {
            $message = $this->t('There was an error processing bulks: @bulk and their dependencies. The item has been sent back to the queue to be processed again later. Check your logs for more info.', [
              '@entities' => $msg_label,
            ]);
          }
          else {
            $message = $this->t('No processing was done for bulks: @bulk and their dependencies. The item has been sent back to the queue to be processed again later. Check your logs for more info.', [
              '@entities' => $msg_label,
            ]);
          }
          $context['message'] = $message->jsonSerialize();
          $context['results'][] = $message->jsonSerialize();
        }
        else {
          // If everything was correct, delete processed item from the queue.
          $this->queue->deleteItem($item);

          // Creating a text message to present to the user.
          $message = $this->t('Processed items: (@count @label sent).', [
            '@count' => $bulks_processed,
            '@label' => $bulks_processed == 1 ? $this->t('bulk') : $this->t('bulks'),
          ]);
          $context['message'] = $message->jsonSerialize();
          $context['results'][] = $message->jsonSerialize();
        }
      }
      catch (\RuntimeException $e) {
        if ($e instanceof SuspendQueueException
          || $e instanceof TransferException) {
          switch ($item->data['action']) {
            case 'insert_or_update':
              foreach ($item->data['entities'] as $entity_item) {
                $this->exportTracker->trackEntity(
                  $entity_item['entity_type_id'],
                  $entity_item['entity_id'],
                  $item->data['langcode'],
                  'exportTimeout'
                );
              }
              break;
          }

          $this->addBulkQueueItem(
            $item->data['action'],
            $item->data['entities'],
            $item->data['langcode']
          );
          $this->queue->deleteItem($item);
        }
      }
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch process succeeded or not.
   * @param array $results
   *   The results array.
   * @param array $operations
   *   An array of operations.
   */
  public function exportBatchFinished($success, array $results, array $operations) {
    if ($success) {
      $this->messenger->addMessage($this->t("The contents are successfully exported."));
    }
    else {
      $error_operation = reset($operations);
      $this->messenger->addMessage($this->t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]
      ));
    }

    // Providing a report on the items processed by the queue.
    $elements = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#items' => $results,
    ];
    $queue_report = $this->renderer->render($elements);
    $this->messenger->addMessage($queue_report);
  }

}
