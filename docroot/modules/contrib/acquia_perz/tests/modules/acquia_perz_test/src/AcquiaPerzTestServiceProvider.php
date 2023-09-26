<?php

namespace Drupal\acquia_perz_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Replace Content Hub Client Factory service for testing purposes.
 */
class AcquiaPerzTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $client_factory_def = $container->getDefinition('acquia_perz.client_factory');
    $client_factory_def->setClass('Drupal\acquia_perz_test\Client\ClientFactoryMock');

  }

}
