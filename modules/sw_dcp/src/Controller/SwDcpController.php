<?php

/**
 * @file
 * Contains \Drupal\sw_dcp\Controller\SwDcpController.
 */

namespace Drupal\sw_dcp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\State\StateInterface;
use Symfony\Component\CssSelector\Node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class SwDcpController.
 *
 * @property \Drupal\Core\Entity\KeyValueStore\Query\QueryFactory entityQueryManager
 * @package Drupal\sw_dcp\Controller
 */
class SwDcpController extends ControllerBase {

  /**
   * Drupal\Core\State\State definition.
   *
   * @var Drupal\Core\State\StateInterface
   */
  protected $state;
  /**
   * {@inheritdoc}
   */
  public function __construct(StateInterface $state, EntityTypeManagerInterface $entityTypeManager, QueryFactory $entityQueryManager) {
    $this->state = $state;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityQueryManager = $entityQueryManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('entity.query')
    );
  }

  /**
   * Getaggregatedcssjs.
   *
   * @return string
   *   Return Hello string.
   */
  public function getAggregatedCssJS() {
    $css_cache = $this->state->get('drupal_css_cache_files');
    $js_cache = $this->state->get('system.js_cache_files');

    $cache_css_files = \Drupal::state()->get('drupal_css_cache_files');
    $cache_js_files = \Drupal::state()->get('system.js_cache_files');

    $cache_assets =  $cache_css_files + $cache_js_files;

    foreach ($cache_assets as $asset) {
      $assets[] = file_create_url($asset);
    }

    return new JsonResponse($assets, 200);
  }

  /**
   * Syncschedule.
   *
   * @param $entity_id
   * @param $action
   * @return string Return Hello string.
   * Return Hello string.
   * @internal param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function syncSchedule($entity_id, $action, $uid) {
    $flag = $this->entityTypeManager->getStorage('flag')->load('add_to_my_schedule');

    $entity = $this->entityTypeManager->getStorage('node')->load($entity_id);
    $account = $this->entityTypeManager->getStorage('user')->load($uid);

    switch ($action) {
      case 'add':
        $flagging = $this->entityTypeManager->getStorage('flagging')->create([
          'uid' => $account->id(),
          'flag_id' => 'add_to_my_schedule',
          'entity_id' => $entity->id(),
          'entity_type' => 'node',
          'global' => 0,
        ]);

        $result = $flagging->save();
        break;
      case 'remove':
        $query = $this->entityQueryManager->get('flagging');

        // The user is supplied with a flag that is not global.
        if (!empty($account) && !empty($flag) && !$flag->isGlobal()) {
          $query->condition('uid', $account->id());
        }

        // The user is supplied but the flag is not.
        if (!empty($account) && empty($flag)) {
          $query->condition('uid', $account->id());
        }
        if (!empty($flag)) {
          $query->condition('flag_id', $flag->id());
        }

        if (!empty($entity_id)) {
          $query->condition('entity_type', 'node')
            ->condition('entity_id', $entity_id);
        }

        $ids = $query->execute();
        $flagId = array_shift($ids);
        $flaggings = $this->entityTypeManager->getStorage('flagging')->loadMultiple(array($flagId));
        array_shift($flaggings)->delete();
        $result = 1;
        break;
    }


    return new JsonResponse(array('result' => $result, 'sessionId' => $entity_id), 200);
  }
}
