<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\ParticipationRules;
use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterizes ParticipationRules without Drupal bootstrap.
 *
 * The kernel counterpart
 * (ParticipationRulesCharacterizationKernelTest) exercises the same policy
 * against real entities and queries. These tests pin the branches that depend
 * on entity-type definitions, query composition and settings defaults.
 */
#[Group('aculta_user')]
final class ParticipationRulesTest extends TestCase {

  /**
   * Settings fall back to the documented defaults.
   */
  public function testSettingsDefaultsWhenConfigurationIsEmpty(): void {
    $rules = $this->rules([]);

    self::assertSame([
      'topico_limite_ativo' => TRUE,
      'topico_comentarios_minimos' => 10,
      'primeiro_comentario_discussao_moderado' => TRUE,
      'bloqueio_ate_aprovacao' => TRUE,
      'papeis_isentos' => ['content_editor', 'administrator'],
    ], $rules->settings());
  }

  /**
   * Configured keys override defaults and keep the remaining defaults.
   */
  public function testSettingsMergeConfiguredValuesWithDefaults(): void {
    $rules = $this->rules([
      'topico_comentarios_minimos' => 3,
      'papeis_isentos' => ['associado'],
    ]);

    $settings = $rules->settings();
    self::assertSame(3, $settings['topico_comentarios_minimos']);
    self::assertSame(['associado'], $settings['papeis_isentos']);
    self::assertTrue($settings['topico_limite_ativo']);
    self::assertTrue($settings['primeiro_comentario_discussao_moderado']);
  }

  /**
   * Settings cache tags come from the configuration object.
   */
  public function testSettingsCacheTagsComeFromConfiguration(): void {
    $rules = $this->rules([], ['config:aculta_user.settings']);

    self::assertSame(['config:aculta_user.settings'], $rules->settingsCacheTags());
  }

  /**
   * Exemption is role intersection, independent of account id.
   */
  public function testIsExemptUsesConfiguredRoles(): void {
    $rules = $this->rules(['papeis_isentos' => ['content_editor', 'administrator']]);

    self::assertTrue($rules->isExempt($this->account(1, ['authenticated', 'content_editor'])));
    self::assertTrue($rules->isExempt($this->account(2, ['administrator'])));
    self::assertFalse($rules->isExempt($this->account(3, ['authenticated', 'email_confirmed'])));
  }

  /**
   * An empty exemption list exempts nobody.
   */
  public function testIsExemptCanBeDisabledByConfiguration(): void {
    $rules = $this->rules(['papeis_isentos' => []]);

    self::assertFalse($rules->isExempt($this->account(1, ['content_editor', 'administrator'])));
  }

  /**
   * The global switch disables moderation without querying participation.
   */
  public function testRequiresFirstDiscussionModerationHonoursDisabledSwitch(): void {
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects(self::never())->method('getStorage');
    $rules = $this->rules(['primeiro_comentario_discussao_moderado' => FALSE], [], $etm);

    self::assertFalse($rules->requiresFirstDiscussionModeration($this->account(7)));
  }

  /**
   * Exempt roles bypass moderation without querying participation.
   */
  public function testRequiresFirstDiscussionModerationSkipsExemptAccounts(): void {
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects(self::never())->method('getStorage');
    $rules = $this->rules([], [], $etm);

    self::assertFalse($rules->requiresFirstDiscussionModeration($this->account(7, ['content_editor'])));
  }

  /**
   * Accounts without a published comment still need moderation.
   */
  public function testRequiresFirstDiscussionModerationWhenNothingPublished(): void {
    $commentQuery = $this->queryMock(0, $log);
    $rules = $this->rules([], [], $this->entityTypeManager($commentQuery));

    self::assertTrue($rules->requiresFirstDiscussionModeration($this->account(7)));

    self::assertQueryLogContainsCondition($log, 'comment_type', ParticipationRules::DISCUSSION_COMMENT_TYPE);
    self::assertQueryLogContainsCondition($log, 'entity_type', 'node');
    self::assertQueryLogContainsCondition($log, 'field_name', 'field_aculta_discussion');
    self::assertQueryLogContainsCondition($log, 'uid', 7);
    self::assertQueryLogContainsCondition($log, 'status', 1);
    // Published lookup includes LMS participation.
    self::assertQueryLogOmitsCondition($log, 'entity_id.entity:node.type');
    self::assertQueryLogContainsCall($log, 'accessCheck', FALSE);
    self::assertQueryLogContainsCall($log, 'range', 0, 1);
    self::assertQueryLogContainsCall($log, 'count');
  }

