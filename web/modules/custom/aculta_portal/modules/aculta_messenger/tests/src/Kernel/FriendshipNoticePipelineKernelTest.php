<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeEventProducer;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeManager;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeQueue;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeQueueInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\aculta_user\Social\FriendshipManager;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\MessageInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;

/**
 * Exercises the real User-to-Messenger friendship event path on Kernel storage.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class FriendshipNoticePipelineKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'message',
    'aculta_messenger_friendship_test',
    'aculta_file_uploader',
    'file',
    'token',
    'image',
    'crop',
    'image_widget_crop',
  ];

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_friendship');
    $this->installEntitySchema('message');
    $this->installConfig(['system', 'user', 'filter', 'message']);
    MessageTemplate::create([
      'template' => FriendshipNoticeSubscriber::TEMPLATE,
      'label' => 'Friendship notice',
      'description' => 'Kernel fixture.',
      'text' => [['value' => 'A member relationship changed.', 'format' => 'plain_text']],
    ])->save();
    foreach ([
      FriendshipNoticeSubscriber::SOURCE_FIELD => ['string', ['max_length' => 192]],
      FriendshipNoticeSubscriber::EVENT_FIELD => ['string', ['max_length' => 16]],
      'field_aculta_msg_delivery' => ['string', ['max_length' => 16]],
      'field_aculta_messenger_attempts' => ['integer', []],
      'field_aculta_enqueue_attempts' => ['integer', []],
      'field_aculta_mailer_managed' => ['boolean', []],
      'field_aculta_friendship_read' => ['boolean', []],
    ] as $name => [$type, $settings]) {
      $this->installMessageField($name, $type, $settings);
    }
  }

  /**
   * A request and acceptance persist one notice for each intended member. */
  public function testRequestAndAcceptancePersistAndQueueDistinctNotices(): void {
    $requester = User::create([
      'name' => 'friendship_requester',
      'mail' => 'friendship-requester@example.invalid',
      'status' => 1,
    ]);
    $requester->save();
    $recipient = User::create([
      'name' => 'friendship_recipient',
      'mail' => 'friendship-recipient@example.invalid',
      'status' => 1,
    ]);
    $recipient->save();

    $currentUser = $this->container->get('current_user');
    $currentUser->setAccount($requester);
    $entityTypeManager = $this->container->get('entity_type.manager');
    $dispatcher = $this->container->get('event_dispatcher');
    $queue = new FriendshipNoticePipelineKernelQueue();
    $dispatcher->addSubscriber(new FriendshipNoticeEventProducer(
      $entityTypeManager,
      new MessengerNoticePublisher($dispatcher),
    ));
    $dispatcher->addSubscriber(new FriendshipNoticeSubscriber(
      $entityTypeManager,
      $this->container->get('lock.persistent'),
      $queue,
    ));
    $manager = new FriendshipManager(
      $entityTypeManager,
      $currentUser,
      $this->container->get('lock.persistent'),
      $this->container->get('flood'),
      $dispatcher,
    );

    $friendship = $manager->request($recipient);
    $manager->request($recipient);
    self::assertSame('pending', $friendship->getStatus());
    self::assertCount(1, $queue->messageIds, 'Replaying a pending request does not produce another notice.');
    $requestNotice = $this->loadNotice($this->entityId($recipient), 'requested');
    self::assertNotNull($requestNotice);
    self::assertSame('1', $this->fieldValue($requestNotice, 'field_aculta_mailer_managed'));

    // Changed timestamps use second precision. With the same timestamp, a
    // withdrawn and re-sent request is a new transition and must not collide
    // with the first persistent notice.
    $firstChangedValue = $friendship->get('changed')->value;
    self::assertIsNumeric($firstChangedValue);
    $firstChanged = (int) $firstChangedValue;
    $manager->cancel($friendship);
    $friendship = $manager->request($recipient);
    $changedValue = $friendship->get('changed')->value;
    self::assertIsNumeric($changedValue);
    self::assertSame($firstChanged, (int) $changedValue);
    $requestNotices = $entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', FriendshipNoticeSubscriber::TEMPLATE)
      ->condition('uid', $this->entityId($recipient))
      ->condition(FriendshipNoticeSubscriber::EVENT_FIELD, 'requested')
      ->execute();
    self::assertCount(2, $requestNotices);
    $requestRecords = $entityTypeManager->getStorage('message')->loadMultiple($requestNotices);
    $requestSources = array_map(
      fn(MessageInterface $notice): string => $this->fieldString($notice, FriendshipNoticeSubscriber::SOURCE_FIELD),
      $this->messages($requestRecords),
    );
    self::assertCount(2, array_unique($requestSources));
    self::assertCount(2, $queue->messageIds);

    $currentUser->setAccount($recipient);
    $manager->accept($friendship);
    $friendshipId = $friendship->id();
    self::assertNotNull($friendshipId);
    $reloadedFriendship = $entityTypeManager->getStorage('aculta_friendship')->load($friendshipId);
    self::assertInstanceOf(AcultaFriendshipInterface::class, $reloadedFriendship);
    $friendship = $reloadedFriendship;
    self::assertSame('accepted', $friendship->getStatus());
    self::assertCount(3, $queue->messageIds);
    $acceptanceNotice = $this->loadNotice($this->entityId($requester), 'accepted');
    self::assertNotNull($acceptanceNotice);
    self::assertSame('1', $this->fieldValue($acceptanceNotice, 'field_aculta_mailer_managed'));

    self::assertSame(
      3,
      (int) $entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
        ->condition('template', FriendshipNoticeSubscriber::TEMPLATE)
        ->count()->execute(),
      'The recipient receives one notice per request transition; acceptance goes to the requester.',
    );
    self::assertSame(
      [
        $this->entityId($requestNotice),
        (int) array_values(array_diff(
          array_map(fn(MessageInterface $notice): int => $this->entityId($notice), $this->messages($requestRecords)),
          [$this->entityId($requestNotice)],
        ))[0],
        $this->entityId($acceptanceNotice),
      ],
      $queue->messageIds,
    );

    $noticeManager = new FriendshipNoticeManager($entityTypeManager);
    self::assertSame(1, $noticeManager->unreadCount($this->entityId($requester)));
    self::assertTrue($noticeManager->setRead($acceptanceNotice, $requester, TRUE));
    self::assertSame(0, $noticeManager->unreadCount($this->entityId($requester)));
  }

  /**
   * Cron marks an exhausted, persisted enqueue lease terminal without queueing.
   */
  public function testRecoveryMarksExhaustedEnqueueLeasePermanentlyFailed(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $message = $storage->create([
      'template' => FriendshipNoticeSubscriber::TEMPLATE,
      'field_aculta_msg_delivery' => 'pending',
      'field_aculta_messenger_attempts' => 0,
      'field_aculta_enqueue_attempts' => 5,
      'field_aculta_mailer_managed' => TRUE,
      'field_aculta_friendship_read' => FALSE,
    ]);
    $message->save();
    $messageId = $this->entityId($message);

    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::never())->method('get');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')
      ->with(FriendshipNoticeSubscriber::EMAIL_LOCK_PREFIX . $messageId, 180.0)
      ->willReturn(TRUE);
    $lock->expects(self::once())->method('release')
      ->with(FriendshipNoticeSubscriber::EMAIL_LOCK_PREFIX . $messageId);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('error')->with(
      'Friendship notice Message @id exhausted queue enqueue attempts.',
      ['@id' => $messageId],
    );
    $time = $this->createConfiguredMock(TimeInterface::class, [
      'getRequestTime' => time() + 10_000,
    ]);
    $queue = new FriendshipNoticeQueue(
      $this->container->get('entity_type.manager'),
      $queueFactory,
      $lock,
      $time,
      $logger,
    );

    $queue->requeuePending();

    $reloaded = $storage->loadUnchanged($messageId);
    self::assertInstanceOf(MessageInterface::class, $reloaded);
    self::assertSame('failed_permanent', $this->fieldValue($reloaded, 'field_aculta_msg_delivery'));
    $attempts = $this->fieldValue($reloaded, 'field_aculta_enqueue_attempts');
    self::assertIsNumeric($attempts);
    self::assertSame(5, (int) $attempts);
  }

  /**
   * Creates a native Message field for the focused Kernel fixture.
   *
   * @param array<string, mixed> $settings
   *   Field storage settings.
   */
  private function installMessageField(string $name, string $type, array $settings): void {
    FieldStorageConfig::create([
      'entity_type' => 'message',
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'message',
      'bundle' => FriendshipNoticeSubscriber::TEMPLATE,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

  /**
   * Loads a persisted friendship notice for a recipient and transition.
   */
  private function loadNotice(int $uid, string $transition): ?MessageInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', FriendshipNoticeSubscriber::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(FriendshipNoticeSubscriber::EVENT_FIELD, $transition)
      ->execute();
    if ($ids === []) {
      return NULL;
    }
    $id = reset($ids);
    $notice = $storage->load($id);
    self::assertInstanceOf(MessageInterface::class, $notice);
    return $notice;
  }

  /**
   * Converts a persisted entity ID to the integer contract used by these tests.
   */
  private function entityId(EntityInterface $entity): int {
    $id = $entity->id();
    self::assertNotNull($id);
    self::assertIsNumeric($id);
    return (int) $id;
  }

  /**
   * Returns the scalar field value for an assertion without unchecked casts.
   */
  private function fieldValue(MessageInterface $message, string $field): mixed {
    return $message->get($field)->value;
  }

  /**
   * Returns a validated string field value from a persisted notice.
   */
  private function fieldString(MessageInterface $message, string $field): string {
    $value = $this->fieldValue($message, $field);
    self::assertIsString($value);
    return $value;
  }

  /**
   * Narrows loaded entity storage results to Message entities.
   *
   * @param array<int|string, EntityInterface> $entities
   *   Entities returned from the message storage.
   *
   * @return list<MessageInterface>
   *   Loaded messages.
   */
  private function messages(array $entities): array {
    $messages = [];
    foreach ($entities as $entity) {
      self::assertInstanceOf(MessageInterface::class, $entity);
      $messages[] = $entity;
    }
    return $messages;
  }

}

/**
 * Captures persisted Message IDs handed off to the email queue contract.
 */
final class FriendshipNoticePipelineKernelQueue implements FriendshipNoticeQueueInterface {

  /**
   * IDs handed to the queue.
   *
   * @var int[]
   */
  public array $messageIds = [];

  /**
   * {@inheritdoc}
   */
  public function enqueue(MessageInterface $message): void {
    $this->messageIds[] = (int) $message->id();
  }

  /**
   * {@inheritdoc} */
  public function requeuePending(): void {}

}
