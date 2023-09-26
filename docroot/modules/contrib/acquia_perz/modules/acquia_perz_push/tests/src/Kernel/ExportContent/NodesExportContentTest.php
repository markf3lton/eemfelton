<?php

namespace Drupal\Tests\acquia_perz_push\Kernel\ExportLogic;

use Drupal\Tests\acquia_perz_push\Kernel\PerzPushTestBase;

/**
 * Tests for export content (node).
 *
 * @group acquia_perz
 */
class NodesExportContentTest extends PerzPushTestBase {

  /**
   * {@inheritdoc}
   */
  private $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  private $bundle = 'article';

  /**
   * {@inheritdoc}
   */
  private $entityConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityConfig = [
      $this->entityTypeId => [
        $this->bundle => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
    ];
  }

  /**
   * Tests slow entity save.
   *
   * Tests use cases around slow cis request when entity has been saved.
   * Use cases:
   * 1. After using 'slow' mode the node should go to the queue.
   * Tracking table should contain 'timeout_export' row.
   * 2. Try to export a node from the queue with 'slow' mode. In this
   * case queue should still has 1 item but id should be updated as queue item
   * is supposed to be recreated. Tracking table should still contain
   * 'timeout_export' row but with modified date.
   * 3. Try to export a node from the queue with 'normal' mode. In this case
   * the queue should be empty, tracking table should contain 1 row with
   * 'exported' status.
   *
   * @throws \Exception
   */
  public function testSlowEntitySave() {
    $this->checkSlowEntitySave(
      $this->entityConfig,
      $this->entityTypeId,
      function () {
        return $this->drupalCreateNode(['type' => $this->bundle]);
      }
    );
  }

  /**
   * Tests normal entity save.
   *
   * Tests use case around normal cis request when entity has been saved.
   * After entity has been saved the queue should be empty,
   * tracking table should contain 1 row with 'exported' status.
   *
   * @throws \Exception
   */
  public function testNormalEntitySave() {
    $this->checkNormalEntitySave(
      $this->entityConfig,
      function () {
        return $this->drupalCreateNode(['type' => $this->bundle]);
      }
    );
  }

  /**
   * Tests on-boarding process.
   *
   * Tests use case for on-boarding process when entities that has been
   * presented in the drupal site will go to the queue and are exported to CIS
   * in a bulk.
   *
   * Use cases:
   *
   * 1. After clicking Rescan button (calling service) the existing entities
   * should go to the queue.
   * 2. Number of queue items depends on number of entities and bulk size.
   * 3. Check that bulk size queue logic works ok and queue contains proper
   * entities.
   * 4. Check purge service that should empty a queue.
   * 5. Check export bulk process - 2 variations: slow endpoint & normal
   * endpoint.
   * a. Slow endpoint: Check that queue is not empty and contains same
   * amount of queue items but with different queue item ids. Also check
   * that entities in the new queue items are valid. Check tracking table
   * and export_timeout statuses.
   * b. Normal endpoint: Check that queue is empty and tracking table status
   * has exported value.
   *
   * @throws \Exception
   */
  public function testOnboarding() {
    $this->checkOnboarding(
      $this->entityConfig,
      5,
      5,
      function () {
        return $this->drupalCreateNode(['type' => $this->bundle]);
      }
    );
  }

}
