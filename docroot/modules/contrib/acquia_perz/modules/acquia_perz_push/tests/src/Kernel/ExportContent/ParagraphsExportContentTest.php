<?php

namespace Drupal\Tests\acquia_perz_push\Kernel\ExportLogic;

use Drupal\Tests\acquia_perz\Traits\CustomParagraphsTestTrait;
use Drupal\Tests\acquia_perz_push\Kernel\PerzPushTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests for export content (paragraph).
 *
 * @group acquia_perz
 */
class ParagraphsExportContentTest extends PerzPushTestBase {

  use CustomParagraphsTestTrait;
  use ParagraphsTestBaseTrait;

  /**
   * {@inheritdoc}
   */
  private $parentEntityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  private $parentBundle = 'article';

  /**
   * {@inheritdoc}
   */
  private $paragraphEntityTypeId = 'paragraph';

  /**
   * {@inheritdoc}
   */
  private $paragraphBundle = 'header_test';

  /**
   * {@inheritdoc}
   */
  private $parentParagraphFieldName = 'field_paragraph';

  /**
   * {@inheritdoc}
   */
  private $entityConfig;

  /**
   * {@inheritdoc}
   */
  private $paragraph;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType([
      'type' => $this->parentBundle,
      'name' => $this->parentBundle,
    ]);
    $this->createParagraphType($this->paragraphBundle);
    $this->addParagraphFieldInBundle(
      $this->parentEntityTypeId,
      $this->parentBundle,
      $this->parentParagraphFieldName,
      $this->paragraphBundle
    );
    $this->entityConfig = [
      $this->paragraphEntityTypeId => [
        $this->paragraphBundle => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
    ];
  }

  /**
   * Tests slow entity save.
   *
   * Tests use cases around slow cis request when entity has been saved.
   * Use cases:
   * 1. After using 'slow' mode the node should go to the queue.
   * Tracking table should contain 'timeout_export' row.
   * 2. Try to export a node from the queue with 'slow' mode. In this
   * case queue should still has 1 item but id should be updated as queue item
   * is supposed to be recreated. Tracking table should still contain
   * 'timeout_export' row but with modified date.
   * 3. Try to export a node from the queue with 'normal' mode. In this case
   * the queue should be empty, tracking table should contain 1 row with
   * 'exported' status.
   *
   * @throws \Exception
   */
  public function testSlowEntitySave() {
    $this->checkSlowEntitySave(
      $this->entityConfig,
      $this->paragraphEntityTypeId,
      function () {
        $this->setUpPerzEntityTypes([]);
        $this->paragraph = $this->createParagraph(
          $this->paragraphBundle,
          'Paragraph title: 1',
          TRUE
        );
        $this->setUpPerzEntityTypes($this->entityConfig);
        $this->createNodeWithParagraph(
          $this->parentBundle,
          'Article title1',
          'Article body1',
          $this->parentParagraphFieldName,
          $this->paragraph
        );
        return $this->paragraph;
      }
    );
  }

  /**
   * Tests normal entity save.
   *
   * Tests use case around normal cis request when entity has been saved.
   * After entity has been saved the queue should be empty,
   * tracking table should contain 1 row with 'exported' status.
   *
   * @throws \Exception
   */
  public function testNormalEntitySave() {
    $this->checkNormalEntitySave(
      $this->entityConfig,
      function () {
        $this->setUpPerzEntityTypes([]);
        $this->paragraph = $this->createParagraph(
          $this->paragraphBundle,
          'Paragraph title: 1',
          TRUE
        );
        $this->setUpPerzEntityTypes($this->entityConfig);
        $this->createNodeWithParagraph(
          $this->parentBundle,
          'Article title1',
          'Article body1',
          $this->parentParagraphFieldName,
          $this->paragraph
        );
        return $this->paragraph;
      }
    );
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
        $this->setUpPerzEntityTypes([]);
        $id = $this->randomString(4);
        $paragraph = $this->createParagraph(
          $this->paragraphBundle,
          'Paragraph title: ' . $id,
          TRUE
        );
        $this->createNodeWithParagraph(
          $this->parentBundle,
          'Article title: ' . $id,
          'Article body: ' . $id,
          $this->parentParagraphFieldName,
          $paragraph
        );
        return $paragraph;
      }
    );
  }

}
