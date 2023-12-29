<?php

namespace Drupal\example_migrate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Example Migrate settings.
 *
 * @package Drupal\example_migrate\Form
 */
class ExampleMigrateConfigForm extends ConfigFormBase {

  /**
   * Config object.
   *
   * @var string
   */
  const SETTINGS = 'example_migrate.settings';

  /**
   * Get editable config name.
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * Get form ID.
   */
  public function getFormId() {
    return 'example_migrate_config_form';
  }

  /**
   * Build form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    // File Server URL.
    $form['file_server_url'] = [
      '#title' => $this->t('File Server URL'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#description' => $this->t('File Server URL which will be used to migrate files without trailing slash. URL should be in format http://example.com.'),
      '#default_value' => $config->get('file_server_url'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config(static::SETTINGS);
    $config->set('file_server_url', $values['file_server_url']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
