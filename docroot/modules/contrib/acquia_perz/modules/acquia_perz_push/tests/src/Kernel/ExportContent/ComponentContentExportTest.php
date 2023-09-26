<?php

namespace Drupal\Tests\acquia_perz_push\Kernel\ExportLogic;

use Drupal\cohesion_elements\Entity\Component;
use Drupal\cohesion_elements\Entity\ComponentContent;
use Drupal\Component\Uuid\Php;
use Drupal\Tests\acquia_perz_push\Kernel\PerzPushTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Test for ComponentContent entities.
 *
 * @group acquia_perz
 *
 * @requires module cohesion
 */
class ComponentContentExportTest extends PerzPushTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  private $entityTypeId = 'component_content';

  /**
   * {@inheritdoc}
   */
  private $bundle = 'component_content';

  /**
   * {@inheritdoc}
   */
  private $entityConfig;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    // PHPUnit has `checkRequirements` as a private method since 9.x.
    // We run Drupal's `checkRequirements` again, here, to verify our module
    // requirement.
    // @todo remove after https://www.drupal.org/i/3261817
    $this->checkRequirements();
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->entityConfig = [
      $this->entityTypeId => [
        $this->bundle => [
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
    $dx8_no_send_to_api = &drupal_static('cohesion_sync_lock');
    $dx8_no_send_to_api = TRUE;

    $this->checkSlowEntitySave(
      $this->entityConfig,
      $this->entityTypeId,
      function () {
        return $this->createComponentContent('3fedc674', 'Test Title 3fedc674');
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
    $dx8_no_send_to_api = &drupal_static('cohesion_sync_lock');
    $dx8_no_send_to_api = TRUE;

    $this->checkNormalEntitySave(
      $this->entityConfig,
      function () {
        return $this->createComponentContent('3fedc674', 'Test Title 3fedc674');
      }
    );
  }

  /**
   * Tests on-boarding process.
   *
   * Tests use case for on-boarding process when entities that has been
   * presented in the drupal site will go to the queue and are exported to CIS
   * in a bulk.
   *
   * Use cases:
   *
   * 1. After clicking Rescan button (calling service) the existing entities
   * should go to the queue.
   * 2. Number of queue items depends on number of entities and bulk size.
   * 3. Check that bulk size queue logic works ok and queue contains proper
   * entities.
   * 4. Check purge service that should empty a queue.
   * 5. Check export bulk process - 2 variations: slow endpoint & normal
   * endpoint.
   * a. Slow endpoint: Check that queue is not empty and contains same
   * amount of queue items but with different queue item ids. Also check
   * that entities in the new queue items are valid. Check tracking table
   * and export_timeout statuses.
   * b. Normal endpoint: Check that queue is empty and tracking table status
   * has exported value.
   *
   * @throws \Exception
   */
  public function testOnboarding() {
    $dx8_no_send_to_api = &drupal_static('cohesion_sync_lock');
    $dx8_no_send_to_api = TRUE;
    $this->checkOnboarding(
      $this->entityConfig,
      5,
      5,
      function () {
        $id = $this->generateRandomUuid();
        return $this->createComponentContent($id, 'Test Title');
      }
    );
  }

  /**
   * Create a Mock Component Content.
   *
   * @param string $uuid
   *   The uuid for component.
   * @param string $title
   *   The title for component content.
   * @param string $json_values
   *   The json_values for component.
   *
   * @return \Drupal\cohesion_elements\Entity\ComponentContent
   *   Returns a mock component content entity.
   */
  protected function createComponentContent(string $uuid, string $title, string $json_values = '{}') {
    $component = Component::create([
      'id' => $uuid,
      'json_values' => $json_values,
    ]);
    $component->save();

    $component_content = ComponentContent::create([
      'title' => $title,
      'component' => $component,
    ]);

    $component_content->setPublished();
    $component_content->save();
    return $component_content;
  }

  /**
   * Generates random uuids.
   *
   * @return string
   *   The  uuid.
   */
  public function generateRandomUuid() {
    $generator = new Php();
    return $generator->generate();
  }

}
