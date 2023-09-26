<?php

/**
 * @file
 * Acquia Perz post update.
 */

use Drupal\acquia_perz\PerzHelper;

/**
 * Clear the cache.
 *
 * Due to change in acquia_perz.entity_helper service signature change.
 */
function acquia_perz_post_update_preview_image_file_generator_service() {
  drupal_flush_all_caches();
}

/**
 * Move the Site ID to configuration from state.
 */
function acquia_perz_post_update_move_site_id_state_config() {
  $state_site_id = PerzHelper::getSiteHash();
  $config = \Drupal::configFactory()->getEditable('acquia_perz.settings');
  $config_site_id = $config->get('api.site_id');
  if (empty($config_site_id)) {
    $config->set('api.site_id', $state_site_id);
    $config->save();
  }
}

/**
 * Clear the cache.
 *
 * Due to change in acquia_perz.client_factory service signature change.
 */
function acquia_perz_post_update_preview_client_factory_service() {
  drupal_flush_all_caches();
}

/**
 * Ensure acquia_perz hooks are invoked after acquia_lift.
 */
function acquia_perz_post_update_module_weight_acquia_lift() {
  module_set_weight('acquia_perz', 10);
}
