<?php

namespace Drupal\acquia_perz_push;

use Drupal\acquia_perz\ClientFactory;
use Drupal\acquia_perz\EntityHelper;
use Drupal\acquia_perz\PerzHelper;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use GuzzleHttp\Exception\TransferException;

/**
 * Contains helper methods for managing Content Index Service exports.
 *
 * @package Drupal\acquia_perz_push
 */
class ExportContent {

  /**
   * The perz http client service.
   *
   * @var \Drupal\acquia_perz\ClientFactory
   */
  protected $perzHttpClient;

  /**
   * The export queue service.
   *
   * @var \Drupal\acquia_perz_push\ExportQueue
   */
  protected $exportQueue;

  /**
   * The export tracker service.
   *
   * @var \Drupal\acquia_perz_push\ExportTracker
   */
  protected $exportTracker;

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
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The uuid generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

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
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Perz entity helper.
   *
   * @var \Drupal\acquia_perz\EntityHelper
   */
  protected $entityHelper;

  /**
   * ExportContent constructor.
   *
   * @param \Drupal\acquia_perz\ClientFactory $perz_http_client
   *   The http client service.
   * @param \Drupal\acquia_perz_push\ExportQueue $export_queue
   *   The Export Queue service.
   * @param \Drupal\acquia_perz_push\ExportTracker $export_tracker
   *   The Export Tracker service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   The UUID generator.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\acquia_perz\EntityHelper $entity_helper
   *   The entity helper service.
   */
  public function __construct(ClientFactory $perz_http_client, ExportQueue $export_queue, ExportTracker $export_tracker, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, RendererInterface $renderer, UuidInterface $uuid_generator, DateFormatterInterface $date_formatter, TimeInterface $time, EntityHelper $entity_helper) {
    $this->perzHttpClient = $perz_http_client;
    $this->exportQueue = $export_queue;
    $this->exportTracker = $export_tracker;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->renderer = $renderer;
    $this->uuidGenerator = $uuid_generator;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
    $this->entityHelper = $entity_helper;
  }

  /**
   * Export all entity view modes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   */
  public function exportEntity(EntityInterface $entity) {
    if (!$this->entityHelper->isValidEntity($entity)) {
      return;
    }
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    $entity_uuid = $entity->uuid();
    $langcode = $entity->language()->getId();

    try {
      $this->exportEntities([
        [
          'entity_type_id' => $entity_type_id,
          'entity_id' => $entity_id,
          'entity_uuid' => $entity_uuid,
        ],
      ], $entity->language()->getId());
    }
    catch (TransferException $e) {
      if ($e->getCode() === 0) {
        $this->exportTracker->exportTimeout(
          $entity_type_id,
          $entity_id,
          $entity_uuid,
          $langcode
        );
        $this->exportQueue->addBulkQueueItem(
          'insert_or_update',
          [
            [
              'entity_type_id' => $entity_type_id,
              'entity_id' => $entity_id,
              'entity_uuid' => $entity_uuid,
            ],
          ],
          $langcode
        );
        return ExportTracker::FAILED;
      }
    }
  }

  /**
   * Get and export entities from the list.
   *
   * @param array $entities
   *   List of the entities that should be exported.
   * @param string $langcode
   *   Language code of the entity translation that should be exported.
   *   'all' value means that all entity translations should be exported.
   */
  public function exportEntities(array $entities, $langcode = 'all') {
    $entities_payload = [];

    // Make default theme active theme.
    $activeTheme = PerzHelper::getActiveTheme();
    $activeDefaultTheme = PerzHelper::getActiveDefaultTheme();
    PerzHelper::setActiveTheme($activeDefaultTheme);

    foreach ($entities as $entity_item) {
      $entity_type_id = $entity_item['entity_type_id'];
      $entity_id = $entity_item['entity_id'];

      $entities_payload = array_merge(
        $entities_payload,
        $this->getEntityPayload($entity_type_id, $entity_id, $langcode)
      );
    }

    // Switch back to original active theme.
    PerzHelper::setActiveTheme($activeTheme);

    $this->sendBulk($entities_payload);
    // Track export for each entity and its languages.
    foreach ($entities as $entity_item) {
      $this->exportTracker->trackEntity(
        $entity_item['entity_type_id'],
        $entity_item['entity_id'],
        $langcode
      );
    }
    return ExportTracker::EXPORTED;
  }

  /**
   * Get and export entity by its entity type and id.
   *
   * @param string $entity_type_id
   *   Entity type id of the entity that should be exported.
   * @param int $entity_id
   *   Id of the entity that should be exported.
   * @param string $langcode
   *   Language code of the entity translation that should be exported.
   *   'all' value means that all entity translations should be exported.
   */
  public function exportEntityById($entity_type_id, $entity_id, $langcode = 'all') {
    $entity_payload = $this->getEntityPayload($entity_type_id, $entity_id, $langcode);
    $this->sendBulk($entity_payload);
    // @todo tracking.
    return ExportTracker::EXPORTED;
  }

