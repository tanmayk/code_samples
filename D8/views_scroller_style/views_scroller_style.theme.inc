<?php

/**
 * @file
 * Theme functions required for site.
 */

use Drupal\file\Entity\File;

/**
 * Prepares variables for views scroller templates.
 *
 * Default template: views-view-scroller.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - view: A ViewExecutable object.
 *   - rows: The raw row data.
 */
function template_preprocess_views_view_scroller(array &$variables) {
  // View options set by user.
  $options = $variables['view']->style_plugin->options;
  $rows = $variables['rows'];
  $items = [];
  $settings = [];
  foreach ($rows as $row_item) {
    $item = [];
    $setting = [];
    $node = $row_item['#row']->_entity->toArray();
    $nid = $node['nid'][0]['value'];
    // Add image url if image is added to content.
    if (!empty($node['field_homepage_section_bg_image'][0]['target_id'])) {
      $file_id = $node['field_homepage_section_bg_image'][0]['target_id'];
      $file = File::load($file_id);

      // TODO Confirm if using file_create_url is fine.
      $url = file_create_url($file->getFileUri());

      $setting['url'] = $url;
    }

    // Get required settings from node.
    $setting['height'] = !empty($node['field_homepage_section_height'][0]['value']) ? $node['field_homepage_section_height'][0]['value'] : $options['height'];
    $setting['opacity'] = !empty($node['field_homepage_section_opacity'][0]['value']) ? $node['field_homepage_section_opacity'][0]['value'] : $options['opacity'];
    $setting['wrapper_color'] = !empty($node['field_homepage_section_wrap_col'][0]['value']) ? $node['field_homepage_section_wrap_col'][0]['value'] : NULL;

    $settings[$nid] = $setting;
    $format_id = !empty($node['body'][0]['format']) ? $node['body'][0]['format'] : 'full_html';

    // Prepare $item for scroller.
    $item['nid'] = $nid;
    $item['title'] = $node['title'][0]['value'];
    $item['body'] = !empty($node['body'][0]['value']) ? check_markup($node['body'][0]['value'], $format_id) : NULL;
    $items[$nid] = $item;
  }

  // Add settings to js.
  $variables['#attached']['drupalSettings']['viewsScrollerStyleScroller'] = $settings;

  // Add items to twig.
  $variables['items'] = $items;
}
