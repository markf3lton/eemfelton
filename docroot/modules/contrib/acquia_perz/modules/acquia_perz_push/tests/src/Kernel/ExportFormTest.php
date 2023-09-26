<?php

namespace Drupal\Tests\acquia_perz_push\Kernel;

use Drupal\acquia_perz_push\Form\ExportForm;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;

/**
 * Tests Export Form.
 *
 * @coversDefaultClass \Drupal\acquia_perz_push\Form\ExportForm
 * @group acquia_perz
 */
class ExportFormTest extends PerzPushTestBase {

  /**
   * The Export settings elements form object under test.
   *
   * @var \Drupal\acquia_perz_push\Form\ExportForm
   */
  protected $exportForm;

  /**
   * {@inheritdoc}
   *
   * @covers ::\__construct()
   */
  protected function setUp(): void {
    parent::setUp();
    $this->exportForm = ExportForm::create($this->container);
  }

  /**
   * Test Export Form.
   */
  public function testExportForm() {
    $this->assertInstanceOf(FormInterface::class, $this->exportForm);
    $id = $this->exportForm->getFormId();
    $this->assertEquals('acquia_perz_push_export_form', $id);
  }

  /**
   * Test Export Form Enqueue Content.
   */
  public function testExportFormEnqueueContent() {
    $triggering_element = [
      "#type" => "submit",
      "#parents" => [
        0 => "enqueue_content",
      ],
    ];

    $form_state = (new FormState())->setTriggeringElement($triggering_element);
    $form = $this->exportForm->buildForm([], $form_state);
    $this->exportForm->submitForm($form, $form_state);
    $messages = \Drupal::messenger()->all();
    \Drupal::messenger()->deleteAll();
    $this->assertTrue(isset($messages['status']));
    $status_messages = $messages['status'];
    $this->assertEquals('All content has been scanned and added to the Queue.', $status_messages[0]);
  }

  /**
   * Test Export Form Purge Queue.
   */
  public function testExportFormPurgeQueue() {
    $triggering_element = [
      "#type" => "submit",
      "#parents" => [
        0 => "purge_queue",
      ],
    ];

    $form_state = (new FormState())->setTriggeringElement($triggering_element);
    $form = $this->exportForm->buildForm([], $form_state);
    $this->exportForm->submitForm($form, $form_state);
    $messages = \Drupal::messenger()->all();
    \Drupal::messenger()->deleteAll();
    $this->assertTrue(isset($messages['status']));
    $status_messages = $messages['status'];
    $this->assertEquals('All content has been purged from the Queue.', $status_messages[0]);
  }

  /**
   * Test Export Form Process Queue.
   */
  public function testExportFormProcessQueue() {
    $triggering_element = [
      "#type" => "submit",
      "#parents" => [
        0 => "process_queue",
      ],
    ];

    $form_state = (new FormState())->setTriggeringElement($triggering_element);
    $form = $this->exportForm->buildForm([], $form_state);
    $this->exportForm->submitForm($form, $form_state);
    $messages = \Drupal::messenger()->all();
    \Drupal::messenger()->deleteAll();
    $this->assertTrue(isset($messages['status']));
    $status_messages = $messages['status'];
    $this->assertEquals('All content has been exported to Personalization from the Queue.', $status_messages[0]);
  }

}
