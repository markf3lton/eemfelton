<?php

namespace Drupal\acquia_perz;

use Drupal\acquia_perz\Session\AcquiaPerzUserSession;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Entity helper service.
 */
class EntityHelper {

  const ENTITY_CONFIG_NAME = 'acquia_perz.entity_config';

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
   * The File Url Generator.
   *
   * @var \Drupal\core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The rendered user.
   *
   * @var \Drupal\Core\Session\UserSession
   */
  protected $renderUser;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\core\File\FileUrlGeneratorInterface $file_url_generator
   *   The File url generator service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    RendererInterface $renderer,
    DateFormatterInterface $date_formatter,
    TimeInterface $time,
    ConfigFactoryInterface $config_factory,
    Connection $database,
    FileUrlGeneratorInterface $file_url_generator
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->renderer = $renderer;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Is valid entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   */
  public function isValidEntity(EntityInterface $entity) {
    $view_modes = $this->getEntityViewModesSettingValue($entity);
    if (empty($view_modes)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Returns the value of the entity view modes setting.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   *
   * @return array
   *   The setting value.
   */
  public function getEntityViewModesSettingValue(EntityInterface $entity): array {
    $view_modes = $this->configFactory
      ->get('acquia_perz.entity_config')->get("view_modes.{$entity->getEntityTypeId()}.{$entity->bundle()}");
    if ($view_modes) {
      return $view_modes;
    }
    return [];
  }

  /**
   * Get entity query.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param array $bundles
   *   List of bundles of entity type.
   * @param int $offset
   *   The query offset.
   * @param int $limit
   *   The query limit.
   *
   * @return array
   *   Returns entity query.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntitiesQuery($entity_type_id, array $bundles, int $offset = NULL, int $limit = NULL) {
    // Check only bundles with at least one view mode activated
    // besides 'acquia_perz_preview_image' view mode.
    $available_bundles = [];
    foreach ($bundles as $bundle => $view_modes) {
      $view_modes = array_keys($view_modes);
      if (count($view_modes) === 1
        && in_array('acquia_perz_preview_image', $view_modes)) {
        continue;
      }
      $available_bundles[] = $bundle;
    }
    // Skip entity type without activated bundles.
    if (empty($available_bundles)) {
      return [];
    }
    $bundle_property_name = $this
      ->entityTypeManager
      ->getStorage($entity_type_id)
      ->getEntityType()
      ->getKey('bundle');
    $query = $this
      ->entityTypeManager
      ->getStorage($entity_type_id)
      ->getQuery();
    // For single-bundle entity types like 'user'
    // we don't use bundle related property.
    if (!empty($bundle_property_name)) {
      $query = $query->condition($bundle_property_name, $available_bundles, 'IN');
    }
    if ($entity_type_id === 'user') {
      $query = $query->condition('uid', 0, '<>');
    }
    if ($offset !== NULL && $limit !== NULL) {
      $query->range($offset, $limit);
    }

    return $query;
  }

  /**
   * Get entities query count by entity type and available bundles.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param array $bundles
   *   List of bundles and its view modes of entity type.
   *   Format:
   *   [
   *    'page' => [
   *       'default' => 1,
   *       'view_mode2' => 1,
   *       ...
   *    ]
   *   ].
   * @param int|null $offset
   *   The query offset.
   * @param int|null $limit
   *   The query limit.
   *
   * @return int
   *   Returns number of entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCountByEntityTypeId($entity_type_id, array $bundles, int $offset = NULL, int $limit = NULL) {
    $query = $this->getEntitiesQuery($entity_type_id, $bundles, $offset, $limit);
    return (int) $query->accessCheck(TRUE)->count()->execute();
  }

  /**
   * Get single entity uuid by entity id and its entity type.
   *
   * @param \Drupal\Core\Entity\Annotation\ContentEntityType $entity_type
   *   Entity type object.
   * @param int $entity_id
   *   The entity id.
   *
   * @return string
   *   Returns entity uuid.
   */
  public function getEntityUuidById(ContentEntityType $entity_type, int $entity_id) {
    $query = $this->database->select($entity_type->get('base_table'), 't');
    $query->addField('t', 'uuid');
    $query->condition($entity_type->getKey('id'), $entity_id);
    $query->range(0, 1);
    return $query->execute()->fetchField();
  }

  /**
   * Get rendered content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   * @param string $view_mode
   *   The view mode.
   * @param string $langcode
   *   The language code.
   */
  public function getRenderedContent(EntityInterface $entity, string $view_mode, string $langcode) {
    $elements = [];
    $render_role = $this->getEntityRenderRole($entity, $view_mode);
    $account = $this->getRenderUser($render_role);
    PerzHelper::switchAccountTo($account);
    $entity_access = $entity->access('view', $account, FALSE);
    if ($entity_access) {
      $elements = PerzHelper::getViewModeMinimalHtml($entity, $view_mode, $langcode);
    }
    $render_content = $this->renderer->renderPlain($elements);

    // Add additional styles added by Site Studio for components.
    // Site studio adds internal CSS styles specific to components
    // while rendering.
    // Since Personalization injects rendered HTML on the client site,
    // it is necessary to have the additional styles for components included
    // in rendered HTML in order to make components work as expected.
    if (!empty($elements['#attached']['cohesion'])) {
      $attachment = implode('', $elements['#attached']['cohesion']);
      $render_content .= $attachment;
    }
    PerzHelper::switchAccountBack();
    return $render_content;
  }

  /**
   * Export entity by view mode.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   * @param string $view_mode
   *   The view mode.
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   Array of entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityVariation(EntityInterface $entity, string $view_mode, string $langcode) {
    $render_role = $this->getEntityRenderRole($entity, $view_mode);
    $rendered_data = $this->getRenderedContent($entity, $view_mode, $langcode);
    $entity_label = !empty($rendered_data) ? $entity->label() : $entity->label() . ' (no content)';
    $personalization_label = $this->getPersonalizationLabel($entity, $view_mode);
    if ($personalization_label !== '') {
      $entity_label = $personalization_label;
    }

    if ($entity->getEntityTypeId() == 'paragraph' || $entity->getEntityTypeId() == 'component_content') {
      $url = NULL;
    }
    else {
      $urlObject = $entity->toUrl()->toString(TRUE);
      $url = $urlObject->getGeneratedUrl();
    }

    $preview_image = NULL;
    $config = $this->configFactory->get(EntityHelper::ENTITY_CONFIG_NAME);
    $config_image_preview_name = 'view_modes.' . $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $view_mode . '.preview_image';
    $config_view_modes = $config->get($config_image_preview_name);
    if (!empty($config_view_modes)) {
      $preview_image_field = $entity->get($config_view_modes);
      if (!empty($preview_image_field) &&  !empty($preview_image_field->target_id)) {
        $preview_image = $this->fileUrlGenerator->generateAbsoluteString($preview_image_field->entity->uri->value);
      }
    }

    $result = [
      'content_uuid' => $entity->uuid(),
      'content_type' => $entity->bundle(),
      'view_mode' => $view_mode,
      'language' => $langcode,
      'number_view' => 0,
      'label' => $entity_label,
      'updated' => $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'Y-m-d\TH:i:s'),
      'rendered_data' => !empty($rendered_data) ? $rendered_data : '<!-- PERZ DEBUG: this content cannot be accessed by the render role ' . $render_role . '  -->',
      'base_url' => PerzHelper::getSiteDomainWithHost(),
      'url' => $url,
      'preview_image' => $preview_image,
    ];
    $taxonomy_relations = $this->getEntityTaxonomyRelations($entity);
    if ($taxonomy_relations) {
      $result['relations'] = $taxonomy_relations;
    }
    return $result;
  }

  /**
   * Get array of related taxonomy term fields/term uuids.
   *
   * Only taxonomies that are checked on Entity settings form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target entity.
   *
   * @return array
   *   Returns array of related taxonomy term fields/term uuids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityTaxonomyRelations(EntityInterface $entity) {
    $relations = [];
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $view_modes = $this->getEntityTypesConfig();
    $available_taxonomies = [];
    if (isset($view_modes['taxonomy_term'])) {
      $available_taxonomies = array_keys($view_modes['taxonomy_term']);
    }
    $fields = $this->entityFieldManager
      ->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fields as $field) {
      if ($field instanceof FieldConfig
        && $field->getType() === 'entity_reference'
        && $field->getSetting('handler') === 'default:taxonomy_term'
      ) {
        $field_name = $field->getName();
        $settings = $field->getSetting('handler_settings');
        $field_taxonomies = $settings['target_bundles'];
        // Check if field contains at least one available taxonomy.
        if (count(array_intersect($available_taxonomies, $field_taxonomies)) == 0) {
          continue;
        }
        $terms = $entity->get($field_name)->getValue();
        $available_field_terms = [];
        foreach ($terms as $term) {
          $term_entity = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->load($term['target_id']);
          $term_uuid = $term_entity->uuid();
          if (in_array($term_entity->bundle(), $available_taxonomies)) {
            $available_field_terms[] = $term_uuid;
          }
        }
        // Skip fields without any terms.
        if (!empty($available_field_terms)) {
          $relations[] = [
            'field' => $field_name,
            'terms' => $available_field_terms,
          ];
        }
      }
    }
    return $relations;
  }

  /**
   * Get list of available types > bundles > view modes.
   *
   * @return array|mixed|null
   *   Returns a list of available types > bundles > view modes.
   */
  public function getEntityTypesConfig() {
    return $this->configFactory
      ->get('acquia_perz.entity_config')
      ->get('view_modes');
  }

  /**
   * Get rendered user.
   *
   * @param string $render_role
   *   The render user role.
   *
   * @return \Drupal\acquia_perz\Session\AcquiaPerzUserSession|\Drupal\Core\Session\UserSession
   *   The rendered user.
   */
  public function getRenderUser(string $render_role) {
    if (!$this->renderUser) {
      $this->renderUser = new AcquiaPerzUserSession($render_role);
    }
    return $this->renderUser;
  }

  /**
   * Returns the value of render role from the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   * @param string $view_mode
   *   The view mode value.
   *
   * @return string
   *   The render role value.
   */
  public function getEntityRenderRole(EntityInterface $entity, string $view_mode) {
    $render_role = 'anonymous';
    $view_modes = $this->getEntityViewModesSettingValue($entity);
    if ($view_modes) {
      $render_role = $view_modes[$view_mode]['render_role'];
    }
    return $render_role;
  }

  /**
   * Returns the Personalization Label of entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   * @param string $view_mode
   *   The view mode value.
   *
   * @return string
   *   The Label to set in Personalization.
   */
  public function getPersonalizationLabel(EntityInterface $entity, string $view_mode) {
    $personalization_label = '';
    $view_modes = $this->getEntityViewModesSettingValue($entity);
    if ($view_modes) {
      $personalization_label_field = $view_modes[$view_mode]['personalization_label'] ?? '';
      if ($personalization_label_field !== '' && $personalization_label_field !== 'default') {
        if ($entity->hasField($personalization_label_field)) {
          $personalization_label = $entity->get($personalization_label_field)->value;
        }
      }
    }
    return $personalization_label;
  }

}
