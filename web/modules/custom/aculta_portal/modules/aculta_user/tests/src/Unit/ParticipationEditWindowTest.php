<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\EditWindowExemptionInterface;
use Drupal\aculta_user\ParticipationEditWindow;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterizes the shared author edit window.
 *
 * The Forum copy of this test documents the consumer contract. Here the policy
 * is pinned in its owning subkernel, including per-host configuration, exact
 * boundaries and exemption precedence.
 */
#[Group('aculta_user')]
final class ParticipationEditWindowTest extends TestCase {

  /**
   * Only owner updates are restricted; everything else stays neutral.
   */
  public function testOnlyOwnerUpdatesAreRestricted(): void {
    $window = $this->window();
    $post = $this->post(17, 900);

    self::assertTrue($window->access($post, 'view', $this->account(17))->isNeutral());
    self::assertTrue($window->access($post, 'delete', $this->account(17))->isNeutral());
    self::assertTrue($window->access($post, 'update', $this->account(18))->isNeutral());
  }

  /**
   * Entities without an owner are outside this policy.
   */
  public function testEntityWithoutOwnerIsNeutral(): void {
    $window = $this->window();
    $anonymous_entity = $this->createMock(EntityInterface::class);

    self::assertTrue($window->access($anonymous_entity, 'update', $this->account(17))->isNeutral());
  }

  /**
   * Administrative permissions never extend or shorten the window.
   */
  public function testAdministrativePermissionsRemainNeutral(): void {
    $window = $this->window();

    $post = $this->post(17, 100);
    self::assertTrue($window->access($post, 'update', $this->account(17, 'administer aculta forum posts'))->isNeutral());

    $comment = $this->comment(17, 'aculta_forum_topic', 100);
    self::assertTrue($window->access($comment, 'update', $this->account(17, 'administer comments'))->isNeutral());
  }

  /**
   * An open window is neutral and reports the exact remaining seconds.
   */
  public function testOpenWindowReportsRemainingSeconds(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);

    $open = $window->access($this->post(17, 401), 'update', $this->account(17));
    self::assertTrue($open->isNeutral());
    self::assertSame(1, $open->getCacheMaxAge());

