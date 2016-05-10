<?php

/**
 * @file
 * Contains \Drupal\advagg\Tests\AdvaggTestBase.
 */

 namespace Drupal\advagg\Tests;

 use Drupal\simpletest\WebTestBase;

 abstract class AdvaggTestBase extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['advagg'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($this->user);
  }
 }