<?php

/*
 * @file
 * Contains \Drupal\advagg_mod\Tests\ModifierPagesTest
 */

namespace Drupal\advagg_mod\Tests;

use Drupal\Core\Url;
use Drupal\advagg\Tests\AdminPagesTest;

/**
 * Tests that all the AdvAgg Modifier path(s) return valid content.
 *
 * @group advagg
 */
class ModifierPagesTest extends AdminPagesTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['advagg_mod'];

  /**
   * Routes to test.
   *
   * @var array
   */
  public $routes = ['advagg_mod.settings'];

}
