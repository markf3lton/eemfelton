<?php

namespace Drupal\Tests\acquia_perz\Kernel;

use Drupal\acquia_connector\Subscription;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\RoleInterface;

/**
 * Tests Module Install and Uninstall.
 *
 * @group acquia_perz
 *
 * @requires module acquia_contenthub
 * @requires module acquia_contenthub_publisher
 * @requires module depcalc
 */
class ModuleInstallationTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paragraphs',
    'entity_reference_revisions',
    'node',
    'datetime',
    'block',
    'block_content',
    'language',
    'locale',
    'taxonomy',
    'image',
    'acquia_perz',
    'acquia_connector',
    'path_alias',
    'acquia_lift',
    'acquia_lift_publisher',
    'acquia_contenthub',
    'acquia_contenthub_publisher',
    'depcalc',
  ];

  /**
   * {@inheritdoc}
   *
   * @requires module acquia_contenthub
   * @requires module acquia_contenthub_publisher
   * @requires module depcalc
   */
  protected function setUp(): void {
    $this->markTestSkipped('Marking as skipped due to acquia_contenthub issues');
    parent::setUp();

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
    $this->installSchema('node', 'node_access');
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location', 'locale_file']);
    $this->installConfig('node');
    $this->installConfig('image');
    $this->installConfig('acquia_perz');
    $this->installConfig('acquia_lift');
    $this->installEntitySchema('node');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('date_format');
    $this->installEntitySchema('paragraph');
    $this->drupalCreateRole([], RoleInterface::ANONYMOUS_ID);
    $this->drupalCreateRole([], RoleInterface::AUTHENTICATED_ID);
    $config = $this->config('acquia_lift.settings');
    $config->set('credential', ['site_id' => 'LiftSiteID']);
    $config->save();
  }

  /**
   * Tests acquia_perz_install and acquia_perz_uninstall.
   */
  public function testInstallUninstall(): void {
    $this->container->get('module_handler')->loadInclude('acquia_perz', 'install');
    acquia_perz_install();
    $site_hash = \Drupal::state()->get('acquia_perz.site_hash');
    $this->assertNotNull($site_hash);
    $this->assertEquals($this->config('acquia_perz.settings')->get('api.site_id'), $this->config('acquia_lift.settings')->get('credential.site_id'));

    acquia_perz_uninstall();
    $site_hash = \Drupal::state()->get('acquia_perz.site_hash');
    $this->assertNull($site_hash);
  }

}
