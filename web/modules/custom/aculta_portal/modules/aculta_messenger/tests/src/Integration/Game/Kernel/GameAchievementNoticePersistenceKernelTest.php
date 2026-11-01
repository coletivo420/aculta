<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Game\Kernel;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Game\GameAchievementNoticeMigration;
use Drupal\aculta_messenger\Integration\Game\GameAchievementNoticeManager;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\aculta_messenger_game_test\Entity\TestBadgeAward;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies Game notice source revalidation against real entity storage.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class GameAchievementNoticePersistenceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'field',
    'text',
    'options',
    'filter',
    'message',
    'aculta_messenger_game_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installEntitySchema('aculta_badge_award');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user', 'file', 'filter', 'message']);
    MessageTemplate::create([
      'template' => GameAchievementNoticeManager::TEMPLATE,
      'label' => 'Game achievement notice',
      'description' => 'Kernel fixture.',
      'text' => [['value' => 'Achievement', 'format' => 'plain_text']],
    ])->save();
    $this->installMessageField(GameAchievementNoticeManager::SOURCE_FIELD, 'string', ['max_length' => 128]);
    $this->installMessageField(GameAchievementNoticeManager::READ_FIELD, 'boolean');
  }

  /**
   * The unread counter batches fresh source grants and excludes revoked ones.
   */
  public function testUnreadCountBatchReloadsCurrentGrants(): void {
    $user = User::create([
      'name' => 'game_notice_member',
      'mail' => 'game-notice@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    $uuids = [];
    foreach ([FALSE, FALSE, TRUE] as $revoked) {
      $award = TestBadgeAward::create([
        'uid' => $user->id(),
        'badge' => 'associado',
        'revogado_em' => $revoked ? 1_792_918_800 : NULL,
      ]);
      $award->save();
      $uuids[] = $award->uuid();
      Message::create([
        'template' => GameAchievementNoticeManager::TEMPLATE,
        'uid' => $user->id(),
        GameAchievementNoticeManager::SOURCE_FIELD => $award->uuid(),
        GameAchievementNoticeManager::READ_FIELD => 0,
        'created' => 1_792_918_800,
      ])->save();
    }

    $entities = $this->container->get('entity_type.manager');
    self::assertInstanceOf(EntityTypeManagerInterface::class, $entities);
    $lock = $this->container->get('lock');
    $manager = new GameAchievementNoticeManager(
      $entities,
      $this->badgeService(),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      $lock,
    );
    $manager->setStringTranslation($this->container->get('string_translation'));

    // Prime the source UUID lookups before counting to ensure storage performs
    // a fresh, batched reload and observes the persisted revocation state.
    foreach ($uuids as $uuid) {
      $entities->getStorage('aculta_badge_award')->loadByProperties(['uuid' => $uuid]);
    }
    self::assertSame(2, $manager->unreadCount((int) $user->id()));
  }

  /**
   * Legacy queues migrate idempotently and discard stale or ineligible awards.
   */
  public function testLegacyNoticesMigrateOnceAndRemoveProcessedQueue(): void {
    $recipient = $this->user('legacy_game_recipient');
    $other = $this->user('legacy_game_other');
    $valid = $this->award((int) $recipient->id(), FALSE);
    $revoked = $this->award((int) $recipient->id(), TRUE);
    $foreign = $this->award((int) $other->id(), FALSE);
    $legacyIds = [$valid->id(), $revoked->id(), $foreign->id(), 99999, $valid->id(), 0];
    $userData = $this->container->get('user.data');
    $userData->set('aculta_user', (int) $recipient->id(), 'game_achievement_notice_queue', $legacyIds);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())->method('publish')->with(self::callback(
      static fn(MessengerNoticeEvent $event): bool => $event->eventType() === 'game.achievement_awarded'
        && $event->sourceEntityUuid() === $valid->uuid()
        && $event->recipientUid() === (int) $recipient->id(),
    ));
    $migration = new GameAchievementNoticeMigration(
      $this->container->get('database'),
      $userData,
      $this->container->get('entity_type.manager'),
      $publisher,
      $this->badgeService(),
    );
    $this->container->set('aculta_messenger.game_achievement_notice_migration', $migration);
    require_once dirname(__DIR__, 5) . '/aculta_messenger.post_update.php';
    $sandbox = [];

    self::assertSame(
      'Migrated 1 accounts with pending achievement notices.',
      aculta_messenger_post_update_game_achievement_notices($sandbox),
    );
    self::assertSame(1, $sandbox['#finished']);
    self::assertNull($userData->get('aculta_user', (int) $recipient->id(), 'game_achievement_notice_queue'));
    self::assertSame(
      'Migrated 0 accounts with pending achievement notices.',
      aculta_messenger_post_update_game_achievement_notices($sandbox),
    );
  }

  /**
   * A publisher failure keeps the legacy queue and UID cursor retryable.
   */
  public function testLegacyQueueRemainsWhenPublishingFails(): void {
    $recipient = $this->user('legacy_game_retry');
    $award = $this->award((int) $recipient->id(), FALSE);
    $userData = $this->container->get('user.data');
    $userData->set('aculta_user', (int) $recipient->id(), 'game_achievement_notice_queue', [$award->id()]);
    $publisher = $this->createMock(MessengerNoticePublisherInterface::class);
    $publisher->expects(self::once())->method('publish')->willThrowException(new \RuntimeException('Temporary consumer failure.'));
    $migration = new GameAchievementNoticeMigration(
      $this->container->get('database'),
      $userData,
      $this->container->get('entity_type.manager'),
      $publisher,
      $this->badgeService(),
    );

    $lastUid = 0;
    try {
      $migration->migrateBatch($lastUid);
      self::fail('The publisher failure must be propagated for retry.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('Temporary consumer failure.', $exception->getMessage());
    }
    self::assertSame(0, $lastUid);
    self::assertSame([$award->id()], $userData->get('aculta_user', (int) $recipient->id(), 'game_achievement_notice_queue'));
  }

  /**
   * Creates an active test account.
   */
  private function user(string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    return $user;
  }

  /**
   * Creates one canonical source award with the requested revocation state.
   */
  private function award(int $uid, bool $revoked): TestBadgeAward {
    $award = TestBadgeAward::create([
      'uid' => $uid,
      'badge' => 'associado',
      'concedido_em' => 1_792_918_800,
      'revogado_em' => $revoked ? 1_792_918_801 : NULL,
    ]);
    $award->save();
    return $award;
  }

  /**
   * Builds the production BadgeService with isolated kernel storage.
   */
  private function badgeService(): BadgeService {
    $entities = $this->container->get('entity_type.manager');
    $lock = $this->container->get('lock');
    $definitions = new AchievementDefinitions(
      $this->container->get('config.factory'),
      $lock,
      $entities,
      $this->container->get('file.usage'),
    );
    return new BadgeService(
      $entities,
      $lock,
      $this->container->get('database'),
      $this->container->get('datetime.time'),
      $definitions,
    );
  }

  /**
   * Adds a storage and field definition to the Message template.
   */
  private function installMessageField(string $name, string $type, array $settings = []): void {
    $storage = FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'message',
      'type' => $type,
      'settings' => $settings,
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => GameAchievementNoticeManager::TEMPLATE,
      'label' => $name,
    ])->save();
  }

}
