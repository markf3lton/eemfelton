<?php

namespace Drupal\Tests\acquia_perz_push\Kernel\ExportLogic;

use Drupal\Tests\acquia_perz\Traits\CreateCustomBlockTrait;
use Drupal\Tests\acquia_perz_push\Kernel\PerzPushTestBase;

/**
 * Tests for export content (custom block).
 *
 * @group acquia_perz
 */
class CustomBlocksExportContentTest extends PerzPushTestBase {

  use CreateCustomBlockTrait;

  /**
   * {@inheritdoc}
   */
  private $entityTypeId = 'block_content';

  /**
   * {@inheritdoc}
   */
  private $bundle = 'basic';

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
        return $this->createCustomBlock();
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
        return $this->createCustomBlock();
      }
    );
  }

  /**
   * Tests on-boarding process.
   *
   * Tests use case around normal endpoint when entity has been saved.
   * After entity has been saved the queue should be empty,
   * tracking table should contain 1 row with 'exported' status.
   *
   * @throws \Exception
   */
  public function testOnboarding() {
    $this->checkOnboarding(
      $this->entityConfig,
      5,
      5,
      function () {
        return $this->createCustomBlock();
      }
    );
  }

}
