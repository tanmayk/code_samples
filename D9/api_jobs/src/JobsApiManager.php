<?php

namespace Drupal\api_jobs;

use Drupal\Core\Http\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client;
use Symfony\Component\Mime\Header\MailboxHeader;
use Symfony\Component\Mime\Address;
use Drupal\Core\Entity\EntityStorageException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Jobs API Manager.
 */
class JobsApiManager {

  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var Drupal\Core\Http\RequestStack
   */
  protected $requestStack;

  /**
   * The http client manager.
   *
   * @var GuzzleHttp\Client
   */
  protected $clientManager;

  /**
   * The json serializer.
   *
   * @var Drupal\Component\Serialization\Json
   */
  protected $jsonSerializer;

  /**
   * The config factory.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger channel factory.
   *
   * @var Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The production site host.
   *
   * @var string
   */
  protected $prodHost = 'apexsystems.com';

  /**
   * The API configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $apiConfig;

  /**
   * The API debug flag.
   *
   * @var bool
   */
  protected $apiDebug;

  /**
   * Constructs a JobsApiManager.
   *
   * @param \Drupal\Core\Http\RequestStack $request_stack
   *   The request stack.
   * @param \GuzzleHttp\Client $client_manager
   *   The http client manager.
   * @param \Drupal\Component\Serialization\Json $json_serializer
   *   The json serializer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   */
  public function __construct(RequestStack $request_stack, Client $client_manager, Json $json_serializer, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger, LanguageManagerInterface $language_manager, MailManagerInterface $mail_manager) {
    $this->requestStack = $request_stack;
    $this->clientManager = $client_manager;
    $this->jsonSerializer = $json_serializer;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->languageManager = $language_manager;
    $this->mailManager = $mail_manager;
    // API config.
    $this->apiConfig = $this->configFactory->get('api_jobs.settings');
    // API debug mode.
    $this->apiDebug = $this->apiConfig->get('jobs_debug');
  }

  /**
   * Job details API request.
   *
   * @param string $job_id
   *   The job id.
   * @param string $catalogcode
   *   Catalog Code. Defaults to USA.
   *
   * @return array
   *   The decoded response from API.
   *
   * @throws \Exception
   */
  public function jobDetails($job_id, $catalogcode = 'USA') {
    $env = $this->requestStack->getMainRequest()->getHost() == $this->prodHost ? 'live' : 'testing';
    $request_url = $this->apiConfig->get('url_jobs_' . $env);
    $params = [
      'jobid' => $job_id,
      'catalogcode' => $catalogcode,
    ];
    return $this->request($request_url, $params, 'Job Details', 'GET');
  }

  /**
   * Search jobs API request.
   *
   * @param array $params
   *   The search params.
   *
   * @return array
   *   The decoded response from API.
   *
   * @throws \Exception
   */
  public function searchJobs(array $params) {
    $env = $this->requestStack->getMainRequest()->getHost() == $this->prodHost ? 'live' : 'testing';
    $request_url = $this->apiConfig->get('url_search_' . $env);
    // Default params.
    $defaults = [
      'remote' => 'false',
      'radius' => 50,
      'catalogcode' => 'USA',
    ];
    foreach ($defaults as $key => $value) {
      if (!isset($params[$key])) {
        $params[$key] = $value;
      }
    }
    return $this->request($request_url, $params, 'Search Jobs', 'GET');
  }

