<?php

namespace Drupal\acquia_perz\Service\Context;

/**
 * Context Interface for Acquia Perz.
 */
interface ContextInterface {

  /**
   * Populate page by context.
   *
   * @param array &$page
   *   The page that is to be populated.
   */
  public function populate(array &$page);

}
