<?php

namespace Drupal\autocomplete_quantity\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Display the referenced entities’ label with their quantities.
 *
 * @FieldFormatter(
 *   id = "entity_reference_quantity_view",
 *   label = @Translation("Entity label and quantity"),
 *   description = @Translation("Display the referenced entities’ label with their quantities."),
 *   field_types = {
 *     "entity_reference_quantity"
 *   }
 * )
 */
class EntityReferenceQuantityView extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $values = $items->getValue();

    foreach ($elements as $delta => $entity) {
      $elements[$delta]['#suffix'] = ' - ' . $values[$delta]['quantity'];
    }

    return $elements;
  }

}
