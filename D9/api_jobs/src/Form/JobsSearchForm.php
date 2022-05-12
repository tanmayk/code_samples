<?php

namespace Drupal\api_jobs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Search form builder.
 */
class JobsSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_jobs_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $search_config = []) {
    // Initiate parameters.
    $catalogcode = 'USA';
    $valid_codes = ['USA', 'CRP', 'MDC'];
    if (!empty($search_config['catalogcode']) && in_array($search_config['catalogcode'], $valid_codes)) {
      $catalogcode = $search_config['catalogcode'];
    }
    $display_remote = isset($search_config['display_remote']) ? $search_config['display_remote'] : FALSE;

    $form['#prefix'] = '<div class="careers-search">';
    $form['#suffix'] = '</div>';

    // Catalog code.
    $form['catalogcode'] = [
      '#type' => 'hidden',
      '#value' => $catalogcode,
    ];

    $default_query = !empty($_GET['query']) ? $_GET['query'] : '';
    $form['keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#title_display' => 'invisible',
      '#default_value' => $default_query != '*' ? $default_query : '',
      '#prefix' => '<div class="keywordContainer search-element">',
      '#suffix' => '</div>',
      '#attributes' => [
        'placeholder' => $this->t('Keyword'),
      ],
    ];

    $form['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($_GET['address']) ? $_GET['address'] : '',
      '#prefix' => '<div id="addressSearchContainer" class="address-container search-element">',
      '#suffix' => '</div>',
      '#attributes' => [
        'placeholder' => $this->t('Location'),
        'class' => ['address'],
      ],
    ];

    // Hidden fields for lat/lng.
    $lat = '';
    $lng = '';
    if (!empty($_GET['location'])) {
      $location = $_GET['location'];
      list($lat, $lng) = explode(',', $location);
    }
    $form['address_lat'] = [
      '#type' => 'hidden',
      '#default_value' => $lat,
      '#attributes' => [
        'class' => ['address-lat'],
      ],
    ];
    $form['address_lng'] = [
      '#type' => 'hidden',
      '#default_value' => $lng,
      '#attributes' => [
        'class' => ['address-lng'],
      ],
    ];

    if ($display_remote) {
      $form['remote'] = [
        '#type' => 'select',
        '#title' => $this->t('Remote'),
        '#title_display' => 'invisible',
        '#default_value' => !empty($_GET['remote']) ? $_GET['remote'] : NULL,
        '#options' => [
          'false' => $this->t('No'),
          'true' => $this->t('Yes'),
        ],
        '#prefix' => '<div class="remoteContainer search-element">',
        '#suffix' => '</div>',
        '#empty_option' => $this->t('Remote'),
      ];

      $form['keyword']['#prefix'] = '<div class="keywordContainer search-element has-remote-field">';
      $form['address']['#prefix'] = '<div id="addressSearchContainer" class="address-container search-element has-remote-field">';
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#prefix' => '<div class="search-button">',
      '#suffix' => '</div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $catalogcode = $values['catalogcode'];
    // Prepare query parameters.
    $query_params = [
      'catalogcode' => $catalogcode,
      'address' => $values['address'],
      'radius' => 50,
      'page' => 1,
      'rows' => 25,
      'query' => '*',
    ];
    // Keyword.
    if (!empty($values['keyword'])) {
      $query_params['query'] = $values['keyword'];
    }
    // Location.
    if (!empty($values['address_lat']) && !empty($values['address_lng'])) {
      $query_params['location'] = $values['address_lat'] . ',' . $values['address_lng'];
    }
    // Remote.
    if (isset($values['remote'])) {
      $query_params['remote'] = $values['remote'];
    }

    $catalogcode = mb_strtoupper($catalogcode);
    $nid_constant = 'API_JOBS_SEARCH_PAGE_' . $catalogcode . '_NID';
    $node_id = constant($nid_constant);

    $url = Url::fromRoute('entity.node.canonical', ['node' => $node_id], ['query' => $query_params]);
    $form_state->setRedirectUrl($url);
  }

}
