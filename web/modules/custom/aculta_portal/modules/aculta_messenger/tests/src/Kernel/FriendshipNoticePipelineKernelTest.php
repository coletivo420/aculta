<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_messenger\Friendship\FriendshipNoticeQueueInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeManager;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeSubscriber;
use Drupal\aculta_messenger\Service\MessengerNoticePublisher;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeEventProducer;
use Drupal\aculta_user\Social\FriendshipManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message\MessageInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

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
    $requestNotice = $this->loadNotice((int) $recipient->id(), 'requested');
    self::assertNotNull($requestNotice);
    self::assertTrue((bool) $requestNotice->get('field_aculta_mailer_managed')->value);

    // Changed timestamps use second precision. With the same timestamp, a
    // withdrawn and re-sent request is a new transition and must not collide
    // with the first persistent notice.
    $firstChanged = (int) $friendship->get('changed')->value;
    $manager->cancel($friendship);
    $friendship = $manager->request($recipient);
    self::assertSame($firstChanged, (int) $friendship->get('changed')->value);
    $requestNotices = $entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', FriendshipNoticeSubscriber::TEMPLATE)
      ->condition('uid', (int) $recipient->id())
      ->condition(FriendshipNoticeSubscriber::EVENT_FIELD, 'requested')
      ->execute();
    self::assertCount(2, $requestNotices);
    $requestRecords = $entityTypeManager->getStorage('message')->loadMultiple($requestNotices);
    $requestSources = array_map(
      static fn(MessageInterface $notice): string => (string) $notice->get(FriendshipNoticeSubscriber::SOURCE_FIELD)->value,
      $requestRecords,
    );
    self::assertCount(2, array_unique($requestSources));
    self::assertCount(2, $queue->messageIds);

    $currentUser->setAccount($recipient);
    $manager->accept($friendship);
    $friendship = $entityTypeManager->getStorage('aculta_friendship')->load($friendship->id());
    self::assertSame('accepted', $friendship->getStatus());
    self::assertCount(3, $queue->messageIds);
    $acceptanceNotice = $this->loadNotice((int) $requester->id(), 'accepted');
    self::assertNotNull($acceptanceNotice);
    self::assertTrue((bool) $acceptanceNotice->get('field_aculta_mailer_managed')->value);

    self::assertSame(
      3,
      (int) $entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
        ->condition('template', FriendshipNoticeSubscriber::TEMPLATE)
        ->count()->execute(),
      'The recipient receives one notice per request transition; acceptance goes to the requester.',
    );
    self::assertSame(
      [
        (int) $requestNotice->id(),
        (int) array_values(array_diff(
          array_map(static fn(MessageInterface $notice): int => (int) $notice->id(), $requestRecords),
          [(int) $requestNotice->id()],
        ))[0],
        (int) $acceptanceNotice->id(),
      ],
      $queue->messageIds,
    );

    $noticeManager = new FriendshipNoticeManager($entityTypeManager);
    self::assertSame(1, $noticeManager->unreadCount((int) $requester->id()));
    self::assertTrue($noticeManager->setRead($acceptanceNotice, $requester, TRUE));
    self::assertSame(0, $noticeManager->unreadCount((int) $requester->id()));
  }

  /**
   * Creates a native Message field for the focused Kernel fixture. */
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
    return $ids === [] ? NULL : $storage->load(reset($ids));
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
