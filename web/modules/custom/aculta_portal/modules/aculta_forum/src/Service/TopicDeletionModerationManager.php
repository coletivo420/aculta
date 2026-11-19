<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages topic deletion requests, permissions and moderation queue.
 */
class TopicDeletionModerationManager {

  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AccountInterface $currentUser,
    protected readonly LoggerInterface $logger,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Checks whether the user can delete or request deletion of the topic.
   */
  public function canDeleteOrRequest(NodeInterface $topic, ?AccountInterface $user = NULL): bool {
    $user = $user ?? $this->currentUser;
    if ($topic->bundle() !== 'aculta_forum_topic') {
      return FALSE;
    }

    if ($this->isModerator($user)) {
      return TRUE;
    }

    if ((int) $topic->getOwnerId() === (int) $user->id() && $user->isAuthenticated()) {
      $policy = $this->getAuthorDeletePolicy();
      return $policy !== 'disabled';
    }

    return FALSE;
  }

  /**
   * Checks if user has permission to delete the topic directly without moderation.
   */
  public function isDirectDelete(NodeInterface $topic, ?AccountInterface $user = NULL): bool {
    $user = $user ?? $this->currentUser;
    if ($this->isModerator($user)) {
      return TRUE;
    }

    if ((int) $topic->getOwnerId() === (int) $user->id() && $user->isAuthenticated()) {
      return $this->getAuthorDeletePolicy() === 'direct_delete';
    }

    return FALSE;
  }