    $wider = $window->access($this->post(17, 900), 'update', $this->account(17));
    self::assertSame(500, $wider->getCacheMaxAge());
  }

  /**
   * The final second still works; the boundary instant itself does not.
   */
  public function testExactBoundaryClosesTheWindow(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);

    $last_second = $window->access($this->post(17, 401), 'update', $this->account(17));
    self::assertTrue($last_second->isNeutral());
    self::assertSame(1, $last_second->getCacheMaxAge());

    $boundary = $window->access($this->post(17, 400), 'update', $this->account(17));
    self::assertTrue($boundary->isForbidden());
    self::assertSame(Cache::PERMANENT, $boundary->getCacheMaxAge());
  }

  /**
   * Past the boundary the author only reaches the base access handler.
   */
  public function testExpiredWindowIsForbidden(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);
    $post = $this->post(17, 100);

    $result = $window->access($post, 'update', $this->account(17));
    self::assertTrue($result->isForbidden());
    self::assertFalse($result->isAllowed());
  }

  /**
   * Without a publication marker the creation time bounds the window.
   */
  public function testMissingPublicationMarkerFallsBackToCreationTime(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);

    $fresh = $window->access($this->comment(17, 'aculta_forum_topic', NULL, 950), 'update', $this->account(17));
    self::assertTrue($fresh->isNeutral());
    self::assertSame(550, $fresh->getCacheMaxAge());

    $stale = $window->access($this->comment(17, 'aculta_forum_topic', NULL, 300), 'update', $this->account(17));
    self::assertTrue($stale->isForbidden());
  }

  /**
   * An empty marker is ignored even when the field exists.
   */
  public function testEmptyPublicationMarkerFallsBackToCreationTime(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);
    $comment = $this->comment(17, 'aculta_forum_topic', 0, 950, TRUE, TRUE);

    $result = $window->access($comment, 'update', $this->account(17));
    self::assertTrue($result->isNeutral());
    self::assertSame(550, $result->getCacheMaxAge());
  }

  /**
   * With neither marker nor creation time the content is locked.
   */
  public function testEntityWithoutAnyTimestampIsLocked(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);

    self::assertTrue($window->access($this->post(17, NULL), 'update', $this->account(17))->isForbidden());
    self::assertTrue($window->access($this->comment(17, 'aculta_forum_topic', NULL, NULL), 'update', $this->account(17))->isForbidden());
  }

  /**
   * Zero disables editing; negative values are normalized to zero.
   */
  public function testZeroAndNegativeWindowsBlockAuthors(): void {
    foreach ([0, -5] as $minutes) {
      $window = $this->window(['aculta_forum.settings' => $minutes], 1000);
      self::assertTrue($window->access($this->post(17, 999), 'update', $this->account(17))->isForbidden());
    }
  }

  /**
   * A missing key resolves to the documented ten-minute default.
   */
  public function testMissingConfigurationKeyUsesTenMinuteDefault(): void {
    $window = $this->window(['aculta_forum.settings' => NULL], 1000);

    $result = $window->access($this->post(17, 401), 'update', $this->account(17));
    self::assertTrue($result->isNeutral());
    self::assertSame(1, $result->getCacheMaxAge());

    self::assertTrue($window->access($this->post(17, 399), 'update', $this->account(17))->isForbidden());
  }

  /**
   * LMS discussions follow their own administrative window.
   */
  public function testLmsCommentUsesLmsWindow(): void {
    $window = $this->window([
      'aculta_forum.settings' => 10,
      'aculta_forum_lms.settings' => 5,
    ], 1000);
    $account = $this->account(17);

    // Ten minutes after publication: open on the Forum policy, closed on LMS.
    self::assertTrue($window->access($this->comment(17, 'aculta_lms_topic', 700), 'update', $account)->isForbidden());
    self::assertTrue($window->access($this->comment(17, 'aculta_forum_topic', 700), 'update', $account)->isNeutral());
  }

  /**
   * Comments hosted outside LMS keep the public Forum window.
   */
  public function testNonLmsHostFallsBackToForumSettings(): void {
    $window = $this->window([
      'aculta_forum.settings' => 10,
      'aculta_forum_lms.settings' => 0,
    ], 1000);
    $account = $this->account(17);

    self::assertTrue($window->access($this->comment(17, 'article', 900), 'update', $account)->isNeutral());
  }

  /**
   * A comment whose host is not a node also uses the Forum window.
   */
  public function testNonNodeHostFallsBackToForumSettings(): void {
    $window = $this->window([
      'aculta_forum.settings' => 10,
      'aculta_forum_lms.settings' => 0,
    ], 1000);
    $host = $this->createMock(EntityInterface::class);
    $comment = $this->commentMock(17, $host, 900);

    self::assertTrue($window->access($comment, 'update', $this->account(17))->isNeutral());
  }

  /**
   * Forum posts never consult the LMS window.
   */
  public function testForumPostIgnoresLmsWindow(): void {
    $window = $this->window([
      'aculta_forum.settings' => 10,
      'aculta_forum_lms.settings' => 0,
    ], 1000);

    self::assertTrue($window->access($this->post(17, 900), 'update', $this->account(17))->isNeutral());
  }

  /**
   * Only an allowed exemption leaves the base handler in charge.
   */
  public function testOnlyAllowedExemptionLiftsTheWindow(): void {
    $post = $this->post(17, 100);
    $account = $this->account(17);

    $allowed = new ParticipationEditWindowExemptionStub(AccessResult::allowed());
    self::assertTrue($this->window(exemptions: [$allowed])->access($post, 'update', $account)->isNeutral());

    $neutral = new ParticipationEditWindowExemptionStub(AccessResult::neutral());
    self::assertTrue($this->window(exemptions: [$neutral])->access($post, 'update', $account)->isForbidden());

    $forbidden = new ParticipationEditWindowExemptionStub(AccessResult::forbidden());
    self::assertTrue($this->window(exemptions: [$forbidden])->access($post, 'update', $account)->isForbidden());
  }

  /**
   * A later exemption can still lift the window.
   */
  public function testExemptionsAreEvaluatedInOrder(): void {
    $post = $this->post(17, 100);
    $neutral = new ParticipationEditWindowExemptionStub(AccessResult::neutral());
    $allowed = new ParticipationEditWindowExemptionStub(AccessResult::allowed());

    self::assertTrue($this->window(exemptions: [$neutral, $allowed])->access($post, 'update', $this->account(17))->isNeutral());
  }

  /**
   * The first allowed exemption wins and evaluation stops there.
   *
   * The contract orders exemptions by service tag priority (highest first);
   * once an exemption allows, later ones are not consulted and their
   * cacheability does not leak into the result.
   */
  public function testFirstAllowedExemptionShortCircuitsTheIteration(): void {
    $first = (new ParticipationEditWindowExemptionStub(AccessResult::allowed()))
      ->withCacheability(['user.group_permissions'], ['group:1']);
    $second = (new ParticipationEditWindowExemptionStub(AccessResult::allowed()))
      ->withCacheability(['user.group_permissions'], ['group:2']);

    $result = $this->window(exemptions: [$first, $second])
      ->access($this->post(17, 100), 'update', $this->account(17));

    self::assertTrue($result->isNeutral());
    self::assertContains('group:1', $result->getCacheTags());
    self::assertNotContains('group:2', $result->getCacheTags());
  }

  /**
   * An allowed exemption also wins over a zero-length window.
   */
  public function testAllowedExemptionWinsOverZeroWindow(): void {
    $post = $this->post(17, 100);
    $allowed = new ParticipationEditWindowExemptionStub(AccessResult::allowed());

    $result = $this->window(['aculta_forum.settings' => 0], exemptions: [$allowed])
      ->access($post, 'update', $this->account(17));

    self::assertTrue($result->isNeutral());
  }

  /**
   * Exemption cacheability is merged into the neutral result.
   */
  public function testExemptionCacheabilityIsMerged(): void {
    $exempt = (new ParticipationEditWindowExemptionStub(AccessResult::allowed()))
      ->withCacheability(
        ['user.group_permissions'],
        ['group:1'],
      );
    $result = $this->window(exemptions: [$exempt])
      ->access($this->comment(17, 'aculta_lms_topic', 100), 'update', $this->account(17));

    self::assertTrue($result->isNeutral());
    self::assertContains('user.group_permissions', $result->getCacheContexts());
    self::assertContains('group:1', $result->getCacheTags());
  }

  /**
   * An unavailable exemption keeps the window instead of breaking the check.
   *
   * A consumer service that cannot answer must never turn into an edit grant:
   * the policy skips it (fail closed) and still evaluates the remaining ones.
   */
  public function testUnavailableExemptionFailsClosed(): void {
    $post = $this->post(17, 100);
    $account = $this->account(17);

    $throwing = new ParticipationEditWindowThrowingExemptionStub();
    self::assertTrue($this->window(exemptions: [$throwing])->access($post, 'update', $account)->isForbidden());

    $allowed = new ParticipationEditWindowExemptionStub(AccessResult::allowed());
    self::assertTrue($this->window(exemptions: [$throwing, $allowed])->access($post, 'update', $account)->isNeutral());
  }

  /**
   * Every decision carries the policy contexts and the entity/config tags.
   */
  public function testCacheMetadataIsPresentOnOpenAndForbiddenResults(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);
    $account = $this->account(17);

    $open = $window->access($this->post(17, 900), 'update', $account);
    self::assertSame(['user', 'user.permissions'], $open->getCacheContexts());
    self::assertContains('config:aculta_forum.settings', $open->getCacheTags());
    self::assertContains('aculta_forum_post:17', $open->getCacheTags());

    $closed = $window->access($this->post(17, 100), 'update', $account);
    self::assertSame(['user', 'user.permissions'], $closed->getCacheContexts());
    self::assertContains('config:aculta_forum.settings', $closed->getCacheTags());
    self::assertContains('aculta_forum_post:17', $closed->getCacheTags());
  }

  /**
   * Publication state is not part of the window decision.
   *
   * The ForumPost double has no isPublished() method at all; the policy still
   * resolves the window from first_published, so status never enters here.
   */
  public function testPublicationStateIsNotConsulted(): void {
    $window = $this->window(['aculta_forum.settings' => 10], 1000);

    self::assertTrue($window->access($this->post(17, 900), 'update', $this->account(17))->isNeutral());
  }

  /**
   * Builds the policy with per-config windows and a fixed clock.
   *
   * @param array<string, int|null> $minutesByConfig
   *   Window length per config object; NULL simulates a missing key.
   * @param int $now
   *   Request time used by the policy.
   * @param iterable<\Drupal\aculta_user\EditWindowExemptionInterface> $exemptions
   *   Consumer exemptions.
   */
  private function window(
    array $minutesByConfig = ['aculta_forum.settings' => 10, 'aculta_forum_lms.settings' => 10],
    int $now = 1000,
    iterable $exemptions = [],
  ): ParticipationEditWindow {
    $configs = [];
    foreach ($minutesByConfig as $name => $minutes) {
      $configs[$name] = $this->config($minutes, $name);
    }
    // Any other config object resolves to the ten-minute default.
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnCallback(
      fn (string $name): ImmutableConfig => $configs[$name] ?? $this->config(NULL, $name),
    );

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn($now);

    return new ParticipationEditWindow($configFactory, $time, $exemptions);
  }

  /**
   * Builds a config object exposing only the edit window.
   *
   * @param int|null $minutes
   *   Configured window; NULL omits the key.
   * @param string $name
   *   Config object name used for the cache tag.
   */
  private function config(?int $minutes, string $name): ImmutableConfig {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn (string $key): ?int => $key === 'edit_window_minutes' ? $minutes : NULL,
    );
    $config->method('getCacheTags')->willReturn(['config:' . $name]);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $config;
  }

  /**
   * Creates a ForumPost-shaped owner entity.
   *
   * @param int $owner
   *   Owner account ID.
   * @param int|null $firstPublished
   *   First publication timestamp; NULL removes the marker.
   */
  private function post(int $owner, ?int $firstPublished): FieldableEntityInterface&EntityOwnerInterface {
    $post = $this->createMockForIntersectionOfInterfaces([FieldableEntityInterface::class, EntityOwnerInterface::class]);
    $post->method('getEntityTypeId')->willReturn('aculta_forum_post');
    $post->method('getOwnerId')->willReturn($owner);
    $post->method('hasField')->willReturnCallback(
      static fn (string $field): bool => $field === 'first_published',
    );
    $post->method('get')->with('first_published')->willReturn(
      new ParticipationEditWindowFieldList($firstPublished ?? 0, $firstPublished === NULL),
    );
    $post->method('getCacheTags')->willReturn(['aculta_forum_post:17']);
    $post->method('getCacheContexts')->willReturn([]);
    $post->method('getCacheMaxAge')->willReturn(-1);
    return $post;
  }

  /**
   * Creates a discussion comment hosted by a node.
   *
   * @param int $owner
   *   Owner account ID.
   * @param string $hostBundle
   *   Host node bundle.
   * @param int|null $publishedAt
   *   Publication marker; NULL removes the value.
   * @param int|null $created
   *   Creation timestamp; NULL removes the field.
   * @param bool $emptyMarker
   *   Whether the marker field exists but is empty.
   */
  private function comment(
    int $owner,
    string $hostBundle,
    ?int $publishedAt,
    ?int $created = NULL,
    bool $emptyMarker = FALSE,
  ): CommentInterface {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn($hostBundle);
    return $this->commentMock($owner, $host, $publishedAt, $created, $emptyMarker);
  }

  /**
   * Creates a discussion comment with an arbitrary host.
   */
  private function commentMock(
    int $owner,
    EntityInterface $host,
    ?int $publishedAt,
    ?int $created = NULL,
    bool $emptyMarker = FALSE,
  ): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getEntityTypeId')->willReturn('comment');
    $comment->method('getOwnerId')->willReturn($owner);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('hasField')->willReturnCallback(
      static fn (string $field): bool => match ($field) {
        'field_aculta_published_at' => TRUE,
        'created' => $created !== NULL,
        default => FALSE,
      },
    );
    $comment->method('get')->willReturnCallback(
      static function (string $field) use ($publishedAt, $created, $emptyMarker): ParticipationEditWindowFieldList {
        if ($field === 'field_aculta_published_at') {
          return new ParticipationEditWindowFieldList($publishedAt ?? 0, $emptyMarker || $publishedAt === NULL);
        }
        return new ParticipationEditWindowFieldList($created ?? 0, $created === NULL);
      },
    );
    $comment->method('getCacheTags')->willReturn(['comment:1']);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(-1);
    return $comment;
  }

  /**
   * Creates an account with an optional administrative permission.
   */
  private function account(int $uid, ?string $permission = NULL): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn($uid);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $candidate): bool => $permission !== NULL && $candidate === $permission,
    );
    return $account;
  }

}

