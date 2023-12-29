<?php

namespace Drupal\example_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 6 user source from database.
 *
 * @MigrateSource(
 *   id = "example_users",
 *   source_module = "user"
 * )
 */
class Users extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('users', 'u');
    $query->leftJoin('users_roles', 'ur', '[u].[uid] = [ur].[uid]');
    $query->fields('u', array_keys($this->baseFields()));
    $query->addExpression('GROUP_CONCAT(ur.rid)', 'roles');
    // Skip anonymous & user 1.
    $query->condition('u.uid', 1, '>');
    $query->orderBy('u.uid');
    $query->groupBy('uid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    // Add roles field.
    $fields['roles'] = $this->t('Roles');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $roles = $row->getSourceProperty('roles');
    if (empty($roles)) {
      // Set it as empty string.
      $row->setSourceProperty('roles', '');
    }

    // Unserialize Data.
    $data = $row->getSourceProperty('data');
    if ($data !== NULL) {
      $row->setSourceProperty('data', unserialize($row->getSourceProperty('data'), [
        'allowed_classes' => TRUE,
      ]));
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'uid' => [
        'type' => 'integer',
        'alias' => 'u',
      ],
    ];
  }

  /**
   * Returns the user base fields to be migrated.
   *
   * @return array
   *   Associative array having field name as key and description as value.
   */
  protected function baseFields() {
    $fields = [
      'uid' => $this->t('User ID'),
      'name' => $this->t('Username'),
      'pass' => $this->t('Password'),
      'mail' => $this->t('Email address'),
      'theme' => $this->t('Theme'),
      'signature' => $this->t('Signature'),
      'signature_format' => $this->t('Signature format'),
      'created' => $this->t('Registered timestamp'),
      'access' => $this->t('Last access timestamp'),
      'login' => $this->t('Last login timestamp'),
      'status' => $this->t('Status'),
      'timezone' => $this->t('Timezone'),
      'language' => $this->t('Language'),
      'picture' => $this->t('Picture'),
      'init' => $this->t('Init'),
      'data' => $this->t('User data'),
    ];

    // Possible field added by Date contributed module.
    // @see https://api.drupal.org/api/drupal/modules%21user%21user.install/function/user_update_7002/7
    if ($this->getDatabase()->schema()->fieldExists('users', 'timezone_name')) {
      $fields['timezone_name'] = $this->t('Timezone (Date)');
    }

    return $fields;
  }

}
