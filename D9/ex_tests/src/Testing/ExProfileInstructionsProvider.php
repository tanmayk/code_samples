<?php

namespace Drupal\ex_tests\Testing;

use Drupal\ex_profile\ExProfileHelper;

/**
 * Provides functions to create profile instructions.
 */
class ExProfileInstructionsProvider {

  /**
   * The profile helper.
   *
   * @var \Drupal\ex_profile\ExProfileHelper
   */
  protected $profileHelper;

  /**
   * Contructs ExProfileInstructionsProvider.
   *
   * @param \Drupal\ex_profile\ExProfileHelper $profile_helper
   *   The profile helper.
   */
  public function __construct(ExProfileHelper $profile_helper) {
    $this->profileHelper = $profile_helper;
  }

  /**
   * Provides instructions dataset.
   */
  public function instructionsDataSetOne() {
    // Get EX API base url.
    $api_base_url = getenv('EX_API_BASE_URL');

    $instructions = [];

    // Prepare PDF & Meta instructions data set.
    $pdf_instructions = [];
    $meta_instructions = [];
    // Instruction: authenticate.
    $instruction_set = [
      'authentication_type' => 'public',
    ];
    $pdf_instructions['authenticate'] = $instruction_set;
    $meta_instructions['authenticate'] = $instruction_set;

    // Instruction: getSource.
    $instruction_set = [
      'source_type' => 'starting_url',
      'starting_url' => $api_base_url . '/test/dataset1.html',
    ];
    $pdf_instructions['getSource'] = $instruction_set;
    $meta_instructions['getSource'] = $instruction_set;

    // Instruction for PDF: getNthDomElementAttributeValue.
    $pdf_instruction_set = [
      'number' => '1',
      'element' => 'a',
      'attribute' => 'title',
      'value' => 'Download as PDF',
    ];
    $pdf_instructions['getNthDomElementAttributeValue'] = $pdf_instruction_set;

    // Instruction for Meta: getNthDomElementAttributeValue.
    $meta_instruction_set = [
      'number' => '1',
      'element' => 'h4',
    ];
    $meta_instructions['getNthDomElementAttributeValue'] = $meta_instruction_set;

    // Instruction: getFilePathFromAttribute.
    $instruction_set = [
      'file_path_attribute' => 'href',
      'url_prefix' => $api_base_url,
    ];
    $pdf_instructions['getFilePathFromAttribute'] = $instruction_set;

    // Instruction for PDF: downloadFiles.
    $instruction_set = [];
    $pdf_instructions['downloadFiles'] = $instruction_set;
    // Instruction for Meta: getMetaContent.
    $meta_instruction_set = [
      'attribute' => '~~text',
      'meta_regex' => '|Issue (?<issue_number>\d*),\s*Volume (?<volume_number>\d*)|i',
      'meta_mapping' => ['volume_number', 'issue_number'],
    ];
    $meta_instructions['getMetaContent'] = $meta_instruction_set;

    $instructions['pdf'] = $pdf_instructions;
    $instructions['meta'] = $meta_instructions;

    return $instructions;
  }

  /**
   * Provides an array of profile instructions.
   *
   * @return array
   *   An array of instructions.
   */
  public function getParagraphValues() {
    // Mapping.
    $mapping = $this->getMapping();

    $paragraph_values = [];
    // @todo Prepare multiple datasets to test against. Revamp this function
    // to prepare paragraphs with multiple datasets.
    // Get first data set.
    $instructions = $this->instructionsDataSetOne();
    $pdf_instructions = $instructions['pdf'];
    $meta_instructions = $instructions['meta'];

    $paragraphs_data = [];
    foreach ($pdf_instructions as $instruction => $set) {
      $type = $mapping[$instruction]['type'];
      $paragraph = [
        'type' => $type,
      ];
      foreach ($set as $parameter => $value) {
        $field_name = $mapping[$instruction][$parameter];
        $paragraph[$field_name] = $value;
      }
      $paragraphs_data[] = $paragraph;
    }
    $paragraph_values['pdf'] = $paragraphs_data;

    $paragraphs_data = [];
    foreach ($meta_instructions as $instruction => $set) {
      $type = $mapping[$instruction]['type'];
      $paragraph = [
        'type' => $type,
      ];
      foreach ($set as $parameter => $value) {
        $field_name = $mapping[$instruction][$parameter];
        $paragraph[$field_name] = $value;
      }
      $paragraphs_data[] = $paragraph;
    }
    $paragraph_values['meta'] = $paragraphs_data;

    return $paragraph_values;
  }

  /**
   * Provides mapping for paragraph field names & instruction parameters.
   *
   * @return array
   *   An array of mapping.
   */
  public function getMapping() {
    $paragraph_types = $this->profileHelper->getInstructionMapping();
    $mapping = [
      'authenticate' => [
        'type' => $paragraph_types['authenticate'],
        'authentication_type' => 'field_auth_type',
        'login_url' => 'field_auth_login_url',
        'login_parameters' => 'field_auth_parameters',
        'number' => 'field_auth_number',
        'element' => 'field_auth_element',
        'attribute' => 'field_auth_attribute',
        'value' => 'field_auth_value',
      ],
      'getSource' => [
        'type' => $paragraph_types['getSource'],
        'source_type' => 'field_source_type',
        'starting_url' => 'field_source_starting_url',
        'var_starting_url' => [
          // @todo Revamp for var_starting_url.
          // We need dynamic calculation here. Probably add callback to prepare
          // the starting url?
        ],
      ],
      'getNthDomElementAttributeValue' => [
        'type' => $paragraph_types['getNthDomElementAttributeValue'],
        'number' => 'field_dom_number',
        'element' => 'field_dom_element',
        'attribute' => 'field_dom_attribute',
        'value' => 'field_dom_value',
        // @todo Revamp for starting value & logic options.
      ],
      'getFilePathFromAttribute' => [
        'type' => $paragraph_types['getFilePathFromAttribute'],
        'file_path_attribute' => 'field_file_path_attribute',
        'file_path_regex' => 'field_file_path_regex',
        'url_prefix' => 'field_file_path_url_prefix',
      ],
      'downloadFiles' => [
        'type' => $paragraph_types['downloadFiles'],
      ],
      'getMetaContent' => [
        'type' => $paragraph_types['getMetaContent'],
        'attribute' => 'field_dom_attribute',
        'meta_regex' => 'field_regex',
        'meta_mapping' => 'field_web_meta_mapping',
      ],
    ];

    return $mapping;
  }

}
