<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_messenger\Hook\PrivateMessageRetentionHooks;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\MessageInterface;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies retention against real Private Message and Message entity storage.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class PrivateMessageRetentionKernelTest extends KernelTestBase {

  private const NOTICE_TEMPLATE = 'aculta_msg_private_notification';

  private const NOW = 1_800_000_000;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'message',
    'message_notify',
    'private_message',
    'private_message_notify',
    'aculta_messenger_retention_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installEntitySchema('private_message_ban');
    $this->installEntitySchema('private_message_thread');
    $this->installEntitySchema('private_message');
    $this->installSchema('private_message', ['pm_thread_history']);
    $this->installConfig([
      'system',
      'user',
      'filter',
      'message',
      'message_notify',
      'private_message_notify',
    ]);

    MessageTemplate::create([
      'template' => self::NOTICE_TEMPLATE,
      'label' => 'Private message notice',
      'description' => 'Kernel retention fixture.',
      'text' => [['value' => 'A private message arrived.', 'format' => 'plain_text']],
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'message',
      'field_name' => 'field_aculta_messenger_source_pm',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'private_message'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'message',
      'bundle' => self::NOTICE_TEMPLATE,
      'field_name' => 'field_aculta_messenger_source_pm',
      'label' => 'Private message source',
    ])->save();
  }

  /**
   * Removes only dormant threads and their Messenger notification records.
   */
  public function testDormantThreadAndNoticeAreDeletedButRecentDataRemains(): void {
    $first = User::create(['name' => 'retention_first', 'status' => 1]);
    $first->save();
    $second = User::create(['name' => 'retention_second', 'status' => 1]);
    $second->save();
    $members = [$first, $second];
    $oldTimestamp = self::NOW - (200 * 86400);
    $recentTimestamp = self::NOW - (30 * 86400);

    $oldThread = $this->createThread($members, (int) $first->id(), $oldTimestamp, 'Old conversation');
    $recentThread = $this->createThread($members, (int) $second->id(), $recentTimestamp, 'Recent conversation');
    $oldMessage = $oldThread->getMessages(TRUE)[0];
    $recentMessage = $recentThread->getMessages(TRUE)[0];
    $oldNotice = $this->createNotice((int) $first->id(), (int) $oldMessage->id());
    $recentNotice = $this->createNotice((int) $second->id(), (int) $recentMessage->id());

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(self::NOW);
    $policy = new ConversationPolicy($this->container->get('config.factory'));
    $hook = new PrivateMessageRetentionHooks(
      $this->container->get('entity_type.manager'),
      $this->container->get('database'),
      $time,
      $this->container->get('logger.factory'),
      $policy,
      $this->container->get('state'),
    );

    $hook->expireDormantThreads();

    $threads = $this->container->get('entity_type.manager')->getStorage('private_message_thread');
    $privateMessages = $this->container->get('entity_type.manager')->getStorage('private_message');
    $notices = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertNull($threads->load($oldThread->id()));
    self::assertNull($privateMessages->load($oldMessage->id()), 'The Private Message entity lifecycle removes messages with their thread.');
    self::assertNull($notices->load($oldNotice->id()), 'The Messenger notice referencing a removed private message is deleted.');
    self::assertNotNull($threads->load($recentThread->id()));
    self::assertNotNull($privateMessages->load($recentMessage->id()));
    self::assertNotNull($notices->load($recentNotice->id()));
  }

  /**
   * Rolls back deleted thread and private message when notice deletion fails.
   */
  public function testNoticeDeletionFailureRestoresThreadAndPrivateMessage(): void {
    $first = User::create(['name' => 'retention_rollback_first', 'status' => 1]);
    $first->save();
    $second = User::create(['name' => 'retention_rollback_second', 'status' => 1]);
    $second->save();
    $timestamp = self::NOW - (200 * 86400);
    $thread = $this->createThread([$first, $second], (int) $first->id(), $timestamp, 'Rollback conversation');
    $privateMessage = $thread->getMessages(TRUE)[0];
    $notice = $this->createNotice((int) $first->id(), (int) $privateMessage->id());
    $this->container->get('state')->set('aculta_messenger_retention_test.fail_notice_delete', TRUE);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(self::NOW);
    $hook = new PrivateMessageRetentionHooks(
      $this->container->get('entity_type.manager'),
      $this->container->get('database'),
      $time,
      $this->container->get('logger.factory'),
      new ConversationPolicy($this->container->get('config.factory')),
      $this->container->get('state'),
    );

    $hook->expireDormantThreads();

    $threads = $this->container->get('entity_type.manager')->getStorage('private_message_thread');
    $privateMessages = $this->container->get('entity_type.manager')->getStorage('private_message');
    $notices = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertNotNull($threads->load($thread->id()), 'The thread deletion is rolled back.');
    self::assertNotNull($privateMessages->load($privateMessage->id()), 'Private Message lifecycle deletions are rolled back.');
    self::assertNotNull($notices->load($notice->id()), 'The notice remains available after the failed cleanup transaction.');
  }

  /**
   * Creates and persists one thread with one private message.
   */
  private function createThread(array $members, int $senderUid, int $timestamp, string $subject): PrivateMessageThread {
    $message = PrivateMessage::create([
      'owner' => $senderUid,
      'message' => ['value' => 'Retention test text.', 'format' => 'plain_text'],
      'created' => $timestamp,
    ]);
    $message->save();
    $thread = PrivateMessageThread::create([
      'members' => array_map(static fn(User $account): int => (int) $account->id(), $members),
      'subject' => $subject,
      'updated' => $timestamp,
    ]);
    $thread->save();
    $thread->addMessage($message);
    $thread->set('updated', $timestamp);
    $thread->save();
    // The contrib changed field replaces an explicit `updated` value during
    // save. Set the fixture timestamp after entity creation so the retention
    // query exercises old and recent rows deterministically.
    $this->container->get('database')->update('private_message_threads')
      ->fields(['updated' => $timestamp])
      ->condition('id', $thread->id())
      ->execute();
    return $thread;
  }

  /**
   * Persists a Message record referencing one private message.
   */
  private function createNotice(int $uid, int $privateMessageId): MessageInterface {
    $notice = Message::create([
      'template' => self::NOTICE_TEMPLATE,
      'uid' => $uid,
      'field_aculta_messenger_source_pm' => $privateMessageId,
    ]);
    $notice->save();
    return $notice;
  }

}
