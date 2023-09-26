<?php

namespace Drupal\acquia_perz_push\Commands;

use Drupal\acquia_perz\ClientFactory;
use Drupal\acquia_perz_push\ExportQueue;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for working with queue (cis push).
 *
 * @package Drupal\acquia_perz_push\Commands
 */
class QueueCommands extends DrushCommands {

  /**
   * The export queue service.
   *
   * @var \Drupal\acquia_perz_push\ExportQueue
   */
  protected $exportQueue;

  /**
   * The Entity Helper service.
   *
   * @var \Drupal\acquia_perz\ClientFactory
   */
  protected $clientFactory;

  /**
   * QueueCommands constructor.
   *
   * @param \Drupal\acquia_perz_push\ExportQueue $export_queue
   *   The export queue.
   * @param \Drupal\acquia_perz\ClientFactory $client_factory
   *   The entity helper service.
   */
  public function __construct(ExportQueue $export_queue, ClientFactory $client_factory) {
    $this->exportQueue = $export_queue;
    $this->clientFactory = $client_factory;
  }

  /**
   * Rescan content.
   *
   * @command acquia:perz-enqueue-content
   * @aliases ap-ec
   */
  public function enqueueContent() {
    $this->exportQueue->rescanContentBulk();
    drush_backend_batch_process();
    $this->output->writeln(dt("All content has been scanned and added to the Queue."));
  }

  /**
   * Purge a queue.
   *
   * @command acquia:perz-purge-queue
   * @aliases ap-pq
   */
  public function purgeQueue() {
    $this->exportQueue->purgeQueue();
    $this->output->writeln(dt("All content has been purged from the Queue."));
  }

  /**
   * Return count of queue items.
   *
   * @command acquia:perz-queue-items
   * @aliases ap-qi
   */
  public function queueItems() {
    $queue_count = intval($this->exportQueue->getQueueCount());
    $this->output->writeln(dt("The number of items in the queue @queue_count.",
      ['@queue_count' => $queue_count]));
  }

  /**
   * Export content.
   *
   * @command acquia:perz-process-queue
   * @aliases ap-pq
   */
  public function processQueue() {
    $this->exportQueue->exportBulkQueueItems();
    drush_backend_batch_process();
    $this->output->writeln(dt("All content has been exported to Personalization from the Queue."));
  }

  /**
   * Deletes this site's content from the Personalization service.
   *
   * @command acquia:perz-purge-current
   * @aliases ap-pc
   */
  public function deleteContent() {
    $question = "Are you sure you want to delete this site's content from Personalization? This action cannot be undone. You will need to re-export Drupal content to continue using Personalization.";
    $confirm = $this->io()->confirm($question, FALSE);
    if ($confirm) {
      $this->clientFactory->deleteContentFromCis();
      $this->output->writeln(dt("This site's content has been deleted from the Personalization service."));
    }
    else {
      $this->output->writeln(dt("This operation has been aborted."));
    }

  }

  /**
   * Delete all contents from all sites from Personalization Service.
   *
   * @command acquia:perz-purge-all
   * @aliases ap-pa
   */
  public function deleteAllContent() {
    $question = "Are you sure you want to delete all contents of all sites from Personalization? This action cannot be undone. This will delete all contents which have been exported from all sites. You will need to re-export Drupal content from all your active sites to continue using Personalization.";
    $confirm = $this->io()->confirm($question, FALSE);
    if ($confirm) {
      $this->clientFactory->deleteAllContentsFromCis();
      $this->output->writeln(dt("All content has been deleted from the Personalization service."));
    }
    else {
      $this->output->writeln(dt("This operation has been aborted."));
    }

  }

}
