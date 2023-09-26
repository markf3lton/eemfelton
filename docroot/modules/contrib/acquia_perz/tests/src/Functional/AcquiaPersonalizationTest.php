<?php

namespace Drupal\Tests\acquia_perz\Functional;

use Drupal\Tests\acquia_perz\Traits\ImageFieldCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the Acquia Personalization UI.
 *
 * @group acquia_perz
 */
class AcquiaPersonalizationTest extends BrowserTestBase {

  use ImageFieldCreationTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field',
    'field_ui',
    'acquia_perz',
    'acquia_connector',
    'node',
    'image',
  ];

  /**
   * Holds the setting configuration ID.
   */
  const ENTITY_CONFIG_NAME = 'acquia_perz.entity_config';
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * @var string
   */
  protected $entityType = 'node';

  /**
   * @var string
   */
  protected $bundle = 'article';

  /**
   * @var string
   */
  protected $viewModeDefault = 'default';

  /**
   * @var string
   */
  protected $viewModeTeaser = 'teaser';

  /**
   * @var string
   */
  protected $settings;

  /**
   * @var string
   */
  protected $session;

  /**
   * @var string
   */
  protected $page;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->settings = $this->config(self::ENTITY_CONFIG_NAME);
    $this->session = $this->assertSession();
    $this->page = $this->getSession()->getPage();

    $this->createContentType([
      'type' => $this->bundle,
      'name' => 'Article',
    ]);

    // Log in as a user that can edit layout templates.
    $this->drupalLogin($this->drupalCreateUser([
      'administer acquia perz',
      'administer node display',
    ]));
  }

  /**
   * Tests Acquia Personalization with image field for default view mode.
   */
  public function testWithImageFieldOnDefaultViewMode() {
    $data = ['render_role' => 'authenticated', 'preview_image' => 'image'];
    $this->createImageField('field_image', $this->bundle);

    $field_ui_prefix = "admin/structure/types/manage/$this->bundle";

    // Enabling Acquia Personalization for the default mode.
    $this->drupalGet("$field_ui_prefix/display/$this->viewModeDefault");
    $this->page->checkField("personalization[view_mode][$this->viewModeDefault]");
    $this->page->selectFieldOption('personalization[acquia_perz_preview_image]', $data['preview_image']);
    $this->page->selectFieldOption('personalization[render_role]', $data['render_role']);
    $this->page->pressButton('Save');

    $view_modes = $this->createFormattingArrayData($this->entityType, $this->bundle, $this->viewModeDefault, $data['render_role'], $data['preview_image']);
    $this->settings->set('view_modes', $view_modes);
    $this->settings->save();
    $config_role_name = sprintf('view_modes.%s.%s.%s.render_role', $this->entityType, $this->bundle, $this->viewModeDefault);
    $preview_image = sprintf('view_modes.%s.%s.%s.preview_image', $this->entityType, $this->bundle, $this->viewModeDefault);
    $this->assertEquals($data['render_role'], $this->settings->get($config_role_name));
    $this->assertEquals($data['preview_image'], $this->settings->get($preview_image));
    $this->session->pageTextContains('Your settings have been saved.');

  }

  /**
   * Tests Acquia Personalization with image field for teaser view mode.
   */
  public function testWithImageFieldOnTeaserViewMode() {
    $data = ['render_role' => 'authenticated', 'preview_image' => 'image'];
    $this->createImageField('field_image', $this->bundle);

    $field_ui_prefix = "admin/structure/types/manage/$this->bundle";

    // Enabling Acquia Personalization for the teaser mode.
    $this->drupalGet("$field_ui_prefix/display/$this->viewModeDefault");
    $this->clickLink('Teaser');
    $this->session->addressEquals("$field_ui_prefix/display/$this->viewModeTeaser");
    $this->page->checkField("personalization[view_mode][$this->viewModeTeaser]");
    $this->page->selectFieldOption('personalization[acquia_perz_preview_image]', $data['preview_image']);
    $this->page->selectFieldOption('personalization[render_role]', $data['render_role']);
    $this->page->pressButton('Save');

    $view_modes = $this->createFormattingArrayData($this->entityType, $this->bundle, $this->viewModeTeaser, $data['render_role'], $data['preview_image']);
    $this->settings->set('view_modes', $view_modes);
    $this->settings->save();
    $config_role_name = sprintf('view_modes.%s.%s.%s.render_role', $this->entityType, $this->bundle, $this->viewModeTeaser);
    $preview_image = sprintf('view_modes.%s.%s.%s.preview_image', $this->entityType, $this->bundle, $this->viewModeTeaser);
    $this->assertEquals($data['render_role'], $this->settings->get($config_role_name));
    $this->assertEquals($data['preview_image'], $this->settings->get($preview_image));
    $this->session->pageTextContains('Your settings have been saved.');

  }

  /**
   * Tests Acquia Personalization without image field for default view mode.
   */
  public function testWithoutImageFieldOnDefaultViewMode() {
    $data = ['render_role' => 'anonymous'];

    $field_ui_prefix = "admin/structure/types/manage/$this->bundle";

    // Enabling Acquia Personalization for the default mode.
    $this->drupalGet("$field_ui_prefix/display/$this->viewModeDefault");
    $this->page->checkField("personalization[view_mode][$this->viewModeDefault]");
    $this->page->selectFieldOption('personalization[render_role]', $data['render_role']);
    $this->page->pressButton('Save');

    $view_modes = $this->createFormattingArrayData($this->entityType, $this->bundle, $this->viewModeDefault, $data['render_role']);
    $this->settings->set('view_modes', $view_modes);
    $this->settings->save();
    $config_role_name = sprintf('view_modes.%s.%s.%s.render_role', $this->entityType, $this->bundle, $this->viewModeDefault);
    $this->assertEquals($data['render_role'], $this->settings->get($config_role_name));
    $this->session->pageTextContains('Your settings have been saved.');

  }

  /**
   * Tests Acquia Personalization without image field for teaser view mode.
   */
  public function testWithoutImageFieldOnTeaserViewMode() {
    $data = ['render_role' => 'authenticated'];

    $field_ui_prefix = "admin/structure/types/manage/$this->bundle";

    // Enabling Acquia Personalization for the teaser mode.
    $this->drupalGet("$field_ui_prefix/display/$this->viewModeDefault");
    $this->clickLink('Teaser');
    $this->session->addressEquals("$field_ui_prefix/display/$this->viewModeTeaser");
    $this->page->checkField("personalization[view_mode][$this->viewModeTeaser]");
    $this->page->selectFieldOption('personalization[render_role]', $data['render_role']);
    $this->page->pressButton('Save');

    $view_modes = $this->createFormattingArrayData($this->entityType, $this->bundle, $this->viewModeTeaser, $data['render_role']);
    $this->settings->set('view_modes', $view_modes);
    $this->settings->save();
    $config_role_name = sprintf('view_modes.%s.%s.%s.render_role', $this->entityType, $this->bundle, $this->viewModeTeaser);
    $this->assertEquals($data['render_role'], $this->settings->get($config_role_name));
    $this->session->pageTextContains('Your settings have been saved.');

  }

  /**
   * Tests Acquia Personalization default Render Role.
   */
  public function testDefaultRenderRole() {
    $field_ui_prefix = "admin/structure/types/manage/$this->bundle";
    $roles = Role::loadMultiple();
    $this->assertEquals(0, $roles['anonymous']->getWeight());
    $roles['anonymous']->setWeight(3)->save();
    $this->assertNotEquals(0, $roles['anonymous']->getWeight());
    $this->drupalGet("$field_ui_prefix/display/$this->viewModeDefault");
    $this->assertTrue($this->assertSession()->optionExists('edit-personalization-render-role', 'anonymous')->isSelected());
  }

  /**
   * Prepare array for config entity.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $bundle
   *   The bundle of entity type.
   * @param string $viewMode
   *   The view_mode of bundle.
   * @param string $user_role
   *   User input data.
   * @param string $image
   *   Config_view_mode of previous store data.
   *
   * @return array
   *   Returns formatted array.
   */
  protected function createFormattingArrayData($entityTypeId, $bundle, $viewMode, $user_role, $image = '') {
    $role['render_role'] = $user_role;
    $preview_image = !empty($image) ? ['preview_image' => $image] : [];
    $view_modes[$entityTypeId][$bundle][$viewMode] = array_merge($role, $preview_image);
    return $view_modes;
  }

}
