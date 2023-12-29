<?php

namespace Drupal\example_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Drupal\Component\Utility\Unicode;

/**
 * Drupal 6 menu links source from database.
 *
 * @MigrateSource(
 *   id = "example_menu_links",
 *   source_module = "menu"
 * )
 */
class MenuLinks extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('menu_links', 'ml');
    $query->leftJoin('menu_links', 'pl', 'ml.plid = pl.mlid');
    $query->fields('ml');
    $query->addField('pl', 'link_path', 'parent_link_path');

    $and = $query->andConditionGroup()
      ->condition('ml.module', 'menu')
      ->condition('ml.router_path', [
        'admin/build/menu-customize/%',
        'admin/structure/menu/manage/%',
      ], 'NOT IN');
    $condition = $query->orConditionGroup()
      ->condition('ml.customized', 1)
      ->condition($and);
    $query->condition($condition);

    // Required menus.
    $menu_names = [
      'menu-aas-menu', 'menu-bibs-menu', 'menu-ces-menu',
      'menu-cs-menu', 'secondary-links', 'primary-links',
    ];
    $query->condition('ml.menu_name', $menu_names, 'IN');

    $query->orderBy('ml.depth');
    $query->orderby('ml.mlid');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'menu_name' => $this->t("The menu name. All links with the same menu name (such as 'navigation') are part of the same menu."),
      'mlid' => $this->t('The menu link ID (mlid) is the integer primary key.'),
      'plid' => $this->t('The parent link ID (plid) is the mlid of the link above in the hierarchy, or zero if the link is at the top level in its menu.'),
      'link_path' => $this->t('The Drupal path or external path this link points to.'),
      'router_path' => $this->t('For links corresponding to a Drupal path (external = 0), this connects the link to a {menu_router}.path for joins.'),
      'link_title' => $this->t('The text displayed for the link, which may be modified by a title callback stored in {menu_router}.'),
      'options' => $this->t('A serialized array of options to set on the URL, such as a query string or HTML attributes.'),
      'module' => $this->t('The name of the module that generated this link.'),
      'hidden' => $this->t('A flag for whether the link should be rendered in menus. (1 = a disabled menu link that may be shown on admin screens, -1 = a menu callback, 0 = a normal, visible link)'),
      'external' => $this->t('A flag to indicate if the link points to a full URL starting with a protocol, like http:// (1 = external, 0 = internal).'),
      'has_children' => $this->t('Flag indicating whether any links have this link as a parent (1 = children exist, 0 = no children).'),
      'expanded' => $this->t('Flag for whether this link should be rendered as expanded in menus - expanded links always have their child links displayed, instead of only when the link is in the active trail (1 = expanded, 0 = not expanded)'),
      'weight' => $this->t('Link weight among links in the same menu at the same depth.'),
      'depth' => $this->t('The depth relative to the top level. A link with plid == 0 will have depth == 1.'),
      'customized' => $this->t('A flag to indicate that the user has manually created or edited the link (1 = customized, 0 = not customized).'),
      'p1' => $this->t('The first mlid in the materialized path. If N = depth, then pN must equal the mlid. If depth > 1 then p(N-1) must equal the plid. All pX where X > depth must equal zero. The columns p1 .. p9 are also called the parents.'),
      'p2' => $this->t('The second mlid in the materialized path. See p1.'),
      'p3' => $this->t('The third mlid in the materialized path. See p1.'),
      'p4' => $this->t('The fourth mlid in the materialized path. See p1.'),
      'p5' => $this->t('The fifth mlid in the materialized path. See p1.'),
      'p6' => $this->t('The sixth mlid in the materialized path. See p1.'),
      'p7' => $this->t('The seventh mlid in the materialized path. See p1.'),
      'p8' => $this->t('The eighth mlid in the materialized path. See p1.'),
      'p9' => $this->t('The ninth mlid in the materialized path. See p1.'),
      'updated' => $this->t('Flag that indicates that this link was generated during the update from Drupal 5.'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'mlid' => [
        'type' => 'integer',
        'alias' => 'ml',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $row->setSourceProperty('options', unserialize($row->getSourceProperty('options'), [
      'allowed_classes' => TRUE,
    ]));
    $hidden = $row->getSourceProperty('hidden');
    $enabled = $hidden == 0;
    $row->setSourceProperty('enabled', $enabled);
    $description = $row->getSourceProperty('options/attributes/title');
    if ($description !== NULL) {
      $row->setSourceProperty('description', Unicode::truncate($description, 255));
    }
    $row->setSourceProperty('skip_translation', TRUE);

    return parent::prepareRow($row);
  }

}
