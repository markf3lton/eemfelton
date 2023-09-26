<?php

namespace Drupal\Tests\acquia_perz_push\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests Queue Commands Form.
 *
 * @coversDefaultClass \Drupal\acquia_perz_push\Commands\QueueCommands
 * @group acquia_perz
 */
class QueueCommandsTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'acquia_connector',
    'acquia_perz',
    'acquia_perz_push',
  ];

  /**
   * Test Rescan Content Command.
   *
   * @covers ::enqueueContent
   * @covers ::queueItems
   * @covers ::purgeQueue
   * @covers ::processQueue
   */
  public function testRescanContent() {
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $uuid = '833587bb-c94c-4995-bcaf-4aee92ca45be';
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test Content',
      'body' => ['value' => 'Test content body', 'format' => 'basic_html'],
      'uuid' => $uuid,
      'uid' => 1,
      'status' => TRUE,
    ]);

    $this->drush('acquia:perz-queue-items');
    $messages = $this->getOutputAsList();
    $this->assertEquals('The number of items in the queue 0.', $messages[0]);

    $this->drush('acquia:perz-enqueue-content');
    $node->delete();
    $messages = $this->getOutputAsList();
    $this->assertEquals('All content has been scanned and added to the Queue.', $messages[0]);

    $this->drush('acquia:perz-process-queue');
    $messages = $this->getOutputAsList();
    $this->assertEquals('All content has been exported to Personalization from the Queue.', $messages[0]);

    $this->drush('acquia:perz-purge-queue');
    $messages = $this->getOutputAsList();
    $this->assertEquals('All content has been purged from the Queue.', $messages[0]);

  }

}
