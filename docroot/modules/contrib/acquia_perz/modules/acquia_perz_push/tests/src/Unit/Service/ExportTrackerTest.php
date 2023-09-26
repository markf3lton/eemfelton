<?php

namespace Drupal\Tests\acquia_perz_push\Unit\Service;

use Drupal\acquia_perz_push\ExportTracker;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sqlite\Driver\Database\sqlite\Statement;
use Drupal\Tests\UnitTestCase;

/**
 * @group acquia_perz
 */
class ExportTrackerTest extends UnitTestCase {

  /**
   * Mock statement.
   *
   * @var \Drupal\sqlite\Driver\Database\sqlite\Statement
   */
  protected $statement;

  /**
   * Mock select interface.
   *
   * @var \Drupal\Core\Database\Query\SelectInterface
   */
  protected $select;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The ExportTracker service.
   *
   * @var \Drupal\acquia_perz_push\ExportTracker
   */
  protected $exportTracker;

  /**
   * Counts calls to fetchAssoc().
   *
   * @var int
   */
  protected $callsToFetch;

  /**
   * The ExportTracker service.
   *
   * @var \Drupal\acquia_perz\ExportTracker
   */
  protected $exportTrackerMock;

  /**
   * Entity data object.
   *
   * @var localvariable
   */
  protected $entity;

  /**
   * Sets up required mocks and the ExportTracker service under test.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entity = (object) [
      'entity_type' => 'node',
      'entity_id' => '1',
      'entity_uuid' => '3f0b403c-4093-4caa-ba78-37df21125f09',
      'langcode'  => 'en,',
    ];
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
  }

  /**
   * Tests the get method.
   *
   * @see \Drupal\acquia_perz_push\ExportTracker::get()
   *
   * @group Drupal
   * @group perz
   */
  public function testGet() {
    $this->exportTracker = $this->getDatabaseObjectForSelect();
    $results = $this->exportTracker->get($this->entity->entity_uuid);
    $this->assertEquals(TRUE, $results);
  }

  /**
   * Tests the clear method.
   *
   * @see \Drupal\acquia_perz_push\ExportTracker::clear()
   *
   * @group Drupal
   * @group perz
   */
  public function testClear() {
    $this->exportTracker = $this->getDatabaseObjectForDelete();
    $this->exportTracker->clear($this->entity->entity_type, $this->entity->entity_id);
  }

  /**
   * Tests the InsertOrUpdate method.
   *
   * @see \Drupal\acquia_perz_push\ExportTracker::InsertOrUpdate()
   *
   * @group Drupal
   * @group perz
   */
  public function testInsertOrUpdate() {
    // Test for Update data.
    $this->updateData();
    // Test for Insert data.
    $this->insertData();
  }

  /**
   * Insert data in table, if not exist.
   */
  protected function insertData() {
    unset($this->exportTrackerMock);
    $insert = $this->createMock(Insert::class);
    $insert->expects($this->any())
      ->method('fields')
      ->will($this->returnSelf());

    $insert->expects($this->any())
      ->method('execute')
      ->will($this->returnValue(['1' => '1']));

    $this->database = $this->createMock(Connection::class);

    $this->database->expects($this->once())
      ->method('insert')
      ->will($this->returnValue($insert));

    $this->exportTrackerMock = $this->getMockBuilder(ExportTracker::class)
      ->onlyMethods(['get'])
      ->setConstructorArgs([
        $this->database,
        $this->entityTypeManager,
      ])
      ->getMock();

    $this->exportTrackerMock->expects($this->any())
      ->method('get')
      ->willReturn(FALSE);

    $this->exportTrackerMock
      ->export($this->entity->entity_type, $this->entity->entity_id, $this->entity->entity_uuid, 'exported', $this->entity->langcode);
  }

  /**
   * Update data in table, if exist.
   */
  protected function updateData() {
    unset($this->exportTrackerMock);
    $update = $this->createMock(Update::class);
    $update->expects($this->any())
      ->method('fields')
      ->will($this->returnSelf());

    $update->expects($this->any())
      ->method('condition')
      ->will($this->returnSelf());

    $update->expects($this->any())
      ->method('execute')
      ->will($this->returnValue(['1' => '1']));

    $this->database = $this->createMock(Connection::class);

    $this->database->expects($this->once())
      ->method('update')
      ->will($this->returnValue($update));

    $this->exportTrackerMock = $this->getMockBuilder(ExportTracker::class)
      ->onlyMethods(['get'])->setConstructorArgs([$this->database, $this->entityTypeManager])
      ->getMock();

    $this->exportTrackerMock->expects($this->any())
      ->method('get')
      ->willReturn(TRUE);

    $this->exportTrackerMock
      ->export($this->entity->entity_type, $this->entity->entity_id, $this->entity->entity_uuid, 'exported', $this->entity->langcode);

  }

  /**
   * Loads a ExportTracker object.
   *
   * @return \Drupal\acquia_perz_push\ExportTracker
   *   ExportTracker object.
   */
  protected function getDatabaseObjectForDelete() {
    $delete = $this->createMock(Delete::class);

    $delete->expects($this->any())
      ->method('condition')
      ->will($this->returnSelf());

    $delete->expects($this->any())
      ->method('execute')
      ->will($this->returnValue(['1' => '1']));

    $this->database = $this->createMock(Connection::class);

    $this->database->expects($this->once())
      ->method('delete')
      ->will($this->returnValue($delete));

    return new ExportTracker($this->database, $this->entityTypeManager);

  }

  /**
   * Loads a ExportTracker object.
   *
   * @return \Drupal\acquia_perz_push\ExportTracker
   *   ExportTracker object.
   */
  protected function getDatabaseObjectForSelect() {
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->statement = $this->createMock(Statement::class);

    $this->statement->expects($this->any())
      ->method('fetchObject')
      ->will($this->returnCallback([$this, 'fetchObjectCallback']));

    $this->select = $this->createMock(Select::class);

    $this->select->expects($this->any())
      ->method('fields')
      ->will($this->returnSelf());

    $this->select->expects($this->any())
      ->method('condition')
      ->will($this->returnSelf());

    $this->select->expects($this->any())
      ->method('execute')
      ->will($this->returnValue($this->statement));

    $this->database = $this->createMock(Connection::class);

    $this->database->expects($this->once())
      ->method('select')
      ->will($this->returnValue($this->select));

    return new ExportTracker($this->database, $this->entityTypeManager);

  }

  /**
   * Return value callback for fetchObject() function on mocked object.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function fetchObjectCallback() {
    $this->callsToFetch++;
    switch ($this->callsToFetch) {
      case 1:
        return TRUE;

      default:
        return FALSE;
    }
  }

}
