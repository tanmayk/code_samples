<?php

namespace Drupal\api_jobs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\EmailValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for API Jobs.
 */
class ApiJobsSettingsForm extends ConfigFormBase {

  /**
   * The email validator.
   *
   * @var Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * Configuration object name.
   *
   * @var string
   */
  protected $configName = 'api_jobs.settings';

  /**
   * Contructs jobs config form.
   *
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator.
   */
  public function __construct(EmailValidatorInterface $email_validator) {
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('email.validator')
    );
  }

  /**
   * Get editable config name.
   */
  protected function getEditableConfigNames() {
    return [
      $this->configName,
    ];
  }

  /**
   * Get form ID.
   */
  public function getFormId() {
    return 'api_jobs_settings_form';
  }

  /**
   * Build form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->configName);

    // Environment options.
    $env_options = $this->envOptions();

    $form['api_urls'] = [
      '#title' => $this->t('API URLs'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
    ];
    foreach ($env_options as $env => $label) {
      // Search API.
      $form['api_urls']['url_search_' . $env] = [
        '#title' => $this->t('@label Search API URL', ['@label' => $label]),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => $config->get('url_search_' . $env),
        '#description' => $this->t('URL to access Search API without trailing slash.'),
        '#maxlength' => 256,
      ];
      // Jobs API.
      $form['api_urls']['url_jobs_' . $env] = [
        '#title' => $this->t('@label Jobs API URL', ['@label' => $label]),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#default_value' => $config->get('url_jobs_' . $env),
        '#description' => $this->t('URL to access Jobs API without trailing slash.'),
        '#maxlength' => 256,
      ];
    }

    $form['logging'] = [
      '#title' => $this->t('Logging and Notifications'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
    ];

    // Email.
    $form['logging']['jobs_debug'] = [
      '#title' => $this->t('Enable Debug Mode'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('jobs_debug'),
      '#description' => $this->t('Enable debug mode to output extra log messages.'),
    ];

    // Email.
    $form['logging']['notify_email'] = [
      '#title' => $this->t('Error Notification Email'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('notify_email'),
      '#description' => $this->t('An email address or addresses to which API error notifications will be sent. Separate email addresses with comma & no space.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $notification_emails = $form_state->getValue('notify_email');
    $values = explode(',', $notification_emails);
    foreach ($values as $value) {
      // Make sure there is no space.
      if (strpos($value, ' ') !== FALSE) {
        $form_state->setErrorByName('email', $this->t('The Error Notification Email ( %field ) has space in it.', ['%field' => $notification_emails]));
      }
      if (!$this->emailValidator->isValid($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $form_state->setErrorByName('email', $this->t('The email %email is invalid.', ['%email' => $value]));
      }
    }
  }

  /**
   * Submit form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // Save the config.
    $config = $this->config($this->configName);

    // Environment options.
    $env_options = $this->envOptions();
    foreach ($env_options as $env => $label) {
      $config->set('url_search_' . $env, $values['url_search_' . $env]);
      $config->set('url_jobs_' . $env, $values['url_jobs_' . $env]);
    }
    $config->set('jobs_debug', $values['jobs_debug']);
    $config->set('notify_email', $values['notify_email']);
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Environment options.
   */
  public function envOptions() {
    return [
      'testing' => $this->t('Testing'),
      'live' => $this->t('Live'),
    ];
  }

}