  /**
   * One published comment (even LMS) makes the account established.
   */
  public function testRequiresFirstDiscussionModerationWhenPublishedExists(): void {
    $commentQuery = $this->queryMock(1);
    $rules = $this->rules([], [], $this->entityTypeManager($commentQuery));

    self::assertFalse($rules->requiresFirstDiscussionModeration($this->account(7)));
  }

  /**
   * A published aculta_forum_post also counts during the cutover.
   */
  public function testHasPublishedDiscussionCommentUsesForumPostStorage(): void {
    $commentQuery = $this->queryMock(0);
    $forumQuery = $this->queryMock(1, $forumLog);
    $rules = $this->rules([], [], $this->entityTypeManager($commentQuery, TRUE, $forumQuery));

    self::assertTrue($rules->hasPublishedDiscussionComment(12));

    self::assertQueryLogContainsCondition($forumLog, 'uid', 12);
    self::assertQueryLogContainsCondition($forumLog, 'status', 1);
    self::assertQueryLogContainsCall($forumLog, 'accessCheck', FALSE);
    self::assertQueryLogContainsCall($forumLog, 'range', 0, 1);
    self::assertQueryLogContainsCall($forumLog, 'count');
  }

  /**
   * No published ForumPost means no published participation.
   */
  public function testHasPublishedDiscussionCommentIgnoresUnpublishedForumPosts(): void {
    $commentQuery = $this->queryMock(0);
    $forumQuery = $this->queryMock(0, $forumLog);
    $rules = $this->rules([], [], $this->entityTypeManager($commentQuery, TRUE, $forumQuery));

    self::assertFalse($rules->hasPublishedDiscussionComment(12));
    self::assertQueryLogContainsCondition($forumLog, 'status', 1);
  }

  /**
   * Without the ForumPost entity type only comments are consulted.
   */
  public function testHasPublishedDiscussionCommentWithoutForumPostDefinition(): void {
    $commentQuery = $this->queryMock(0);
    $rules = $this->rules([], [], $this->entityTypeManager($commentQuery, FALSE));

    // entityTypeManager() throws for an unexpected aculta_forum_post storage.
    self::assertFalse($rules->hasPublishedDiscussionComment(12));
  }

  /**
   * Pending lookup excludes LMS and filters status zero.
   */
  public function testHasPendingDiscussionCommentExcludesLms(): void {
    $commentQuery = $this->queryMock(1, $log);
    $rules = $this->rules([], [], $this->entityTypeManager($commentQuery));

    self::assertTrue($rules->hasPendingDiscussionComment(9));

    self::assertQueryLogContainsCondition($log, 'status', 0);
    self::assertQueryLogContainsCondition($log, 'entity_id.entity:node.type', 'aculta_lms_topic', '<>');
    self::assertQueryLogContainsCall($log, 'range', 0, 1);
    self::assertQueryLogContainsCall($log, 'count');
  }

  /**
   * The first pending comment is pending only while nothing is published.
   */
  public function testFirstDiscussionCommentPendingRequiresNoPublishedComment(): void {
    $published = $this->queryMock(0);
    $pending = $this->queryMock(1);
    $rules = $this->rules([], [], $this->entityTypeManagerFromSequence([$published, $pending]));

    self::assertTrue($rules->firstDiscussionCommentPending(4));
  }

  /**
   * Published participation clears the pending flag.
   */
  public function testFirstDiscussionCommentPendingFalseWhenPublished(): void {
    $published = $this->queryMock(1);
    $rules = $this->rules([], [], $this->entityTypeManager($published));

    self::assertFalse($rules->firstDiscussionCommentPending(4));
  }

  /**
   * Pending LMS comments never make the first public comment pending.
   */
  public function testFirstDiscussionCommentPendingFalseWithoutPendingComments(): void {
    $published = $this->queryMock(0);
    $pending = $this->queryMock(0);
    $rules = $this->rules([], [], $this->entityTypeManagerFromSequence([$published, $pending]));

    self::assertFalse($rules->firstDiscussionCommentPending(4));
  }

