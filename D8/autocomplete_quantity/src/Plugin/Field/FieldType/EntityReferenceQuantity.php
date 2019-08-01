<?php

namespace Drupal\autocomplete_quantity\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * An entity field containing an entity reference with a quantity.
 *
 * @FieldType(
 *   id = "entity_reference_quantity",
 *   label = @Translation("Entity reference quantity"),
 *   description = @Translation("An entity field containing an entity reference with a quantity."),
 *   category = @Translation("Reference"),
 *   default_widget = "entity_reference_autocomplete",
 *   default_formatter = "entity_reference_label",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class EntityReferenceQuantity extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $quantity_definition = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Quantity'))
      ->addConstraint('Range', ['min' => 1])
      ->setRequired(TRUE);
    $properties['quantity'] = $quantity_definition;
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['quantity'] = [
      'type' => 'int',
      'size' => 'tiny',
      'unsigned' => TRUE,
    ];
    return $schema;
  }

}
