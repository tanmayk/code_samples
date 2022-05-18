<?php

namespace Drupal\Tests\gizra_og\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;
use Drupal\Core\Url;
use Drupal\og\OgMembershipInterface;

/**
 * DTT generate test cases for Gizra OG.
 */
class GizraOgGeneralTest extends ExistingSiteBase {

  /**
   * A test method for greeting text.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testGreeting() {
    $group_title = 'Lorem Ipsum';
    // Create a group node.
    $node = $this->createNode([
      'title' => $group_title,
      'type' => 'group',
      'uid' => 1,
    ]);

    $username = mb_strtolower($this->randomMachineName());
    // Create a user.
    $account = $this->createUser([], $username);
    // Log the user in.
    $this->drupalLogin($account);

    // Go to the group node.
    $this->drupalGet($node->toUrl());

    $assert = $this->assertSession();

    $parameters = [
      'entity_type_id' => $node->getEntityTypeId(),
      'group' => $node->id(),
      'og_membership_type' => OgMembershipInterface::TYPE_DEFAULT,
    ];
    $url = Url::fromRoute('og.subscribe', $parameters);

    $expected = 'Hi ' . $username . ', click <a href="' . $url->toString() . '">here</a> if you would like to subscribe to this group called ' . $group_title . '.';
    $assert->pageTextContains($expected);
  }

}
