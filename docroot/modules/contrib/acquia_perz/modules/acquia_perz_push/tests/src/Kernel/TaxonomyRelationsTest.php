<?php

namespace Drupal\Tests\acquia_perz_push\Kernel;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Tests\acquia_perz\Traits\CreateCustomBlockTrait;
use Drupal\Tests\acquia_perz\Traits\CustomParagraphsTestTrait;
use Drupal\Tests\acquia_perz\Traits\TaxonomyFieldCreationTrait;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests for taxonomy relations during Exported variations.
 *
 * @group acquia_perz
 */
class TaxonomyRelationsTest extends PerzPushTestBase {

  use TaxonomyTestTrait;
  use TaxonomyFieldCreationTrait;
  use CreateCustomBlockTrait;
  use CustomParagraphsTestTrait;
  use ParagraphsTestBaseTrait;

  protected $tagsVocabulary;

  protected $materialsVocabulary;

  protected $tagsFieldName = 'field_tags';

  protected $materialsFieldName = 'field_materials';

  protected $categoriesFieldName = 'field_categories';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('taxonomy_term');
    $this->tagsVocabulary = $this->createVocabulary();
    $this->materialsVocabulary = $this->createVocabulary();
  }

  /**
   * Test for nodes.
   */
  public function testNodes() {
    $entity_type_id = 'node';
    $bundle = 'news';

    $this->drupalCreateContentType([
      'type' => $bundle,
      'name' => $bundle,
    ]);
    $this->checkTaxonomyRelations(
      $entity_type_id,
      $bundle,
      $this->tagsVocabulary,
      $this->tagsFieldName,
      $this->materialsVocabulary,
      $this->materialsFieldName,
      $this->categoriesFieldName,
      function ($values) {
        $entity = $this->drupalCreateNode($values);
        return $entity;
      }
    );
  }

  /**
   * Test for custom blocks.
   */
  public function testCustomBlocks() {
    $entity_type_id = 'block_content';
    $bundle = 'basic';

    $block_content_type = BlockContentType::create([
      'id' => $bundle,
      'label' => 'A basic block type',
      'description' => "Provides a block type that is square.",
    ]);
    $block_content_type->save();

    $tags_field_name = $this->tagsFieldName;
    $materials_field_name = $this->materialsFieldName;
    $categories_field_name = $this->categoriesFieldName;
    $this->checkTaxonomyRelations(
      $entity_type_id,
      $bundle,
      $this->tagsVocabulary,
      $this->tagsFieldName,
      $this->materialsVocabulary,
      $this->materialsFieldName,
      $this->categoriesFieldName,
      function ($values) use (
        $tags_field_name,
        $materials_field_name,
        $categories_field_name
      ) {
        $custom_block = $this->createCustomBlock();
        $custom_block->set('info', $values['title']);
        if (isset($values[$tags_field_name])) {
          $custom_block->set($tags_field_name, $values[$tags_field_name]);
        }
        if (isset($values[$materials_field_name])) {
          $custom_block->set($materials_field_name, $values[$materials_field_name]);
        }
        if (isset($values[$categories_field_name])) {
          $custom_block->set($categories_field_name, $values[$categories_field_name]);
        }
        $custom_block->save();
        return $custom_block;
      }
    );
  }

  /**
   * Test for paragraphs.
   */
  public function testParagraphs() {
    $parent_entity_type_id = 'node';
    $parent_bundle = 'news';
    $parent_paragraph_field_name = 'field_paragraph';
    $paragraph_entity_type_id = 'paragraph';
    $paragraph_bundle = 'header_test';

    $this->drupalCreateContentType([
      'type' => $parent_bundle,
      'name' => $parent_bundle,
    ]);

    $this->createParagraphType($paragraph_bundle);
    $this->addParagraphFieldInBundle(
      $parent_entity_type_id,
      $parent_bundle,
      $parent_paragraph_field_name,
      $paragraph_bundle
    );

    $tags_field_name = $this->tagsFieldName;
    $materials_field_name = $this->materialsFieldName;
    $categories_field_name = $this->categoriesFieldName;
    $this->checkTaxonomyRelations(
      $paragraph_entity_type_id,
      $paragraph_bundle,
      $this->tagsVocabulary,
      $this->tagsFieldName,
      $this->materialsVocabulary,
      $this->materialsFieldName,
      $this->categoriesFieldName,
      function ($values) use (
        $tags_field_name,
        $materials_field_name,
        $categories_field_name,
        $parent_bundle,
        $paragraph_bundle,
        $parent_paragraph_field_name
      ) {
        // Prevent adding paragraph twice as it will be added
        // when node is saved.
        $this->setUpPerzEntityTypes([]);
        $paragraph = $this->createParagraph(
          $paragraph_bundle,
          'Test body',
          TRUE
        );
        if (isset($values[$tags_field_name])) {
          $paragraph->set($tags_field_name, $values[$tags_field_name]);
        }
        if (isset($values[$materials_field_name])) {
          $paragraph->set($materials_field_name, $values[$materials_field_name]);
        }
        if (isset($values[$categories_field_name])) {
          $paragraph->set($categories_field_name, $values[$categories_field_name]);
        }
        $paragraph->save();
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
