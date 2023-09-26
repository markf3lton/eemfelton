<?php

namespace Drupal\Tests\acquia_perz\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Test trait for Custom Paragraphs tests.
 */
trait CustomParagraphsTestTrait {

  use TestFileCreationTrait;

  protected function createParagraph($paragraph_type, $paragraph_title, $paragraph_status) {
    $paragraph = Paragraph::create([
      'type' => $paragraph_type,
      'field_title' => $paragraph_title,
      'field_status' => $paragraph_status,
    ]);
    $paragraph->save();
    return $paragraph;
  }

  protected function createNodeWithParagraph($bundle, $node_title, $node_body, $node_paragraph_field_name, $paragraph) {
    $node = Node::create([
      'type' => $bundle,
      'title' => $node_title,
      'body' => ['value' => $node_body],
      $node_paragraph_field_name => [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ],
    ]);
    $node->save();
    return $node;
  }

  protected function createParagraphType($paragraph_type, $string_field_name = 'field_title', $boolean_field_name = 'field_status') {
    $this->addParagraphsType($paragraph_type);
    $this->addFieldtoParagraphType($paragraph_type, $string_field_name, 'string');
    $this->addFieldtoParagraphType($paragraph_type, $boolean_field_name, 'boolean');

  }

  protected function addParagraphFieldInBundle($entity_type_id, $bundle, $paragraphs_field_name, $paragraphs_type, $view_mode = 'default', $cardinality = -1) {
    $field_storage = FieldStorageConfig::loadByName($entity_type_id, $paragraphs_field_name);
    if (!$field_storage) {
      // Add a paragraphs field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $paragraphs_field_name,
        'entity_type' => $entity_type_id,
        'type' => 'entity_reference_revisions',
        'cardinality' => $cardinality,
        'settings' => [
          'target_type' => 'paragraph',
        ],
      ]);
      $field_storage->save();
    }
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'negate' => 0,
          'target_bundles' => [$paragraphs_type => $paragraphs_type],
          'target_bundles_drag_drop' => [
            $paragraphs_type => [
              'enabled' => TRUE,
            ],
          ],
        ],
        'field_type' => 'entity_reference_revisions',
      ],
    ]);
    $field->save();

    $view_display = \Drupal::service('entity_display.repository')->getViewDisplay($entity_type_id, $bundle, $view_mode);
    $view_display->setComponent($paragraphs_field_name, [
      'label' => 'above',
      'type' => 'entity_reference_revisions_entity_view',
      'settings' => [
        'view_mode' => $view_mode,
        'link' => '',
      ],
      'region' => 'content',
    ]);
    $view_display->save();

  }

  /**
   * Adds a field to a given Node type.
   *
   * @param string $content_type_id
   *   The content type id (bundle).
   * @param string $field_name
   *   Field name to be used.
   * @param string $field_type
   *   Type of the field.
   * @param string $entity_type
   *   The entity type id of the field.
   * @param string $view_mode
   *   The view mode.
   * @param array $storage_settings
   *   Settings for the field storage.
   */
  protected function addFieldtoNodeType($content_type_id, $field_name, $field_type, $entity_type, $view_mode, array $storage_settings = []) {
    // Add a paragraphs field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $field_type,
      'cardinality' => 1,
      'settings' => $storage_settings,
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $content_type_id,
      'settings' => [],
    ]);
    $field->save();

    $field_type_definition = \Drupal::service('plugin.manager.field.field_type')->getDefinition($field_type);

    $form_display = \Drupal::service('entity_display.repository')->getFormDisplay($entity_type, $content_type_id);
    $form_display->setComponent($field_name, ['type' => $field_type_definition['default_widget']])
      ->save();

    $view_display = \Drupal::service('entity_display.repository')->getViewDisplay($entity_type, $content_type_id, $view_mode);
    $view_display->setComponent($field_name, ['label' => 'above', 'type' => $field_type_definition['default_formatter']]);
    $view_display->save();
  }

  protected function addTaxonomyFieldInNode($bundle, $field_name, $entity_type, $view_mode, $widget_type = 'options_select') {
    $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
    if (!$field_storage) {
      // Add a paragraphs field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'entity_reference',
        'cardinality' => '-1',
        'settings' => [
          'target_type' => 'taxonomy_term',
        ],
      ]);
      $field_storage->save();
    }
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['tags' => 'tags']],
      ],
    ]);
    $field->save();

    $form_display = \Drupal::service('entity_display.repository')->getFormDisplay($entity_type, $bundle);
    $form_display = $form_display->setComponent($field_name, ['type' => $widget_type]);
    $form_display->save();

    $view_display = \Drupal::service('entity_display.repository')->getViewDisplay($entity_type, $bundle, $view_mode);
    $view_display->setComponent($field_name, ['type' => 'entity_reference_entity_view']);
    $view_display->save();
  }

}
