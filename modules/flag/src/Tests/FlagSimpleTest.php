<?php

/**
 * @file
 * Contains \Drupal\flag\FlagSimpleTest.
 */

namespace Drupal\flag\Tests;

use Drupal\flag\FlagInterface;


/**
 * Tests the Flag form actions (add/edit/delete).
 *
 * @group flag
 */
class FlagSimpleTest extends FlagTestBase {

  /**
   * The label of the flag to create for the test.
   *
   * @var string
   */
  protected $label = 'Test label 123';

  /**
   * The ID of the flag to create for the test.
   *
   * @var string
   */
  protected $id = 'test_label_123';

  /**
   * The flag link type.
   *
   * @var string
   */
  protected $flagLinkType;

  /**
   * The flag to test.
   *
   * @var FlagInterface
   */
  protected $flag;

  /**
   * Configures test base and executes test cases.
   */
  public function testFlagForm() {
    // Log in our user.
    $this->drupalLogin($this->adminUser);

    // Create flag.
    $this->flag = $this->createFlagWithForm();

    $this->doFlagLinksTest();
    $this->doTestFlagCounts();
  }

  /**
   * Test the flag link in different states, for different users.
   */
  public function doFlagLinksTest() {
    $node = $this->drupalCreateNode(['type' => $this->nodeType]);
    $node_id = $node->id();

    // Grant the flag permissions to the authenticated role.
    $this->grantFlagPermissions($this->flag);

    // Check that the anonymous user, who does not have the necessary
    // permissions, does not see the flag link.
    // TODO: move this to a new test LinkPermissionAccessTest test class.
    $this->drupalLogout();
    $this->drupalGet('node/' . $node_id);
    $this->assertNoLink('Flag this item');
  }



  /**
   * Flags a node using different user accounts and checks flag counts.
   */
  public function doTestFlagCounts() {
    /** \Drupal\Core\Database\Connection $connection */
    $connection = \Drupal::database();

    $node = $this->drupalCreateNode(['type' => $this->nodeType]);
    $node_id = $node->id();

    // Grant the flag permissions to the authenticated role, so that both
    // users have the same roles and share the render cache.
    $this->grantFlagPermissions($this->flag);

    // Create and login user 1.
    $user_1 = $this->drupalCreateUser();
    $this->drupalLogin($user_1);

    // Flag node (first count).
    $this->drupalGet('node/' . $node_id);
    $this->clickLink($this->flag->getFlagShortText());
    $this->assertResponse(200);
    $this->assertLink($this->flag->getUnflagShortText());

    // Check for 1 flag count.
    $count_flags_before = $connection->select('flag_counts')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', $node->getEntityTypeId())
      ->condition('entity_id', $node_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertTrue(1, $count_flags_before);

    // Logout user 1, create and login user 2.
    $user_2 = $this->drupalCreateUser();
    $this->drupalLogin($user_2);

    // Flag node (second count).
    $this->drupalGet('node/' . $node_id);
    $this->clickLink($this->flag->getFlagShortText());
    $this->assertResponse(200);
    $this->assertLink($this->flag->getUnflagShortText());

    // Check for 2 flag counts.
    $count_flags_after = $connection->select('flag_counts')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', $node->getEntityTypeId())
      ->condition('entity_id', $node_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertTrue(2, $count_flags_after);

    // Unflag the node again.
    $this->drupalGet('node/' . $node_id);
    $this->clickLink($this->flag->getUnflagShortText());
    $this->assertResponse(200);
    $this->assertLink($this->flag->getFlagShortText());

    // Check for 1 flag count.
    $count_flags_before = $connection->select('flag_counts')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', $node->getEntityTypeId())
      ->condition('entity_id', $node_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEqual(1, $count_flags_before);

    // Delete  user 1.
    $user_1->delete();

    // Check for 0 flag counts, user deletion should lead to count decrement
    // or row deletion.
    $count_flags_before = $connection->select('flag_counts')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', $node->getEntityTypeId())
      ->condition('entity_id', $node_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEqual(0, $count_flags_before);
  }

}