  /**
   * Get list of all entity variations (view modes/translations).
   *
   * @param string $entity_type_id
   *   Entity type id of the entity that should be exported.
   * @param int $entity_id
   *   Id of the entity that should be exported.
   * @param string $langcode
   *   Language code of the entity translation that should be exported.
   *   'all' value means that all entity translations should be exported.
   *
   * @return array|void
   *   Returns list of available view modes/translations variations.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityPayload($entity_type_id, $entity_id, $langcode = 'all') {
    $payload = [];
    $entity = $this
      ->entityTypeManager
      ->getStorage($entity_type_id)
      ->load($entity_id);
    if (!$entity instanceof ContentEntityInterface) {
      return [];
    }
    if (!$view_modes = $this->entityHelper->getEntityViewModesSettingValue($entity)) {
      return [];
    }
    foreach (array_keys($view_modes) as $view_mode) {
      // The preview image field setting is saved along side the view modes.
      // Don't process it as one.
      if ($view_mode == 'acquia_perz_push_preview_image') {
        continue;
      }
      if ($langcode === 'all') {
        foreach ($entity->getTranslationLanguages() as $language) {
          $language_id = $language->getId();
          $translation = $entity->getTranslation($language_id);
          $payload[] = $this->entityHelper->getEntityVariation($translation, $view_mode, $language_id);
        }
      }
      else {
        $translation = $entity->getTranslation($langcode);
        $payload[] = $this->entityHelper->getEntityVariation($translation, $view_mode, $langcode);
      }
    }
    return $payload;
  }

  /**
   * Delete entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   */
  public function deleteEntity(EntityInterface $entity) {
    if ($this->entityHelper->isValidEntity($entity) === FALSE) {
      return;
    }

    $langcode = $entity->language()->getId();
    $entity_uuid = $entity->uuid();
    $view_mode = NULL;
    try {
      $this->deleteEntityById($entity_uuid, $langcode, $view_mode);
    }
    catch (TransferException $e) {
      if ($e->getCode() === 0) {
        $this->exportQueue->addBulkQueueItem(
          'delete_entity',
          [
            [
              'langcode' => $langcode,
              'view_mode' => $view_mode,
              'entity_uuid' => $entity->uuid(),
            ],
          ]
        );
        return ExportTracker::FAILED;
      }
    }
  }

  /**
   * Delete entity by its entity type and id.
   *
   * @param string $entity_uuid
   *   Entity uuid of the entity that should be deleted.
   * @param string $langcode
   *   Langcode of the entity that should be deleted.
   * @param string $view_mode
   *   Viewmode of the entity that should be deleted.
   */
  public function deleteEntityById(string $entity_uuid, string $langcode, string $view_mode = NULL) {
    $data = [
      'content_uuid' => $entity_uuid,
      'account_id' => PerzHelper::getAccountId(),
      'origin' => PerzHelper::getSiteId(),
      'environment' => PerzHelper::getSiteEnvironment(),
      'language' => $langcode,
      'view_mode' => $view_mode,
      'site_hash' => PerzHelper::getSiteHash(),
    ];
    return $this->perzHttpClient->deleteEntities($data);
  }

  /**
   * Delete translation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $translation
   *   The current entity translation.
   * @param string $langcode
   *   Language code of the entity translation that should be deleted.
   */
  public function deleteTranslation(EntityInterface $translation, $langcode = '') {
    if ($this->entityHelper->isValidEntity($translation) === FALSE) {
      return;
    }
    $langcode = $translation->language()->getId();
    $entity_uuid = $translation->uuid();
    $view_mode = NULL;
    try {
      $this->deleteEntityById($entity_uuid, $langcode, $view_mode);
    }

    catch (TransferException $e) {
      if ($e->getCode() === 0) {
        $this->exportQueue->addBulkQueueItem(
          'delete_translation',
          [
            [
              'langcode' => $langcode,
              'view_mode' => $view_mode,
              'entity_uuid' => $entity_uuid,
            ],
          ]
        );
        return ExportTracker::FAILED;
      }
    }
  }

  /**
   * Delete translation by its entity type, entity id and langcode.
   *
   * @param string $entity_type_id
   *   Entity type id of the entity that should be deleted.
   * @param int $entity_id
   *   Id of the entity that should be deleted.
   * @param string $entity_uuid
   *   The entity uuid of the entity that should be deleted.
   * @param string $langcode
   *   Language code of the entity translation that should be deleted.
   */
  public function deleteTranslationById($entity_type_id, $entity_id, $entity_uuid, $langcode) {
    $entity_payload = $this->getEntityPayload($entity_type_id, $entity_id, $langcode);
    $this->send('DELETE', $entity_payload);
  }

  /**
   * Send bulk request to CIS.
   *
   * @param array $entity_variations
   *   The data that should be sent to CIS.
   *
   * @return string
   *   Export status.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function sendBulk(array $entity_variations) {
    $site_hash = PerzHelper::getSiteId();
    $site_env = PerzHelper::getSiteEnvironment();
    $account_id = PerzHelper::getAccountId();
    $this->perzHttpClient->pushDataToPersonalization(
      $account_id,
      $site_hash,
      $site_env,
      $entity_variations
    );
    return ExportTracker::EXPORTED;
  }

}
