<?php

namespace Drupal\acquia_perz\EventSubscriber\AcquiaSubscriptionData;

use Drupal\acquia_connector\AcquiaConnectorEvents;
use Drupal\acquia_connector\Event\AcquiaSubscriptionDataEvent;
use Drupal\acquia_perz\EventSubscriber\PerzProductSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Add metadata from Acquia Perz to Acquia Connector's subscription.
 */
class AcquiaPerzData implements EventSubscriberInterface {

  /**
   * Representation of the current HTTP request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Acquia Perz Subscription Data Constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request Stack Service.
   */
  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // phpcs:ignore
    $events[AcquiaConnectorEvents::GET_SUBSCRIPTION][] = ['onGetSubscriptionData', 100];
    return $events;
  }

  /**
   * Gets a prebuilt Settings object from Drupal's settings file.
   *
   * @param \Drupal\acquia_connector\Event\AcquiaSubscriptionDataEvent $event
   *   The dispatched event.
   *
   * @see \Drupal\acquia_connector\Settings
   */
  public function onGetSubscriptionData(AcquiaSubscriptionDataEvent $event) {
    $config = $event->getConfig('acquia_connector.settings');
    $config_data = $config->get('third_party_settings.' . PerzProductSettings::$productMachineName);
    if ($config_data === NULL) {
      return;
    }
    $subscription_data = $event->getData();

    // Set default endpoint.
    if (empty($config_data['endpoint'])) {
      $config_data['endpoint'] = 'https://us.perz-api.cloudservices.acquia.io';
    }

    $subscription_data[PerzProductSettings::$productMachineName] = $config_data;

    // Add Acquia Perz module version to subscription data.
    $event->setData($subscription_data);
  }

}
