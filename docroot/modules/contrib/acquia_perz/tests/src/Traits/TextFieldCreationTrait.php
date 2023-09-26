<?php

namespace Drupal\Tests\acquia_perz\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides helper methods for creating taxonomy fields.
 */
trait TextFieldCreationTrait {

  /**
   * Create field storage of the text type.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $field_name
   *   The field name.
   * @param int $cardinality
   *   The field cardinality (-1 for unlimited).
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\field\Entity\FieldStorageConfig
   *   Returns field storage config.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTextFieldStorage($entity_type_id, $field_name, $cardinality = -1) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'type' => 'string',
      'settings' => [
        'max_length' => 255,
        'is_ascii' => FALSE,
        'case_sensitive' => FALSE,
      ],
      'cardinality' => $cardinality,
    ]);
    $field_storage->save();
    return $field_storage;
  }

  /**
   * Create field of the text type.
   *
   * @param \Drupal\field\Entity\FieldStorageConfig $field_storage
   *   The field storage.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\field\Entity\FieldConfig
   *   The field config.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTextField(FieldStorageConfig $field_storage, $entity_type_id, $bundle) {
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'field_type' => 'string',
    ]);
    $field->save();
    return $field;
  }

}
