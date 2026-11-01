<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Plugin\views\filter\ForumUnreadContent;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\views\Plugin\views\query\Sql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

if (!defined('HISTORY_READ_LIMIT')) {
  define('HISTORY_READ_LIMIT', 1700000000);
}

/**
 * Verifies the unread filter fails closed and includes ForumPost activity.
 */
#[CoversClass(ForumUnreadContent::class)]
#[Group('aculta_forum')]
final class ForumUnreadContentFilterTest extends TestCase {

  /**
   * Anonymous visitors get a constant false condition.
   */
  public function testAnonymousGetsClosedCondition(): void {
    $captured = [];
    $query = $this->query($captured);
    $filter = $this->filter(new AnonymousUserSession(), $query);

    $filter->query();

    self::assertSame([[0, '1 = 0', []]], $captured);
  }

  /**
   * Members get the History, topic, Comment and ForumPost conditions.
   */
  public function testMemberGetsUnreadConditions(): void {
    $captured = [];
    $query = $this->query($captured);
    $filter = $this->filter($this->createMock(AccountInterface::class), $query);

    $filter->query();

    self::assertCount(1, $captured);
    [, $expression, $arguments] = $captured[0];
    self::assertStringContainsString('history_alias.timestamp IS NULL', $expression);
    self::assertStringContainsString('node_alias.changed', $expression);
    self::assertStringContainsString('statistics_alias.last_comment_timestamp', $expression);
    self::assertStringContainsString('forum_unread_post.first_published', $expression);
    self::assertSame(HISTORY_READ_LIMIT, $arguments[':forum_history_limit_missing']);
    self::assertSame(HISTORY_READ_LIMIT, $arguments[':forum_history_limit_post']);
  }

  /**
   * Builds the filter over the mocked query plugin.
   */
  private function filter(AccountInterface $account, Sql $query): ForumUnreadContent {
    $filter = new ForumUnreadContent(
      [],
      'aculta_forum_unread_content',
      ['id' => 'aculta_forum_unread_content', 'table' => 'history', 'field' => 'aculta_forum_unread_content'],
      $account,
    );
    $filter->table = 'history';
    $filter->query = $query;
    $filter->options['group'] = 0;
    $filter->relationship = 'none';
    return $filter;
  }

  /**
   * Builds a query plugin that records added where expressions.
   *
   * @param array<int, array{0: int|string, 1: string, 2: array}> $captured
   *   Captured arguments, by reference.
   */
  private function query(array &$captured): Sql {
    $query = $this->createMock(Sql::class);
    $query->method('ensureTable')->willReturnCallback(
      static fn (string $table): string => match ($table) {
        'history' => 'history_alias',
        'node_field_data' => 'node_alias',
        'comment_entity_statistics' => 'statistics_alias',
        default => 'other_alias',
      },
    );
    $query->method('addWhereExpression')->willReturnCallback(
      static function ($group, $expression, $arguments = []) use (&$captured): void {
        $captured[] = [$group, $expression, $arguments];
      },
    );
    return $query;
  }

}
