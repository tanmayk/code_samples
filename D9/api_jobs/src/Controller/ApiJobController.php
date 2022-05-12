<?php

namespace Drupal\api_jobs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\api_jobs\JobsApiManager;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Api Jobs Controller.
 */
class ApiJobController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The job details.
   *
   * @var array
   */
  protected $job;

  /**
   * Constructs a new ApiJobController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Http\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\api_jobs\JobsApiManager $job_api_manager
   *   The job API manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, DateFormatterInterface $date_formatter, JobsApiManager $job_api_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->dateFormatter = $date_formatter;
    $this->jobApiManager = $job_api_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('date.formatter'),
      $container->get('api_jobs.api.manager')
    );
  }

  /**
   * Jobs detail page.
   */
  public function buildJob($job_code = NULL, $title = NULL) {
    if (empty($job_code)) {
      // Return page not found.
      throw new NotFoundHttpException();
    }
    $job_parts = explode('_', $job_code);
    if (empty($job_parts[1])) {
      // No catalog code present. Return page not found.
      throw new NotFoundHttpException();
    }
    $job_id = $job_parts[0];
    // Catalog code from url.
    $url_catalog_code = mb_strtoupper($job_parts[1]);
    if (empty($this->job)) {
      $query = $this->requestStack->getMainRequest()->query->all();
      $code = !empty($query['catalogcode']) ? $query['catalogcode'] : 'USA';
      $job_response = $this->jobApiManager->jobDetails($job_id, $code);

      if (empty($job_response['docs'][0])) {
        // Job not found.
        $block_id = constant('API_JOBS_NOT_FOUND_' . $url_catalog_code . '_ID');
        $block = $this->entityTypeManager->getStorage('block_content')->load($block_id);
        if ($block) {
          $block_view = $this->entityTypeManager->getViewBuilder('block_content')->view($block);
          return $block_view;
        }
        if (isset($this->job['status']) && $this->job['status'] == 0) {
          return [
            '#type' => 'markup',
            '#markup' => '<div class="p-4 alert-danger" role="alert">' . $this->job['content'] . '</div>',
          ];
        }
        else {
          return [
            '#type' => 'markup',
            '#markup' => '<div class="p-4 alert-danger" role="alert">' . $this->t('Job not found.') . '</div>',
          ];
        }
      }

      if (isset($this->job['status']) && $this->job['status'] == 0) {
        return [
          '#type' => 'markup',
          '#markup' => '<div class="p-4 alert-danger" role="alert">' . $this->job['content'] . '</div>',
        ];
      }

      $this->job = $job_response['docs'][0];
    }

    // Match the catalog code from url & job.
    $job_catalog_code = mb_strtoupper($this->job['catalogCode']);
    if ($url_catalog_code != $job_catalog_code) {
      // Catalog code is wrong. Return page not found.
      throw new NotFoundHttpException();
    }

    $date_posted = strtotime($this->job['lastPosted']);

    // Fix the messy description output.
    $description = str_replace(['\n', '\r', '\t'], '', $this->job['jobDescription']);
    // Remove multiple <br> tags to have one.
    $description = preg_replace('#(<br */?>\s*)+#i', '<br />', $description);
    // Remove empty <p> tags.
    $description = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $description);

    $query = $this->requestStack->getMainRequest()->query->all();
    // Prepare similar jobs.
    $similar_jobs = [];
    if (!empty($this->job['similarJobs'])) {
      foreach ($this->job['similarJobs'] as $item) {
        $job_id = $item['jobId'];
        $catalogcode = mb_strtolower($item['catalogCode']);
        $display_job_id = $job_id . '_' . $catalogcode;
        $job_url_title = $this->jobApiManager->cleanJobTitleForUrl($item['jobTitle']);
        $similar_jobs[] = Link::createFromRoute(
          $item['jobTitle'],
          'api_jobs.job',
          ['job_code' => $display_job_id, 'title' => $job_url_title],
          ['query' => $query]
        );
      }
    }

    $build = [
      'content' => [
        '#theme' => 'api_job',
        '#job_id' => $this->job['jobId'],
        '#job_number' => $this->job['jobId'],
        '#job_type' => $this->job['jobGroup'],
        '#title' => $this->job['jobTitle'],
        '#apply_link' => !empty($this->job['applyUrl']) ? $this->job['applyUrl'] : NULL,
        '#type' => $this->job['jobType'],
        '#description' => $description,
        '#employee_type' => $this->job['employmentType'],
        '#location' => $this->job['city'] . ', ' . $this->job['state'] . ', ' . $this->job['country'],
        '#date_posted' => $date_posted ? $this->dateFormatter->format($date_posted, 'custom', 'F j, Y') : '-',
        '#similar_jobs' => $similar_jobs,
      ],
    ];
    return $build;
  }

  /**
   * Jobs title callback.
   */
  public function buildJobTitle($job_code = NULL, $title = NULL) {
    if (empty($job_code)) {
      return '';
    }
    $job_parts = explode('_', $job_code);
    if (empty($job_parts[1])) {
      return '';
    }
    // Match the catalog code from url & job.
    $job_id = $job_parts[0];
    if (empty($this->job)) {
      $code = !empty($_GET['catalogcode']) ? $_GET['catalogcode'] : 'USA';
      $job_response = $this->jobApiManager->jobDetails($job_id, $code);
      if (empty($job_response['docs'][0])) {
        if (isset($this->job['status']) && $this->job['status'] == 0) {
          return 'Job API Error';
        }
        return 'Job Not Found';
      }
      $this->job = $job_response['docs'][0];
    }
    if (isset($this->job['status']) && $this->job['status'] == 0) {
      return 'Job API Error';
    }
    return $this->job['jobTitle'];
  }

}