  /**
   * Public forum counts filter on the Forum topic bundle.
   */
  public function testPublishedPublicForumCommentCountFiltersForumTopics(): void {
    $commentQuery = $this->queryMock(4, $log);
    $rules = $this->rules([], [], $this->entityTypeManager($commentQuery));

    self::assertSame(4, $rules->publishedPublicForumCommentCount(5));

    self::assertQueryLogContainsCondition($log, 'status', 1);
    self::assertQueryLogContainsCondition($log, 'entity_id.entity:node.type', 'aculta_lms_topic', '<>');
    self::assertQueryLogContainsCondition($log, 'entity_id.entity:node.type', 'aculta_forum_topic');
  }

  /**
   * A comment outside the shared discussion field is never moderated here.
   */
  public function testIsNonLmsDiscussionCommentRejectsOtherBundles(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('comment');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntityTypeId')->willReturn('node');

    self::assertFalse($this->rules([])->isNonLmsDiscussionComment($comment));
  }

  /**
   * A comment outside the shared discussion field name is rejected.
   */
  public function testIsNonLmsDiscussionCommentRejectsOtherFields(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn(ParticipationRules::DISCUSSION_COMMENT_TYPE);
    $comment->method('getFieldName')->willReturn('comment_body');
    $comment->method('getCommentedEntityTypeId')->willReturn('node');

    self::assertFalse($this->rules([])->isNonLmsDiscussionComment($comment));
  }

  /**
   * LMS topics are excluded from public first-comment moderation.
   */
  public function testIsNonLmsDiscussionCommentRejectsLmsHost(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_lms_topic');

    $comment = $this->discussionComment($host);

    self::assertFalse($this->rules([])->isNonLmsDiscussionComment($comment));
  }

  /**
   * A non-LMS discussion comment on a clean host belongs to the policy.
   */
  public function testIsNonLmsDiscussionCommentAcceptsPublicForumComment(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_forum_topic');
    $host->method('hasField')->with('field_autor_legado')->willReturn(FALSE);

    $comment = $this->discussionComment($host);

    self::assertTrue($this->rules([])->isNonLmsDiscussionComment($comment));
  }

  /**
   * A historic author marker on the comment exempts imported material.
   */
  public function testIsNonLmsDiscussionCommentRejectsHistoricCommentAuthor(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_forum_topic');
    $host->method('hasField')->with('field_autor_legado')->willReturn(FALSE);

    $comment = $this->discussionComment($host, TRUE);

    self::assertFalse($this->rules([])->isNonLmsDiscussionComment($comment));
  }

  /**
   * A historic author marker on the host exempts imported material.
   */
  public function testIsNonLmsDiscussionCommentRejectsHistoricHostAuthor(): void {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn('aculta_forum_topic');
    $host->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $host->method('get')->with('field_autor_legado')->willReturn($this->fieldList(FALSE));

    $comment = $this->discussionComment($host);

    self::assertFalse($this->rules([])->isNonLmsDiscussionComment($comment));
  }

  /**
   * Builds ParticipationRules with a scripted configuration factory.
   */
  private function rules(
    array $settings,
    array $cacheTags = ['config:aculta_user.settings'],
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
  ): ParticipationRules {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn($settings);
    $config->method('getCacheTags')->willReturn($cacheTags);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('aculta_user.settings')
      ->willReturn($config);

    return new ParticipationRules(
      $configFactory,
      $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
    );
  }

  /**
   * Builds an entity type manager returning one comment query.
   */
  private function entityTypeManager(
    QueryInterface $commentQuery,
    bool $hasForumPost = FALSE,
    ?QueryInterface $forumPostQuery = NULL,
  ): EntityTypeManagerInterface {
    return $this->entityTypeManagerFromSequence([$commentQuery], $hasForumPost, $forumPostQuery);
  }

