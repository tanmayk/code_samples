<?php

namespace Drupal\example_migrate\Plugin\migrate\source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal 6 gallery node revisions source from database.
 *
 * @MigrateSource(
 *   id = "example_galleries_revisions",
 *   source_module = "node"
 * )
 */
class GalleriesRevisions extends DrupalSqlBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The content type.
   *
   * @var string
   */
  protected $contentType = 'gallery';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('node_revisions', 'nr');
    $query->leftJoin('node', 'n', '[n].[nid] = [nr].[nid]');
    $query->leftJoin('content_field_body', 'fb', '[fb].[vid] = [nr].[vid]');

    $query->fields('n', [
      'nid', 'type', 'language', 'status', 'created', 'changed', 'comment',
      'promote', 'moderate', 'sticky', 'tnid', 'translate',
    ]);
    $query->fields('nr', ['title', 'log', 'timestamp', 'format', 'vid']);
    $query->addField('n', 'uid', 'node_uid');
    $query->addField('nr', 'uid', 'revision_uid');
    $query->fields('fb', ['field_body_value', 'field_body_format']);

    $query->condition('n.type', $this->contentType);

    $query->orderBy('nr.vid');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'type' => $this->t('Type'),
      'title' => $this->t('Title'),
      'field_body_value' => $this->t('Body'),
      'field_body_format' => $this->t('Body (Format)'),
      'node_uid' => $this->t('Node authored by (uid)'),
      'revision_uid' => $this->t('Revision authored by (uid)'),
      'created' => $this->t('Created timestamp'),
      'changed' => $this->t('Modified timestamp'),
      'status' => $this->t('Published'),
      'promote' => $this->t('Promoted to front page'),
      'sticky' => $this->t('Sticky at top of lists'),
      'revision' => $this->t('Create new revision'),
      'language' => $this->t('Language (fr, en, ...)'),
      'tnid' => $this->t('The translation set id for this node'),
      'timestamp' => $this->t('The timestamp the latest revision of this node was created.'),
      'files' => $this->t('File attachments'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // File server url config.
    $config = $this->configFactory->get('example_migrate.settings');
    $file_server_url = $config->get('file_server_url') . '/';

    $nid = $row->getSourceProperty('tnid');
    $vid = $row->getSourceProperty('vid');
    // Since file attachments can be multiple with different meta data, let's
    // fetch it here.
    $query = $this->select('node_revisions', 'nr');
    $query->leftJoin('node', 'n', '[n].[nid] = [nr].[nid]');
    $query->leftJoin('upload', 'u', '[u].[vid] = [nr].[vid]');
    $query->leftJoin('files', 'f', '[f].[fid] = [u].[fid]');
    $query->fields('u', ['description', 'list', 'weight']);
    $query->fields('f', ['filepath']);
    $query->condition('n.nid', $nid);
    $query->condition('nr.vid', $vid);
    $query->orderBy('u.weight');
    $result = $query->execute();

    $file_field_data = [];
    foreach ($result as $record) {
      if (!empty($record['filepath'])) {
        $file_field_data[] = [
          'filepath' => $file_server_url . $record['filepath'],
          'description' => $record['description'],
          'list' => $record['list'],
          'weight' => $record['weight'],
        ];
      }
    }
    $row->setSourceProperty('files', $file_field_data);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'vid' => [
        'type' => 'integer',
        'alias' => 'nr',
      ],
    ];
  }

}
