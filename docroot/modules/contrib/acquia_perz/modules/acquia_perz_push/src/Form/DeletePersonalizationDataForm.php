<?php

namespace Drupal\acquia_perz_push\Form;

use Drupal\acquia_perz\ClientFactory;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete Personalization Data Form.
 */
class DeletePersonalizationDataForm extends ConfirmFormBase {

  use StringTranslationTrait;

  /**
   * The Client Factory Service.
   *
   * @var \Drupal\acquia_perz\ClientFactory
   */
  protected $clientFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\acquia_perz\ClientFactory $client_factory
   *   Entity Helper service.
   */
  public function __construct(ClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = new static(
      $container->get('acquia_perz.client_factory')
    );
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_delete_personalizaiton_data_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to purge content from Personalization?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('acquia_perz_push.export_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. You will need to re-export Drupal content to continue using Personalization.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Purge');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->clientFactory->deleteContentFromCis();
    $messenger = $this->messenger();
    $messenger->addMessage("All contents exported from this site have been deleted from the Personalization service.");
    $form_state->setRedirect('acquia_perz_push.export_form');
  }

}