  /**
   * API request.
   *
   * @param string $request_url
   *   API request URL.
   * @param array $params
   *   An array of request parameters.
   * @param string $api_name
   *   The name of the API.
   * @param string $method
   *   Method to use for request.
   *
   * @return array
   *   The decoded response from API.
   *
   * @throws \Exception
   */
  public function request($request_url, array $params, $api_name, $method = 'POST') {
    $method = strtolower($method);

    if ($method === 'get') {

      // Send request to Log.
      if ($this->apiDebug) {
        $this->logger->get("api_jobs")->info("JobsApiManager::request<br>Sending<br>" . ucwords($method) . " <br>URL: " . $request_url . "<br>Parameters :  <pre>" . print_r($params, TRUE) . "</pre>");
      }

      try {
        // Send the request.
        $response = $this->clientManager->{$method}($request_url, [
          'query' => $params,
        ]);
        $response_json = $response->getBody()->getContents();
        if ($this->apiDebug) {
          $this->logger->get("api_jobs")->info("JobsApiManager::response<br>General response<br><pre>" . print_r($response, TRUE) . "</pre>");
          $this->logger->get("api_jobs")->info("JobsApiManager::response<br>Response JSON<br><pre>" . print_r($response_json, TRUE) . "</pre>");
        }
      }
      catch (EntityStorageException $e) {
        return $this->notifyError($e->getMessage(), $api_name);
      }
      catch (GuzzleException $e) {
        $ex_response = $e->getResponse();
        if (!$ex_response) {
          $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $e->getMessage());
          return $this->notifyApiDown($e->getMessage(), $api_name);
        }
        $response_status_code = $ex_response->getStatusCode();
        if ($response_status_code >= 500 && $response_status_code <= 599) {
          $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $e->getMessage());
          return $this->notifyApiDown($e->getMessage(), $api_name);
        }
        $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $e->getMessage());
        return $this->notifyError($e->getMessage(), $api_name);
      }
      catch (Exception $e) {
        $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $e->getMessage());
        return $this->notifyError($e->getMessage(), $api_name);
      }
    }
    // Method = post.
    else {
      // Prepare json string.
      $request = $this->jsonSerializer->encode($params);

      // Send request to Log.
      $this->logger->get("api_jobs")->info("Jobs " . ucwords($method) . " <br>URL: " . $request_url . "<br>Payload :  <pre>" . print_r($request, TRUE) . "</pre>");

      try {
        // Send the request.
        $response = $this->clientManager->{$method}($request_url, [
          'body'    => $request,
          'headers' => [
            'Content-Type' => 'application/json',
          ],
        ]);

        $response_json = $response->getBody()->getContents();
      }
      catch (EntityStorageException $e) {
        return $this->notifyError($e->getMessage(), $api_name);
      }
      catch (GuzzleException $e) {
        $ex_response = $e->getResponse();
        if (!$ex_response) {
          return $this->notifyApiDown($e->getMessage(), $api_name);
        }
        $response_status_code = $ex_response->getStatusCode();
        if ($response_status_code >= 500 && $response_status_code <= 599) {
          return $this->notifyApiDown($e->getMessage(), $api_name);
        }
        return $this->notifyError($e->getMessage(), $api_name);
      }
      catch (Exception $e) {
        return $this->notifyError($e->getMessage(), $api_name);
      }
    }

    $response_body = $this->jsonSerializer->decode($response_json);

    // Send response to log.
    if ($this->apiDebug) {
      $this->logger->get("api_jobs")->info("Jobs Response " . ucwords($method) . " <br>Endpoint: " . $request_url . "<br>Payload :  <pre>" . print_r($response_body, TRUE) . "</pre>");
    }

    // If this is job details api, how would we know that job does not exists?
    // @todo confirm the error details.
    if ($response->getStatusCode() == 200) {
      if (isset($response_body['status']) && $response_body['status'] == 0) {
        $message_error = implode("\n\r", $response_body['messages']);
        $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $message_error);
        return $this->notifyError($message_error, $api_name);
      }
      if ($api_name == 'Search Jobs' && !isset($response_body['docs'])) {
        $message_error = $this->t('Something went wrong while searching for the jobs.');
        if (isset($response_body['messages'])) {
          $message_error = implode("\n\r", $response_body['messages']);
        }
        $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $message_error);
        return $this->notifyError($message_error, $api_name);
      }
    }
    else {
      $this->logger->get('api_jobs')->error('<pre>' . print_r($response, TRUE) . '</pre>');
      $response_status_code = $response->getStatusCode();
      // @todo Confirm the error key from response.
      $message_error = $response_body['error'];
      if ($response_status_code >= 500 && $response_status_code <= 599) {
        // This means there is an issue at server.
        $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $message_error);
        return $this->notifyApiDown($message_error, $api_name);
      }
      else {
        // There is another error.
        $this->logger->get("api_jobs")->error($api_name . ' Error: ' . $message_error);
        return $this->notifyError($message_error, $api_name);
      }
    }

    return $response_body;
  }

  /**
   * Error notification.
   */
  public function notifyError($error, $api_name) {
    $content = "Error: Error Returned from API\n\rSeverity: Error\n\rMessage: The $api_name API returned the following error.\n\rNotification: $error";
    $this->logger->get('api_jobs')->error('<pre>' . print_r($content, TRUE) . '</pre>');

    // Send notification email.
    $site_config = $this->configFactory->get('system.site');
    $from_email = $site_config->get('mail');
    $from_name = $site_config->get('name');
    // Format the email.
    $mailbox = new MailboxHeader($from_name, new Address($from_email, $from_name));
    $from = $mailbox->getBodyAsString();

    $content = str_replace("\n\r", '<br>', $content);
    $message = check_markup($content, 'full_html');
    // Prepare parameters.
    $params['message'] = $message;
    $params['subject'] = 'Apex Jobs: API Notification';
    $params['from'] = $from;

    $to = $this->apiConfig->get('notify_email');

    // Get language code.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $status = $this->mailManager->mail('api_jobs', 'api_notification', $to, $langcode, $params);
    return [
      'status'  => 0,
      'content' => $content,
    ];
  }

  /**
   * API down notification.
   */
  public function notifyApiDown($error, $api_name) {
    $content = "Error: No Response from API\n\rSeverity: Error\n\rMessage: The $api_name API is unresponsive.\n\rNotification: $error";
    $this->logger->get('api_jobs')->error('<pre>' . print_r($content, TRUE) . '</pre>');

    // Send notification email.
    $site_config = $this->configFactory->get('system.site');
    $from_email = $site_config->get('mail');
    $from_name = $site_config->get('name');
    // Format the email.
    $mailbox = new MailboxHeader($from_name, new Address($from_email, $from_name));
    $from = $mailbox->getBodyAsString();

    $content = str_replace("\n\r", '<br>', $content);
    $message = check_markup($content, 'full_html');
    // Prepare parameters.
    $params['message'] = $message;
    $params['subject'] = 'Apex Jobs: API Notification';
    $params['from'] = $from;

    $to = $this->apiConfig->get('notify_email');

    // Get language code.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $this->mailManager->mail('api_jobs', 'api_notification', $to, $langcode, $params);
    return [
      'status'  => 0,
      'content' => $content,
    ];
  }

  /**
   * Clean job title to be used in URL.
   */
  public function cleanJobTitleForUrl($title, $separator = '-') {
    $output = mb_strtolower($title);
    // Trim any spaces.
    $output = trim($output);
    // Trim any ASCII control characters.
    $output = trim($output, "\x00..\x1F");
    // Trim any leading or trailing separators.
    $output = trim($output, $separator);
    // Replace spaces with separator.
    $output = str_replace(' ', $separator, $output);
    // Remove any spacial characters.
    $output = preg_replace('/[^A-Za-z0-9\-]/', '', $output);
    return $output;
  }

}
