<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Entity\PrivateMessageInterface;
use Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * The private message service for the private message module.
 *
 * @todo This service refactoring is postponed to #3489224. To be implemented:
 *   - Replacement of direct calls to database with entity API calls and
 *     removing the $database class property
 *   - Strict typing with respect to BC.
 * @see https://www.drupal.org/project/private_message/issues/3489224
 */
class PrivateMessageService implements PrivateMessageServiceInterface {

  /**
   * The machine name of the private message module.
   */
  private const MODULE_KEY = 'private_message';

  /**
   * The timestamp at which unread private messages were marked as read.
   */
  private const LAST_CHECK_KEY = 'last_notification_check_timestamp';

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly UserDataInterface $userData,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TimeInterface $time,
    protected readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getThreadForMembers(array $members): PrivateMessageThreadInterface {
    $thread_id = $this->getThreadIdForMembers($members);

    if ($thread_id) {
      /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface $thread */
      $thread = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->load($thread_id);
      return $thread;
    }

    return $this->createPrivateMessageThread($members);
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstThreadForUser(UserInterface $user): PrivateMessageThreadInterface|false {
    $thread_id = $this->getFirstThreadIdForUser($user);
    if ($thread_id) {
      /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface $thread */
      $thread = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->load($thread_id);
      return $thread;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @param int $count
   *   A quantidade.
   * @param int|false|null $timestamp
   *   O instante de referência, quando informado.
   */
  public function getThreadsForUser(int $count, /* ?int */$timestamp = NULL): array {
    if ($timestamp === FALSE) {
      $timestamp = NULL;
    }

    $return = [
      'threads' => [],
      'next_exists' => FALSE,
    ];

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());
    /** @var list<int|string> $thread_ids */
    $thread_ids = $this->getThreadIdsForUser($user, $count, $timestamp);
    if (count($thread_ids)) {
      /** @var array<int, \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface> $threads */
      $threads = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->loadMultiple($thread_ids);
      if (count($threads)) {
        /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface $last_thread */
        $last_thread = end($threads);
        /** @var int $last_timestamp */
        $last_timestamp = $last_thread->get('updated')->value;
        $return['next_exists'] = $this->checkForNextThread($user, $last_timestamp);
        $return['threads'] = $threads;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountThreadsForUser(): int {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());
    $thread_ids = $this->getThreadIdsForUser($user);
    return count($thread_ids);
  }

  /**
   * {@inheritdoc}
   *
   * @param int $threadId
   *   O identificador da conversa.
   * @param int $messageId
   *   O identificador da mensagem.
   *
   * @return list<\Drupal\aculta_messenger\Entity\PrivateMessageInterface>
   *   O valor resultante.
   */
  public function getNewMessages($threadId, $messageId) {
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface|null $private_message_thread */
    $private_message_thread = $this->entityTypeManager
      ->getStorage('private_message_thread')
      ->load($threadId);
    if (!$private_message_thread || !$private_message_thread->isMember($this->currentUser->id())) {
      return [];
    }

    $response = [];
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageInterface $message */
    foreach ($private_message_thread->getMessages() as $message) {
      if ($message->id() > $messageId) {
        $response[] = $message;
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   *
   * @param int $threadId
   *   O identificador da conversa.
   * @param int $messageId
   *   O identificador da mensagem.
   *
   * @return array<string, mixed>
   *   O valor resultante.
   */
  public function getPreviousMessages($threadId, $messageId) {
    $return = [
      'messages' => [],
      'next_exists' => FALSE,
    ];

    $start_index = FALSE;
    $slice_count = FALSE;

    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface|null $private_message_thread */
    $private_message_thread = $this->entityTypeManager
      ->getStorage('private_message_thread')
      ->load($threadId);
    if ($private_message_thread && $private_message_thread->isMember($this->currentUser->id())) {
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($this->currentUser->id());
      /** @var array<int, \Drupal\aculta_messenger\Entity\PrivateMessageInterface> $messages */
      $messages = $private_message_thread->filterUserDeletedMessages($user);
      /** @var array<string, mixed> $settings */
      $settings = $this->configFactory->get('core.entity_view_display.private_message_thread.private_message_thread.default')->get('content.private_messages.settings');
      /** @var int $count */
      $count = $settings['ajax_previous_load_count'];

      $total = count($messages);
      foreach ($messages as $index => $message) {
        if ($message->id() >= $messageId) {
          $start_index = max($index - $count, 0);
          $slice_count = min($index, $count);
          break;
        }
      }

      if ($start_index !== FALSE && $slice_count !== FALSE) {
        $messages = array_splice($messages, $start_index, $slice_count);
        if (count($messages)) {
          $order = $settings['message_order'];
          if ($order === 'desc') {
            $messages = array_reverse($messages);
          }

          $return['messages'] = $messages;
          $old_messages = $total - ($start_index + $slice_count);
          $return['next_exists'] = ($old_messages + count($messages)) < $total;
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $existingThreadInfo
   *   As informações das conversas já exibidas.
   * @param int|false $count
   *   A quantidade.
   *
   * @return array<string, mixed>
   *   O valor resultante.
   */
  public function getUpdatedInboxThreads(array $existingThreadInfo, $count = FALSE) {
    $thread_info = $this->getUpdatedInboxThreadIds(array_keys($existingThreadInfo), $count);
    $new_threads = [];
    $thread_ids = [];
    $ids_to_load = [];
    foreach (array_keys($thread_info) as $thread_id) {
      $thread_ids[] = $thread_id;
      if (!isset($existingThreadInfo[$thread_id]) || $existingThreadInfo[$thread_id] != $thread_info[$thread_id]->updated) {
        $ids_to_load[] = $thread_id;
      }
    }

    if (count($ids_to_load)) {
      $new_threads = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->loadMultiple($ids_to_load);
    }

    return [
      'thread_ids' => $thread_ids,
      'new_threads' => $new_threads,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadThreadCount() {
    $uid = $this->currentUser->id();
    $last_check_timestamp = $this->userData->get(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY);
    $last_check_timestamp = is_numeric($last_check_timestamp) ? $last_check_timestamp : 0;

    return (int) $this->getUnreadThreadCountHelper($uid, $last_check_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadMessageCount() {
    $uid = $this->currentUser->id();
    $last_check_timestamp = $this->userData->get(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY);
    $last_check_timestamp = is_numeric($last_check_timestamp) ? $last_check_timestamp : 0;

    return (int) $this->getUnreadMessageCountHelper($uid, $last_check_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastCheckTime() {
    $uid = $this->currentUser->id();
    $this->userData->set(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY, $this->time->getRequestTime());

    $tags[] = 'private_message_notification_block:uid:' . $this->currentUser->id();
    $tags[] = 'private_message:status:uid:' . $this->currentUser->id();

    $this->cacheTagsInvalidator->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function updateThreadAccessTime(PrivateMessageThreadInterface $thread) {
    $thread->updateLastAccessTime($this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadFromMessage(PrivateMessageInterface $privateMessage) {
    $thread_id = $this->getThreadIdFromMessage($privateMessage);
    if ($thread_id) {
      /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThread $thread */
      $thread = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->load($thread_id);
      return $thread;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $build
   *   O array de renderização.
   * @param EntityInterface $entity
   *   A entidade.
   * @param EntityViewDisplayInterface $display
   *   A configuração de exibição.
   * @param string $view_mode
   *   O modo de visualização.
   */
  public function createRenderablePrivateMessageThreadLink(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    if ($display->getComponent('private_message_link')) {
      if ($entity instanceof UserInterface) {
        $author = $entity;
      }
      else {
        /** @var \Drupal\user\EntityOwnerInterface&\Drupal\Core\Entity\EntityInterface $owned_entity */
        $owned_entity = $entity;
        /** @var \Drupal\user\UserInterface $author */
        $author = $owned_entity->getOwner();
      }
      $current_user = $this->currentUser;
      if ($current_user->isAuthenticated()) {
        if ($current_user->hasPermission('use private messaging system') && $current_user->id() != $author->id()) {
          $user_entity = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
          /** @var array<\Drupal\user\UserInterface> $members */
          $members = [$user_entity, $author];
          $thread_id = $this->getThreadIdForMembers($members);
          if ($thread_id) {
            $url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $thread_id], ['attributes' => ['class' => ['private_message_link']]]);
            $build['private_message_link'] = [
              '#type' => 'link',
              '#url' => $url,
              '#title' => t('Send private message'),
              '#prefix' => '<div class="private_message_link_wrapper">',
              '#suffix' => '</div>',
            ];
          }
          else {
            $url = Url::fromRoute('private_message.private_message_create', [], ['query' => ['recipient' => $author->id()]]);
            $build['private_message_link'] = [
              '#type' => 'link',
              '#url' => $url,
              '#title' => t('Send private message'),
              '#prefix' => '<div class="private_message_link_wrapper">',
              '#suffix' => '</div>',
            ];
          }
        }
      }
      else {
        $url = Url::fromRoute('user.login');
        $build['private_message_link'] = [
          '#type' => 'link',
          '#url' => $url,
          '#title' => t('Send private message'),
          '#prefix' => '<div class="private_message_link_wrapper">',
          '#suffix' => '</div>',
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return list<mixed>
   *   O valor resultante.
   */
  public function getThreadIds() {
    $query = $this->database->select('private_message_threads', 'pmt')
      ->fields('pmt', ['id']);
    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->execute();
    /** @var list<mixed> $thread_ids */
    $thread_ids = $statement->fetchCol();
    return $thread_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadUnreadMessageCount($uid, $thread_id) {
    // @todo Optimize this, consider deletions and banned users.
    $query = $this->database->select('aculta_pm_thread_history', 'aculta_pm_thread_history')
      ->condition('uid', $uid)
      ->condition('thread_id', $thread_id);
    $query->join(
      'private_message_thread__private_messages',
      'thread_message',
      'thread_message.entity_id = aculta_pm_thread_history.thread_id'
    );
    $query->join(
      'private_messages',
      'messages_data',
      'messages_data.id = thread_message.private_messages_target_id'
    );
    $query->where('[messages_data].[created] > [aculta_pm_thread_history].[access_timestamp]');
    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->countQuery()->execute();
    /** @var int $count */
    $count = $statement->fetchField();
    return $count;
  }

  /**
   * Create a new private message thread for the given users.
   *
   * @param \Drupal\user\UserInterface[] $members
   *   An array of users who will be members of the given thread.
   *
   * @return \Drupal\aculta_messenger\Entity\PrivateMessageThread
   *   The new private message thread.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPrivateMessageThread(array $members) {
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageThread $thread */
    $thread = $this->entityTypeManager->getStorage('private_message_thread')->create();
    foreach ($members as $member) {
      $thread->addMember($member);
    }

    $thread->save();

    return $thread;
  }

  /**
   * Retrieves the ID of a thread from the database given a list of members.
   *
   * @param \Drupal\user\UserInterface[] $members
   *   A list of users, members of a given thread.
   *
   * @return int|false
   *   The thread ID, if a thread is found, or FALSE.
   */
  protected function getThreadIdForMembers(array $members) {
    $uids = array_map(fn(UserInterface $user) => (int) $user->id(), $members);

    // Select threads common for the given members.
    $query = $this->database->select('private_message_thread__members', 'pmt')
      ->fields('pmt', ['entity_id'])
      ->groupBy('entity_id');
    // Add conditions where the threads are in the set of threads for each of
    // the users.
    foreach ($uids as $uid) {
      $subQuery = $this->database->select('private_message_thread__members', 'pmt')
        ->fields('pmt', ['entity_id'])
        ->condition('members_target_id', $uid);
      $query->condition('entity_id', $subQuery, 'IN');
    }
    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->execute();
    /** @var list<int|string> $thread_ids */
    $thread_ids = $statement->fetchCol();

    // Exclude threads with other participants.
    foreach ($thread_ids as $thread_id) {
      $query = $this->database->select('private_message_thread__members', 'pmt')
        ->condition('members_target_id', $uids, 'NOT IN')
        ->condition('entity_id', $thread_id);
      /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $count_statement */
      $count_statement = $query->countQuery()->execute();
      if ($count_statement->fetchField() == 0) {
        return (int) $thread_id;
      }
    }

    return FALSE;
  }

  /**
   * Retrieves the ID of the most recently updated thread for the given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose most recently updated thread should be retrieved.
   *
   * @return int|false
   *   The ID of the most recently updated thread where the user is a member, or
   *   FALSE if one doesn't exist.
   */
  protected function getFirstThreadIdForUser(UserInterface $user) {
    /** @var int|string $user_id */
    $user_id = $user->id();
    $bannedThreadsQuery = $this->getBannedThreads($user_id);

    $query = $this->database->select('private_message_threads', 'thread');
    $query->addField('thread', 'id');
    $query->innerJoin('aculta_pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.uid = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__private_messages', 'thread_messages', 'thread_messages.entity_id = thread.id');
    $query->innerJoin('private_messages', 'messages', 'messages.id = thread_messages.private_messages_target_id AND thread_history.delete_timestamp <= messages.created');
    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');
    $query->orderBy('thread.updated', 'desc');
    $query->range(0, 1);
    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->execute();
    /** @var int|string|false $return */
    $return = $statement->fetchField();

    return $return !== FALSE ? (int) $return : FALSE;
  }

  /**
   * Retrieves a list of thread IDs for threads the user belongs to.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose most recently thread IDs should be retrieved.
   * @param int|false $count
   *   The number of thread IDs to retrieve or FALSE to retrieve them all.
   * @param int|false|null $timestamp
   *   A timestamp relative to which only thread IDs with an earlier timestamp
   *   should be returned.
   *
   * @return list<mixed>
   *   An array of thread IDs if any threads exist.
   */
  protected function getThreadIdsForUser(UserInterface $user, $count = FALSE, $timestamp = FALSE): array {
    /** @var int|string $user_id */
    $user_id = $user->id();
    $bannedThreadsQuery = $this->getBannedThreads($user_id);

    $query = $this->database->select('private_message_threads', 'thread');
    $query->addField('thread', 'id');
    $query->addExpression('MAX(thread.updated)', 'last_updated');
    $query->innerJoin('aculta_pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.uid = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__private_messages', 'thread_messages', 'thread_messages.entity_id = thread.id');
    $query->innerJoin('private_messages', 'messages', 'messages.id = thread_messages.private_messages_target_id AND thread_history.delete_timestamp <= messages.created');

    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');

    if ($timestamp) {
      $query->condition('updated', $timestamp, '<');
    }

    $query->groupBy('thread.id');
    $query->orderBy('last_updated', 'desc');
    $query->orderBy('thread.id');

    if ($count > 0) {
      /** @var int $count */
      $query->range(0, $count);
    }

    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->execute();
    /** @var list<mixed> $thread_ids */
    $thread_ids = $statement->fetchCol();
    return $thread_ids;
  }

  /**
   * Checks if a thread exists after with an ID greater than a given thread ID.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for whom to check.
   * @param int $timestamp
   *   The timestamp to check against.
   *
   * @return bool
   *   TRUE if a previous thread exists, FALSE if one doesn't.
   */
  protected function checkForNextThread(UserInterface $user, $timestamp): bool {
    $query = 'SELECT DISTINCT(thread.id) ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {aculta_pm_thread_history} aculta_pm_thread_history ' .
      'ON aculta_pm_thread_history.thread_id = thread.id AND aculta_pm_thread_history.uid = :history_uid ' .
      'JOIN {private_message_thread__members} AS thread_member ' .
      'ON thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'WHERE aculta_pm_thread_history.delete_timestamp <= messages.created ' .
      'AND thread.updated < :timestamp';
    $vars = [
      ':uid' => $user->id(),
      ':history_uid' => $user->id(),
      ':timestamp' => $timestamp,
    ];

    return (bool) $this->database->queryRange(
      $query,
      0, 1,
      $vars
    )->fetchField();
  }

  /**
   * Retrieves a list of recently updated private message thread IDs.
   *
   * The last updated timestamp will also be returned. If any ids are provided
   * in $existingThreadIds, the IDs of all threads that have been updated since
   * the oldest updated timestamp for the given thread IDs will be returned.
   * Otherwise the number of IDs returned will be the number provided for
   * $count.
   *
   * @param list<string> $existingThreadIds
   *   An array of thread IDs to be compared against.
   * @param int|false $count
   *   The number of threads to return if no existing thread IDs were provided.
   *
   * @return array<string, \stdClass>
   *   An array, keyed by thread ID, with each element of the array containing
   *   an object with the following two properties:
   *   - id: The thread ID
   *   - updated: The timestamp at which the thread was last updated
   */
  protected function getUpdatedInboxThreadIds(array $existingThreadIds, $count = FALSE): array {
    $bannedThreadsQuery = $this->getBannedThreads($this->currentUser->id());

    $query = $this->database->select('private_message_threads', 'thread');
    $query->addField('thread', 'id');
    $query->addField('thread', 'updated');
    $query->innerJoin('aculta_pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.uid = :uid', [':uid' => $this->currentUser->id()]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $this->currentUser->id()]);
    $query->innerJoin('private_message_thread__private_messages', 'thread_messages', 'thread_messages.entity_id = thread.id');
    $query->innerJoin('private_messages', 'messages', 'messages.id = thread_messages.private_messages_target_id AND thread_history.delete_timestamp <= messages.created');
    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');
    $query->orderBy('thread.updated', 'desc');
    $query->groupBy('thread.id');

    if (count($existingThreadIds)) {
      $subquery = $this->database->select('private_message_threads', 'thread');
      $subquery->addExpression('MIN(updated)');
      $subquery->condition('id', $existingThreadIds, 'IN');

      $query->condition('thread.updated', $subquery, '>=');
    }
    else {
      $query->range(0, (int) $count);
    }

    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->execute();
    /** @var array<string, \stdClass> $thread_infos */
    $thread_infos = $statement->fetchAllAssoc('id');
    return $thread_infos;
  }

  /**
   * Gets the current user's unread thread count.
   *
   * Retrieves the number of the current user's threads that have been updated
   * since the last time this number was checked.
   *
   * @param int $uid
   *   The user ID of the user whose count should be retrieved.
   * @param int|float|string $lastCheckTimestamp
   *   A UNIX timestamp indicating the time after which to check.
   *
   * @return int
   *   The number of threads updated since the given timestamp
   */
  protected function getUnreadThreadCountHelper($uid, $lastCheckTimestamp) {
    $bannedThreadsQuery = $this->getBannedThreads($uid);

    $query = $this->database->select('private_messages', 'message');
    $query->addField('thread', 'id');
    $query->innerJoin('private_message_thread__private_messages', 'thread_message', 'message.id = thread_message.private_messages_target_id');
    $query->innerJoin('private_message_threads', 'thread', 'thread_message.entity_id = thread.id');
    $query->innerJoin('aculta_pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.access_timestamp < thread.updated AND thread_history.uid = :uid', [':uid' => $uid]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $uid]);
    $query->condition('thread.updated', (int) $lastCheckTimestamp, '>');
    $query->condition('message.created', (int) $lastCheckTimestamp, '>');
    $query->condition('message.owner', $uid, '<>');
    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');
    $query->groupBy('thread.id');

    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->countQuery()->execute();
    /** @var int $count */
    $count = $statement->fetchField();
    return $count;
  }

  /**
   * Gets the current user's unread message count.
   *
   * Retrieves the number of the current user's messages that have been updated
   * since the last time this number was checked.
   *
   * @param int $uid
   *   The user ID of the user whose count should be retrieved.
   * @param int|float|string $lastCheckTimestamp
   *   A UNIX timestamp indicating the time after which to check.
   *
   * @return int
   *   The number of threads updated since the given timestamp
   */
  protected function getUnreadMessageCountHelper($uid, $lastCheckTimestamp) {
    $bannedThreadsQuery = $this->getBannedThreads($uid);

    $query = $this->database->select('private_messages', 'message');
    $query->join(
      'private_message_thread__private_messages',
      'thread_message',
      'message.id = thread_message.private_messages_target_id'
    );
    $query->join(
      'private_message_threads',
      'thread',
      'thread_message.entity_id = thread.id'
    );
    $query->join(
      'aculta_pm_thread_history',
      'thread_history',
      'thread_history.thread_id = thread.id AND thread_history.uid = :uid',
      [':uid' => $uid]
    );
    $query->join(
      'private_message_thread__members',
      'thread_member',
      'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid',
      [':uid' => $uid]
    );
    $query
      ->condition('thread.updated ', (int) $lastCheckTimestamp, '>')
      ->condition('message.created', (int) $lastCheckTimestamp, '>')
      ->condition('message.owner', $uid, '<>')
      ->condition('thread.id', $bannedThreadsQuery, 'NOT IN')
      ->where('thread_history.access_timestamp < thread.updated');
    $query = $query->countQuery();
    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->execute();
    /** @var int $count */
    $count = $statement->fetchField();
    return $count;
  }

  /**
   * Loads the thread id of the thread that a private message belongs to.
   *
   * @param \Drupal\aculta_messenger\Entity\PrivateMessageInterface $privateMessage
   *   The private message for which the thread ID of the thread it belongs to
   *   should be returned.
   *
   * @return int
   *   The private message thread ID of the thread to which the private message
   *   belongs.
   */
  protected function getThreadIdFromMessage(PrivateMessageInterface $privateMessage) {
    $query = $this->database->select('private_message_threads', 'thread');
    $query->fields('thread', ['id']);
    $query->join('private_message_thread__private_messages',
      'messages',
      'messages.entity_id = thread.id AND messages.private_messages_target_id = :message_id',
      [':message_id' => $privateMessage->id()]
    );
    $query->range(0, 1);
    /** @var \Drupal\Core\Database\StatementInterface<int, mixed> $statement */
    $statement = $query->execute();
    /** @var int $thread_id */
    $thread_id = $statement->fetchField();
    return $thread_id;
  }

  /**
   * Returns query object of banned threads for the user.
   *
   * @param int|string $user_id
   *   The user id.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query object.
   */
  protected function getBannedThreads(int|string $user_id): SelectInterface {
    // Get the list of banned users for this user.
    $subquery = $this->database->select('private_message_ban', 'pmb');
    $subquery->addField('pmb', 'target');
    $subquery->condition('pmb.owner', $user_id);

    // Get list of threads with banned users.
    $bannedThreadsQuery = $this->database->select('private_message_thread__members', 'thread_member');
    $bannedThreadsQuery->addField('thread_member', 'entity_id');
    $bannedThreadsQuery->condition('thread_member.members_target_id', $subquery, 'IN');
    $bannedThreadsQuery->groupBy('entity_id');

    return $bannedThreadsQuery;
  }

}
