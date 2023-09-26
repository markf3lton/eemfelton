<?php

namespace Drupal\Tests\acquia_perz_push\Kernel;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Tests\acquia_perz\Traits\CreateCustomBlockTrait;
use Drupal\Tests\acquia_perz\Traits\CustomParagraphsTestTrait;
use Drupal\Tests\acquia_perz\Traits\TextFieldCreationTrait;
use Drupal\Tests\EntityViewTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\user\RoleInterface;

/**
 * Tests custom view modes for Exported variations.
 *
 * @group acquia_perz
 */
class PersonalizationLabelTest extends PerzPushTestBase {

  use EntityViewTrait;
  use TextFieldCreationTrait;
  use CreateCustomBlockTrait;
  use CustomParagraphsTestTrait;
  use ParagraphsTestBaseTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Prevent error during content render.
    DateFormat::create([
      'id' => 'fallback',
      'label' => 'Fallback',
      'pattern' => 'Y-m-d',
    ])->save();

  }

  /**
   * Test for Node.
   */
  public function testNodeLabel() {
    $entity_type_id = 'node';
    $bundle = 'news';

    $this->drupalCreateContentType([
      'type' => $bundle,
      'name' => 'News',
    ]);

    $this->checkPersonalizationLabel($entity_type_id, $bundle, function (
      $title,
      $body_value,
      $text1_field_name,
      $text1_field_value
    ) use ($bundle) {
      $entity = $this->drupalCreateNode([
        'type' => $bundle,
        'title' => $title,
        'body' => ['value' => $body_value],
        $text1_field_name => ['value' => $text1_field_value],
      ]);
      $entity->save();
      return $entity;
    });
  }

  /**
   * Test for paragraphs.
   */
  public function testParagraphLabel() {

    $parent_entity_type_id = 'node';
    $parent_bundle = 'news';

    $paragraph_entity_type_id = 'paragraph';
    $paragraph_bundle = 'header_test';
    $parent_paragraph_field_name = 'field_paragraph';

    $this->drupalCreateContentType([
      'type' => $parent_bundle,
      'name' => $parent_bundle,
    ]);

    $this->createParagraphType($paragraph_bundle, 'body');
    $this->addParagraphFieldInBundle(
      $parent_entity_type_id,
      $parent_bundle,
      $parent_paragraph_field_name,
      $paragraph_bundle
    );

    $this->checkPersonalizationLabel($paragraph_entity_type_id, $paragraph_bundle, function (
      $label,
      $body_value,
      $text1_field_name,
      $text1_field_value
    ) use (
      $parent_bundle,
      $paragraph_bundle,
      $parent_paragraph_field_name
    ) {
      // Prevent adding paragraph twice as it will be added
      // when node is saved.
      $this->setUpPerzEntityTypes([]);
      $paragraph = $this->createParagraph(
        $paragraph_bundle,
        'paragraph title 1',
        TRUE
      );
      $paragraph->set('body', [['value' => $body_value]]);
      $paragraph->set($text1_field_name, [['value' => $text1_field_value]]);
      $paragraph->save();
      $this->createNodeWithParagraph(
        $parent_bundle,
        'Article title 1',
        'Article body 1',
        $parent_paragraph_field_name,
        $paragraph
      );
      return $paragraph;
    });
  }

  /**
   * Test for custom blocks.
   */
  public function testCustomBlocksLabel() {
    $entity_type_id = 'block_content';
    $bundle = 'basic';

    // Create a block content type.
    $block_content_type = BlockContentType::create([
      'id' => $bundle,
      'label' => 'A basic block type',
      'description' => "Provides a block type that is square.",
    ]);
    $block_content_type->save();

    // Create body textfield for block type and make it visible for
    // 'default' view mode.
    $body_field_name = 'body';
    $field_storage = $this->createTextFieldStorage(
      $entity_type_id,
      $body_field_name
    );
    $this->createTextField(
      $field_storage,
      $entity_type_id,
      $bundle
    );
    EntityViewDisplay::create([
      'targetEntityType' => $entity_type_id,
      'bundle' => $bundle,
      'mode' => 'default',
    ])->setStatus(TRUE)
      ->setComponent($body_field_name, [
        'label' => 'inline',
        'type' => 'string',
      ])
      ->save();

    $this->checkPersonalizationLabel($entity_type_id, $bundle, function (
      $label,
      $body_value,
      $text1_field_name,
      $text1_field_value
    ) {
      $custom_block = $this->createCustomBlock();
      $custom_block->set('info', $label);
      $custom_block->set('body', [['value' => $body_value]]);
      $custom_block->set($text1_field_name, [['value' => $text1_field_value]]);
      $custom_block->save();
      return $custom_block;
    });
  }

  /**
   * Test for Taxonomy Term.
   */
  public function testTaxonomyTermLabel() {
    $entity_type_id = 'taxonomy_term';
    $vocabulary = $this->createVocabulary();
    $bundle = $vocabulary->id();

    $this->checkPersonalizationLabel($entity_type_id, $bundle, function (
      $title,
      $body_value,
      $text1_field_name,
      $text1_field_value
    ) use ($vocabulary) {
      $entity = $this->createTerm($vocabulary, [
        'name' => $title,
        $text1_field_name => ['value' => $text1_field_value],
      ]);
      $entity->save();
      return $entity;
    });
  }

  /**
   * Checks & asserts label selected for Personazlition.
   *
   * Create custom view modes for entity type.
   *
   * Create text fields field_text1 and make field_text1
   * title for default view mode and do not select a title
   * for custom1 view mode.
   *
   * Check rendered output of exported variations if view modes contain
   * field_text_1 as title for default view and default paragraph title
   * for custom1 view mode.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $create_entity_callback
   *   Callback that returns single entity that should be tested.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function checkPersonalizationLabel($entity_type_id, $bundle, $create_entity_callback) {
    if (!is_callable($create_entity_callback)) {
      throw new \Exception('Create entity function is not callable');
    }
    // Field text 1.
    $text1_field_name = 'field_text1';
    $field_storage = $this->createTextFieldStorage(
      $entity_type_id,
      $text1_field_name
    );
    $this->createTextField(
      $field_storage,
      $entity_type_id,
      $bundle
    );

    $custom1_view_mode_name = 'custom1';

    // Create 2 custom view modes: custom1 and custom2.
    EntityViewMode::create([
      'id' => "{$entity_type_id}.{$custom1_view_mode_name}",
      'label' => 'Custom 1',
      'targetEntityType' => $entity_type_id,
    ])->save();

    // Create 2 custom view displays: custom1 and custom2.
    // Attach 'field_text1' for custom1 view display and
    // 'field_text2' for custom1 view display.
    EntityViewDisplay::create([
      'targetEntityType' => $entity_type_id,
      'bundle' => $bundle,
      'mode' => $custom1_view_mode_name,
    ])->setStatus(TRUE)
      ->setComponent($text1_field_name, [
        'label' => 'inline',
        'type' => 'string',
      ])
      ->save();

    $en_title = 'EN article 1';
    $en_body_value = 'EN article 1 body';
    $en_text1_field_value = 'EN article 1 text 1';
    $entity = $create_entity_callback(
      $en_title,
      $en_body_value,
      $text1_field_name,
      $en_text1_field_value
    );

    $this->setUpPerzEntityTypes([
      $entity_type_id => [
        $bundle => [
          'default' => [
            'render_role' => 'anonymous',
            'preview_image' => '',
            'personalization_label' => $text1_field_name,
          ],
          $custom1_view_mode_name => [
            'render_role' => 'anonymous',
            'preview_image' => '',
            'personalization_label' => '',
          ],
        ],
      ],
    ]);

    $export_content = $this->container->get('acquia_perz_push.export_content');
    // User has permission to view everything.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content' => TRUE,
      'access comments' => TRUE,
    ]);

    $payload = $export_content->getEntityPayload($entity_type_id, $entity->id());
    $en_default_variation = $payload[0];
    $this->assertSame($en_text1_field_value, $en_default_variation['label']);

    $en_default_variation = $payload[1];
    if ($entity_type_id == 'paragraph') {
      $this->assertSame('Article title 1 > field_paragraph', $en_default_variation['label']);
    }
    else {
      $this->assertSame($en_title, $en_default_variation['label']);
    }
  }

}
