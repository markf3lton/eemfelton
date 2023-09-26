<?php

namespace Drupal\Tests\acquia_perz\Traits;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides common helper methods for Custom block's related tests.
 */
trait EntityHelperTestTrait {

  /**
   * Assert base entity variation properties during Export content routine.
   *
   * Properties:
   * - uuid
   * - entity type id
   * - view_mode
   * - langcode
   * - entity title.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param array $variation
   *   The variation array.
   * @param string $langcode
   *   The language code.
   * @param string $view_mode
   *   The view mode.
   */
  protected function assertVariationBaseValues(EntityInterface $entity, array $variation, $langcode, $view_mode) {
    $entity_bundle = $entity->bundle();
    $this->assertSame($entity->uuid(), $variation['content_uuid']);
    $this->assertSame($entity_bundle, $variation['content_type']);
    $this->assertSame($view_mode, $variation['view_mode']);
    $this->assertSame($langcode, $variation['language']);
  }

}
