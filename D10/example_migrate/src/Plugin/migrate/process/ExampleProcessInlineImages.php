<?php

namespace Drupal\example_migrate\Plugin\migrate\process;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Image;

/**
 * Provides a 'ExampleProcessInlineImages' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "example_inline_images"
 * )
 */
class ExampleProcessInlineImages extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Stream Wrapper Manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;


  /**
   * Image location base.
   *
   * @var string
   */
  protected $imageBase;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, Client $http_client, FileSystemInterface $file_system, StreamWrapperManagerInterface $stream_wrapper_manager, FileUrlGeneratorInterface $file_url_generator, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileStorage = $this->entityTypeManager->getStorage('file');
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->currentUser = $current_user;
    $this->imageBase = 'public://migrate_images';
    if (isset($this->configuration['base'])) {
      $this->imageBase = $this->configuration['base'];
    }
    if (!$this->streamWrapperManager->getScheme($this->imageBase)) {
      throw new MigrateException('Base path specification must be in URI form, e.g., public://');
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_url_generator'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $domCrawler = new Crawler($value, NULL, Url::fromRoute('<front>')->setAbsolute()->toString());
    // Search for all <img> tag in the value (usually the body).
    if ($images = $domCrawler->filter('img')->images()) {
      foreach ($images as $image) {
        // Clean up the attributes in the img tag.
        $this->cleanUpImageAttributes($image, $migrate_executable);
      }
      return $domCrawler->html();
    }

    return $value;
  }

  /**
   * Download an external file.
   *
   * @see \Drupal\migrate\Plugin\migrate\process\Download
   */
  protected function downloadFile($image_path) {
    $destination = $this->imageBase
      . DIRECTORY_SEPARATOR
      . $this->fileSystem->basename($image_path);
    // Modify the destination filename if necessary.
    $replace = !empty($this->configuration['rename']) ?
      FileSystemInterface::EXISTS_RENAME :
      FileSystemInterface::EXISTS_REPLACE;
    $final_destination = $this->fileSystem->getDestinationFilename($destination, $replace);

    // Try opening the file first, to avoid calling file_prepare_directory()
    // unnecessarily. We're suppressing fopen() errors because we want to try
    // to prepare the directory before we give up and fail.
    $destination_stream = @fopen($final_destination, 'w');
    if (!$destination_stream) {
      // If fopen didn't work, make sure there's a writable directory in place.
      $dir = $this->fileSystem->dirname($final_destination);
      if (!$this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new MigrateException("Could not create or write to directory '$dir'");
      }
      // Let's try that fopen again.
      $destination_stream = @fopen($final_destination, 'w');
      if (!$destination_stream) {
        throw new MigrateException("Could not write to file '$final_destination'");
      }
    }

    // Stream the request body directly to the final destination stream.
    $this->configuration['guzzle_options']['sink'] = $destination_stream;

    try {
      // Make the request. Guzzle throws an exception for anything but 200.
      $this->httpClient->get($image_path, $this->configuration['guzzle_options']);
    }
    catch (\Exception $e) {
      throw new MigrateException("{$e->getMessage()} ({$image_path})");
    }

    $file = $this->fileStorage->create([
      'uri' => $final_destination,
      'langcode' => 'en',
      'uid' => $this->currentUser->id(),
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Process the image tag by either locating a local file or downloading it.
   */
  protected function cleanUpImageAttributes(Image $image, MigrateExecutableInterface $migrateExecutable) {
    $image_path = $image->getNode()->getAttribute('src');
    try {
      $server_image_path = $image_path;
      $parsed_url = parse_url($server_image_path);
      if (empty($parsed_url['host'])) {
        // File server url config.
        $config = $this->configFactory->get('example_migrate.settings');
        $server_image_path = $config->get('file_server_url') . $image_path;
      }
      $file = $this->downloadFile($server_image_path);
    }
    catch (MigrateException $e) {
      $migrateExecutable
        ->saveMessage(
          "Could not process image path {$image_path}: {$e->getMessage()}",
          MigrationInterface::MESSAGE_ERROR
        );
      // Do no manipulation.
      return;
    }

    $this->determineAlign($image);
    $streamWrapper = $this->streamWrapperManager->getViaUri($file->getFileUri());
    if ($streamWrapper instanceof LocalStream) {
      $url = $this->fileUrlGenerator->generateString($file->getFileUri());
    }
    else {
      $url = $streamWrapper->getExternalUrl();
    }
    // Attempt to get a local path if it's supported; otherwise, external.
    $image->getNode()->setAttribute('data-entity-uuid', $file->uuid());
    $image->getNode()->setAttribute('data-entity-type', 'file');
    $image->getNode()->setAttribute('src', $url);

    // If parent node is link, change the link.
    $parent_anchor = $image->getNode()->parentNode;
    $href = $parent_anchor->getAttribute('href');
    if (!empty($href)) {
      $parent_anchor->setAttribute('href', $url);
    }
  }

  /**
   * Add data-align attribute to match existing alignment.
   *
   * This is in its own method to allow easy overriding, e.g. to include
   * additional logic to match legacy tags, e.g. those added by
   * wysiwyg_imageupload module.
   */
  protected function determineAlign(Image $image) {
    if ($alignment = $image->getNode()->getAttribute('align')) {
      $image->getNode()->setAttribute('data-align', $alignment);
    }
  }

}
