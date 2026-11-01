<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Removes Growroom archive comments from the Forum moderation queues.
 */
final class ForumHistoricModerationQueueHooks {

  private const VIEW_ID = 'aculta_forum_admin_comments';

  private const MODERATION_DISPLAYS = ['page_pending', 'page_rejected'];

  public function __construct(
    #[Autowire(service: 'database')]
    private readonly Connection $database,
  ) {}

  /**
   * Excludes comments hosted by an immutable historical topic.
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    if ($view->id() !== self::VIEW_ID
      || !in_array($view->current_display, self::MODERATION_DISPLAYS, TRUE)
      || !$query instanceof Sql) {
      return;
    }

    // Views keys aliased relationship tables by their actual alias (for
    // example, node_field_data_comment_field_data), not by the base table
    // name. Resolve the configured Node relationship first, then validate
    // that it points to the expected table before using its SQL alias.
    $nodeRelationship = $view->relationship['node'] ?? NULL;
    $relationshipAlias = $nodeRelationship->alias ?? NULL;
    $hostTable = is_string($relationshipAlias)
      ? $query->getTableInfo($relationshipAlias)
      : NULL;
    $hostAlias = $hostTable['alias'] ?? NULL;
    // The Comment base alias is normally comment_field_data, but a View
    // override could alias it. Validate it too instead of hardcoding an alias
    // that would turn the guard into a PDOException.
    $commentTable = is_string($relationshipAlias)
      ? $query->getTableInfo('comment_field_data')
      : NULL;
    $commentAlias = $commentTable['alias'] ?? NULL;
    if (($hostTable['table'] ?? NULL) !== 'node_field_data'
      || !is_string($hostAlias)
      || !preg_match('/^[A-Za-z0-9_]+$/', $hostAlias)
      || ($commentTable['table'] ?? NULL) !== 'comment_field_data'
      || !is_string($commentAlias)
      || !preg_match('/^[A-Za-z0-9_]+$/', $commentAlias)
      || !$this->database->schema()->tableExists('node__field_autor_legado')
      || !$this->database->schema()->tableExists('comment__field_autor_legado')) {
      // A changed View must fail closed rather than expose archive comments.
      $query->addWhereExpression(0, '1 = 0');
      return;
    }

    $query->addWhereExpression(0, 'NOT EXISTS (SELECT 1 FROM {node__field_autor_legado} historical_topic WHERE historical_topic.entity_id = ' . $hostAlias . '.nid AND historical_topic.deleted = 0 AND historical_topic.field_autor_legado_target_id IS NOT NULL)');
    $query->addWhereExpression(0, 'NOT EXISTS (SELECT 1 FROM {comment__field_autor_legado} historical_comment WHERE historical_comment.entity_id = ' . $commentAlias . '.cid AND historical_comment.deleted = 0 AND historical_comment.field_autor_legado_target_id IS NOT NULL)');
  }

}
