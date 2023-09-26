<?php

namespace Drupal\acquia_perz_push\Form;

use Drupal\acquia_perz_push\ExportQueue;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the form to export content via Queue.
 */
class ExportForm extends FormBase {

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Export Queue Service.
   *
   * @var \Drupal\acquia_perz_push\ExportQueue
   */
  protected $exportQueue;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The database object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The CronInterface object.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\acquia_perz_push\ExportQueue $export_queue
   *   Export Queue service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory service to get new/existing queues for use.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param Drupal\Core\CronInterface $cron
   *   The cron service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, ExportQueue $export_queue, QueueFactory $queue_factory, Connection $database, CronInterface $cron) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->exportQueue = $export_queue;
    $this->queueFactory = $queue_factory;
    $this->database = $database;
    $this->cron = $cron;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('acquia_perz_push.export_queue'),
      $container->get('queue'),
      $container->get('database'),
      $container->get('cron')
    );
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_perz_push_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['queue'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Export Queue'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $queue_count = intval($this->exportQueue->getQueueCount());
    $form['queue']['run_export_queue']['queue-list'] = [
      '#type' => 'item',
      '#title' => $this->t('Number of bulk queue items in the Export Queue'),
      '#description' => $this->t('%num @items.', [
        '%num' => $queue_count,
        '@items' => $queue_count === 1 ? $this->t('item') : $this->t('items'),
      ]),
    ];

    $form['queue']['enqueue_content'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enqueue Content'),
    ];

    $form['queue']['purge_queue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purge Queue'),
    ];

    $form['queue']['process_queue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process Queue'),
    ];

    $form['personalization'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Purge content from Personalization service'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['personalization']['description'] = [
      '#type' => 'item',
      '#description' => $this->t('Click the button below to purge all contents which were exported from this site. Note that this will not delete contents exported from your other sites. If you need to purge your entire Personalization account and delete all contents exported from all your sites, you must use the drush command <code>acquia:perz-purge-all</code>.'),
    ];

    $form['personalization']['purge_content'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purge Personalization Content'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = $this->messenger();
    $triggered_button = $form_state->getTriggeringElement()['#parents'][0];
    if ($triggered_button === 'enqueue_content') {
      $this->exportQueue->rescanContentBulk();
      $messenger->addMessage('All content has been scanned and added to the Queue.');
    }
    elseif ($triggered_button === 'process_queue') {
      $this->exportQueue->exportBulkQueueItems();
      $messenger->addMessage('All content has been exported to Personalization from the Queue.');
    }
    elseif ($triggered_button === 'purge_queue') {
      $this->exportQueue->purgeQueue();
      $messenger->addMessage('All content has been purged from the Queue.');
    }
    elseif ($triggered_button === 'purge_content') {
      $form_state->setRedirect('acquia_perz_push.acquia_delete_personalization_data_form');
    }

  }

}