  /**
   * Executes deletion or registers moderation request according to policy.
   *
   * @return string
   *   'deleted' if deleted directly, or 'queued' if sent to moderation queue.
   */
  public function processDeletion(NodeInterface $topic, ?AccountInterface $user = NULL): string {
    $user = $user ?? $this->currentUser;
    if (!$this->canDeleteOrRequest($topic, $user)) {
      throw new \DomainException('User is not authorized to delete or request deletion of this topic.');
    }

    if ($this->isDirectDelete($topic, $user)) {
      $nid = $topic->id();
      $topic->delete();
      $this->logger->notice('Topic @nid deleted directly by user @uid.', [
        '@nid' => $nid,
        '@uid' => $user->id(),
      ]);
      return 'deleted';
    }

    // Unpublish topic while pending moderation review.
    $topic->setUnpublished();
    $topic->save();

    $this->database->insert('aculta_forum_topic_deletion')
      ->fields([
        'nid' => (int) $topic->id(),
        'uid' => (int) $user->id(),
        'status' => 'pending',
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();

    $this->logger->notice('Topic @nid deletion request queued by author @uid.', [
      '@nid' => $topic->id(),
      '@uid' => $user->id(),
    ]);

    return 'queued';
  }

  /**
   * Returns count of topics pending deletion review.
   */
  public function countPending(): int {
    $count = $this->database->select('aculta_forum_topic_deletion', 'd')
      ->condition('d.status', 'pending')
      ->countQuery()
      ->execute()
      ?->fetchField();
    return is_numeric($count) ? (int) $count : 0;
  }

  /**
   * Loads pending deletion requests with topic details.
   *
   * @return list<array{
   *   id: int,
   *   nid: int,
   *   uid: int,
   *   topic: \Drupal\node\NodeInterface|null,
   *   requester: \Drupal\user\UserInterface|null,
   *   created: int
   * }>
   */
  public function getPendingRequests(int $limit = 50, int $offset = 0): array {
    $query = $this->database->select('aculta_forum_topic_deletion', 'd')
      ->fields('d', ['id', 'nid', 'uid', 'status', 'created'])
      ->condition('d.status', 'pending')
      ->orderBy('d.created', 'DESC')
      ->range($offset, $limit);

    $results = $query->execute()?->fetchAll();
    $items = [];
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $userStorage = $this->entityTypeManager->getStorage('user');

    if (is_iterable($results)) {
      foreach ($results as $row) {
        if (!is_object($row)) {
          continue;
        }
        $nid = property_exists($row, 'nid') && is_numeric($row->nid) ? (int) $row->nid : 0;
        $uid = property_exists($row, 'uid') && is_numeric($row->uid) ? (int) $row->uid : 0;
        $id = property_exists($row, 'id') && is_numeric($row->id) ? (int) $row->id : 0;
        $created = property_exists($row, 'created') && is_numeric($row->created) ? (int) $row->created : 0;

        $node = $nid > 0 ? $nodeStorage->load($nid) : NULL;
        $author = $uid > 0 ? $userStorage->load($uid) : NULL;
        $items[] = [
          'id' => $id,
          'nid' => $nid,
          'uid' => $uid,
          'topic' => $node instanceof NodeInterface ? $node : NULL,
          'requester' => $author instanceof \Drupal\user\UserInterface ? $author : NULL,
          'created' => $created,
        ];
      }
    }

    return $items;
  }

  /**
   * Approves deletion request: deletes node permanently.
   */
  public function approveDeletion(int $id, AccountInterface $moderator): bool {
    $row = $this->database->select('aculta_forum_topic_deletion', 'd')
      ->fields('d', ['id', 'nid', 'status'])
      ->condition('d.id', $id)
      ->condition('d.status', 'pending')
      ->execute()
      ?->fetchObject();

    if (!is_object($row) || !property_exists($row, 'nid') || !is_numeric($row->nid)) {
      return FALSE;
    }

    $nid = (int) $row->nid;
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $node = $nodeStorage->load($nid);
    if ($node instanceof NodeInterface) {
      $node->delete();
    }

    $this->database->update('aculta_forum_topic_deletion')
      ->fields([
        'status' => 'approved',
        'reviewed_by' => (int) $moderator->id(),
        'reviewed_at' => $this->time->getRequestTime(),
      ])
      ->condition('id', $id)
      ->execute();

    $this->logger->notice('Deletion request @id for topic @nid approved by moderator @uid.', [
      '@id' => $id,
      '@nid' => $nid,
      '@uid' => $moderator->id(),
    ]);

    return TRUE;
  }

  /**
   * Rejects deletion request: re-publishes the topic and closes request.
   */
  public function rejectDeletion(int $id, AccountInterface $moderator): bool {
    $row = $this->database->select('aculta_forum_topic_deletion', 'd')
      ->fields('d', ['id', 'nid', 'status'])
      ->condition('d.id', $id)
      ->condition('d.status', 'pending')
      ->execute()
      ?->fetchObject();

    if (!is_object($row) || !property_exists($row, 'nid') || !is_numeric($row->nid)) {
      return FALSE;
    }

    $nid = (int) $row->nid;
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $node = $nodeStorage->load($nid);
    if ($node instanceof NodeInterface) {
      $node->setPublished();
      $node->save();
    }

    $this->database->update('aculta_forum_topic_deletion')
      ->fields([
        'status' => 'rejected',
        'reviewed_by' => (int) $moderator->id(),
        'reviewed_at' => $this->time->getRequestTime(),
      ])
      ->condition('id', $id)
      ->execute();

    $this->logger->notice('Deletion request @id for topic @nid rejected by moderator @uid.', [
      '@id' => $id,
      '@nid' => $nid,
      '@uid' => $moderator->id(),
    ]);

    return TRUE;
  }

  protected function isModerator(AccountInterface $user): bool {
    return $user->hasPermission('administer nodes')
      || $user->hasPermission('delete any aculta_forum_topic content')
      || $user->hasPermission('moderate aculta forum topic deletions');
  }

  protected function getAuthorDeletePolicy(): string {
    $val = $this->configFactory->get('aculta_forum.settings')->get('topic_author_delete_policy');
    return is_string($val) && $val !== '' ? $val : 'moderation_queue';
  }

}