  /**
   * Builds an entity type manager returning comment queries in order.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface[] $commentQueries
   *   Queries returned by successive getStorage('comment')->getQuery() calls.
   * @param bool $hasForumPost
   *   Whether the aculta_forum_post entity type is defined.
   * @param \Drupal\Core\Entity\Query\QueryInterface|null $forumPostQuery
   *   Optional query returned for the aculta_forum_post storage.
   */
  private function entityTypeManagerFromSequence(
    array $commentQueries,
    bool $hasForumPost = FALSE,
    ?QueryInterface $forumPostQuery = NULL,
  ): EntityTypeManagerInterface {
    $commentStorage = $this->createMock(EntityStorageInterface::class);
    $commentStorage->method('getQuery')->willReturnOnConsecutiveCalls(...$commentQueries);

    $storages = ['comment' => $commentStorage];
    if ($forumPostQuery !== NULL) {
      $forumStorage = $this->createMock(EntityStorageInterface::class);
      $forumStorage->method('getQuery')->willReturn($forumPostQuery);
      $storages['aculta_forum_post'] = $forumStorage;
    }

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('hasDefinition')
      ->willReturnCallback(static fn (string $id): bool => $id === 'aculta_forum_post' ? $hasForumPost : TRUE);
    $entityTypeManager->method('getStorage')
      ->willReturnCallback(static function (string $id) use ($storages) {
        if (!isset($storages[$id])) {
          throw new \RuntimeException('Unexpected storage requested: ' . $id);
        }
        return $storages[$id];
      });

    return $entityTypeManager;
  }

  /**
   * Builds a query double that records every call in $log.
   */
  private function queryMock(int|string $result, ?array &$log = NULL): QueryInterface {
    $log = [];
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnCallback(static function ($flag) use ($query, &$log) {
      $log[] = ['accessCheck', $flag];
      return $query;
    });
    $query->method('condition')->willReturnCallback(static function (...$arguments) use ($query, &$log) {
      $log[] = ['condition', ...$arguments];
      return $query;
    });
    $query->method('range')->willReturnCallback(static function (...$arguments) use ($query, &$log) {
      $log[] = ['range', ...$arguments];
      return $query;
    });
    $query->method('count')->willReturnCallback(static function () use ($query, &$log) {
      $log[] = ['count'];
      return $query;
    });
    $query->method('execute')->willReturn($result);

    return $query;
  }

  /**
   * Builds a discussion comment mock.
   */
  private function discussionComment(NodeInterface $host, bool $historicComment = FALSE): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn(ParticipationRules::DISCUSSION_COMMENT_TYPE);
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntityTypeId')->willReturn('node');
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('hasField')->with('field_autor_legado')->willReturn($historicComment);
    if ($historicComment) {
      $comment->method('get')->with('field_autor_legado')->willReturn($this->fieldList(FALSE));
    }
    return $comment;
  }

  /**
   * Builds a field item list double with a fixed emptiness.
   */
  private function fieldList(bool $empty): FieldItemListInterface {
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('isEmpty')->willReturn($empty);
    return $list;
  }

  /**
   * Builds an account double.
   */
  private function account(int $uid, array $roles = ['authenticated']): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('getRoles')->willReturn($roles);
    return $account;
  }

  /**
   * Asserts a condition call with field and value (and optional operator).
   */
  private static function assertQueryLogContainsCondition(array $log, string $field, mixed $value, ?string $operator = NULL): void {
    foreach ($log as $call) {
      if ($call[0] !== 'condition' || $call[1] !== $field || $call[2] !== $value) {
        continue;
      }
      if ($operator === NULL || ($call[3] ?? NULL) === $operator) {
        self::assertTrue(TRUE);
        return;
      }
    }
    self::fail(sprintf('Condition %s = %s was not recorded.', $field, var_export($value, TRUE)));
  }

  /**
   * Asserts no condition call was recorded for a field.
   */
  private static function assertQueryLogOmitsCondition(array $log, string $field): void {
    foreach ($log as $call) {
      if ($call[0] === 'condition' && $call[1] === $field) {
        self::fail(sprintf('Unexpected condition on %s.', $field));
      }
    }
    self::assertTrue(TRUE);
  }

  /**
   * Asserts a recorded call with exact arguments after the call name.
   */
  private static function assertQueryLogContainsCall(array $log, string $name, mixed ...$arguments): void {
    foreach ($log as $call) {
      if ($call[0] === $name && array_slice($call, 1) === $arguments) {
        self::assertTrue(TRUE);
        return;
      }
    }
    self::fail(sprintf('Call %s was not recorded with the expected arguments.', $name));
  }

}
