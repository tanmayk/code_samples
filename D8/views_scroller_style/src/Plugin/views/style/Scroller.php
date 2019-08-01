<?php

namespace Drupal\views_scroller_style\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render each item in scroller format.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "scroller",
 *   title = @Translation("Scroller"),
 *   help = @Translation("Displays each item in scroller format."),
 *   theme = "views_view_scroller",
 *   display_types = {"normal"}
 * )
 */
class Scroller extends StylePluginBase {

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['height'] = ['default' => '354'];
    $options['opacity'] = ['default' => '0.7'];

    return $options;
  }

  /**
   * Render the given style.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Default height of header. This will be overriden if provided while adding scroller content.'),
      '#size' => '6',
      '#default_value' => $this->options['height'],
      '#required' => TRUE,
      '#field_suffix' => 'px',
    ];

    $opacity_options = [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1];
    // As per https://www.drupal.org/node/2207453.
    $options = array_combine($opacity_options, $opacity_options);

    $form['opacity'] = [
      '#type' => 'select',
      '#title' => $this->t('Opacity'),
      '#description' => $this->t('Default opacity for header color. This will be overriden if provided while adding scroller content.'),
      '#options' => $options,
      '#default_value' => $this->options['opacity'],
    ];
  }

}
