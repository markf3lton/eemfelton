<?php

namespace Drupal\Tests\acquia_perz_push\Kernel\ExportLogic;

use Drupal\Tests\acquia_perz\Traits\CreateCustomBlockTrait;
use Drupal\Tests\acquia_perz_push\Kernel\PerzPushTestBase;

/**
 * Tests for export content (random entities).
 *
 * @group acquia_perz
 */
class RandomEntitiesExportContentTest extends PerzPushTestBase {

  use CreateCustomBlockTrait;

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
      'node' => [
        'article' => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
      'block_content' => [
        'basic' => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
    ];
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
        return $this->createRandomEntity();
      }
    );
  }

  /**
   * Create node/custom-block entity randomly.
   *
   * @return \Drupal\block_content\Entity\BlockContent|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\node\NodeInterface
   *   Return node or custom-block entity randomly.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createRandomEntity() {
    $random_float = rand(0, 10) / 10;
    if ($random_float <= 0.5 && $random_float > 0) {
      return $this->createCustomBlock();
    }
    else {
      return $this->drupalCreateNode(['type' => 'article']);
    }
  }

}
