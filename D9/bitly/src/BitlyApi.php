<?php

namespace Drupal\bitly;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;

/**
 * Provides bit.ly url shortening service.
 *
 * @code
 * $response = \Drupal::service('bitly.api')->shorten('http://example.com');
 * $shorten_url = $response['url'];
 * @endcode
 */
class BitlyApi {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The URL of the standard bitly v3 API.
   *
   * @var string
   */
  private $apiUrl = 'http://api.bit.ly/v3/';

  /**
   * Login.
   *
   * @var string
   */
  private $login = NULL;

  /**
   * API Key.
   *
   * @var string
   */
  private $apiKey = NULL;

  /**
   * Constructs a BitlyApi.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory->get('bitly.settings');
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory->get('bitly');
    $this->login = $this->configFactory->get('login');
    $this->apiKey = $this->configFactory->get('api_key');
  }

  /**
   * Shorten the url.
   */
  public function shorten($url, $domain = NULL) {
    if (empty($domain)) {
      $domain = $this->configFactory->get('base_url');
    }

    $api_url = $this->apiUrl . 'shorten?login=' . $this->login . '&apiKey=' . $this->apiKey . '&format=json&longUrl=' . urlencode($url);
    if (!empty($domain)) {
      $api_url .= '&domain=' . $domain;
    }

    // Init result.
    $result = [
      'status' => FALSE,
    ];

    try {
      $request = $this->httpClient->get($api_url);
      $status = $request->getStatusCode();
      $contents = $request->getBody()->getContents();
      if ($status == 200) {
        $result['status'] = TRUE;
        $json = Json::decode($contents);
        if (!empty($json['data']) && is_array($json['data'])) {
          foreach ($json['data'] as $key => $value) {
            $result[$key] = $value;
          }
        }
      }
    }
    catch (Exception $e) {
      // An error happened.
      $this->loggerFactory->error('Error occured while shortening @url : @message',
      [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
    }

    return $result;
  }

}
