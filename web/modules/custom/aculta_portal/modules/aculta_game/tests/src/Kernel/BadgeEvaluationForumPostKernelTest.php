<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Kernel;

use Drupal\aculta_game\ForumParticipationPolicy;
use Drupal\aculta_game\Hook\BadgeEvaluationHooks;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\aculta_user\Badge\BadgeCatalog;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction\TransactionManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\Component\Datetime\TimeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the Game hook with the production ForumPost entity class.
 */
#[Group('aculta_game')]
#[RunTestsInSeparateProcesses]
final class BadgeEvaluationForumPostKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'domain',
    'workflows',
    'content_moderation',
    'aculta_forum_post_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'system',
      'user',
      'field',
      'filter',
      'text',
      'node',
      'domain',
      'workflows',
      'content_moderation',
    ]);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
    ])->save();
    $this->addLegacyAuthorField('node', 'aculta_forum_topic');
    $this->addLegacyAuthorField('aculta_forum_post', 'aculta_forum_post');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * A real first public post reaches the Game hook and awards only once.
   */
  public function testRealForumPostAwardsOnce(): void {
    $author = User::create(['name' => 'game-kernel-author', 'status' => 1]);
    $author->save();
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico de teste',
      'status' => 1,
      'uid' => $author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    $post = ForumPost::create([
      'uid' => $author->id(),
      'topic' => $topic->id(),
      'body' => ['value' => 'Resposta pública de teste', 'format' => 'plain_text'],
    ]);
    $post->setPublished();
    $post->save();

    $createdValues = [];
    $award = $this->createMock(EntityInterface::class);
    $award->expects(self::once())->method('save');
    $awardQuery = $this->createMock(QueryInterface::class);
    $awardQuery->method('accessCheck')->willReturnSelf();
    $awardQuery->method('condition')->willReturnSelf();
    $awardQuery->expects(self::exactly(2))
      ->method('execute')
      ->willReturnOnConsecutiveCalls([], [41]);
    $awardStorage = $this->createMock(EntityStorageInterface::class);
    $awardStorage->method('getQuery')->willReturn($awardQuery);
    $awardStorage->method('create')->willReturnCallback(
      static function (array $values) use (&$createdValues, $award): EntityInterface {
        $createdValues[] = $values;
        return $award;
      },
    );
    $awardStorage->method('loadMultiple')->willReturnCallback(
      static fn (?array $ids): array => $ids ? [$award] : [],
    );
    $userStorage = $this->container->get('entity_type.manager')->getStorage('user');
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['aculta_badge_award', $awardStorage],
    ]);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('definitions')->willReturn(NULL);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('aculta_user.achievement_definitions')
      ->willReturn($config);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $transactions = $this->createMock(TransactionManagerInterface::class);
    $transactions->method('inTransaction')->willReturn(FALSE);
    $database = $this->createMock(Connection::class);
    $database->method('transactionManager')->willReturn($transactions);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(1_800_000_000);
    $definitions = new AchievementDefinitions(
      $configFactory,
      $lock,
      $entities,
      $this->createMock(FileUsageInterface::class),
    );
    $badges = new BadgeService($entities, $lock, $database, $time, $definitions);
    $purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->createMock(DomainNegotiatorInterface::class),
      $configFactory,
      new RequestStack(),
    );
    $hook = new BadgeEvaluationHooks($badges, new ForumParticipationPolicy($purpose));

    self::assertTrue((new ForumParticipationPolicy($purpose))->isEligiblePost($post));
    $hook->forumPostSave($post);
    $hook->forumPostSave($post);

    self::assertCount(1, $createdValues);
    self::assertSame((int) $author->id(), $createdValues[0]['uid']);
    self::assertSame(BadgeCatalog::PRIMEIRO_COMENTARIO, $createdValues[0]['badge']);
  }

  /**
   * Declares the historical author marker used by the production policy.
   */
  private function addLegacyAuthorField(string $entityType, string $bundle): void {
    FieldStorageConfig::create([
      'entity_type' => $entityType,
      'field_name' => 'field_autor_legado',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'field_name' => 'field_autor_legado',
      'label' => 'Autor legado',
    ])->save();
  }

}
