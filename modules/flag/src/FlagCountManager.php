<?php

/**
 * @file
 * Contains Drupal\flag\FlagCountManager.
 */

namespace Drupal\flag;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\FlagResetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class FlagCountManager.
 */
class FlagCountManager implements FlagCountManagerInterface, EventSubscriberInterface {

  /**
   * Stores flag counts per entity.
   *
   * @var array
   */
  protected $entityCounts = [];

  /**
   * Stores flag counts per flag.
   *
   * @var array
   */
  protected $flagCounts = [];

  /**
   * Stores flagged entity counts per flag.
   *
   * @var array
   */
  protected $flagEntityCounts = [];

  /**
   * Stores flag counts per flag and user.
   *
   * @var array
   */
  protected $userFlagCounts = [];

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a FlagCountManager.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFlagCounts(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    if (!isset($this->entityCounts[$entity_type][$entity_id])) {
      $this->entityCounts[$entity_type][$entity_id] = [];
      $query = $this->connection->select('flag_counts', 'fc');
      $result = $query
        ->fields('fc', ['flag_id', 'count'])
        ->condition('fc.entity_type', $entity_type)
        ->condition('fc.entity_id', $entity_id)
        ->execute();
      foreach ($result as $row) {
        $this->entityCounts[$entity_type][$entity_id][$row->flag_id] = $row->count;
      }
    }

    return $this->entityCounts[$entity_type][$entity_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagFlaggingCount(FlagInterface $flag) {
    $flag_id = $flag->id();
    $entity_type = $flag->getFlaggableEntityTypeId();

    // We check to see if the flag count is already in the cache,
    // if it's not, run the query.
    if (!isset($this->flagCounts[$flag_id][$entity_type])) {
      $result = $this->connection->select('flagging', 'f')
        ->fields('f', ['flag_id'])
        ->condition('flag_id', $flag_id)
        ->condition('entity_type', $entity_type)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->flagCounts[$flag_id][$entity_type] = $result;
    }

    return $this->flagCounts[$flag_id][$entity_type];
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagEntityCount(FlagInterface $flag) {
    $flag_id = $flag->id();

    if (!isset($this->flagEntityCounts[$flag_id])) {
      $this->flagEntityCounts[$flag_id] = $this->connection->select('flag_counts', 'fc')
        ->fields('fc', array('flag_id'))
        ->condition('flag_id', $flag_id)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    return $this->flagEntityCounts[$flag_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getUserFlagFlaggingCount(FlagInterface $flag, AccountInterface $user) {
    $flag_id = $flag->id();
    $uid = $user->id();

    // We check to see if the flag count is already in the cache,
    // if it's not, run the query.
    if (!isset($this->userFlagCounts[$flag_id][$uid])) {
      $result = $this->connection->select('flagging', 'f')
        ->fields('f', ['flag_id'])
        ->condition('flag_id', $flag_id)
        ->condition('uid', $uid)
        ->countQuery()
        ->execute()
        ->fetchField();
      $this->userFlagCounts[$flag_id][$uid] = $result;
    }

    return $this->userFlagCounts[$flag_id][$uid];
  }

  /**
   * Increments count of flagged entities.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function incrementFlagCounts(FlaggingEvent $event) {
    $this->connection->merge('flag_counts')
      ->key([
        'flag_id' => $event->getFlag()->id(),
        'entity_id' => $event->getEntity()->id(),
        'entity_type' => $event->getEntity()->getEntityTypeId(),
      ])
      ->fields([
        'last_updated' => REQUEST_TIME,
        'count' => 1,
      ])
      ->expression('count', 'count + :inc', [':inc' => 1])
      ->execute();

    $this->resetLoadedCounts($event->getEntity(), $event->getFlag());
  }

  /**
   * Decrements count of flagged entities.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging Event.
   */
  public function decrementFlagCounts(FlaggingEvent $event) {

    /* @var \Drupal\flag\FlaggingInterface flag */
    $flag = $event->getFlag();
    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $event->getEntity();

    $count_result = $this->connection->select('flag_counts')
      ->fields(NULL, ['flag_id', 'entity_id', 'entity_type', 'count'])
      ->condition('flag_id', $flag->id())
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityTypeId())
      ->execute()
      ->fetchAll();
    if ($count_result[0]->count == '1') {
      $this->connection->delete('flag_counts')
        ->condition('flag_id', $flag->id())
        ->condition('entity_id', $entity->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->execute();
    }
    else {
      $this->connection->update('flag_counts')
        ->expression('count', 'count - 1')
        ->condition('flag_id', $flag->id())
        ->condition('entity_id', $entity->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->execute();
    }
    $this->resetLoadedCounts($entity, $flag);
  }

  /**
   * Deletes all of a flag's count entries.
   *
   * @param \Drupal\flag\event\FlagResetEvent $event
   *   The flag reset event.
   */
  public function resetFlagCounts(FlagResetEvent $event) {
    /* @var \Drupal\flag\FlaggingInterface flag */
    $flag = $event->getFlag();

    $this->connection->delete('flag_counts')
      ->condition('flag_id', $flag->id())
      ->execute();

    // Reset statically cached counts.
    $this->entityCounts = [];
    $this->flagCounts = [];
    $this->flagEntityCounts = [];
    $this->userFlagCounts = [];

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = array();
    $events[FlagEvents::ENTITY_FLAGGED][] = array('incrementFlagCounts', -100);
    $events[FlagEvents::ENTITY_UNFLAGGED][] = array('decrementFlagCounts', -100);
    $events[FlagEvents::FLAG_RESET][] = array('resetFlagCounts', -100);
    return $events;
  }

  /**
   * Resets loaded flag counts.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The flagged entity.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag.
   */
  protected function resetLoadedCounts(EntityInterface $entity, FlagInterface $flag) {
    // @todo Consider updating them instead of just clearing it.
    unset($this->entityCounts[$entity->getEntityTypeId()][$entity->id()]);
    unset($this->flagCounts[$flag->id()]);
    unset($this->flagEntityCounts[$flag->id()]);
    unset($this->userFlagCounts[$flag->id()]);
  }

}
