<?php

namespace Drupal\acquia_perz;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ActiveTheme;

/**
 * Perz helper class.
 */
class PerzHelper {

  /**
   * Returns a unique hash for the current site.
   *
   * @return string
   *   A unique site hash, containing only alphanumeric characters.
   */
  public static function getSiteId() {
    $config = \Drupal::configFactory()->get('acquia_perz.settings');
    $site_id = $config->get('api.site_id') ?? '';
    if ($site_id == '' && \Drupal::moduleHandler()->moduleExists('acquia_lift')) {
      $site_id = \Drupal::configFactory()->get('acquia_lift.settings')->get('credential.site_id') ?? '';
    }
    return $site_id;
  }

  /**
   * Returns a environment variable.
   *
   * @return string
   *   An acquia site environment.
   */
  public static function getSiteEnvironment() {
    // During Beta, only production environment is supported.
    return 'prod';
  }

  /**
   * Get a site domain.
   *
   * @return string
   *   Returns a site domain.
   */
  public static function getSiteDomain() {
    return \Drupal::requestStack()->getCurrentRequest()->getHost();
  }

  /**
   * Get a site domain with host.
   *
   * @return string
   *   Returns a site domain with host.
   */
  public static function getSiteDomainWithHost() {
    return \Drupal::requestStack()->getCurrentRequest()->getSchemeAndHttpHost();
  }

  /**
   * Get account id.
   *
   * @return string|null
   *   Returns account id.
   */
  public static function getAccountId() {
    $subscription = \Drupal::service('acquia_connector.subscription');
    $subscription_data = $subscription->getSubscription();
    if (isset($subscription_data['acquia_perz'])) {
      return $subscription_data['acquia_perz']['account_id'];
    }
    return NULL;
  }

  /**
   * Collect imagefields for a particular entity bundle.
   *
   * @param string $entity_type_id
   *   The entity type identifier.
   * @param string $bundle
   *   The bundle name.
   *
   * @return array
   *   The array of image fields for a given entity bundle.
   */
  public static function collectImageFields($entity_type_id, $bundle) {
    $image_fields = [];
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($field_definitions as $field_key => $field_definition) {
      if ($field_definition->getType() === 'image') {
        $image_fields[$field_key] = $field_definition->getLabel();
      }
    }
    return $image_fields;
  }

  /**
   * Remove/update data from settings configuration.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle of entity type.
   * @param string $view_mode
   *   The view_mode of bundle.
   * @param array $data
   *   User input data.
   * @param array $config_view_mode
   *   Config_view_mode of previous store data.
   */
  public static function removeViewModeFromConfig($entity_type_id, $bundle, $view_mode, array $data, array &$config_view_mode) {
    $entity_view_perz_config = array_filter($data['view_mode']);
    if ((!empty($config_view_mode) && isset($config_view_mode[$entity_type_id][$bundle])
        && array_key_exists($view_mode, $config_view_mode[$entity_type_id][$bundle]))
      && empty($entity_view_perz_config)) {
      if (count($config_view_mode[$entity_type_id][$bundle]) > 1) {
        unset($config_view_mode[$entity_type_id][$bundle][$view_mode]);
      }
      elseif (count($config_view_mode[$entity_type_id]) > 1) {
        unset($config_view_mode[$entity_type_id][$bundle]);
      }
      else {
        unset($config_view_mode[$entity_type_id]);
      }
    }
    else {
      if (!empty($entity_view_perz_config)) {
        $role['render_role'] = $data['render_role'];
        $preview_image = isset($data['acquia_perz_preview_image']) ? ['preview_image' => $data['acquia_perz_preview_image']] : [];
        $personalization_label = isset($data['personalization_label']) ? ['personalization_label' => $data['personalization_label']] : [];
        $config_view_mode[$entity_type_id][$bundle][$view_mode] = array_merge($role, $preview_image, $personalization_label);
      }
    }
  }

  /**
   * Get view mode minimal HTML.
   *
   * @param \Drupal\Core\Entity\EntityInterface $object
   *   The content entity object.
   * @param string $view_mode
   *   The view mode identifier.
   * @param string $lang_code
   *   The Language code.
   *
   * @return array
   *   The view mode minimal HTML.
   */
  public static function getViewModeMinimalHtml(EntityInterface $object, string $view_mode, string $lang_code) {
    $entity_type_id = $object->getEntityTypeId();
    if ($entity_type_id === 'block_content') {
      $build = self::getBlockMinimalBuildArray($object, $view_mode, $lang_code);
    }
    else {
      $build = self::getViewMode($object, $view_mode, $lang_code);
    }
    return $build;
  }

  /**
   * Run decision webhook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function runDecisionWebhook(EntityInterface $entity = NULL) {
    $config = \Drupal::configFactory()->get('acquia_perz.settings');
    $decision_endpoint = $config->get('api.endpoint');
    if (empty($decision_endpoint)
      || \Drupal::moduleHandler()->moduleExists('acquia_perz_push')
      || !\Drupal::service('entity_helper')->isValidEntity($entity)) {
      return;
    }
    $data = [
      'account_id' => self::getAccountId(),
      'origin' => self::getSiteId(),
      'environment' => self::getSiteEnvironment(),
      'domain' => self::getSiteDomain(),
      'entity_type' => $entity->getEntityTypeId(),
      'entity_uuid' => $entity->uuid(),
      'site_hash' => self::getSiteHash(),
    ];
    \Drupal::service('client_factory')->pushEntity($data);
  }

  /**
   * Get Active Theme.
   *
   * @return \Drupal\Core\Theme\ActiveTheme
   *   The active theme object.
   */
  public static function getActiveTheme() {
    return \Drupal::service('theme.manager')->getActiveTheme();
  }

