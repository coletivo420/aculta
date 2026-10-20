<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\ForumHistoricModerationQueueHooks;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests archive exclusion from Forum moderation Views.
 */
#[Group('aculta_forum')]
final class ForumHistoricModerationQueueHooksTest extends UnitTestCase {

  /**
   * Pending and rejected queues exclude topics with a historic author.
   */
  public function testModerationDisplaysExcludeHistoricTopics(): void {
    foreach (['page_pending', 'page_rejected'] as $display) {
      $view = $this->view('aculta_forum_admin_comments', $display);
      $query = $this->createMock(Sql::class);
      $query->method('getTableInfo')->with('node_field_data')->willReturn(['alias' => 'node_host']);
      $conditions = [];
      $query->expects(self::exactly(2))
        ->method('addWhereExpression')
        ->willReturnCallback(static function (int $group, string $expression) use (&$conditions): void {
          $conditions[] = [$group, $expression];
        });

      $this->hook(TRUE)->viewsQueryAlter($view, $query);
      self::assertSame([
        [
          0,
          'NOT EXISTS (SELECT 1 FROM {node__field_autor_legado} historical_topic '
          . 'WHERE historical_topic.entity_id = node_host.nid AND historical_topic.deleted = 0 '
          . 'AND historical_topic.field_autor_legado_target_id IS NOT NULL)',
        ],
        [
          0,
          'NOT EXISTS (SELECT 1 FROM {comment__field_autor_legado} historical_comment '
          . 'WHERE historical_comment.entity_id = comment_field_data.cid AND historical_comment.deleted = 0 '
          . 'AND historical_comment.field_autor_legado_target_id IS NOT NULL)',
        ],
      ], $conditions);
    }
  }

  /**
   * Non-moderation displays are unaffected.
   */
  public function testPublishedDisplayDoesNotAddArchiveFilter(): void {
    $view = $this->view('aculta_forum_admin_comments', 'page_published');
    $query = $this->createMock(Sql::class);
    $query->expects(self::never())->method('addTable');
    $query->expects(self::never())->method('addWhere');
    $query->expects(self::never())->method('addWhereExpression');

    $this->hook(TRUE)->viewsQueryAlter($view, $query);
  }

  /**
   * A missing View relationship fails the moderation queue closed.
   */
  public function testMissingHostRelationshipFailsClosed(): void {
    $view = $this->view('aculta_forum_admin_comments', 'page_pending');
    $query = $this->createMock(Sql::class);
    $query->method('getTableInfo')->with('node_field_data')->willReturn(NULL);
    $query->expects(self::once())->method('addWhereExpression')->with(0, '1 = 0');
    $query->expects(self::never())->method('addTable');

    $this->hook(FALSE)->viewsQueryAlter($view, $query);
  }

  /**
   * Missing marker field tables fail the moderation queue closed.
   */
  public function testMissingAuthorFieldTablesFailClosed(): void {
    $view = $this->view('aculta_forum_admin_comments', 'page_rejected');
    $query = $this->createMock(Sql::class);
    $query->method('getTableInfo')->with('node_field_data')->willReturn(['alias' => 'node_host']);
    $query->expects(self::once())->method('addWhereExpression')->with(0, '1 = 0');

    $this->hook(FALSE)->viewsQueryAlter($view, $query);
  }

  /**
   * Creates the hook with a schema that can identify historical field tables.
   */
  private function hook(bool $tablesExist): ForumHistoricModerationQueueHooks {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn($tablesExist);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    return new ForumHistoricModerationQueueHooks($database);
  }

  /**
   * Creates a view with the requested ID and display.
   */
  private function view(string $id, string $display): ViewExecutable {
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn($id);
    $view->current_display = $display;
    return $view;
  }

}
