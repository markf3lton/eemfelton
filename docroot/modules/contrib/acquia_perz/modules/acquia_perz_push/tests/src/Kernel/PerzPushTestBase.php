<?php

namespace Drupal\Tests\acquia_perz_push\Kernel;

use Drupal\acquia_connector\Subscription;
use Drupal\acquia_perz\EntityHelper;
use Drupal\acquia_perz_push\ExportContent;
use Drupal\acquia_perz_push\ExportQueue;
use Drupal\acquia_perz_push\ExportTracker;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\acquia_perz\Traits\EntityHelperTestTrait;
use Drupal\Tests\acquia_perz\Traits\TextFieldCreationTrait;
use Drupal\Tests\EntityViewTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\RoleInterface;
use GuzzleHttp\Exception\TransferException;

/**
 * {@inheritdoc}
 */
abstract class PerzPushTestBase extends KernelTestBase {

  use EntityHelperTestTrait;

  use NodeCreationTrait {
    getNodeByTitle as drupalGetNodeByTitle;
    createNode as drupalCreateNode;
  }
  use UserCreationTrait {
    createUser as drupalCreateUser;
    createRole as drupalCreateRole;
    createAdminRole as drupalCreateAdminRole;
  }
  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }
  use EntityViewTrait;
  use TextFieldCreationTrait;
  use TestFileCreationTrait;
  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paragraphs',
    'entity_reference_revisions',
    'file',
    'node',
    'datetime',
    'user',
    'system',
    'block',
    'block_content',
    'filter',
    'field',
    'field_permissions',
    'text',
    'language',
    'content_translation',
    'locale',
    'taxonomy',
    'serialization',
    'rest',
    'image',
    'acquia_connector',
    'acquia_perz',
    'acquia_perz_push',
    'layout_builder',
    'layout_discovery',
    'field_layout',
    'path_alias',
    'comment',
    'acquia_perz_push_test',
    'cohesion',
    'cohesion_elements',
    'cohesion_templates',
  ];

  /**
   * {@inheritdoc}
   */
  protected $viewModeDefaultValue = [
    'render_role' => 'anonymous',
    'preview_image' => '',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('cohesion', ['coh_usage']);
    $this->installConfig('cohesion_elements');
    $this->installConfig('cohesion_templates');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location', 'locale_file']);
    $this->installSchema('acquia_perz_push', 'acquia_perz_push_export_tracking');
    $this->installConfig('rest');
    $this->installConfig(['node', 'filter', 'text', 'user', 'field']);
    $this->installConfig('image');
    $this->installConfig('acquia_perz');
    $this->installConfig('acquia_perz_push');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('date_format');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('component_content');
    $this->installConfig(['user', 'comment']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    \Drupal::moduleHandler()->loadInclude('paragraphs', 'install');
    $site_base_url = \Drupal::service('request_stack')->getCurrentRequest()->getSchemeAndHttpHost();
    $subscriptionServiceMock = $this->createMock(Subscription::class);
    $subscriptionServiceMock->expects($this->any())
      ->method('getSubscription')
      ->willReturn([
        'acquia_perz' => [
          'api_key' => 'AUTH-TEST-1',
          'secret_key' => 'a491206bc0a61d51e4dfac8a81d5d1a7',
          'account_id' => 'PERZTESTv3',
          'endpoint' => $site_base_url,
        ],
      ]
      );
    $container = \Drupal::getContainer();
    $container->set('acquia_connector.subscription', $subscriptionServiceMock);
  }

  /**
   * Check that tracking items are unique and have expected status.
   *
   * @param array $expected_uuids
   *   The expected uuids.
   * @param string $expected_status
   *   The expected status.
   */
  public function assertTrackingItems(array $expected_uuids, $expected_status) {
    $unique_entities = [];
    $tracking_items = $this->getTrackingItems();
    $this->assertCount(count($expected_uuids), $tracking_items);
    foreach ($tracking_items as $tracking_item) {
      $this->assertContains($tracking_item->entity_uuid, $expected_uuids);
      $this->assertNotContains($tracking_item->entity_uuid, $unique_entities);
      $this->assertEquals($tracking_item->status, $expected_status);
      $unique_entities[] = $tracking_item->entity_uuid;
    }
  }

  /**
   * Loop through all queue items assert its entities.
   *
   * @param array $expected_uuids
   *   The expected uuids.
   * @param int $bulk_size
   *   The bulk size.
   *
   * @return array
   *   List of queue item ids.
   */
  public function assertQueueEntities(array $expected_uuids, $bulk_size) {
    $queue_item_ids = [];
    $unique_entities = [];
    $queue_items = $this->getQueueItems();
    foreach ($queue_items as $queue_item) {
      $queue_item_ids[] = $queue_item->item_id;
      $data = unserialize($queue_item->data, ['allowed_classes' => FALSE]);
      $entities = $data['entities'];
      // Check that number of entities is not more than bulk size.
      $this->assertLessThanOrEqual($bulk_size, count($entities));
      foreach ($entities as $entity) {
        // Check that entity is present in the expected list.
        $this->assertContains($entity['entity_uuid'], $expected_uuids);
        // Make sure that entity is unique.
        $this->assertNotContains($entity['entity_uuid'], $unique_entities);
        $unique_entities[] = $entity['entity_uuid'];
      }
    }
    // Check that all entities in the queue are unique.
    $this->assertEqualsCanonicalizing($expected_uuids, $unique_entities);
    return $queue_item_ids;
  }

  /**
   * Get list of all nodes from database.
   *
   * @return mixed
   *   List of node ids from database.
   *
   * @throws \Exception
   */
  protected function getNodes() {
    $connection = $this->container->get('database');
    $query = $connection->select('node', 'n')
      ->fields('n', ['nid']);
    return $query->execute()->fetchAll();
  }

  /**
   * Get list of all queue items of perz queue.
   *
   * @return mixed
   *   List of queue items from database.
   *
   * @throws \Exception
   */
  protected function getQueueItems() {
    $connection = $this->container->get('database');
    $query = $connection->select('queue', 'q')
      ->fields('q', ['item_id', 'data'])
      ->condition('name', 'acquia_perz_push_content_export');
    return $query->execute()->fetchAll();
  }

  /**
   * Get list of single/all tracking items.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param int $entity_id
   *   The entity id.
   *
   * @return mixed
   *   Returns list of rows from tracking table.
   *
   * @throws \Exception
   */
  protected function getTrackingItems($entity_type_id = NULL, $entity_id = NULL) {
    $connection = $this->container->get('database');
    $query = $connection->select('acquia_perz_push_export_tracking', 't')
      ->fields('t', ['entity_type', 'entity_id', 'entity_uuid', 'langcode', 'status', 'modified']);
    if ($entity_type_id !== NULL) {
      $query->condition('entity_type', $entity_type_id);
    }
    if ($entity_id !== NULL) {
      $query->condition('entity_id', $entity_id);
    }
    return $query->execute()->fetchAll();
  }

  /**
   * Slow endpoint mock callback.
   *
   * @throws \GuzzleHttp\Exception\TransferException
   */
  public function slowEndpointRequestCallback() {
    throw new TransferException("Slow endpoint");
  }

  /**
   * Normal endpoint mock callback.
   *
   * @return string
   *   Returns exported status.
   */
  public function normalEndpointRequestCallback() {
    return ExportTracker::EXPORTED;
  }

  /**
   * Export bulk queue items mock method.
   *
   * @throws \Exception
   */
  public function exportBulkQueueItems() {
    $export_queue = $this->container->get('acquia_perz_push.export_queue');
    $context = [];
    $queue_count = $export_queue->getQueueCount();
    for ($i = 0; $i < $queue_count; $i++) {
      // Some delay to prevent same second actions.
      sleep(1);
      $export_queue->exportBulkBatchProcess($context);
    }
  }

  /**
   * Setup Perz entity configuration.
   *
   * @param array $view_modes
   *   The array of entity_types > bundles > view_modes.
   */
  protected function setUpPerzEntityTypes(array $view_modes) {
    $entity_settings = $this->config('acquia_perz.entity_config');
    $entity_settings->set('view_modes', $view_modes);
    $entity_settings->save();
  }

  /**
   * Setup bulk size.
   *
   * @param int $bulk_size
   *   The bulk size.
   */
  protected function setUpBulkSize($bulk_size) {
    $cis_settings = $this->config('acquia_perz_push.settings');
    $cis_settings->set('cis.queue_bulk_max_size', $bulk_size);
    $cis_settings->save();
  }

  /**
   * Create a mock for ExportContent class.
   *
   * @param string $send_bulk_endpoint_callback_name
   *   The endpoint mock method callback.
   *
   * @throws \Exception
   */
  protected function createExportContentMock($send_bulk_endpoint_callback_name) {
    unset($this->exportContentMock);
    $this->exportContentMock = $this->getMockBuilder(ExportContent::class)
      ->onlyMethods(['sendBulk'])
      ->setConstructorArgs([
        $this->container->get('acquia_perz.client_factory'),
        $this->container->get('acquia_perz_push.export_queue'),
        $this->container->get('acquia_perz_push.tracker'),
        $this->container->get('config.factory'),
        $this->container->get('entity_type.manager'),
        $this->container->get('entity_field.manager'),
        $this->container->get('renderer'),
        $this->container->get('uuid'),
        $this->container->get('date.formatter'),
        $this->container->get('datetime.time'),
        $this->container->get('acquia_perz.entity_helper'),
      ])
      ->getMock();
    $this->exportContentMock->expects($this->any())
      ->method('sendBulk')
      ->will($this->returnCallback([$this, $send_bulk_endpoint_callback_name]));
    $this->container->set('acquia_perz_push.export_content', $this->exportContentMock);
  }

  /**
   * Create a mock for EntityHelper class.
   *
   * @throws \Exception
   */
  protected function createEntityHelperMock() {
    unset($this->entityHelperMock);
    $this->entityHelperMock = $this->getMockBuilder(EntityHelper::class)
      ->onlyMethods(['getRenderedContent'])
      ->setConstructorArgs([
        $this->container->get('entity_type.manager'),
        $this->container->get('entity_field.manager'),
        $this->container->get('renderer'),
        $this->container->get('date.formatter'),
        $this->container->get('datetime.time'),
        $this->container->get('config.factory'),
        $this->container->get('database'),
        $this->container->get('file_url_generator'),
      ])
      ->getMock();
    $this->entityHelperMock->expects($this->any())
      ->method('getRenderedContent')
      ->will($this->returnValue('Rendered content'));
    $this->container->set('acquia_perz.entity_helper', $this->entityHelperMock);
  }

  /**
   * Create a mock for ExportQueue class.
   *
   * @throws \Exception
   */
  protected function createExportQueueMock() {
    // Mock ExportQueue class.
    if (!isset($this->exportQueueMock)) {
      $this->exportQueueMock = $this->getMockBuilder(ExportQueue::class)
        ->onlyMethods(['exportBulkQueueItems'])
        ->setConstructorArgs([
          $this->container->get('acquia_perz_push.tracker'),
          $this->container->get('config.factory'),
          $this->container->get('renderer'),
          $this->container->get('entity_type.manager'),
          $this->container->get('queue'),
          $this->container->get('plugin.manager.queue_worker'),
          $this->container->get('messenger'),
        ])
        ->getMock();
    }
    $this->exportQueueMock->expects($this->any())
      ->method('exportBulkQueueItems')
      ->will($this->returnCallback([$this, 'exportBulkQueueItems']));
    $this->container->set('acquia_perz_push.export_queue', $this->exportQueueMock);
  }

  /**
   * @see testSlowEntitySave
   *
   * @param array $entity_config
   *   The list of entity types > bundles > view modes that should be tested.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $create_entity_callback
   *   Callback that returns single entity that should be tested.
   *
   * @throws \Exception
   */
  public function checkSlowEntitySave(array $entity_config, $entity_type_id, $create_entity_callback) {
    $entity_helper = $this->container->get('acquia_perz.entity_helper');
    $this->setUpPerzEntityTypes($entity_config);
    $this->createEntityHelperMock();
    $this->createExportContentMock('slowEndpointRequestCallback');
    if (!is_callable($create_entity_callback)) {
      throw new \Exception('Create entity function is not callable');
    }
    $entity = $create_entity_callback();
    $expected_uuids[] = $entity->uuid();
    // 1. Use case: Create node = Export Timeout
    // Check that 'node' table has 1 node.
    $entities_number = $entity_helper->getCountByEntityTypeId(
      $entity_type_id,
      $entity_config[$entity_type_id]
    );
    $this->assertSame(1, $entities_number);
    $tracking_items = $this->getTrackingItems();
    $old_tracking_modified = $tracking_items[0]->modified;

    // Check that 'tracking' table has 1 row with 'export_timeout' state.
    $this->assertCount(1, $tracking_items);
    $this->assertTrackingItems($expected_uuids, ExportTracker::EXPORT_TIMEOUT);

    // Check that 'queue' table has 1 row with proper worker.
    $queue_items = $this->getQueueItems();

    $this->assertCount(1, $queue_items);
    $prev_queue_item_id = $queue_items[0]->item_id;

    // Check unserialized data of queue row has corresponding node.
    $data = unserialize($queue_items[0]->data, ['allowed_classes' => FALSE]);
    $this->assertEquals($data['action'], 'insert_or_update');
    $this->assertEquals($data['langcode'], 'en');
    $this->assertCount(1, $data['entities']);
    $this->assertEquals($data['entities'][0]['entity_type_id'], $entity->getEntityTypeId());
    $this->assertEquals($data['entities'][0]['entity_id'], $entity->id());

    // 2. Check use case when Export button is clicked (corresponding
    // service is started) but endpoint stills returns exception.
    $this->createExportQueueMock();
    $this
      ->container
      ->get('acquia_perz_push.export_queue')
      ->exportBulkQueueItems();
    // Check that 'tracking' table still has 1 row with with 'export_timeout'
    // state but Modified date is different.
    $tracking_items = $this->getTrackingItems();
    $this->assertNotEquals($tracking_items[0]->modified, $old_tracking_modified);
    $this->assertTrackingItems($expected_uuids, ExportTracker::EXPORT_TIMEOUT);

    // Check that 'queue' table has 1 row with proper worker
    // but queue item id is different (prev queue item was deleted
    // and recreated.
    $queue_items = $this->getQueueItems();
    $this->assertCount(1, $queue_items);
    $this->assertNotEquals($queue_items[0]->item_id, $prev_queue_item_id);
    // Check unserialized data of queue row has corresponding node.
    $data = unserialize($queue_items[0]->data, ['allowed_classes' => FALSE]);
    $this->assertEquals($data['action'], 'insert_or_update');
    $this->assertEquals($data['langcode'], 'en');
    $this->assertCount(1, $data['entities']);
    $this->assertEquals($data['entities'][0]['entity_type_id'], $entity->getEntityTypeId());
    $this->assertEquals($data['entities'][0]['entity_id'], $entity->id());

    // 3. Check use case when Export button is clicked (corresponding
    // service is started) but endpoint returns normal response.
    // Update mock endpoint to return Exported response.
    $this->createExportContentMock('normalEndpointRequestCallback');
    $this
      ->container
      ->get('acquia_perz_push.export_queue')
      ->exportBulkQueueItems();

    // Check that 'queue' table has no rows (probably no queue table at all).
    $queue_items = $this->getQueueItems();
    $this->assertCount(0, $queue_items);

    // Check that 'tracking' table still has 1 row with with 'exported' state.
    $this->assertTrackingItems($expected_uuids, ExportTracker::EXPORTED);
  }

  /**
   * @see testNormalEntitySave
   *
   * @param array $entity_config
   *   The list of entity types > bundles > view modes that
   *   should be on-boarded.
   * @param string $create_entity_callback
   *   Callback that returns single entity that should be tested.
   *
   * @throws \Exception
   */
  public function checkNormalEntitySave(array $entity_config, $create_entity_callback) {
    $this->setUpPerzEntityTypes($entity_config);
    $this->createEntityHelperMock();
    $this->createExportContentMock('normalEndpointRequestCallback');
    if (!is_callable($create_entity_callback)) {
      throw new \Exception('Create entity function is not callable');
    }
    $expected_uuids[] = $create_entity_callback()->uuid();
    // Check that 'queue' table has no rows (probably no queue table at all).
    $connection = $this->container->get('database');
    $this->assertFalse($connection->schema()->tableExists('queue'));

    // Check that 'tracking' table still has 1 row with with 'exported' state.
    $this->assertTrackingItems($expected_uuids, ExportTracker::EXPORTED);
  }

  /**
   * @see testOnboarding
   *
   * @param array $entity_config
   *   The list of entity types > bundles > view modes that should
   *   be on-boarded.
   * @param int $bulk_size
   *   Number of entities that can be added per 1 queue item.
   * @param int $number_of_entities
   *   Number of entities that should be created for testing.
   * @param string $create_entity_callback
   *   Callback that returns single entity that should be tested.
   *
   * @throws \Exception
   */
  public function checkOnboarding(array $entity_config, $bulk_size, $number_of_entities, $create_entity_callback) {
    $expected_uuids = [];
    if (!is_callable($create_entity_callback)) {
      throw new \Exception('Create entity function is not callable');
    }
    for ($i = 0; $i < $number_of_entities; $i++) {
      $expected_uuids[] = $create_entity_callback()->uuid();
    }

    // Setup bulk size = 5 to expect only 1 bulk for our entities.
    $this->setUpBulkSize($bulk_size);

    // Setup perz tracking for types of entities that we created for testing.
    $this->setUpPerzEntityTypes($entity_config);

    $export_queue = $this
      ->container
      ->get('acquia_perz_push.export_queue');

    // Run perz rescan (without batch api).
    $export_queue->rescanContentBulk(FALSE);

    // Check that queue has only 1 bulk.
    $queue_items = $this->getQueueItems();
    $this->assertCount(1, $queue_items);

    $this->assertQueueEntities($expected_uuids, $bulk_size);

    // Purge a queue.
    $export_queue->purgeQueue();

    // Check that queue is actually empty.
    $queue_items = $this->getQueueItems();
    $this->assertCount(0, $queue_items);

    // Switch off tracking entity types to add a new node without
    // running perz hooks.
    $this->setUpPerzEntityTypes([]);

    $expected_uuids[] = $create_entity_callback()->uuid();

    $this->setUpPerzEntityTypes($entity_config);

    // Run perz rescan (without batch api).
    $export_queue->rescanContentBulk(FALSE);

    // Check that queue has 2 bulks.
    $queue_items = $this->getQueueItems();
    $this->assertCount(2, $queue_items);

    // Check list of entities inside each queue item.
    $old_queue_item_ids = $this->assertQueueEntities($expected_uuids, $bulk_size);

    // Create ExportQueue mock.
    $this->createExportQueueMock();
    // Switch a mock to slow endpoint request.
    $this->createEntityHelperMock();
    $this->createExportContentMock('slowEndpointRequestCallback');
    $this
      ->container
      ->get('acquia_perz_push.export_queue')
      ->exportBulkQueueItems();

    // Check that queue has 2 bulks.
    $queue_items = $this->getQueueItems();
    $this->assertCount(2, $queue_items);
    $new_queue_item_ids = $this->assertQueueEntities($expected_uuids, $bulk_size);
    // Check that queue items are different (been recreated).
    $this->assertNotEqualsCanonicalizing($old_queue_item_ids, $new_queue_item_ids);
    // Check that tracking items are unique and have timeout status.
    $this->assertTrackingItems($expected_uuids, ExportTracker::EXPORT_TIMEOUT);

    // Switch a mock to normal endpoint request.
    $this->createExportContentMock('normalEndpointRequestCallback');
    $this
      ->container
      ->get('acquia_perz_push.export_queue')
      ->exportBulkQueueItems();

    // Check that queue has 0 bulks.
    $queue_items = $this->getQueueItems();
    $this->assertCount(0, $queue_items);

    // Check that tracking items are unique and have Exported status.
    $this->assertTrackingItems($expected_uuids, ExportTracker::EXPORTED);
  }

  /**
   * Checks & asserts custom view modes for entity.
   *
   * Create 2 custom view modes (custom1 & custom2) for node entity type.
   *
   * Create text fields (field_text1 & field_text2) and make field_text1
   * visible for custom1 view mode and field_text2 for custom2 view mode.
   *
   * Check rendered output of exported variations if view modes contain
   * corresponding field values.
   *
   * Check use case with LayoutBuilder (2cols layout).
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
  public function checkCustomViewModes($entity_type_id, $bundle, $create_entity_callback) {
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
    // Field text 2.
    $text2_field_name = 'field_text2';
    $field_storage = $this->createTextFieldStorage(
      $entity_type_id,
      $text2_field_name
    );
    $field_storage->setThirdPartySetting('field_permissions', 'permission_type', FieldPermissionTypeInterface::ACCESS_CUSTOM);
    $field_storage->save();

    $this->createTextField(
      $field_storage,
      $entity_type_id,
      $bundle
    );

    $custom1_view_mode_name = 'custom1';
    $custom2_view_mode_name = 'custom2';

    // Create 2 custom view modes: custom1 and custom2.
    EntityViewMode::create([
      'id' => "{$entity_type_id}.{$custom1_view_mode_name}",
      'label' => 'Custom 1',
      'targetEntityType' => $entity_type_id,
    ])->save();
    EntityViewMode::create([
      'id' => "{$entity_type_id}.{$custom2_view_mode_name}",
      'label' => 'Custom 2',
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
    $custom2_display = EntityViewDisplay::create([
      'targetEntityType' => $entity_type_id,
      'bundle' => $bundle,
      'mode' => $custom2_view_mode_name,
    ]);
    $custom2_display
      ->setStatus(TRUE)
      ->setComponent($text2_field_name, [
        'label' => 'inline',
        'type' => 'string',
      ])
      // Add Layout builder for custom2 view mode to see if render
      // works correctly.
      ->setLayoutId('layout_twocol')->save();

    $en_title = 'EN article 1';
    $en_body_value = 'EN article 1 body';
    $en_text1_field_value = 'EN article 1 text 1';
    $en_text2_field_value = 'EN article 1 text 2';
    $entity = $create_entity_callback(
      $en_title,
      $en_body_value,
      $text1_field_name,
      $en_text1_field_value,
      $text2_field_name,
      $en_text2_field_value
    );

    CommentType::create([
      'id' => 'comment',
      'label' => 'Default comments',
      'description' => 'Default comment field',
      'target_entity_type_id' => $entity_type_id,
    ])->save();

    $this->addDefaultCommentField($entity_type_id, $bundle, 'comment', CommentItemInterface::OPEN, 'comment', 'default');
    $comment = Comment::create([
      'entity_type' => $entity_type_id,
      'name' => $this->randomString(),
      'entity_id' => $entity->id(),
      'comment_type' => 'comment',
      'field_name' => 'comment',
      'status' => 1,
    ]);
    $comment->setPublished();
    $this->setUpPerzEntityTypes([
      $entity_type_id => [
        $bundle => [
          'default' => $this->viewModeDefaultValue,
          $custom1_view_mode_name => $this->viewModeDefaultValue,
          $custom2_view_mode_name => $this->viewModeDefaultValue,
        ],
      ],
    ]);

    $export_content = $this->container->get('acquia_perz_push.export_content');
    // User has permission to view everything.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content' => TRUE,
      'access comments' => TRUE,
      'post comments' => TRUE,
      'view field_text2' => TRUE,
    ]);

    $payload = $export_content->getEntityPayload($entity_type_id, $entity->id());
    $en_default_variation = $payload[0];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_default_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_default_variation, 'en', 'default');
    // Check that only text1 field value is rendered and else fields are hidden.
    $this->assertStringContainsString($en_body_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text1_field_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text2_field_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString('layout--twocol', $en_default_variation['rendered_data']);
    $this->assertStringContainsString('comment-comment-form', $en_default_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_default_variation['rendered_data']);

    $en_custom1_variation = $payload[1];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_custom1_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_custom1_variation, 'en', $custom1_view_mode_name);
    // Check that only text1 field value is rendered and else fields are hidden.
    $this->assertStringNotContainsString($en_body_value, $en_custom1_variation['rendered_data']);
    $this->assertStringContainsString($en_text1_field_value, $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text2_field_value, $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString('layout--twocol', $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_custom1_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_custom1_variation['rendered_data']);

    $en_custom2_variation = $payload[2];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_custom2_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_custom2_variation, 'en', $custom2_view_mode_name);
    // Check that only text2 field value is rendered and else fields are hidden.
    $this->assertStringNotContainsString($en_body_value, $en_custom2_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text1_field_value, $en_custom2_variation['rendered_data']);
    $this->assertStringContainsString($en_text2_field_value, $en_custom2_variation['rendered_data']);
    $this->assertStringContainsString('layout--twocol', $en_custom2_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_custom2_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_custom2_variation['rendered_data']);

    // User has permission to view entity and a specific field but not comment.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content' => TRUE,
      'access comments' => FALSE,
      'post comments' => FALSE,
      'view field_text2' => TRUE,
    ]);
    $payload = $export_content->getEntityPayload($entity_type_id, $entity->id());
    $en_default_variation = $payload[0];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_default_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_default_variation, 'en', 'default');
    // Check that only text1 field value is rendered and else fields are hidden.
    $this->assertStringContainsString($en_body_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text1_field_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text2_field_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString('layout--twocol', $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_default_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_default_variation['rendered_data']);

    $en_custom1_variation = $payload[1];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_custom1_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_custom1_variation, 'en', $custom1_view_mode_name);
    // Check that only text1 field value is rendered and else fields are hidden.
    $this->assertStringNotContainsString($en_body_value, $en_custom1_variation['rendered_data']);
    $this->assertStringContainsString($en_text1_field_value, $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text2_field_value, $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString('layout--twocol', $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_custom1_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_custom1_variation['rendered_data']);

    $en_custom2_variation = $payload[2];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_custom2_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_custom2_variation, 'en', $custom2_view_mode_name);
    // Check that only text2 field value is rendered and else fields are hidden.
    $this->assertStringNotContainsString($en_body_value, $en_custom2_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text1_field_value, $en_custom2_variation['rendered_data']);
    $this->assertStringContainsString($en_text2_field_value, $en_custom2_variation['rendered_data']);
    $this->assertStringContainsString('layout--twocol', $en_custom2_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_custom2_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_custom2_variation['rendered_data']);

    // User has permission to view entity but not comment and a specific field.
    user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content' => TRUE,
      'access comments' => FALSE,
      'post comments' => FALSE,
      'view field_text2' => FALSE,
    ]);
    $payload = $export_content->getEntityPayload($entity_type_id, $entity->id());
    $en_default_variation = $payload[0];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_default_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_default_variation, 'en', 'default');
    // Check that only text1 field value is rendered and else fields are hidden.
    $this->assertStringContainsString($en_body_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text1_field_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text2_field_value, $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString('layout--twocol', $en_default_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_default_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_default_variation['rendered_data']);

    $en_custom1_variation = $payload[1];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_custom1_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_custom1_variation, 'en', $custom1_view_mode_name);
    // Check that only text1 field value is rendered and else fields are hidden.
    $this->assertStringNotContainsString($en_body_value, $en_custom1_variation['rendered_data']);
    $this->assertStringContainsString($en_text1_field_value, $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text2_field_value, $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString('layout--twocol', $en_custom1_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_custom1_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_custom1_variation['rendered_data']);

    $en_custom2_variation = $payload[2];
    if ($entity_type_id !== 'paragraph') {
      $this->assertSame($entity->label(), $en_custom2_variation['label']);
    }
    $this->assertVariationBaseValues($entity, $en_custom2_variation, 'en', $custom2_view_mode_name);
    // Check that body, text1 and text2 field value is not rendered.
    $this->assertStringNotContainsString($en_body_value, $en_custom2_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text1_field_value, $en_custom2_variation['rendered_data']);
    $this->assertStringNotContainsString($en_text2_field_value, $en_custom2_variation['rendered_data']);
    $this->assertStringContainsString('layout--twocol', $en_custom2_variation['rendered_data']);
    $this->assertStringNotContainsString('comment-comment-form', $en_custom2_variation['rendered_data']);
    $this->assertBlockMarkup($entity_type_id, $en_custom2_variation['rendered_data']);

    if ($entity_type_id != 'block_content' && $entity_type_id != 'paragraph') {
      // User has no permission to view entity.
      user_role_change_permissions(RoleInterface::ANONYMOUS_ID, [
        'access content' => FALSE,
        'access comments' => FALSE,
        'post comments' => FALSE,
        'view field_text2' => FALSE,
      ]);
      $payload = $export_content->getEntityPayload($entity_type_id, $entity->id());

      $en_default_variation = $payload[0];
      $this->assertSame($entity->label() . ' (no content)', $en_default_variation['label']);
      $this->assertVariationBaseValues($entity, $en_default_variation, 'en', 'default');
      // Check that render data is empty
      // if anonymous user does not have access content permission.
      $this->assertEquals($en_default_variation['rendered_data'], '<!-- PERZ DEBUG: this content cannot be accessed by the render role anonymous  -->');

      $en_custom1_variation = $payload[1];
      $this->assertSame($entity->label() . ' (no content)', $en_custom1_variation['label']);
      $this->assertVariationBaseValues($entity, $en_custom1_variation, 'en', $custom1_view_mode_name);
      $this->assertEquals($en_custom1_variation['rendered_data'], '<!-- PERZ DEBUG: this content cannot be accessed by the render role anonymous  -->');

      $en_custom2_variation = $payload[2];
      $this->assertSame($entity->label() . ' (no content)', $en_custom2_variation['label']);
      $this->assertVariationBaseValues($entity, $en_custom2_variation, 'en', $custom2_view_mode_name);
      $this->assertEquals($en_custom2_variation['rendered_data'], '<!-- PERZ DEBUG: this content cannot be accessed by the render role anonymous  -->');
    }

  }

  /**
   * Checks & asserts custom view modes for entity.
   *
   * Create 2 custom view modes (custom1 & custom2) for node entity type.
   *
   * Create text fields (field_text1 & field_text2) and make field_text1
   * visible for custom1 view mode and field_text2 for custom2 view mode.
   *
   * Check rendered output of exported variations if view modes contain
   * corresponding field values.
   *
   * Check use case with LayoutBuilder (2cols layout).
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
  public function checkCustomViewModesUnpublished($entity_type_id, $bundle, $create_entity_callback) {
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
    // Field text 2.
    $text2_field_name = 'field_text2';
    $field_storage = $this->createTextFieldStorage(
      $entity_type_id,
      $text2_field_name
    );
    $this->createTextField(
      $field_storage,
      $entity_type_id,
      $bundle
    );

    $custom1_view_mode_name = 'custom1';
    $custom2_view_mode_name = 'custom2';

    // Create 2 custom view modes: custom1 and custom2.
    EntityViewMode::create([
      'id' => "{$entity_type_id}.{$custom1_view_mode_name}",
      'label' => 'Custom 1a',
      'targetEntityType' => $entity_type_id,
    ])->save();
    EntityViewMode::create([
      'id' => "{$entity_type_id}.{$custom2_view_mode_name}",
      'label' => 'Custom 2a',
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
    $custom2_display = EntityViewDisplay::create([
      'targetEntityType' => $entity_type_id,
      'bundle' => $bundle,
      'mode' => $custom2_view_mode_name,
    ]);
    $custom2_display
      ->setStatus(TRUE)
      ->setComponent($text2_field_name, [
        'label' => 'inline',
        'type' => 'string',
      ])
      // Add Layout builder for custom2 view mode to see if render
      // works correctly.
      ->setLayoutId('layout_twocol')->save();

    $en_title = 'EN article 1';
    $en_body_value = 'EN article 1 body';
    $en_text1_field_value = 'EN article 1 text 1';
    $en_text2_field_value = 'EN article 1 text 2';
    $entity = $create_entity_callback(
      $en_title,
      $en_body_value,
      $text1_field_name,
      $en_text1_field_value,
      $text2_field_name,
      $en_text2_field_value
    );

    $this->setUpPerzEntityTypes([
      $entity_type_id => [
        $bundle => [
          'default' => $this->viewModeDefaultValue,
          $custom1_view_mode_name => $this->viewModeDefaultValue,
          $custom2_view_mode_name => $this->viewModeDefaultValue,
        ],
      ],
    ]);

    $export_content = $this->container->get('acquia_perz_push.export_content');
    $payload = $export_content->getEntityPayload($entity_type_id, $entity->id());
    $en_default_variation = $payload[0];
    $this->assertSame($entity->label() . ' (no content)', $en_default_variation['label']);
    $this->assertVariationBaseValues($entity, $en_default_variation, 'en', 'default');
    // Check that render data is empty
    // if anonymous user does not have access content permission.
    $this->assertEquals($en_default_variation['rendered_data'], '<!-- PERZ DEBUG: this content cannot be accessed by the render role anonymous  -->');

    $en_custom1_variation = $payload[1];
    $this->assertSame($entity->label() . ' (no content)', $en_custom1_variation['label']);
    $this->assertVariationBaseValues($entity, $en_custom1_variation, 'en', $custom1_view_mode_name);
    $this->assertEquals($en_custom1_variation['rendered_data'], '<!-- PERZ DEBUG: this content cannot be accessed by the render role anonymous  -->');

    $en_custom2_variation = $payload[2];
    $this->assertSame($entity->label() . ' (no content)', $en_custom2_variation['label']);
    $this->assertVariationBaseValues($entity, $en_custom2_variation, 'en', $custom2_view_mode_name);
    $this->assertEquals($en_custom2_variation['rendered_data'], '<!-- PERZ DEBUG: this content cannot be accessed by the render role anonymous  -->');

  }

  /**
   * Checks & asserts traslations for entity.
   *
   * Add translation language for content type and create a content
   * with translation. Export content and check rendered content
   * of variations if translation is there.
   *
   * @param string $translation_langcode
   *   The translation language code.
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $create_entity_callback
   *   Callback that returns single entity that should be tested.
   *
   * @throws \Exception
   */
  public function checkTranslation($translation_langcode, $entity_type_id, $bundle, $create_entity_callback) {
    $default_langcode = 'en';
    // Activate translatable option for bundle.
    \Drupal::service('content_translation.manager')->setEnabled(
      $entity_type_id,
      $bundle,
      TRUE
    );

    $entity = $create_entity_callback(
      $translation_langcode . ' article 1',
      $translation_langcode . ' article body'
    );

    $entity->addTranslation($translation_langcode, [
      'title' => $translation_langcode . ' article 1',
      'body' => ['value' => $translation_langcode . ' article body'],
    ]);
    $entity->save();

    $this->setUpPerzEntityTypes([
      $entity_type_id => [
        $bundle => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
    ]);

    $export_content = $this->container->get('acquia_perz_push.export_content');

    $payload = $export_content->getEntityPayload($entity_type_id, $entity->id());

    // Expect 2 variations - for each language.
    $this->assertCount(2, $payload);

    $default_variation = $payload[0];
    $this->assertVariationBaseValues($entity, $default_variation, $default_langcode, 'default');
    $this->assertStringContainsString($entity->get('body')->getString(), $default_variation['rendered_data']);

    $translated_variation = $payload[1];
    $translated_entity = $entity->getTranslation($translation_langcode);
    $this->assertVariationBaseValues($translated_entity, $translated_variation, $translation_langcode, 'default');
    $this->assertStringContainsString($translated_entity->get('body')->getString(), $translated_variation['rendered_data']);
  }

  /**
   * Checks & asserts taxonomy relations for entity.
   *
   * Create 2 taxonomies (tags & materials) and attach 3 taxonomy fields
   * to the content type:
   * - field_tags (tags only)
   * - field_materials (materials only)
   * - field_category (tags & materials)
   *
   * Create 3 tag terms (t1, t2, t3) and 3 material terms (m1, m2, m3).
   *
   * Create 6 nodes with different taxonomy fields values:
   *
   * - node1
   * field_tags (t1, t2)
   * - node2
   * field_tags (t1, t2)
   * field_materials (m1)
   * - node3
   * field_tags (t1, t2)
   * field_materials (m1)
   * field_category (t3, m2)
   * - node4
   * field_tags (t1, t2)
   * field_materials (m1, m2, m3)
   * field_category (t1, m2, t3, m4)
   *
   * Check export content output that corresponding terms are available for
   * corresponding fields in response. Also check that if we don't use some
   * taxonomies on entity configuration form then they are also removed from
   * the response. And if all taxonomies are unchecked on the form then
   * 'relations' is empty for response.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param \Drupal\taxonomy\Entity\Vocabulary $tags_vocabulary
   *   The tags vocabulary entity.
   * @param string $tags_field_name
   *   The tags field name of the parent entity (node).
   * @param \Drupal\taxonomy\Entity\Vocabulary $materials_vocabulary
   *   The materials vocabulary entity.
   * @param string $materials_field_name
   *   The materials field name of the parent entity (node).
   * @param string $categories_field_name
   *   The categories field name of the parent entity (node).
   * @param string $create_entity_callback
   *   Callback that returns single entity that should be tested.
   *
   * @throws \Exception
   */
  public function checkTaxonomyRelations($entity_type_id, $bundle, Vocabulary $tags_vocabulary, $tags_field_name, Vocabulary $materials_vocabulary, $materials_field_name, $categories_field_name, $create_entity_callback) {
    if (!is_callable($create_entity_callback)) {
      throw new \Exception('Create entity function is not callable');
    }
    $tags_vocabulary_id = $tags_vocabulary->id();
    $materials_vocabulary_id = $materials_vocabulary->id();
    // Create tags taxonomy field.
    $tags_field_storage = $this->createTaxonomyTermFieldStorage(
      $entity_type_id,
      $tags_field_name
    );
    $this->createTaxonomyTermField(
      $tags_field_storage,
      $entity_type_id,
      $bundle,
      [
        $tags_vocabulary_id => $tags_vocabulary_id,
      ]
    );

    // Create materials taxonomy field.
    $materials_field_storage = $this->createTaxonomyTermFieldStorage(
      $entity_type_id,
      $materials_field_name
    );
    $this->createTaxonomyTermField(
      $materials_field_storage,
      $entity_type_id,
      $bundle,
      [
        $materials_vocabulary_id => $materials_vocabulary_id,
      ]
    );

    // Create categories taxonomy field.
    $categories_field_storage = $this->createTaxonomyTermFieldStorage(
      $entity_type_id,
      $categories_field_name
    );
    $this->createTaxonomyTermField(
      $categories_field_storage,
      $entity_type_id,
      $bundle,
      [
        $tags_vocabulary_id => $tags_vocabulary_id,
        $materials_vocabulary_id => $materials_vocabulary_id,
      ]
    );

    // Create tags terms.
    $tag1 = $this->createTerm($tags_vocabulary);
    $tag2 = $this->createTerm($tags_vocabulary);
    $tag3 = $this->createTerm($tags_vocabulary);

    // Create materials terms.
    $material1 = $this->createTerm($materials_vocabulary);
    $material2 = $this->createTerm($materials_vocabulary);
    $material3 = $this->createTerm($materials_vocabulary);

    $entity1 = $create_entity_callback([
      'type' => $bundle,
      'title' => 'Article 1',
      $tags_field_name => [
        ['target_id' => $tag1->id()],
        ['target_id' => $tag2->id()],
      ],
    ]);

    $entity2 = $create_entity_callback([
      'type' => $bundle,
      'title' => 'Article 2',
      $tags_field_name => [
        ['target_id' => $tag1->id()],
        ['target_id' => $tag2->id()],
      ],
      $materials_field_name => [
        ['target_id' => $material1->id()],
      ],
    ]);

    $entity3 = $create_entity_callback([
      'type' => $bundle,
      'title' => 'Article 3',
      $tags_field_name => [
        ['target_id' => $tag1->id()],
        ['target_id' => $tag2->id()],
      ],
      $materials_field_name => [
        ['target_id' => $material1->id()],
      ],
      $categories_field_name => [
        ['target_id' => $tag3->id()],
        ['target_id' => $material2->id()],
      ],
    ]);

    $entity4 = $create_entity_callback([
      'type' => $bundle,
      'title' => 'Article 4',
      $tags_field_name => [
        ['target_id' => $tag1->id()],
        ['target_id' => $tag2->id()],
      ],
      $materials_field_name => [
        ['target_id' => $material1->id()],
        ['target_id' => $material2->id()],
        ['target_id' => $material3->id()],
      ],
      $categories_field_name => [
        ['target_id' => $tag1->id()],
        ['target_id' => $material2->id()],
        ['target_id' => $tag2->id()],
        ['target_id' => $material3->id()],
      ],
    ]);

    $entity_helper = $this->container->get('acquia_perz.entity_helper');

    // Setup all taxonomy terms for tracking.
    $this->setUpPerzEntityTypes([
      'taxonomy_term' => [
        $tags_vocabulary_id => [
          'default' => $this->viewModeDefaultValue,
        ],
        $materials_vocabulary_id => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
    ]);

    // Use case when 1 taxonomy field is used.
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity1),
      [
        [
          'field' => $tags_field_name,
          'terms' => [
            $tag1->uuid(),
            $tag2->uuid(),
          ],
        ],
      ],
    );

    // Use case when 2 taxonomy field are used (2 single vocabulary fields).
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity2),
      [
        [
          'field' => $materials_field_name,
          'terms' => [
            $material1->uuid(),
          ],
        ],
        [
          'field' => $tags_field_name,
          'terms' => [
            $tag1->uuid(),
            $tag2->uuid(),
          ],
        ],
      ]
    );

    // Use case when 3 taxonomy field are used but all terms are unique.
    // (2 single vocabulary fields + 1 multiple vocabulary field).
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity3),
      [
        [
          'field' => $categories_field_name,
          'terms' => [
            $tag3->uuid(),
            $material2->uuid(),
          ],
        ],
        [
          'field' => $materials_field_name,
          'terms' => [
            $material1->uuid(),
          ],
        ],
        [
          'field' => $tags_field_name,
          'terms' => [
            $tag1->uuid(),
            $tag2->uuid(),
          ],
        ],
      ]
    );
    // Use case when 3 taxonomy field are used but terms can be same.
    // (2 single vocabulary fields + 1 multiple vocabulary field).
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity4),
      [
        [
          'field' => $categories_field_name,
          'terms' => [
            $tag1->uuid(),
            $material2->uuid(),
            $tag2->uuid(),
            $material3->uuid(),
          ],
        ],
        [
          'field' => $materials_field_name,
          'terms' => [
            $material1->uuid(),
            $material2->uuid(),
            $material3->uuid(),
          ],
        ],
        [
          'field' => $tags_field_name,
          'terms' => [
            $tag1->uuid(),
            $tag2->uuid(),
          ],
        ],
      ]
    );
    // Setup only 1 taxonomy (tags) for tracking.
    $this->setUpPerzEntityTypes([
      'taxonomy_term' => [
        $tags_vocabulary_id => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
    ]);
    // Expect only tag terms for fields where its available.
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity1),
      [
        [
          'field' => $tags_field_name,
          'terms' => [
            $tag1->uuid(),
            $tag2->uuid(),
          ],
        ],
      ],
    );
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity2),
      [
        [
          'field' => $tags_field_name,
          'terms' => [
            $tag1->uuid(),
            $tag2->uuid(),
          ],
        ],
      ]
    );

    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity3),
      [
        [
          'field' => $categories_field_name,
          'terms' => [
            $tag3->uuid(),
          ],
        ],
        [
          'field' => $tags_field_name,
          'terms' => [
            $tag1->uuid(),
            $tag2->uuid(),
          ],
        ],
      ]
    );

    // Setup only 1 taxonomy (materials) term for tracking.
    $this->setUpPerzEntityTypes([
      'taxonomy_term' => [
        $materials_vocabulary_id => [
          'default' => $this->viewModeDefaultValue,
        ],
      ],
    ]);

    // Expect only material terms for fields where its available.
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity1),
      []
    );
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity2),
      [
        [
          'field' => $materials_field_name,
          'terms' => [
            $material1->uuid(),
          ],
        ],
      ]
    );
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity3),
      [
        [
          'field' => $categories_field_name,
          'terms' => [
            $material2->uuid(),
          ],
        ],
        [
          'field' => $materials_field_name,
          'terms' => [
            $material1->uuid(),
          ],
        ],
      ]
    );
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity4),
      [
        [
          'field' => $categories_field_name,
          'terms' => [
            $material2->uuid(),
            $material3->uuid(),
          ],
        ],
        [
          'field' => $materials_field_name,
          'terms' => [
            $material1->uuid(),
            $material2->uuid(),
            $material3->uuid(),
          ],
        ],
      ]
    );
    // Set up no taxonomies available for tracking.
    $this->setUpPerzEntityTypes([]);
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity1),
      []
    );
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity2),
      []
    );
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity3),
      []
    );
    $this->assertEqualsCanonicalizing(
      $entity_helper->getEntityTaxonomyRelations($entity4),
      []
    );
  }

  public function createEntity($bundle, $entity_type_id, $create_entity_callback, $acquia_perz_render_setting) {
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
    // Field text 2.
    $text2_field_name = 'field_text2';
    $field_storage = $this->createTextFieldStorage(
      $entity_type_id,
      $text2_field_name
    );
    $this->createTextField(
      $field_storage,
      $entity_type_id,
      $bundle
    );

    $custom1_view_mode_name = 'custom1';
    $custom2_view_mode_name = 'custom2';

    // Create 2 custom view modes: custom1 and custom2.
    EntityViewMode::create([
      'id' => "{$entity_type_id}.{$custom1_view_mode_name}",
      'label' => 'Custom 1',
      'targetEntityType' => $entity_type_id,
    ])->save();
    EntityViewMode::create([
      'id' => "{$entity_type_id}.{$custom2_view_mode_name}",
      'label' => 'Custom 2',
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
    $custom2_display = EntityViewDisplay::create([
      'targetEntityType' => $entity_type_id,
      'bundle' => $bundle,
      'mode' => $custom2_view_mode_name,
    ]);
    $custom2_display
      ->setStatus(TRUE)
      ->setComponent($text2_field_name, [
        'label' => 'inline',
        'type' => 'string',
      ])
      // Add Layout builder for custom2 view mode to see if render
      // works correctly.
      ->setLayoutId('layout_twocol')->save();

    $en_title = 'EN article 1';
    $en_body_value = 'EN article 1 body';
    $en_text1_field_value = 'EN article 1 text 1';
    $en_text2_field_value = 'EN article 1 text 2';
    $entity = $create_entity_callback(
      $en_title,
      $en_body_value,
      $text1_field_name,
      $en_text1_field_value,
      $text2_field_name,
      $en_text2_field_value
    );

    $this->setUpPerzEntityTypes([
      $entity_type_id => [
        $bundle => [
          'default' => $acquia_perz_render_setting,
          $custom1_view_mode_name => $acquia_perz_render_setting,
          $custom2_view_mode_name => $acquia_perz_render_setting,
        ],
      ],
    ]);

    $export_content = $this->container->get('acquia_perz_push.export_content');
    return [
      'entity' => $entity,
      'payload' => $export_content->getEntityPayload($entity_type_id, $entity->id()),
    ];
  }

  public function assertBlockMarkup($entity_type_id, $variation) {
    if ($entity_type_id == 'block_content') {
      $this->assertStringContainsString('custom-twig-template-class', $variation);
      $this->assertStringContainsString('<p>CUSTOM TWIG TEMPLATE CONTENT</p>', $variation);
    }
  }

}
