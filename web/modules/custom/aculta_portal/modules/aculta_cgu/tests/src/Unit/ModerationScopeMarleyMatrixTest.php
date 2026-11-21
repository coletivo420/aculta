<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Unit;

use Drupal\aculta_cgu\ModerationScope;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises permission isolation for CGU cases shown in Studio Marley.
 *
 * These tests deliberately use grants rather than role names. The frontend
 * never inherits global CGU privileges from unrelated editorial roles.
 */
#[Group('aculta_cgu')]
final class ModerationScopeMarleyMatrixTest extends UnitTestCase {

  /**
   * @param list<string> $grants
   *   Permission names assigned to the mock user.
   */
  #[DataProvider('purposeCases')]
  public function testPurposePermissionMatrix(array $grants, string $purpose, bool $allowed): void {
    self::assertSame($allowed, ModerationScope::allows($this->account($grants), $purpose));
  }

  /**
   * @return iterable<string, array{list<string>, string, bool}>
   *   Isolated scopes and deliberately unrelated permissions.
   */
  public static function purposeCases(): iterable {
    yield 'forum legacy permission' => [['moderar denuncias do forum'], 'forum', TRUE];
    yield 'forum granular permission' => [['review aculta forum reports'], 'forum', TRUE];
    yield 'forum cgu purpose permission' => [['moderar denuncias cgu: forum'], 'forum', TRUE];
    yield 'forum does not grant wiki' => [['review aculta forum reports'], 'wiki', FALSE];
    yield 'forum does not grant revista' => [['moderar denuncias do forum'], 'revista', FALSE];
    yield 'revista does not grant forum' => [['moderar denuncias cgu: revista'], 'forum', FALSE];
    yield 'wiki does not grant forum' => [['moderar denuncias cgu: wiki'], 'forum', FALSE];
    yield 'topic moderation does not grant cgu' => [['moderate aculta forum topics'], 'forum', FALSE];
    yield 'forum post moderation does not grant cgu' => [['moderate aculta forum posts'], 'forum', FALSE];
    yield 'plain authenticated has no cgu' => [[], 'forum', FALSE];
    yield 'global moderation covers forum' => [['moderar denuncias cgu'], 'forum', TRUE];
    yield 'global moderation covers revista' => [['moderar denuncias cgu'], 'revista', TRUE];
    yield 'global moderation covers unknown purpose' => [['moderar denuncias cgu'], 'new_purpose', TRUE];
    yield 'forum scoped cannot moderate unknown purpose' => [['review aculta forum reports'], 'new_purpose', FALSE];
  }

  public function testForumOnlyModeratorSeesExactlyForumInPurposeList(): void {
    self::assertSame(
      ['forum'],
      ModerationScope::purposesFor($this->account(['review aculta forum reports'])),
    );
  }

  public function testGlobalModeratorUsesStablePurposeOrdering(): void {
    self::assertSame(
      ModerationScope::PURPOSES,
      ModerationScope::purposesFor($this->account(['moderar denuncias cgu'])),
    );
  }

  public function testTwoPurposeGrantsNeverInferOtherPurposes(): void {
    self::assertSame(
      ['forum', 'wiki'],
      ModerationScope::purposesFor($this->account([
        'review aculta forum reports',
        'moderar denuncias cgu: wiki',
      ])),
    );
  }

  /**
   * @param list<string> $grants
   *   Granted permissions, independent of role labels.
   */
  private function account(array $grants): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $grants, TRUE),
    );
    return $account;
  }

}
