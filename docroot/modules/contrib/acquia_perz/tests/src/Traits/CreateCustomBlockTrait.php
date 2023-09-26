<?php

namespace Drupal\Tests\acquia_perz\Traits;

use Drupal\block_content\Entity\BlockContent;

/**
 * Provides helper methods for creating taxonomy fields.
 */
trait CreateCustomBlockTrait {

  /**
   * Create and return default custom block.
   *
   * @return \Drupal\block_content\Entity\BlockContent|\Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface
   *   The custom block entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createCustomBlock() {
    $block_content = BlockContent::create([
      'info' => $this->randomMachineName(32),
      'type' => 'basic',
    ]);
    $block_content->save();
    return $block_content;
  }

}
