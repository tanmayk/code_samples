<?php

namespace Drupal\autocomplete_quantity\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * An autocomplete text field with an associated quantity.
 *
 * @FieldWidget(
 *   id = "entity_reference_autocomplete_quantity",
 *   label = @Translation("Autocomplete w/Quantity"),
 *   description = @Translation("An autocomplete text field with an associated quantity."),
 *   field_types = {
 *     "entity_reference_quantity"
 *   }
 * )
 */
class EntityReferenceAutocompleteQuantity extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $widget = parent::formElement($items, $delta, $element, $form, $form_state);

    // Prepare default options.
    $options = [];
    for ($i = 1; $i <= 30; $i++) {
      $options[$i] = $i;
    }
    $widget['quantity'] = [
      '#title' => $this->t('Quantity'),
      '#type' => 'select',
      '#default_value' => isset($items[$delta]) ? $items[$delta]->quantity : 1,
      '#options' => $options,
      '#weight' => 10,
    ];

    return $widget;
  }

}