  /**
   * Get Default Theme.
   *
   * @return \Drupal\Core\Theme\ActiveTheme
   *   The active theme object.
   */
  public static function getActiveDefaultTheme() {
    $activeDefaultThemeName = \Drupal::configFactory()->get('system.theme')->get('default');
    return \Drupal::service('theme.initialization')->getActiveThemeByName($activeDefaultThemeName);
  }

  /**
   * Set Active Theme.
   *
   * @param \Drupal\Core\Theme\ActiveTheme $theme
   *   The active theme object.
   */
  public static function setActiveTheme(ActiveTheme $theme) {
    \Drupal::service('theme.initialization')->loadActiveTheme($theme);
    \Drupal::service('theme.manager')->setActiveTheme($theme);
  }

  /**
   * Safely switches to another account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to switch to.
   */
  public static function switchAccountTo(AccountInterface $account) {
    \Drupal::service('account_switcher')->switchTo($account);
  }

  /**
   * Reverts to a previous account after switching.
   */
  public static function switchAccountBack() {
    \Drupal::service('account_switcher')->switchBack();
  }

  /**
   * Creates a unique hash for the current site.
   *
   * @return string
   *   A unique hash, containing only alphanumeric characters.
   */
  public static function createSiteHash() {
    $base_url = \Drupal::requestStack()->getCurrentRequest()->getHost();
    $hash = substr(base_convert(hash('sha256', uniqid($base_url, TRUE)), 16, 36), 0, 6);
    \Drupal::state()->set('acquia_perz.site_hash', $hash);
    return $hash;
  }

  /**
   * Returns a unique hash for the current site.
   *
   * @return string
   *   A unique hash, containing only alphanumeric characters.
   */
  public static function getSiteHash() {
    if (!($hash = \Drupal::state()->get('acquia_perz.site_hash', FALSE))) {
      $hash = self::createSiteHash();
    }
    return $hash;
  }

  /**
   * Renders block using BlockViewBuilder.
   *
   * @param \Drupal\Core\Entity\EntityInterface $object
   *   The Content Entity Object.
   * @param string $view_mode
   *   The request view mode identifier.
   * @param string $lang_code
   *   The Language code.
   *
   * @return array
   *   Render array for the block.
   */
  public static function getBlockMinimalBuildArray(EntityInterface $object, string $view_mode, string $lang_code) {
    $block = \Drupal::service('plugin.manager.block')->createInstance('block_content:' . $object->uuid());
    $build = [
      '#theme' => 'block',
      '#attributes' => [],
      '#contextual_links' => [],
      '#weight' => 0,
      '#configuration' => $block->getConfiguration(),
      '#plugin_id' => $block->getPluginId(),
      '#base_plugin_id' => $block->getBaseId(),
      '#derivative_plugin_id' => $block->getDerivativeId(),
    ];

    if ($build['#configuration']['label'] === '') {
      $build['#configuration']['label'] = $object->label();
    }

    // Block entity itself doesn't have configuration.
    $block->setConfigurationValue('view_mode', $view_mode);
    $build['#configuration']['view_mode'] = $view_mode;
    // See \Drupal\block\BlockViewBuilder::preRender() for reference.
    $content = self::getViewMode($object, $view_mode, $lang_code);
    if ($content !== NULL && !Element::isEmpty($content)) {
      foreach (['#attributes', '#contextual_links'] as $property) {
        if (isset($content[$property])) {
          $build[$property] += $content[$property];
          unset($content[$property]);
        }
      }
    }
    $build['content'] = $content;
    return $build;
  }

  /**
   * Returns the applicable render array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The renderable entity.
   * @param string $view_mode
   *   The view mode to render in.
   * @param string $lang_code
   *   The Language code.
   *
   * @return array
   *   The render array.
   */
  public static function getViewMode(EntityInterface $entity, string $view_mode, string $lang_code) {
    return \Drupal::entityTypeManager()
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode, $lang_code);
  }

  /**
   * Override Acquia Lift settings.
   */
  public static function shouldOverrideLiftSettings() {
    $override_lift = \Drupal::configFactory()->get('acquia_perz.settings')->get('override_lift_meta_tags');

    if (\Drupal::moduleHandler()->moduleExists('acquia_lift') && !$override_lift) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Migrate Site ID.
   */
  public static function migrateSiteId() {
    if (\Drupal::moduleHandler()->moduleExists('acquia_lift')) {
      $acquia_lift_settings = \Drupal::configFactory()->get('acquia_lift.settings');
      $acquia_perz_settings = \Drupal::configFactory()->getEditable('acquia_perz.settings');
      if (!empty($acquia_lift_settings->get('credential.site_id'))) {
        $acquia_perz_settings->set('api.site_id', $acquia_lift_settings->get('credential.site_id'));
      }
      $acquia_perz_settings->save();
    }
  }

  /**
   * Get Personalization Regions.
   *
   * @return array
   *   Returns array of Personalization regions.
   */
  public static function getRegions() {
    return [
      'us' => 'The Americas',
      'eu' => 'Europe',
      'ap' => 'Asia-Pacific',
      'demo' => 'Demo',
    ];
  }

  /**
   * Get Regions Endpoint.
   *
   * @param string $region
   *   Region code.
   *
   * @return string
   *   Returns Endpoint url for the region.
   */
  public static function getRegionEndpoint(string $region = 'us') {
    switch ($region) {
      case 'eu':
        return 'https://eu.perz-api.cloudservices.acquia.io';

      case 'ap':
        return 'https://ap.perz-api.cloudservices.acquia.io';

      case 'demo':
        return 'https://demo.perz-api.cloudservices.acquia.io';

      default:
        return 'https://us.perz-api.cloudservices.acquia.io';
    }
  }

}
