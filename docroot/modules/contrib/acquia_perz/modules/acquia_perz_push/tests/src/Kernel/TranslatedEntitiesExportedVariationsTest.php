<?php

namespace Drupal\Tests\acquia_perz_push\Kernel;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\acquia_perz\Traits\CreateCustomBlockTrait;
use Drupal\Tests\acquia_perz\Traits\CustomParagraphsTestTrait;
use Drupal\Tests\EntityViewTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests translated entities for Exported variations.
 *
 * @group acquia_perz
 */
class TranslatedEntitiesExportedVariationsTest extends PerzPushTestBase {

  use EntityViewTrait;
  use CreateCustomBlockTrait;
  use CustomParagraphsTestTrait;
  use ParagraphsTestBaseTrait;

  protected $translationLangcode = 'es';

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
   * Test translated nodes.
   */
  public function testNodes() {
    $default_langcode = 'en';
    ConfigurableLanguage::createFromLangcode($this->translationLangcode)->save();

    $entity_type_id = 'node';
    $bundle = 'news';

    $this->drupalCreateContentType([
      'type' => $bundle,
      'name' => $bundle,
    ]);
    user_role_change_permissions('anonymous', ['access content' => TRUE]);
    $this->checkTranslation($this->translationLangcode, $entity_type_id, $bundle,
      function () use ($bundle, $default_langcode) {
        $entity = $this->drupalCreateNode([
          'type' => $bundle,
          'title' => $default_langcode . ' article 1',
          'body' => ['value' => $default_langcode . ' article body'],
        ]);
        $entity->save();
        return $entity;
      }
    );
  }

  /**
   * Test translated custom blocks.
   */
  public function testCustomBlocks() {
    ConfigurableLanguage::createFromLangcode($this->translationLangcode)->save();

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

    $this->checkTranslation($this->translationLangcode, $entity_type_id, $bundle,
      function ($label, $body_value) {
        $custom_block = $this->createCustomBlock();
        $custom_block->set('info', $label);
        $custom_block->set('body', [['value' => $body_value]]);
        $custom_block->save();
        return $custom_block;
      }
    );
  }

  /**
   * Test translated paragraphs.
   */
  public function testParagraphs() {
    ConfigurableLanguage::createFromLangcode($this->translationLangcode)->save();

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

    $this->checkTranslation($this->translationLangcode, $paragraph_entity_type_id, $paragraph_bundle,
      function (
        $label,
        $body_value) use (
        $parent_bundle,
        $paragraph_bundle,
        $parent_paragraph_field_name
      ) {
        // Prevent adding paragraph twice as it will be added
        // when node is saved.
        $this->setUpPerzEntityTypes([]);
        $paragraph = $this->createParagraph(
          $paragraph_bundle,
          $body_value,
          TRUE
        );
        $this->createNodeWithParagraph(
          $parent_bundle,
          'Article title1',
          'Article body1',
          $parent_paragraph_field_name,
          $paragraph
        );
        return $paragraph;
      }
    );
  }

}
