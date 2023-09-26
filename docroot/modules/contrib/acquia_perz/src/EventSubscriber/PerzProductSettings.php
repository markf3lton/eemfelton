<?php

namespace Drupal\acquia_perz\EventSubscriber;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaProductSettingsEvent;
use Drupal\acquia_perz\PerzHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add metadata from Acquia Search to Acquia Connector's subscription.
 */
class PerzProductSettings implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Acquia Perz settings derived from Connector third party settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Product Name. Would be nice to grab this from the info.yml in the future.
   *
   * @var string
   */
  public static $productName = 'Personalization';

  /**
   * Product Machine Name. Someday, grab this from info.yml.
   *
   * @var string
   */
  public static $productMachineName = 'acquia_perz';

  /**
   * Constructor for Perz Product Settings.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory Service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('acquia_connector.settings')->get('third_party_settings.' . self::$productMachineName);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AcquiaConnectorEvents::ACQUIA_PRODUCT_SETTINGS][] = [
      'onGetProductSettings', 100,
    ];
    $events[AcquiaConnectorEvents::ALTER_PRODUCT_SETTINGS_SUBMIT][] = [
      'onSetProductSettings', 101,
    ];
    return $events;
  }

  /**
   * Places Perz settings within Acquia Connector.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaProductSettingsEvent $event
   *   The dispatched event.
   *
   * @see \Drupal\acquia_connector\Form\SettingsForm
   */
  public function onGetProductSettings(AcquiaProductSettingsEvent $event) {
    $subscription = $event->getSubscription();
    $subscription_data = $subscription->getSubscription();
    $form = [];

    $form['region'] = [
      '#type' => 'select',
      '#title' => $this->t('Region'),
      '#options' => PerzHelper::getRegions(),
      '#default_value' => $subscription_data[self::$productMachineName]['region'] ?? '',
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config['api_key'] ?? '',
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => '',
      '#description' => $this->t('Secret is not displayed in this form. Leave empty unless you want to change the secret to a different value than what is currently stored in Drupal config.<br> The actual value can be seen via <code><strong>drush config-get acquia_connector.settings third_party_settings.acquia_perz.</strong></code>'),
      '#required' => empty($subscription_data[self::$productMachineName]['secret_key']),
    ];

    $form['account_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account ID'),
      '#default_value' => $this->config['account_id'] ?? '',
      '#required' => TRUE,
    ];

    $form['endpoint'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Endpoint URL'),
      '#default_value' => $this->config['endpoint'] ?? PerzHelper::getRegionEndpoint('us'),
    ];

    $event->setProductSettings(self::$productName, self::$productMachineName, $form);
  }

  /**
   * Alters Perz API settings on submission.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaProductSettingsEvent $event
   *   The dispatched event.
   *
   * @see \Drupal\acquia_connector\Form\SettingsForm
   */
  public function onSetProductSettings(AcquiaProductSettingsEvent $event) {
    $subscription = $event->getSubscription();
    $form_state = $event->getFormState();
    $subscription_data = $subscription->getSubscription();
    $secret_key = $form_state['product_settings'][self::$productMachineName]['settings']['secret_key'];
    if (empty($secret_key)) {
      $form_state['product_settings'][self::$productMachineName]['settings']['secret_key'] = $subscription_data[self::$productMachineName]['secret_key'];
    }
    $form_state['product_settings'][self::$productMachineName]['settings']['endpoint'] = PerzHelper::getRegionEndpoint($form_state['product_settings'][self::$productMachineName]['settings']['region']);
    $event->alterProductSettingsSubmit($form_state);
  }

}
