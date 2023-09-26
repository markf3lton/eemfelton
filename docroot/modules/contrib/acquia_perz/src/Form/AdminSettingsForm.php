<?php

namespace Drupal\acquia_perz\Form;

use Drupal\acquia_perz\EntityHelper;
use Drupal\acquia_perz\PerzHelper;
use Drupal\acquia_perz\Service\Helper\SettingsHelper;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that configures settings.
 */
class AdminSettingsForm extends ConfigFormBase {
  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Acauia Perz Entity Helper service.
   *
   * @var \Drupal\acquia_perz\EntityHelper
   */
  protected $entityHelper;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_perz_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'acquia_perz.settings',
    ];
  }

  /**
   * Constructs an AdminSettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity manager.
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *   The messenger service (or null).
   * @param \Drupal\acquia_perz\EntityHelper $entity_helper
   *   The Acauia Perz Entity Helper service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module Handler Service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager Service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, MessengerInterface $messenger, EntityHelper $entity_helper, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityDisplayRepositoryInterface $entity_display_repository) {
    $this->entityFieldManager = $entity_field_manager;
    $this->messenger = $messenger;
    $this->entityHelper = $entity_helper;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // MessengerInterface was introduced by Drupal 8.5.
    // This code is for backwards-compatibility to 8.4 and below.
    $messenger = $container->has('messenger') ? $container->get('messenger') : NULL;

    return new static(
      $container->get('entity_field.manager'),
      $messenger,
      $container->get('acquia_perz.entity_helper'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $site_id = PerzHelper::getSiteId();
    $form['markup'] = [
      '#markup' => $this->t('<p>API settings for the Acquia Personalization service can be found on the <a href="/admin/config/services/acquia-connector">Acquia Connector form</a>.</p>'),
    ];

    // Data collection settings.
    $form['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Acquia Personalization Site ID'),
      '#description' => $this->t("Current site's Acquia Personalization site ID. It must only contain letters, numbers, dashes and underscores. Warning: each site must use a unique value here."),
      '#default_value' => $site_id,
      '#required' => TRUE,
      '#pattern' => '[A-Za-z0-9_-]+',
    ];

    if ($this->moduleHandler->moduleExists('acquia_lift')) {
      $override_value = $this->config('acquia_perz.settings')->get('override_lift_meta_tags') ?? 0;
      $form['override_lift_meta_tags'] = [
        '#title' => $this->t('Switch over anonymous traffic to be personalized by the new Acquia Personalization module'),
        '#type' => 'checkbox',
        '#default_value' => $override_value,
        '#description' => $this->t('While Acquia Lift is enabled, the new Acquia Personalization module will not override HTML meta tags which are used by lift.js to personalize each page for anonymous traffic. This is to allow you to configure Acquia Personalization while keeping your site running on Acquia Lift until the moment you are ready to make the switch to the new module. Once you are ready, check this checkbox.'),
      ];

      $form['migrate_configuration_details'] = [
        '#title' => $this->t('Configuration Migration'),
        '#type' => 'details',
        '#tree' => TRUE,
        '#open' => TRUE,
        '#description' => $this->t('While the Acquia Lift Publisher module is enabled, and before you uninstall the Acquia Lift modules, you can migrate the configuration of all the entity types which were set to be exported in the old module. Note: this will not migrate your content to the new service. Once you have migrated your configuration and verified that the entity types you want to share with Acquia Personalization are set, you will need to re-export all this content using the "Export" tab above.<br><br>'),
      ];

      $form['migrate_configuration_details']['migrate_configuration'] = [
        '#name' => 'migrate_configuration',
        '#type' => 'submit',
        '#value' => $this->t('Migrate configuration'),
      ];
    }

    $form['data_collection_settings'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Data collection settings'),
    ];
    $form['identity'] = $this->buildIdentityForm();
    $form['field_mappings'] = $this->buildFieldMappingsForm();
    $form['udf_person_mappings'] = $this->buildUdfMappingsForm('person');
    $form['udf_touch_mappings'] = $this->buildUdfMappingsForm('touch');
    $form['udf_event_mappings'] = $this->buildUdfMappingsForm('event');
    $form['visibility'] = $this->buildVisibilityForm();
    $form['export_configuration'] = $this->buildEntityConfigurationForm();
    $form['advanced'] = $this->buildAdvancedForm();
    if ($this->moduleHandler->moduleExists('cohesion')) {
      $form['site_studio'] = $this->buildSiteStudioConfigurationForm();
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Build identity form.
   *
   * @return array
   *   Identity form.
   */
  private function buildIdentityForm() {
    $identity_settings = $this->config('acquia_perz.settings')->get('identity');
    $identity_parameter_display_value = $identity_settings['identity_parameter'] ?: 'identity';
    $identity_type_parameter_display_value = $identity_settings['identity_type_parameter'] ?: 'identityType';
    $default_identity_type_display_value = $identity_settings['default_identity_type'] ?: 'account';
    $default_identity_type_default_value = $identity_settings['default_identity_type'] ?: 'email';

    $form = [
      '#title' => $this->t('Identity'),
      '#type' => 'details',
      '#tree' => TRUE,
      '#group' => 'data_collection_settings',
    ];

    $form['identity_parameter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identity Parameter'),
      '#description' => $this->t("The URL link parameter for specific visitor information, such as an email address or social media username, which is sent to the Personalization Profile Manager. Example using <strong>@identity_parameter_display_value</strong>: ?<strong><ins>@identity_parameter_display_value</ins></strong>=jdoe01", [
        '@identity_parameter_display_value' => $identity_parameter_display_value,
      ]),
      '#default_value' => $identity_settings['identity_parameter'],
    ];
    $form['identity_type_parameter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identity Type Parameter'),
      '#description' => $this->t("The URL link parameter that corresponds to a Personalization Profile Manager identifier type (one of the pre-defined ones or a new one you have created). Example using <strong>@identity_type_parameter_display_value</strong>: ?@identity_parameter_display_value=jdoe01&<strong><ins>@identity_type_parameter_display_value</ins></strong>=@default_identity_type_default_value", [
        '@identity_parameter_display_value' => $identity_parameter_display_value,
        '@identity_type_parameter_display_value' => $identity_type_parameter_display_value,
        '@default_identity_type_default_value' => $default_identity_type_default_value,
      ]),
      '#default_value' => $identity_settings['identity_type_parameter'],
      '#states' => [
        'visible' => [
          ':input[name="identity[identity_parameter]"]' => ['!value' => ''],
        ],
      ],
    ];
    $form['default_identity_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Identity Type'),
      '#description' => $this->t('The Personalization Profile Manager identifier type to be used by default. Example using <strong>@default_identity_type_display_value</strong>: a visitor may visit the site through ?@identity_parameter_display_value=jdoe01 and omit the "@identity_type_parameter_display_value" query, and Personalization will automatically identify this visitor as "jdoe01" of <strong><ins>@default_identity_type_display_value</ins></strong></strong> type. Leave this field blank to default to <strong>@default</strong> identity type.', [
        '@default' => 'email',
        '@identity_parameter_display_value' => $identity_parameter_display_value,
        '@identity_type_parameter_display_value' => $identity_type_parameter_display_value,
        '@default_identity_type_display_value' => $default_identity_type_display_value,
      ]),
      '#default_value' => $identity_settings['default_identity_type'],
      '#placeholder' => SettingsHelper::DEFAULT_IDENTITY_TYPE_DEFAULT,
      '#states' => [
        'visible' => [
          ':input[name="identity[identity_parameter]"]' => ['!value' => ''],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Build field mappings form.
   *
   * @return array
   *   Field mappings form.
   */
  private function buildFieldMappingsForm() {
    $field_mappings_settings = $this->config('acquia_perz.settings')->get('field_mappings');
    $field_names = $this->getTaxonomyTermFieldNames();

    $form = [
      '#title' => $this->t('Field Mappings'),
      '#description' => $this->t('Create <a href="@url" target="_blank">Taxonomy vocabularies</a> and map to "content section", "content keywords", and "persona" fields.', [
        '@url' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
      ]),
      '#type' => 'details',
      '#tree' => TRUE,
      '#group' => 'data_collection_settings',
    ];
    $form['content_section'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Section'),
      '#empty_value' => '',
      '#options' => $field_names,
      '#default_value' => $field_mappings_settings['content_section'],
    ];
    $form['content_keywords'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Keywords'),
      '#empty_value' => '',
      '#options' => $field_names,
      '#default_value' => $field_mappings_settings['content_keywords'],
    ];
    $form['persona'] = [
      '#type' => 'select',
      '#title' => $this->t('Persona'),
      '#empty_value' => '',
      '#options' => $field_names,
      '#default_value' => $field_mappings_settings['persona'],
    ];

    return $form;
  }

  /**
   * Build UDF mappings form.
   *
   * @param string $type
   *   The type of UDF field. Can be person, touch or event.
   *
   * @return array
   *   UDF mappings form.
   *
   * @throws \Exception
   *   An exception if the type given is not supported.
   */
  private function buildUdfMappingsForm($type = 'person') {
    if ($type !== 'person' && $type !== 'touch' && $type !== 'event') {
      throw new \Exception('This Udf Field type is not supported');
    }

    $field_mappings_settings = $this->config('acquia_perz.settings')->get('udf_' . $type . '_mappings');
    $field_names = $this->getTaxonomyTermFieldNames();
    $udf_limit = SettingsHelper::getUdfLimitsForType($type);

    $form = [
      '#title' => $this->t('User @type Mappings', ['@type' => ucfirst($type)]),
      '#description' => $this->t('Map taxonomy terms to Visitor Profile @type fields in Acquia Personalization. Select a Taxonomy Reference Field that, if present, will map the value of the specified field to the Acquia Personalization Profile for that specific visitor. No options available? Create <a href="@url" target="_blank">Taxonomy vocabularies</a> and map the corresponding value.', [
        '@url' => Url::fromRoute('entity.taxonomy_vocabulary.collection')->toString(),
        '@type' => $type,
      ]),
      '#type' => 'details',
      '#tree' => TRUE,
      '#group' => 'data_collection_settings',
    ];

    // Go over the amount of fields that we can map.
    for ($i = 1; $i < $udf_limit + 1; $i++) {
      $default_udf_value = $field_mappings_settings[$type . '_udf' . $i]['value'] ?? '';
      $form[$type . '_udf' . $i] = [
        '#type' => 'select',
        '#title' => $this->t('User Profile @type Field @number', [
          '@number' => $i,
          '@type' => ucfirst($type),
        ]),
        '#empty_value' => '',
        '#options' => $field_names,
        '#default_value' => $default_udf_value,
      ];
    }

    return $form;
  }

  /**
   * Get a list of Field names that are targeting type Taxonomy Terms.
   *
   * @return array
   *   An array of field names.
   */
  private function getTaxonomyTermFieldNames() {
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions('node');
    $field_names = [];
    foreach ($definitions as $field_name => $field_storage) {
      if ($field_storage->getType() != 'entity_reference' || $field_storage->getSetting('target_type') !== 'taxonomy_term') {
        continue;
      }
      $field_names[$field_name] = $field_name;
    }

    return $field_names;
  }

  /**
   * Build visibility form.
   *
   * @return array
   *   Visibility form.
   */
  private function buildVisibilityForm() {
    $visibility_settings = $this->config('acquia_perz.settings')->get('visibility');

    $form = [
      '#title' => $this->t('Visibility'),
      '#description' => $this->t('Personalization will skip data collection on those URLs and their aliases.'),
      '#type' => 'details',
      '#tree' => TRUE,
      '#group' => 'data_collection_settings',
    ];
    $form['path_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Path patterns'),
      '#default_value' => $visibility_settings['path_patterns'],
    ];

    return $form;
  }

  /**
   * Display advanced form.
   *
   * @return array
   *   The render array for the advanced form.
   */
  private function buildAdvancedForm() {
    $advanced_settings = $this->config('acquia_perz.settings')->get('advanced');

    // Bootstrap mode was introduced in a update. Instead of providing a update
    // hook, we just handle the "missing default value" case in code.
    if (!isset($advanced_settings['bootstrap_mode'])) {
      $advanced_settings['bootstrap_mode'] = 'auto';
    }

    $form = [
      '#title' => $this->t('Advanced configuration'),
      '#type' => 'details',
      '#tree' => TRUE,
      '#open' => FALSE,
    ];
    $form['bootstrap_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Bootstrap Mode'),
      '#description' => $this->t('"Auto" means Personalization scripts will automatically bootstrap and act as quickly as possible. "Manual" means Personalization scripts will load but withhold itself from collecting data, delivering content, and allowing admins to login; this option is useful when you want to do things on your site (e.g. check a cookie, set field value) before you want Personalization to start bootstrapping; to resume Personalization\'s bootstrapping process, call AcquiaLiftPublicApi.personalize().'),
      '#default_value' => $advanced_settings['bootstrap_mode'],
      '#options' => [
        'auto' => $this->t('Auto'),
        'manual' => $this->t('Manual'),
      ],
    ];
    $form['content_replacement_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Content replacement mode'),
      '#description' => $this->t('The default, site-wide setting for <a href="https://docs.acquia.com/lift/exp-builder/config/modes/" target="_blank">content replacement mode</a>.'),
      '#default_value' => $advanced_settings['content_replacement_mode'],
      '#options' => [
        'trusted' => $this->t('Trusted'),
        'customized' => $this->t('Customized'),
      ],
    ];

    $form['content_origins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Origin Site UUIDs'),
      '#description' => $this->t('Please leave this blank! This is an optional field and should be empty unless recommended otherwise by Acquia. Origins or Sources entered in this field will only be utilized during Personalization configuration & execution. Enter one origin site UUID per line.'),
      '#default_value' => $advanced_settings['content_origins'],
    ];

    return $form;
  }

  /**
   * Build Site Studio Configuration form.
   *
   * @return array
   *   Site Studio Configuration form.
   */
  private function buildSiteStudioConfigurationForm() {
    $config = $this->config(EntityHelper::ENTITY_CONFIG_NAME);
    $config_view_modes = $config->get('view_modes');
    $view_mode = FALSE;
    if ((!empty($config_view_modes) && isset($config_view_modes['component_content']['component_content'])
      && array_key_exists('default', $config_view_modes['component_content']['component_content']))) {
      $view_mode = TRUE;
    }
    $form = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Site Studio Configuration'),
      '#tree' => TRUE,
    ];
    $form['view_mode']['default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Make all Site Studio Component Contents available to Personalization Service'),
      '#default_value' => $view_mode,
    ];
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $options = [];
    foreach ($roles as $role) {
      $options[$role->id()] = $role->label();
    }
    $config_role_name = sprintf('view_modes.%s.%s.%s.render_role', 'component_content', 'component_content', 'default');
    $form['render_role'] = [
      '#title' => $this->t('Render role'),
      '#description' => $this->t('The role to use when rendering entities for personalization.'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $config->get($config_role_name),
      '#states' => [
        'visible' => [
          [':input[name="site_studio[view_mode][default]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Display entity configuration form.
   *
   * @return array
   *   The render array for the advanced form.
   */
  private function buildEntityConfigurationForm() {
    $configurations = $this->config('acquia_perz.entity_config')->get('view_modes');
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    ksort($configurations);
    $form = [
      '#title' => $this->t('Entity configuration'),
      '#type' => 'details',
      '#tree' => TRUE,
      '#open' => FALSE,
      '#description' => $this->t('Below is a list of all the entity types and bundles which are currently set for export to the Acquia Personalization service'),
    ];
    $items = [];
    foreach (array_keys($configurations) as $entity_type_id) {
      $children = [];
      $entity_type_label = (string) $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();
      $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      $children['title'] = $entity_type_label;
      $children['items'] = [];
      foreach (array_keys($configurations[$entity_type_id]) as $bundle_id) {
        $grandChildren = [];
        $grandChildren['title'] = $bundleInfo[$bundle_id]['label'];
        $viewModeOptions = $this->entityDisplayRepository->getViewModeOptionsByBundle($entity_type_id, $bundle_id);
        $grandChildren['items'] = [];
        foreach ($configurations[$entity_type_id][$bundle_id] as $view_mode => $render_role) {
          $item = $viewModeOptions[$view_mode] . ' (Render Role : ' . $roles[$render_role['render_role']]->label() . ')';
          $grandChildren['items'][] = $item;
        }
        $children['items'][] = $grandChildren;
      }
      $items[] = $children;
    }

    $form['list_entity'] = [
      '#type' => 'inline_template',
      '#template' => '{% if items is empty %}
                        <p>No entity configuration found.</p>
                      {% else %}
                        <ul>
                          {% for item in items %}
                            <li><strong>{{item.title}}</strong></li>
                            <ul>
                              {% for item in item.items %}
                                <li><strong>{{item.title}}</strong></li>
                                <ul>
                                  {% for item in item.items %}
                                    <li>{{item}}</li>
                                  {% endfor %}
                                </ul>
                              {% endfor %}
                            </ul>
                          {% endfor %}
                        <ul>
                      {% endif %}',
      '#context' => [
        'items' => $items,
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Verify that the site_id contains no disallowed characters.
    if (preg_match('@[^A-Za-z0-9_-]+@', $form_state->getValue('site_id'))) {
      $form_state->setError($form['site_id'], $this->t('The Acquia Personalization Site ID must only contain letters, numbers, dashes and underscores.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggered_button = $form_state->getTriggeringElement();
    if ($triggered_button['#name'] === 'migrate_configuration') {
      $lift_viewmodes = $this->buildPerzEntityConfiguration();
      $this->configFactory->getEditable('acquia_perz.entity_config')->set('view_modes', $lift_viewmodes)->save();
      $this->migrateSettings();
      $this->messenger->addStatus($this->t('The configuration of all the entity types has been migrated successfully'));
    }
    else {
      $settings = $this->config('acquia_perz.settings');
      $values = $form_state->getValues();
      $this->setSiteIdValue($settings, $values['site_id']);
      $this->setIdentityValues($settings, $values['identity']);
      $this->setFieldMappingsValues($settings, $values['field_mappings']);
      $this->setUdfMappingsValues($settings, $values['udf_person_mappings'], 'person');
      $this->setUdfMappingsValues($settings, $values['udf_event_mappings'], 'event');
      $this->setUdfMappingsValues($settings, $values['udf_touch_mappings'], 'touch');
      $this->setVisibilityValues($settings, $values['visibility']);
      $this->setAdvancedValues($settings, $values['advanced']);
      if ($this->moduleHandler->moduleExists('acquia_lift')) {
        $settings->set('override_lift_meta_tags', trim($values['override_lift_meta_tags']));
      }
      $settings->save();
      if ($this->moduleHandler->moduleExists('cohesion')) {
        $this->setSiteStudioConfiguration($values['site_studio']);
      }
    }
    parent::submitForm($form, $form_state);

    // It is required to flush all caches on save. This is because many settings
    // here impact page caches and their invalidation strategies.
    drupal_flush_all_caches();
  }

  /**
   * Set identity values.
   *
   * @param \Drupal\Core\Config\Config $settings
   *   Acquia Personalization config settings.
   * @param array $values
   *   Identity values.
   */
  private function setIdentityValues(Config $settings, array $values) {
    $settings->set('identity.identity_parameter', trim($values['identity_parameter']));
    $settings->set('identity.identity_type_parameter', trim($values['identity_type_parameter']));
    $settings->set('identity.default_identity_type', trim($values['default_identity_type']));
  }

  /**
   * Set field mapping values.
   *
   * @param \Drupal\Core\Config\Config $settings
   *   Acquia Personalization config settings.
   * @param array $values
   *   Field mappings values.
   */
  private function setFieldMappingsValues(Config $settings, array $values) {
    $settings->set('field_mappings.content_section', $values['content_section']);
    $settings->set('field_mappings.content_keywords', $values['content_keywords']);
    $settings->set('field_mappings.persona', $values['persona']);
  }

  /**
   * Set Udf Mapping mapping values to our config object.
   *
   * @param \Drupal\Core\Config\Config $settings
   *   Acquia Personalization config settings.
   * @param array $values
   *   Field mappings values.
   * @param string $type
   *   The type of UDF field. Can be person, touch or event.
   *
   * @throws \Exception
   *   An exception if the type given is not supported.
   */
  private function setUdfMappingsValues(Config $settings, array $values, $type = 'person') {
    if ($type !== 'person' && $type !== 'touch' && $type !== 'event') {
      throw new \Exception('This Udf Field type is not supported');
    }
    $mappings = [];
    foreach ($values as $value_id => $value) {
      if (empty($value)) {
        continue;
      }
      $mappings[$value_id] = [
        'id' => $value_id,
        'value' => $value,
        'type' => 'taxonomy',
      ];
    }
    $settings->set('udf_' . $type . '_mappings', $mappings);
  }

  /**
   * Set visibility values.
   *
   * @param \Drupal\Core\Config\Config $settings
   *   Acquia Personalization config settings.
   * @param array $values
   *   Visibility values.
   */
  private function setVisibilityValues(Config $settings, array $values) {
    $settings->set('visibility.path_patterns', $values['path_patterns']);
  }

  /**
   * Sets the advanced values.
   *
   * @param \Drupal\Core\Config\Config $settings
   *   Acquia Personalization config settings.
   * @param array $values
   *   Advanced values.
   */
  private function setAdvancedValues(Config $settings, array $values) {
    $settings->set('advanced.bootstrap_mode', $values['bootstrap_mode']);
    $settings->set('advanced.content_replacement_mode', $values['content_replacement_mode']);
    $settings->set('advanced.content_origins', $values['content_origins']);
  }

  /**
   * Set Site ID value.
   *
   * @param \Drupal\Core\Config\Config $settings
   *   Acquia Personalization config settings.
   * @param string $value
   *   Site ID value.
   */
  private function setSiteIdValue(Config $settings, string $value) {
    $settings->set('api.site_id', trim($value));
  }

  /**
   * Sets Site Studio configuration.
   *
   * @param array $values
   *   Configuration Values.
   */
  private function setSiteStudioConfiguration(array $values) {
    $config = $this->configFactory->getEditable(EntityHelper::ENTITY_CONFIG_NAME);
    $config_view_mode = $config->get('view_modes');

    // Check if view modes exist and disabled in current request.
    PerzHelper::removeViewModeFromConfig('component_content', 'component_content', 'default', $values, $config_view_mode);
    $config->set('view_modes', $config_view_mode);
    $config->save();
  }

  /**
   * Build Configuration Migration.
   *
   * @return array
   *   Return view mode array.
   */
  public function buildPerzEntityConfiguration() {
    $view_modes = [];
    $config = $this->configFactory->getEditable('acquia_lift_publisher.entity_config');
    $acquia_lift_render_role = $config->get('render_role');
    $acquia_lift_view_modes = $config->get('view_modes');
    foreach (array_keys($acquia_lift_view_modes) as $view_mode) {
      foreach (array_keys($acquia_lift_view_modes[$view_mode]) as $display) {
        foreach ($acquia_lift_view_modes[$view_mode][$display] as $key => $value) {
          if ($key != 'acquia_lift_preview_image') {
            if (isset($acquia_lift_view_modes[$view_mode][$display]['acquia_lift_preview_image'])) {
              $preview_image_field = $acquia_lift_view_modes[$view_mode][$display]['acquia_lift_preview_image'];
              $view_modes[$view_mode][$display][$key] = [
                'render_role' => $acquia_lift_render_role,
                'preview_image' => $preview_image_field,
              ];
            }
            else {
              $view_modes[$view_mode][$display][$key] = [
                'render_role' => $acquia_lift_render_role,
              ];
            }
          }
        }
      }
    }
    return $view_modes;
  }

  /**
   * Migrate Acquia Lift settings.
   */
  public function migrateSettings() {
    $acquia_lift_settings = $this->configFactory->get('acquia_lift.settings');
    $acquia_perz_settings = $this->configFactory->getEditable('acquia_perz.settings');
    $acquia_perz_settings->set('identity', $acquia_lift_settings->get('identity'));
    $acquia_perz_settings->set('field_mappings', $acquia_lift_settings->get('field_mappings'));
    $acquia_perz_settings->set('udf_person_mappings', $acquia_lift_settings->get('udf_person_mappings'));
    $acquia_perz_settings->set('udf_touch_mappings', $acquia_lift_settings->get('udf_touch_mappings'));
    $acquia_perz_settings->set('udf_event_mappings', $acquia_lift_settings->get('udf_event_mappings'));
    $acquia_perz_settings->set('visibility', $acquia_lift_settings->get('visibility'));
    $acquia_perz_settings->set('advanced.bootstrap_mode', $acquia_lift_settings->get('advanced.bootstrap_mode'));
    $acquia_perz_settings->set('advanced.content_replacement_mode', $acquia_lift_settings->get('advanced.content_replacement_mode'));
    $acquia_perz_settings->set('advanced.content_origins', $acquia_lift_settings->get('advanced.content_origins'));
    $acquia_perz_settings->set('langcode', $acquia_lift_settings->get('langcode'));
    $acquia_perz_settings->save();
  }

}
