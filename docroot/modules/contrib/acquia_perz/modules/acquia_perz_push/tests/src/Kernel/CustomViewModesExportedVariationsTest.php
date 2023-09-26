<?php

namespace Drupal\Tests\acquia_perz_push\Kernel;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\acquia_perz\Traits\CreateCustomBlockTrait;
use Drupal\Tests\acquia_perz\Traits\CustomParagraphsTestTrait;
use Drupal\Tests\acquia_perz\Traits\TextFieldCreationTrait;
use Drupal\Tests\EntityViewTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests custom view modes for Exported variations.
 *
 * @group acquia_perz
 */
class CustomViewModesExportedVariationsTest extends PerzPushTestBase {

  use EntityViewTrait;
  use TextFieldCreationTrait;
  use CreateCustomBlockTrait;
  use CustomParagraphsTestTrait;
  use ParagraphsTestBaseTrait;

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

    DateFormat::create([
      'id' => 'html_date',
      'label' => 'Html Date',
      'pattern' => 'Y-m-d',
    ])->save();

    DateFormat::create([
      'id' => 'html_time',
      'label' => 'Html Time',
      'pattern' => 'H:i:s',
    ])->save();

  }

  /**
   * Test for nodes.
   */
  public function testNodes() {
    $entity_type_id = 'node';
    $bundle = 'news';

    $this->drupalCreateContentType([
      'type' => $bundle,
      'name' => 'News',
    ]);

    $this->checkCustomViewModes($entity_type_id, $bundle, function (
      $title,
      $body_value,
      $text1_field_name,
      $text1_field_value,
      $text2_field_name,
      $text2_field_value
    ) use ($bundle) {
      $entity = $this->drupalCreateNode([
        'type' => $bundle,
        'title' => $title,
        'body' => ['value' => $body_value],
        $text1_field_name => ['value' => $text1_field_value],
        $text2_field_name => ['value' => $text2_field_value],
      ]);
      $entity->save();
      return $entity;
    });
  }

  /**
   * Test for unpublished nodes.
   */
  public function testUnpublishedNodes() {
    $entity_type_id = 'node';
    $bundle = 'news';

    $this->drupalCreateContentType([
      'type' => $bundle,
      'name' => 'News',
    ]);

    $this->checkCustomViewModesUnpublished($entity_type_id, $bundle, function (
      $title,
      $body_value,
      $text1_field_name,
      $text1_field_value,
      $text2_field_name,
      $text2_field_value
    ) use ($bundle) {
      $entity = $this->drupalCreateNode([
        'type' => $bundle,
        'title' => $title,
        'body' => ['value' => $body_value],
        $text1_field_name => ['value' => $text1_field_value],
        $text2_field_name => ['value' => $text2_field_value],
        'status' => 0,
      ]);
      $entity->save();
      return $entity;
    });

  }

  /**
   * Test for custom blocks.
   */
  public function testCustomBlocks() {
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

    $this->checkCustomViewModes($entity_type_id, $bundle, function (
      $label,
      $body_value,
      $text1_field_name,
      $text1_field_value,
      $text2_field_name,
      $text2_field_value
    ) {
      $custom_block = $this->createCustomBlock();
      $custom_block->set('info', $label);
      $custom_block->set('body', [['value' => $body_value]]);
      $custom_block->set($text1_field_name, [['value' => $text1_field_value]]);
      $custom_block->set($text2_field_name, [['value' => $text2_field_value]]);
      $custom_block->save();
      return $custom_block;
    });
  }

  /**
   * Test for paragraphs.
   */
  public function testParagraphs() {

    user_role_change_permissions('authenticated', ['access content' => TRUE]);
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

    $this->checkCustomViewModes($paragraph_entity_type_id, $paragraph_bundle, function (
      $label,
      $body_value,
      $text1_field_name,
      $text1_field_value,
      $text2_field_name,
      $text2_field_value
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
      $paragraph->set($text2_field_name, [['value' => $text2_field_value]]);
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

}
