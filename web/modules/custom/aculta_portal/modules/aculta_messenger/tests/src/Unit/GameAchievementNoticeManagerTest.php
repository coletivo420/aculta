<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\BadgeAwardNoticeEventProducer;
use Drupal\aculta_messenger\Integration\Game\GameAchievementNoticeManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests persistence of Game notices through the shared Message storage.
 */
#[CoversClass(GameAchievementNoticeManager::class)]
#[Group('aculta_messenger')]
final class GameAchievementNoticeManagerTest extends UnitTestCase {

  /**
   * A canonical active award is stored once as a recipient-owned Message.
   */
  public function testRecordCreatesMessageForCanonicalGrant(): void {
    $uid = 27;
    $awardId = 31;
    $uuid = 'd68be3c8-48c2-4a44-92df-318f205a145e';
    $timestamp = 1_792_918_800;
    $event = BadgeAwardNoticeEventProducer::event($uuid, $awardId, $uid, $timestamp);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('loadUnchanged')->with($uid)->willReturn($recipient);

    $award = $this->award($awardId, $uuid, $uid, 'associado');
    $awardStorage = $this->createMock(EntityStorageInterface::class);
    $awardStorage->expects(self::once())->method('loadByProperties')->with(['uuid' => $uuid])->willReturn([$awardId => $award]);
    $awardStorage->expects(self::once())->method('loadUnchanged')->with($awardId)->willReturn($award);

    $query = $this->createMock(QueryInterface::class);
    $query->expects(self::once())->method('accessCheck')->with(FALSE)->willReturnSelf();
    $conditions = [];
    $query->expects(self::exactly(3))->method('condition')->willReturnCallback(
      static function (string $field, mixed $value, ?string $operator = NULL) use ($query, &$conditions): QueryInterface {
        $conditions[] = [$field, $value, $operator];
        return $query;
      },
    );
    $query->expects(self::once())->method('range')->with(0, 1)->willReturnSelf();
    $query->expects(self::once())->method('execute')->willReturn([]);

    $message = $this->createMock(MessageInterface::class);
    $message->expects(self::once())->method('save')->willReturn(1);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->expects(self::once())->method('getQuery')->willReturn($query);
    $messageStorage->expects(self::once())->method('create')->with([
      'template' => GameAchievementNoticeManager::TEMPLATE,
      'uid' => $uid,
      GameAchievementNoticeManager::SOURCE_FIELD => $uuid,
      GameAchievementNoticeManager::READ_FIELD => 0,
      'created' => $timestamp,
    ])->willReturn($message);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['aculta_badge_award', $awardStorage],
      ['message', $messageStorage],
    ]);
    $lock = $this->createMock(LockBackendInterface::class);
    $lockName = 'aculta_messenger:game_award_notice:' . hash('sha256', $event->idempotencyKey());
    $lock->expects(self::once())->method('acquire')->with($lockName, 30.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with($lockName);

    $manager = new GameAchievementNoticeManager(
      $entities,
      $this->badgeService($entities),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      $lock,
    );

    self::assertSame($message, $manager->record($event));
    self::assertSame([
      ['template', GameAchievementNoticeManager::TEMPLATE, NULL],
      ['uid', $uid, NULL],
      [GameAchievementNoticeManager::SOURCE_FIELD, $uuid, NULL],
    ], $conditions);
  }

  /**
   * A replay returns the recipient's existing Message without another save.
   */
  public function testReplayReturnsExistingSharedMessage(): void {
    $uid = 27;
    $awardId = 31;
    $uuid = 'd68be3c8-48c2-4a44-92df-318f205a145e';
    $event = BadgeAwardNoticeEventProducer::event($uuid, $awardId, $uid, 1_792_918_800);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with($uid)->willReturn($recipient);
    $award = $this->award($awardId, $uuid, $uid, 'associado');
    $awardStorage = $this->createMock(EntityStorageInterface::class);
    $awardStorage->method('loadByProperties')->with(['uuid' => $uuid])->willReturn([$awardId => $award]);
    $awardStorage->method('loadUnchanged')->with($awardId)->willReturn($award);

    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->with(0, 1)->willReturnSelf();
    $query->expects(self::once())->method('execute')->willReturn([56]);
    $existing = $this->createMock(MessageInterface::class);
    $messageStorage = $this->createMock(EntityStorageInterface::class);
    $messageStorage->method('getQuery')->willReturn($query);
    $messageStorage->expects(self::once())->method('loadUnchanged')->with(56)->willReturn($existing);
    $messageStorage->expects(self::never())->method('create');
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['aculta_badge_award', $awardStorage],
      ['message', $messageStorage],
    ]);
    $lock = $this->createMock(LockBackendInterface::class);
    $lockName = 'aculta_messenger:game_award_notice:' . hash('sha256', $event->idempotencyKey());
    $lock->expects(self::once())->method('acquire')->with($lockName, 30.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with($lockName);

    $manager = new GameAchievementNoticeManager(
      $entities,
      $this->badgeService($entities),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      $lock,
    );

    self::assertSame($existing, $manager->record($event));
  }

  /**
   * Creates a source entity with the fields used by the award adapter.
   */
  private function award(int $id, string $uuid, int $uid, string $badge): ContentEntityInterface {
    $award = $this->createMock(ContentEntityInterface::class);
    $award->method('getEntityTypeId')->willReturn('aculta_badge_award');
    $award->method('id')->willReturn((string) $id);
    $award->method('uuid')->willReturn($uuid);
    $award->method('get')->willReturnCallback(fn(string $field): FieldItemListInterface => match ($field) {
      'uid' => $this->field(['target_id' => $uid]),
      'badge' => $this->field(['value' => $badge]),
      'revogado_em' => $this->field([], TRUE),
      default => throw new \InvalidArgumentException('Unexpected field ' . $field),
    });
    return $award;
  }

  /**
   * Builds the real badge definition service with isolated Drupal dependencies.
   */
  private function badgeService(EntityTypeManagerInterface $entities): BadgeService {
    $lock = $this->createMock(LockBackendInterface::class);
    $definitions = new AchievementDefinitions(
      $this->getConfigFactoryStub(['aculta_user.achievement_definitions' => ['definitions' => []]]),
      $lock,
      $entities,
      $this->createMock(FileUsageInterface::class),
    );
    return new BadgeService(
      $entities,
      $lock,
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class),
      $definitions,
    );
  }

  /**
   * Creates a Drupal field item list with named public field values.
   */
  private function field(array $values, bool $empty = FALSE): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->willReturnCallback(static fn(string $name): mixed => $values[$name] ?? NULL);
    $field->method('isEmpty')->willReturn($empty);
    return $field;
  }

}
