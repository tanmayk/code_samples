<?php

namespace Drupal\bitly\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for bit.ly.
 */
class BitlyConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bitly_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bitly.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bitly.settings');

    $form['api_creds_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('API Credentials'),
      '#description' => $this->t('Please supply the bit.ly API credentials that will be used while shortening the url. <a target="_blank" href="@link">Find your credentials</a>.', ['@link' => 'http://bit.ly/a/your_api_key']),
      '#open' => TRUE,
    ];

    // Login.
    $form['api_creds_wrapper']['login'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login'),
      '#required' => TRUE,
      '#default_value' => $config->get('login'),
      '#description' => $this->t('This field is case-sensitive.'),
    ];

    // API Key.
    $form['api_creds_wrapper']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#required' => TRUE,
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('This field is case-sensitive.'),
    ];

    $form['base_url_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('You can optionally select for bit.ly to shorten URLs to the j.mp domain instead.'),
      '#open' => TRUE,
    ];

    // Base URL.
    $form['base_url_wrapper']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#required' => TRUE,
      '#default_value' => $config->get('base_url'),
      '#description' => $this->t('You may use bit.ly or j.mp. No trailing slash and do not prefix with http://. If you have a custom domain you can put that here.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config('bitly.settings')
      ->set('login', $form_state->getValue('login'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('base_url', $form_state->getValue('base_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
