<?php

namespace Drupal\Tests\acquia_perz\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides helper methods for creating taxonomy fields.
 */
trait TaxonomyFieldCreationTrait {

  /**
   * Create field storage of the taxonomy term type.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $field_name
   *   The field name.
   * @param int $cardinality
   *   The field cardinality (-1 for unlimited).
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\field\Entity\FieldStorageConfig
   *   The field storage.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTaxonomyTermFieldStorage($entity_type_id, $field_name, $cardinality = -1) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => $cardinality,
    ]);
    $field_storage->save();
    return $field_storage;
  }

  /**
   * Create field of the taxonomy term type.
   *
   * @param \Drupal\field\Entity\FieldStorageConfig $field_storage
   *   The field storage.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param array $vocabularies
   *   The list of vocabularies:
   *   Format:
   *   [
   *      vocabulary_id1 => vocabulary_id1,
   *      vocabulary_id2 => vocabulary_id2,
   *   ].
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\field\Entity\FieldConfig
   *   The field config.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTaxonomyTermField(FieldStorageConfig $field_storage, $entity_type_id, $bundle, array $vocabularies) {
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'field_type' => 'entity_reference',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => $vocabularies,
        ],
      ],
    ]);
    $field->save();
    return $field;
  }

}
