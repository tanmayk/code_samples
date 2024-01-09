<?php

namespace Drupal\Tests\ex_tests\ExistingSite;

use Drupal\Tests\node\Traits\NodeCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Profile flag testing.
 */
class ProfileFlagsTest extends ExistingSiteBase {
  use NodeCreationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The process manager.
   *
   * @var Drupal\ex_data_retrieval\ProcessManager
   */
  protected $processManager;

  /**
   * The retrieval manager.
   *
   * @var Drupal\ex_data_retrieval\RetrievalManager
   */
  protected $retrievalManager;

  /**
   * The entity create manager for EX.
   *
   * @var Drupal\ex_tests\Testing\EntityCreateManager
   */
  protected $exEntityManager;

  /**
   * The instructions provider.
   *
   * @var Drupal\ex_tests\Testing\ExProfileInstructionsProvider
   */
  protected $instructionsProvider;

  /**
   * Test orange flag.
   */
  public function testFlags() {
    // First create required paragraph types with instructions set.
    $paragraph_values = $this->getInstructionsProvider()->getParagraphValues();
    // Store paragraph ids to use in node.
    $pdf_ids = [];
    $meta_ids = [];
    foreach ($paragraph_values['pdf'] as $paragraph_item) {
      $paragraph = $this->getExEntityManager()->createEntity('paragraph', $paragraph_item);
      $pdf_ids[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
      // Mark this paragraph for clean up once tests are finished.
      $this->markEntityForCleanup($paragraph);
    }
    foreach ($paragraph_values['meta'] as $paragraph_item) {
      $paragraph = $this->getExEntityManager()->createEntity('paragraph', $paragraph_item);
      $meta_ids[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
      // Mark this paragraph for clean up once tests are finished.
      // @todo Better place to call this under our trait itself?
      $this->markEntityForCleanup($paragraph);
    }
    // Profile node values.
    $serial_control_number = 'ATLA0001111111';
    $serial_title = 'Testing Profile';
    $profile_process_date = '2023-01-15';
    // @todo Specify default values for testing.
    $node_values = [
      'type' => 'web_profile',
      'title' => $serial_title,
      'field_profile_control_number' => $serial_control_number,
      'field_profile_next_process_date' => $profile_process_date,
      'field_profile_max_var' => 15,
      'field_profile_publication_freq' => 2,
      'body' => NULL,
      'uid' => 1,
    ];
    $node = $this->createNode($node_values);
    // Now we have web profile node.
    // Let's execute the profile 3 times to check the flags.
    $this->getProcessManager()->processWebProfile($node);

    // Assertion for yellow flag counter.
    $yellow_counter = $node->get('field_counter_yellow')->getString();
    $this->assertEquals(1, $yellow_counter);

    // Set the date again.
    $node->set('field_profile_next_process_date', $profile_process_date)->save();
    $this->getProcessManager()->processWebProfile($node);

    // Assertion for yellow flag counter.
    $yellow_counter = $node->get('field_counter_yellow')->getString();
    $this->assertEquals(2, $yellow_counter);

    $node->set('field_profile_next_process_date', $profile_process_date)->save();
    $this->getProcessManager()->processWebProfile($node);

    // Assertion for yellow flag counter.
    $yellow_counter = $node->get('field_counter_yellow')->getString();
    $this->assertEquals(3, $yellow_counter);

    // Special case for red flag.
    // Now set the max variance exceeded date in past.
    $red_flag_months = $this->getRetrievalManager()->getMonthsForRedFlag();
    $days = 2;
    $time = strtotime('-' . $red_flag_months . ' months') - (60 * 60 * 24 * $days);
    $max_var_date = date('Y-m-d', $time);
    $node->set('field_profile_max_var_date', $max_var_date)->save();
    $node->set('field_profile_next_process_date', $profile_process_date)->save();
    $this->getProcessManager()->processWebProfile($node);

    // Assertion for yellow flag counter.
    $yellow_counter = $node->get('field_counter_yellow')->getString();
    $this->assertEquals(4, $yellow_counter);

    // The processing is done. If everything worked fine, there is should be an
    // issue node created.
    $query = $this->getEntityTypeManager()->getStorage('node')->getQuery();
    $query->condition('field_issue_profile', $node->id());
    $query->accessCheck(FALSE);
    $issue_nids = $query->execute();
    // If issue nids are empty, there is something wrong with execution.
    $this->assertNotEmpty($issue_nids);
    // Get the first node id that we get from query.
    // There will be single content though.
    $issue_id = reset($issue_nids);
    $issue = $this->getEntityTypeManager()->getStorage('node')->load($issue_id);
    // Mark this issue node for clean up once tests are finished.
    $this->markEntityForCleanup($issue);

    // Flag assertions.
    $yellow_flag = $node->get('field_flag_yellow')->getString();
    $orange_flag = $node->get('field_flag_orange')->getString();
    $red_flag = $node->get('field_flag_red')->getString();
    $this->assertEquals(1, $yellow_flag);
    $this->assertEquals(1, $orange_flag);
    $this->assertEquals(1, $red_flag);

    // Flag warning assertions.
    $yellow_flag_warning = $node->get('field_warning_flag_yellow')->getString();
    $orange_flag_warning = $node->get('field_warning_flag_orange')->getString();
    $red_flag_warning = $node->get('field_warning_flag_red')->getString();
    $this->assertEquals(1, $yellow_flag_warning);
    $this->assertEquals(1, $orange_flag_warning);
    $this->assertEquals(1, $red_flag_warning);

    // Email notification flag assertions.
    $orange_flag_notify = $node->get('field_notified_orange_flag')->getString();
    $red_flag_notify = $node->get('field_notified_red_flag')->getString();
    $this->assertEquals(1, $orange_flag_notify);
    $this->assertEquals(1, $red_flag_notify);
  }

  /**
   * Returns instance of entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = $this->container->get('entity_type.manager');
    }
    return $this->entityTypeManager;
  }

  /**
   * Returns instance of config factory service.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  protected function getConfigFactory() {
    if (!isset($this->configFactory)) {
      $this->configFactory = $this->container->get('config.factory');
    }
    return $this->configFactory;
  }

  /**
   * Returns instance of process manager service.
   *
   * @return \Drupal\ex_data_retrieval\ProcessManager
   *   The process manager.
   */
  protected function getProcessManager() {
    if (!isset($this->processManager)) {
      $this->processManager = $this->container->get('ex_data_retrieval.process');
    }
    return $this->processManager;
  }

  /**
   * Returns instance of retrieval manager service.
   *
   * @return \Drupal\ex_data_retrieval\RetrievalManager
   *   The retrieval manager.
   */
  protected function getRetrievalManager() {
    if (!isset($this->retrievalManager)) {
      $this->retrievalManager = $this->container->get('ex_data_retrieval.manager');
    }
    return $this->retrievalManager;
  }

  /**
   * Returns instance of entity create manager for EX.
   *
   * @return \Drupal\ex_tests\Testing\EntityCreateManager
   *   The entity create manager for EX.
   */
  protected function getExEntityManager() {
    if (!isset($this->exEntityManager)) {
      $this->exEntityManager = $this->container->get('ex_tests.entity_manager');
    }
    return $this->exEntityManager;
  }

  /**
   * Returns instance of the instructions provider.
   *
   * @return \Drupal\ex_tests\Testing\ExProfileInstructionsProvider
   *   The instructions provider.
   */
  protected function getInstructionsProvider() {
    if (!isset($this->instructionsProvider)) {
      $this->instructionsProvider = $this->container->get('ex_tests.instructions_manager');
    }
    return $this->instructionsProvider;
  }

}
