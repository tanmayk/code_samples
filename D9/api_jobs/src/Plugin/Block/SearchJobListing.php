<?php

namespace Drupal\api_jobs\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\api_jobs\JobsApiManager;

/**
 * Provides a 'Job Listing' block.
 *
 * @Block(
 *   id = "search_listing",
 *   admin_label = @Translation("API: Jobs"),
 *   category = @Translation("API Jobs"),
 * )
 */
class SearchJobListing extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request stack.
   *
   * @var Drupal\Core\Http\RequestStack
   */
  protected $requestStack;

  /**
   * The date formatter.
   *
   * @var Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The job API manager.
   *
   * @var Drupal\api_jobs\JobsApiManager
   */
  protected $jobApiManager;

  /**
   * Constructs a new searchJobListing.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Http\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\api_jobs\JobsApiManager $job_api_manager
   *   The job API manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, DateFormatterInterface $date_formatter, JobsApiManager $job_api_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->dateFormatter = $date_formatter;
    $this->jobApiManager = $job_api_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('date.formatter'),
      $container->get('api_jobs.api.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'catalogcode' => 'USA',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['catalogcode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Catalog Code'),
      '#description' => $this->t('The default catalog code to use for job listing.'),
      '#options' => [
        'USA' => 'USA',
        'CRP' => 'CRP',
        'MDC' => 'MDC',
      ],
      '#default_value' => $this->configuration['catalogcode'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['catalogcode'] = $form_state->getValue('catalogcode');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $code = $this->configuration['catalogcode'];

    // Initiate default search parameters.
    $params['catalogcode'] = isset($_GET['catalogcode']) ? $_GET['catalogcode'] : $code;
    $params['sort'] = isset($_GET['sort']) ? $_GET['sort'] : '';
    $params['radius'] = isset($_GET['radius']) ? $_GET['radius'] : 50;
    $params['page'] = isset($_GET['page']) ? $_GET['page'] : 1;
    $params['rows'] = isset($_GET['rows']) ? $_GET['rows'] : 25;
    $params['query'] = isset($_GET['query']) ? $_GET['query'] : '*';
    $display_total_rows = $params['rows'];
    // Add other filters.
    $filters = ['jobtitle', 'location', 'remote'];
    foreach ($filters as $filter) {
      if (isset($_GET[$filter])) {
        $params[$filter] = $_GET[$filter];
      }
    }
    // Location.
    if (!empty($_GET['address_lat']) && !empty($_GET['address_lng'])) {
      $params['location'] = $_GET['address_lat'] . ',' . $_GET['address_lng'];
    }

    // Call the search api.
    $response = $this->jobApiManager->searchJobs($params);

    if (isset($response['status']) && $response['status'] == 0) {
      return [
        '#type' => 'markup',
        '#markup' => '<div class="p-4 alert-danger" role="alert">' . $response['content'] . '</div>',
      ];
    }
    // Get the query parameters if any.
    $query = $this->requestStack->getMainRequest()->query->all();
    $rows = [];
    foreach ($response['docs'] as $job) {
      $job_id = $job['jobId'];
      $catalogcode = mb_strtolower($job['catalogCode']);
      $display_job_id = $job_id . '_' . $catalogcode;
      $job_url_title = $this->jobApiManager->cleanJobTitleForUrl($job['jobTitle']);
      $date_posted = strtotime($job['lastPosted']);

      $city = !empty($job['city']) ? $job['city'] : '-';
      $state = !empty($job['state']) ? $job['state'] : '-';
      $row = [
        Link::createFromRoute(
          $job['jobTitle'],
          'api_jobs.job',
          ['job_code' => $display_job_id, 'title' => $job_url_title],
          ['query' => $query, 'attributes' => ['class' => ['job-td-link']]]
        ),
        Link::createFromRoute(
          $city,
          'api_jobs.job',
          ['job_code' => $display_job_id, 'title' => $job_url_title],
          ['query' => $query, 'attributes' => ['class' => ['job-td-link']]]
        ),
        Link::createFromRoute(
          $state,
          'api_jobs.job',
          ['job_code' => $display_job_id, 'title' => $job_url_title],
          ['query' => $query, 'attributes' => ['class' => ['job-td-link']]]
        ),
        Link::createFromRoute(
          $this->dateFormatter->format($date_posted, 'custom', 'j/n/Y'),
          'api_jobs.job',
          ['job_code' => $display_job_id, 'title' => $job_url_title],
          ['query' => $query, 'attributes' => ['class' => ['job-td-link']]]
        ),
      ];
      $rows[] = $row;
    }

    $job_title_query = $query;
    $job_title_query['sort'] = 'jobtitle';
    $job_title_query['page'] = 1;
    $date_posted_query = $query;
    $date_posted_query['sort'] = 'lastposteddesc';
    $date_posted_query['page'] = 1;

    if (isset($query['sort']) && $query['sort'] == 'lastposteddesc') {
      $date_posted_query['sort'] = 'lastpostedasc';
    }

    $header = [
      Link::createFromRoute(
        $this->t('Job Title'),
        '<current>',
        [],
        [
          'query' => $job_title_query,
          'attributes' => ['class' => ['job-td-link']],
        ]
      ),
      new FormattableMarkup('<div class="job-td-link">' . $this->t('City') . '</div>', []),
      new FormattableMarkup('<div class="job-td-link">' . $this->t('State') . '</div>', []),
      Link::createFromRoute(
        $this->t('Date Posted'),
        '<current>',
        [],
        [
          'query' => $date_posted_query,
          'attributes' => ['class' => ['job-td-link']],
        ]
      ),
    ];

    // Prepare table.
    $build = [
      'content' => [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No jobs found.'),
        '#attributes' => [
          'class' => ['job-table'],
        ],
      ],
    ];

    return $build;
  }

}
