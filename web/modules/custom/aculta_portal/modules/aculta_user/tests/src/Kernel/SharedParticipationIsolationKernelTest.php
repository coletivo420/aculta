<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\EditWindowExemptionInterface;
use Drupal\aculta_user\Hook\ForumModerationHooks;
use Drupal\aculta_user\ParticipationEditWindow;
use Drupal\aculta_user\ParticipationRules;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Characterizes the security and isolation matrix of the shared policies.
 *
 * Cells already characterized elsewhere are not duplicated here:
 * - ForumPost published versus revisioned:
 *   Drupal\Tests\aculta_forum\Kernel\ForumPostWorkflowKernelTest.
 * - Divergent Group relation and ungrouped LMS topics:
 *   Drupal\Tests\aculta_forum_lms\Kernel\LmsDiscussionContextIsolationKernelTest
 *   and Drupal\Tests\aculta_forum_lms\Unit\LmsEditWindowExemptionTest.
 *
 * This kernel test pins the remaining cells against real entities: account
 * isolation, LMS versus Forum windows, conservative timestamps, exemption
 * fail-closed behavior, e-mail confirmation and cache metadata/invalidation.
 */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class SharedParticipationIsolationKernelTest extends KernelTestBase {

  use CommentTestTrait;

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
    'comment',
    'aculta_user_test',
  ];

  /**
   * {@inheritdoc}
   *
   * The Forum modules are not enabled here, so their settings schema is not
   * installed. Excluding these names keeps the real save path (and its cache
   * invalidation) while skipping only the schema assertion.
   */
  protected static $configSchemaCheckerExclusions = [
    'aculta_forum.settings',
    'aculta_forum_lms.settings',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'filter', 'node', 'comment']);

    CommentType::create([
      'id' => ParticipationRules::DISCUSSION_COMMENT_TYPE,
      'label' => 'Public discussion post',
      'target_entity_type_id' => 'node',
    ])->save();
    foreach (['aculta_forum_topic', 'aculta_lms_topic'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
      $this->addDefaultCommentField(
        'node',
        $bundle,
        'field_aculta_discussion',
        CommentingStatus::Open,
        ParticipationRules::DISCUSSION_COMMENT_TYPE,
      );
    }
    NodeType::create(['type' => 'article', 'name' => 'article'])->save();

    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_aculta_published_at',
      'type' => 'timestamp',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => ParticipationRules::DISCUSSION_COMMENT_TYPE,
      'field_name' => 'field_aculta_published_at',
      'label' => 'First publication',
    ])->save();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_autor_legado',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_autor_legado',
      'label' => 'Historical author marker',
    ])->save();

    Role::create(['id' => 'content_editor', 'label' => 'Content editor'])->save();
    Role::create([
      'id' => 'bypass_node',
      'label' => 'Bypass node access',
      'permissions' => ['bypass node access'],
    ])->save();
    Role::create([
      'id' => EmailConfirmationPolicy::ROLE,
      'label' => 'E-mail confirmed',
    ])->save();

    // Occupy uid 1 so test accounts never inherit the superuser bypass.
    User::create(['name' => 'site_owner', 'status' => 1])->save();

    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('primeiro_comentario_discussao_moderado', TRUE)
      ->set('papeis_isentos', [])
      ->save();
    $this->setEditWindow('aculta_forum.settings', 10);
    $this->setEditWindow('aculta_forum_lms.settings', 5);
  }

  /**
   * The author window belongs to one account and never leaks to another.
   */
  public function testAuthorWindowIsIsolatedBetweenAccounts(): void {
    $author = $this->createUser();
    $other = $this->createUser();
    $host = $this->createTopic('aculta_forum_topic');
    $now = $this->requestTime();
    $open = $this->createComment($host, $author, $now - 60);
    $expired = $this->createComment($host, $author, $now - 700);
    $other_open = $this->createComment($host, $other, $now - 60);
    $policy = $this->editWindow();

    $author_open = $policy->access($open, 'update', $author);
    self::assertTrue($author_open->isNeutral());
    self::assertSame(['user', 'user.permissions'], $author_open->getCacheContexts());
    self::assertSame(540, $author_open->getCacheMaxAge());
    self::assertContains('config:aculta_forum.settings', $author_open->getCacheTags());
    self::assertContains('comment:' . $open->id(), $author_open->getCacheTags());

    self::assertTrue($policy->access($expired, 'update', $author)->isForbidden());

    // Another account is never bound by the author's window.
    $other_on_expired = $policy->access($expired, 'update', $other);
    self::assertTrue($other_on_expired->isNeutral());
    self::assertSame([], $other_on_expired->getCacheContexts());
    self::assertSame([], $other_on_expired->getCacheTags());
    self::assertSame(Cache::PERMANENT, $other_on_expired->getCacheMaxAge());

    $other_on_own = $policy->access($other_open, 'update', $other);
    self::assertTrue($other_on_own->isNeutral());
    self::assertSame(540, $other_on_own->getCacheMaxAge());

    $author_on_other = $policy->access($other_open, 'update', $author);
    self::assertTrue($author_on_other->isNeutral());
    self::assertSame([], $author_on_other->getCacheContexts());
  }

  /**
   * Without the publication marker the creation time is the conservative bound.
   */
  public function testMissingPublicationMarkerFallsBackToCreationTime(): void {
    $author = $this->createUser();
    $host = $this->createTopic('aculta_forum_topic');
    $now = $this->requestTime();
    $policy = $this->editWindow();

    $boundary = $this->createComment($host, $author, $now - 600);
    self::assertTrue($policy->access($boundary, 'update', $author)->isForbidden());

    $last_second = $this->createComment($host, $author, $now - 599);
    $last_second_result = $policy->access($last_second, 'update', $author);
    self::assertTrue($last_second_result->isNeutral());
    self::assertSame(1, $last_second_result->getCacheMaxAge());

    // No first-publication marker: "created" measures the same window.
    $without_marker = $this->createComment($host, $author, NULL, $now - 601);
    self::assertTrue($policy->access($without_marker, 'update', $author)->isForbidden());

    $fresh_without_marker = $this->createComment($host, $author, NULL, $now - 60);
    $fresh_result = $policy->access($fresh_without_marker, 'update', $author);
    self::assertTrue($fresh_result->isNeutral());
    self::assertSame(540, $fresh_result->getCacheMaxAge());
  }

  /**
   * LMS comments use the LMS window; Forum comments keep the Forum window.
   */
  public function testLmsAndForumWindowsAreConfiguredSeparately(): void {
    $author = $this->createUser();
    $now = $this->requestTime();
    $lms_host = $this->createTopic('aculta_lms_topic');
    $forum_host = $this->createTopic('aculta_forum_topic');
    $lms = $this->createComment($lms_host, $author, $now - 360);
    $forum = $this->createComment($forum_host, $author, $now - 360);
    $policy = $this->editWindow();

    $lms_result = $policy->access($lms, 'update', $author);
    self::assertTrue($lms_result->isForbidden());
    self::assertContains('config:aculta_forum_lms.settings', $lms_result->getCacheTags());
    self::assertNotContains('config:aculta_forum.settings', $lms_result->getCacheTags());

    $forum_result = $policy->access($forum, 'update', $author);
    self::assertTrue($forum_result->isNeutral());
    self::assertSame(240, $forum_result->getCacheMaxAge());
    self::assertContains('config:aculta_forum.settings', $forum_result->getCacheTags());
  }

  /**
   * Only an allowed exemption lifts the window; neutral fails closed.
   */
  public function testExemptionsFailClosedUnlessAllowed(): void {
    $author = $this->createUser();
    $host = $this->createTopic('aculta_forum_topic');
    $now = $this->requestTime();
    $expired = $this->createComment($host, $author, $now - 700);
    $open = $this->createComment($host, $author, $now - 60);

    // An unavailable contextual decision reports neutral: the window stays.
    $neutral = $this->exemption('neutral', ['exemption:neutral'], ['user.group_permissions']);
    $policy = $this->editWindow([$neutral]);
    $neutral_result = $policy->access($expired, 'update', $author);
    self::assertTrue($neutral_result->isForbidden());
    self::assertSame(['user', 'user.permissions'], $neutral_result->getCacheContexts());
    self::assertNotContains('exemption:neutral', $neutral_result->getCacheTags());
    self::assertSame(540, $policy->access($open, 'update', $author)->getCacheMaxAge());

    // A denial is not an exemption either.
    $denied = $this->exemption('forbidden', ['exemption:denied'], []);
    $denied_policy = $this->editWindow([$denied]);
    self::assertTrue($denied_policy->access($expired, 'update', $author)->isForbidden());

    // An allowed exemption lifts the window and carries its own metadata.
    $allowed = $this->exemption('allowed', ['exemption:allowed'], ['user.group_permissions']);
    $allowed_result = $this->editWindow([$allowed])->access($expired, 'update', $author);
    self::assertTrue($allowed_result->isNeutral());
    $allowed_contexts = $allowed_result->getCacheContexts();
    self::assertCount(3, $allowed_contexts);
    self::assertContains('user', $allowed_contexts);
    self::assertContains('user.permissions', $allowed_contexts);
    self::assertContains('user.group_permissions', $allowed_contexts);
    self::assertContains('exemption:allowed', $allowed_result->getCacheTags());
    self::assertSame(Cache::PERMANENT, $allowed_result->getCacheMaxAge());
  }

  /**
   * Moderating another author requires a confirmed e-mail for editors.
   */
  public function testModerationOfAnotherAuthorRequiresConfirmedEmail(): void {
    $author = $this->createUser();
    $editor = $this->createUser(['content_editor']);
    $outsider = $this->createUser();
    $admin = $this->createUser(['bypass_node']);
    $topic = $this->createTopic('aculta_forum_topic', $author);
    $hooks = $this->moderationHooks();

    $unconfirmed = $hooks->entityAccess($topic, 'update', $editor);
    self::assertNotNull($unconfirmed);
    self::assertTrue($unconfirmed->isForbidden());
    self::assertContains('user.roles', $unconfirmed->getCacheContexts());
    self::assertContains('node:' . $topic->id(), $unconfirmed->getCacheTags());

    $this->emailPolicy()->markConfirmed((int) $editor->id());
    $confirmed = $hooks->entityAccess($topic, 'update', $editor);
    self::assertNotNull($confirmed);
    self::assertTrue($confirmed->isAllowed());
    self::assertContains('user.roles', $confirmed->getCacheContexts());

    // Own content, other roles and the global bypass stay neutral.
    self::assertNull($hooks->entityAccess($topic, 'update', $author));
    self::assertNull($hooks->entityAccess($topic, 'update', $outsider));
    self::assertNull($hooks->entityAccess($topic, 'update', $admin));

    // Other bundles and non-moderation operations are outside the policy.
    $article = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'status' => 1,
    ]);
    $article->save();
    self::assertNull($hooks->entityAccess($article, 'update', $editor));
    self::assertNull($hooks->entityAccess($topic, 'view', $editor));
  }

  /**
   * Imported historic topics stay read-only even for bypass administrators.
   */
  public function testHistoricTopicsRemainReadOnly(): void {
    $author = $this->createUser();
    $admin = $this->createUser(['bypass_node']);
    $historic = $this->createTopic('aculta_forum_topic', $author, 'historic-author');
    $hooks = $this->moderationHooks();

    $result = $hooks->entityAccess($historic, 'update', $admin);
    self::assertNotNull($result);
    self::assertTrue($result->isForbidden());
    self::assertContains('node:' . $historic->id(), $result->getCacheTags());
  }

  /**
   * The result tags and max-age follow the current configuration.
   */
  public function testCacheMetadataIsInvalidatedByConfiguration(): void {
    $author = $this->createUser();
    $host = $this->createTopic('aculta_forum_topic');
    $now = $this->requestTime();
    $comment = $this->createComment($host, $author, $now - 100);
    $policy = $this->editWindow();

    $first = $policy->access($comment, 'update', $author);
    self::assertTrue($first->isNeutral());
    self::assertSame(500, $first->getCacheMaxAge());
    self::assertContains('config:aculta_forum.settings', $first->getCacheTags());

    $this->setEditWindow('aculta_forum.settings', 0);
    self::assertTrue($policy->access($comment, 'update', $author)->isForbidden());

    $this->setEditWindow('aculta_forum.settings', 20);
    $reopened = $policy->access($comment, 'update', $author);
    self::assertTrue($reopened->isNeutral());
    self::assertSame(1100, $reopened->getCacheMaxAge());
  }

  /**
   * Builds the edit window policy with the test container.
   *
   * @param iterable<\Drupal\aculta_user\EditWindowExemptionInterface> $exemptions
   *   Consumer exemptions registered for the account.
   */
  private function editWindow(iterable $exemptions = []): ParticipationEditWindow {
    return new ParticipationEditWindow(
      $this->container->get('config.factory'),
      $this->container->get('datetime.time'),
      $exemptions,
    );
  }

  /**
   * Builds a scripted exemption with a fixed decision and cache metadata.
   */
  private function exemption(string $decision, array $tags, array $contexts): EditWindowExemptionInterface {
    return new SharedParticipationIsolationExemption($decision, $tags, $contexts);
  }

  /**
   * Builds the real e-mail confirmation policy from the test container.
   */
  private function emailPolicy(): EmailConfirmationPolicy {
    return new EmailConfirmationPolicy(
      $this->container->get('user.data'),
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
    );
  }

  /**
   * Builds the Forum moderation hook around the real policy.
   */
  private function moderationHooks(): ForumModerationHooks {
    return new ForumModerationHooks(
      $this->emailPolicy(),
      $this->container->get('entity_type.manager'),
    );
  }

  /**
   * Reads the request time used by the policy.
   */
  private function requestTime(): int {
    return $this->container->get('datetime.time')->getRequestTime();
  }

  /**
   * Saves a window setting through the regular config API.
   *
   * The names are declared in $configSchemaCheckerExclusions because the Forum
   * settings schema is not installed in this minimal kernel.
   */
  private function setEditWindow(string $name, int $minutes): void {
    $this->config($name)->set('edit_window_minutes', $minutes)->save();
    $this->container->get('config.factory')->reset($name);
  }

  /**
   * Creates and saves an account, optionally with roles.
   *
   * @param string[] $roles
   *   Role IDs added to the account.
   */
  private function createUser(array $roles = []): UserInterface {
    $account = User::create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);
    foreach ($roles as $role) {
      $account->addRole($role);
    }
    $account->save();
    return $account;
  }

  /**
   * Creates and saves a node acting as a discussion host.
   */
  private function createTopic(
    string $bundle,
    ?UserInterface $author = NULL,
    ?string $historic_author = NULL,
  ): Node {
    $values = [
      'type' => $bundle,
      'title' => $this->randomMachineName(),
      'status' => 1,
    ];
    if ($author !== NULL) {
      $values['uid'] = $author->id();
    }
    if ($historic_author !== NULL) {
      $values['field_autor_legado'] = ['value' => $historic_author];
    }
    $topic = Node::create($values);
    $topic->save();
    return $topic;
  }

  /**
   * Creates and saves a discussion comment on the host.
   *
   * @param \Drupal\node\Entity\Node $host
   *   Discussion host node.
   * @param \Drupal\user\UserInterface $author
   *   Comment owner.
   * @param int|null $published_at
   *   First-publication timestamp, or NULL to leave the marker empty.
   * @param int|null $created
   *   Explicit creation timestamp, or NULL for the default.
   */
  private function createComment(
    Node $host,
    UserInterface $author,
    ?int $published_at,
    ?int $created = NULL,
  ): Comment {
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => $host->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => ParticipationRules::DISCUSSION_COMMENT_TYPE,
      'uid' => $author->id(),
      'subject' => $this->randomMachineName(),
      'comment_body' => ['value' => 'Conteúdo de teste.', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    if ($published_at !== NULL) {
      $comment->set('field_aculta_published_at', $published_at);
    }
    if ($created !== NULL) {
      $comment->set('created', $created);
    }
    $comment->save();
    return $comment;
  }

}

/**
 * Exemption double with a fixed decision and cache metadata.
 */
final class SharedParticipationIsolationExemption implements EditWindowExemptionInterface {

  public function __construct(
    private readonly string $decision,
    private readonly array $cacheTags,
    private readonly array $cacheContexts,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    $result = match ($this->decision) {
      'allowed' => AccessResult::allowed(),
      'forbidden' => AccessResult::forbidden(),
      default => AccessResult::neutral(),
    };
    return $result
      ->addCacheTags($this->cacheTags)
      ->addCacheContexts($this->cacheContexts);
  }

}
