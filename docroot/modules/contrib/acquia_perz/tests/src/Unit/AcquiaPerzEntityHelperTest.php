<?php

use Drupal\acquia_perz\EntityHelper;
use Drupal\acquia_perz\PerzHelper;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @group acquia_perz
 */
class AcquiaPerzEntityHelperTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Representation of the current HTTP request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The ExportContent service.
   *
   * @var \Drupal\acquia_perz\EntityHelper
   */
  protected $entityHelperMock;

  /**
   * The mocked entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $entityViewBuilder;

  /**
   * The mocked entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The mocked content entity type.
   *
   * @var \Drupal\Core\Entity\ContentEntityType
   */
  protected $contentEntityType;

  /**
   * The mocked entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The mocked symphony request.
   *
   * @var Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The mocked file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  private $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected $tagsFieldName = 'field_tags';

  /**
   * {@inheritdoc}
   */
  protected $materialsFieldName = 'field_materials';

  /**
   * {@inheritdoc}
   */
  protected $categoriesFieldName = 'field_categories';

  /**
   * {@inheritdoc}
   */
  protected $uuid = '3f0b403c-4093-4caa-ba78-37df21125f09';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);
    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->database = $this->createMock(Connection::class);
    $this->state = $this->createMock(StateInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->entityViewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $this->entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->contentEntityType = $this->createMock(ContentEntityType::class);
    $this->entityType = $this->createMock(EntityTypeInterface::class);
    $this->request = $this->createMock(Request::class);
    $this->fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $config = $this->createMock(Config::class);
    $account_switcher = $this->createMock(AccountSwitcherInterface::class);
    $this->configFactory
      ->method('get')
      ->with('acquia_perz.entity_config')
      ->willReturn($config);

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($this->request);
    $this->request->expects($this->any())
      ->method('getHost')
      ->willReturn('localhost');

    $container = new ContainerBuilder();
    $container->set('request_stack', $this->requestStack);
    $container->set('account_switcher', $account_switcher);
    $container->set('entity_type.manager', $this->entityTypeManager);

    \Drupal::setContainer($container);

  }

  /**
   * Test get entity variation with relation taxonomy data.
   */
  public function testGetEntityVariation() {
    $this->entityHelperMock = $this->getMockBuilder(EntityHelper::class)
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->entityFieldManager,
        $this->renderer,
        $this->dateFormatter,
        $this->time,
        $this->configFactory,
        $this->database,
        $this->fileUrlGenerator,
      ])->onlyMethods(['getEntityTaxonomyRelations'])
      ->getMock();
    $entity = $this->createEntity();
    $this->setupEntityTypeManager();
    // Validate entity with NULL value in taxonomy.
    $this->validateWithNullRelation($entity);
    $expected = $this->createTaxonomies();
    // Validate entity with taxonomy relation.
    $this->validateWithTaxonomyRelation($entity, $expected);
  }

  /**
   * Test getSiteDomain.
   */
  public function testGetSiteDomain() {
    $this->entityHelperMock = $this->getMockBuilder(EntityHelper::class)
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->entityFieldManager,
        $this->renderer,
        $this->dateFormatter,
        $this->time,
        $this->configFactory,
        $this->database,
        $this->fileUrlGenerator,
      ])->onlyMethods(['getEntityTaxonomyRelations'])
      ->getMock();

    $site_hash = PerzHelper::getSiteDomain();
    $this->assertEquals('localhost', $site_hash);
  }

  /**
   * Ensures that the entity type manager returns an entity storage.
   */
  protected function setupEntityTypeManager() {
    $this->entityTypeManager->expects($this->any())
      ->method('getViewBuilder')
      ->with('node')
      ->willReturn($this->entityViewBuilder);
  }

  /**
   * Create taxonomies.
   */
  protected function createTaxonomies() {
    $tags = $this->createMock(EntityInterface::class);
    $tags->method('id')->willReturn('1');
    $tags->method('bundle')->willReturn('tags');
    $tags->method('getEntityTypeId')->willReturn('taxonomy');
    $tags->method('uuid')->willReturn($this->uuid);

    $material = $this->createMock(EntityInterface::class);
    $material->method('id')->willReturn('1');
    $material->method('bundle')->willReturn('material');
    $material->method('getEntityTypeId')->willReturn('taxonomy');
    $material->method('uuid')->willReturn($this->uuid);

    $categories = $this->createMock(EntityInterface::class);
    $categories->method('id')->willReturn('1');
    $categories->method('bundle')->willReturn('categories');
    $categories->method('getEntityTypeId')->willReturn('taxonomy');
    $categories->method('uuid')->willReturn($this->uuid);

    return [
      [
        "field" => $this->tagsFieldName,
        "terms" => [$tags->uuid()],
      ],
      [
        "field" => $this->materialsFieldName,
        "terms" => [$material->uuid()],
      ],
      [
        "field" => $this->categoriesFieldName,
        "terms" => [$categories->uuid()],
      ],
    ];
  }

  /**
   * Create entity.
   */
  protected function createEntity() {
    $entity = $this->createMock(EntityInterface::class);
    $generatedUrl = $this->createMock(GeneratedUrl::class);
    $generatedUrl->method('getGeneratedUrl')->willReturn('/entity/1');
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn($generatedUrl);
    $link = $this->createMock(Link::class);
    $link->method('getUrl')->willReturn($url);
    $entity->method('label')->willReturn('My title');
    $entity->method('id')->willReturn('1');
    $entity->method('bundle')->willReturn('article');
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('uuid')->willReturn($this->uuid);
    $entity->method('uuid')->willReturn($this->uuid);
    $entity->method('toLink')->willReturn($link);
    $entity->method('toUrl')->willReturn($url);
    $entity->method('access')->willReturn(TRUE);
    return $entity;
  }

  /**
   * Validate entity with empty taxonomy relation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target entity that contains node fields.
   */
  protected function validateWithNullRelation(EntityInterface $entity) {
    $entityHelper = clone $this->entityHelperMock;
    $entityHelper->expects($this->any())
      ->method('getEntityTaxonomyRelations')
      ->willReturn(NULL);

    // Validate with the NULL value in taxonomy relation.
    $result = $this->entityHelperMock
      ->getEntityVariation($entity, 'default', 'en');
    $this->assertEquals($entity->uuid(), $result['content_uuid']);
  }

  /**
   * Validate entity with taxonomy relation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target entity that contains node fields.
   * @param array $expected
   *   Expected result from test.
   */
  protected function validateWithTaxonomyRelation(EntityInterface $entity, array $expected) {
    $this->entityHelperMock->expects($this->any())
      ->method('getEntityTaxonomyRelations')
      ->with($entity)
      ->willReturn($expected);

    // Validate with the taxonomy data in taxonomy field relation.
    $result = $this->entityHelperMock
      ->getEntityVariation($entity, 'default', 'en');
    $this->assertEqualsCanonicalizing($result['relations'], $expected);
  }

}
