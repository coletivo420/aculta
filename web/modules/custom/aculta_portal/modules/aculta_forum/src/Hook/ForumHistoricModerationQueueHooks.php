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

    $hostTable = $query->getTableInfo('node_field_data');
    $hostAlias = $hostTable['alias'] ?? NULL;
    if (!is_string($hostAlias)
      || !preg_match('/^[A-Za-z0-9_]+$/', $hostAlias)
      || !$this->database->schema()->tableExists('node__field_autor_legado')
      || !$this->database->schema()->tableExists('comment__field_autor_legado')) {
      // A changed View must fail closed rather than expose archive comments.
      $query->addWhereExpression(0, '1 = 0');
      return;
    }

    $query->addWhereExpression(0, 'NOT EXISTS (SELECT 1 FROM {node__field_autor_legado} historical_topic WHERE historical_topic.entity_id = ' . $hostAlias . '.nid AND historical_topic.deleted = 0 AND historical_topic.field_autor_legado_target_id IS NOT NULL)');
    $query->addWhereExpression(0, 'NOT EXISTS (SELECT 1 FROM {comment__field_autor_legado} historical_comment WHERE historical_comment.entity_id = comment_field_data.cid AND historical_comment.deleted = 0 AND historical_comment.field_autor_legado_target_id IS NOT NULL)');
  }

}
