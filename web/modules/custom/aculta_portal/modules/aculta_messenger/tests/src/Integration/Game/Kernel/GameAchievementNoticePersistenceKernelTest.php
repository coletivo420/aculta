<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Game\Kernel;

use Drupal\aculta_messenger\Integration\Game\GameAchievementNoticeManager;
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
    $definitions = new AchievementDefinitions(
      $this->container->get('config.factory'),
      $lock,
      $entities,
      $this->container->get('file.usage'),
    );
    $badges = new BadgeService(
      $entities,
      $lock,
      $this->container->get('database'),
      $this->container->get('datetime.time'),
      $definitions,
    );
    $manager = new GameAchievementNoticeManager(
      $entities,
      $badges,
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