/**
 * Minimal field list exposing a timestamp and emptiness.
 */
final class ParticipationEditWindowFieldList extends FieldItemList {

  public function __construct(private readonly int $timestamp, private readonly bool $empty = FALSE) {}

  /**
   * {@inheritdoc}
   */
  public function __get($property_name): mixed {
    return $property_name === 'value' ? $this->timestamp : parent::__get($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return $this->empty;
  }

}

/**
 * Exemption stub that cannot answer, simulating an unavailable consumer.
 */
final class ParticipationEditWindowThrowingExemptionStub implements EditWindowExemptionInterface {

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    throw new \RuntimeException('Exemption service unavailable.');
  }

}

/**
 * Exemption stub returning a fixed result with optional cacheability.
 */
final class ParticipationEditWindowExemptionStub implements EditWindowExemptionInterface {

  /**
   * Optional cacheability to merge into the exemption result.
   *
   * @var array{cacheContexts: string[], cacheTags: string[]}|null
   */
  private ?array $cacheability = NULL;

  public function __construct(private readonly AccessResultInterface $result) {}

  /**
   * Declares cacheability to merge into the exemption result.
   */
  public function withCacheability(array $contexts, array $tags): self {
    $this->cacheability = ['cacheContexts' => $contexts, 'cacheTags' => $tags];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    if ($this->cacheability === NULL) {
      return $this->result;
    }
    return $this->result
      ->addCacheContexts($this->cacheability['cacheContexts'])
      ->addCacheTags($this->cacheability['cacheTags']);
  }

}
