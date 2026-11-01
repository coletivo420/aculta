<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Unit;

use Drupal\aculta_game\ForumParticipationPolicy;
use Drupal\aculta_game\Hook\BadgeEvaluationHooks;
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
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Component\Datetime\TimeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the real Forum post hook and idempotent User service.
 */
#[Group('aculta_game')]
final class BadgeEvaluationHooksTest extends UnitTestCase {

  /**
   * A first public Forum post awards once even when insert and update fire.
   */
  public function testEligibleForumPostUsesBadgeServiceIdempotently(): void {
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

    $activeUser = $this->createMock(UserInterface::class);
    $activeUser->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(73)->willReturn($activeUser);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['aculta_badge_award', $awardStorage],
    ]);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('definitions')->willReturn(NULL);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_user.achievement_definitions')->willReturn($config);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $transactionManager = $this->createMock(TransactionManagerInterface::class);
    $transactionManager->method('inTransaction')->willReturn(FALSE);
    $database = $this->createMock(Connection::class);
    $database->method('transactionManager')->willReturn($transactionManager);
    $time = $this->createMock(TimeInterface::class);
    $definitions = new AchievementDefinitions(
      $configFactory,
      $lock,
      $entities,
      $this->createMock(FileUsageInterface::class),
    );
    $badges = new BadgeService($entities, $lock, $database, $time, $definitions);

    $purpose = new DomainPurposeManager(
      $entities,
      $this->createMock(DomainNegotiatorInterface::class),
      $configFactory,
      new RequestStack(),
    );
    $hook = new BadgeEvaluationHooks($badges, new ForumParticipationPolicy($purpose));
    $post = $this->publishedForumPost();
    self::assertTrue((new ForumParticipationPolicy($purpose))->isEligiblePost($post));
    $hook->forumPostSave($post);
    $hook->forumPostSave($post);

    self::assertCount(1, $createdValues);
    self::assertSame(73, $createdValues[0]['uid']);
    self::assertSame(BadgeCatalog::PRIMEIRO_COMENTARIO, $createdValues[0]['badge']);
  }

  /**
   * Builds a ForumPost-shaped entity and its current public topic context.
   */
  private function publishedForumPost(): EntityInterface {
    $emptyField = $this->createMock(FieldItemListInterface::class);
    $emptyField->method('isEmpty')->willReturn(TRUE);

    $domainField = $this->createMock(FieldItemListInterface::class);
    $domainField->method('isEmpty')->willReturn(FALSE);
    $domainField->method('__get')->with('target_id')->willReturn('forum_aculta_org');

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('isPublished')->willReturn(TRUE);
    $topic->method('hasField')->willReturnCallback(
      static fn (string $name): bool => in_array($name, ['field_autor_legado', 'field_domain_source'], TRUE),
    );
    $topic->method('get')->willReturnMap([
      ['field_autor_legado', $emptyField],
      ['field_domain_source', $domainField],
    ]);

    $topicReference = $this->createMock(FieldItemListInterface::class);
    $topicReference->method('isEmpty')->willReturn(FALSE);
    $topicReference->method('__get')->with('entity')->willReturn($topic);

    $post = $this->createMock(BadgeTestForumPostInterface::class);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('isPublished')->willReturn(TRUE);
    $post->method('isDefaultRevision')->willReturn(TRUE);
    $post->method('getOwnerId')->willReturn(73);
    $post->method('getOriginal')->willReturn(NULL);
    $post->method('hasField')->willReturnCallback(
      static fn (string $name): bool => in_array($name, ['field_autor_legado', 'topic'], TRUE),
    );
    $post->method('get')->willReturnMap([
      ['field_autor_legado', $emptyField],
      ['topic', $topicReference],
    ]);
    return $post;
  }

}
