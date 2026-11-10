<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Analytics;

use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\aculta_portal\Statistics\MetricProviderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Calcula indicadores do Fórum público diretamente nas entidades oficiais.
 *
 * Nenhum identificador de pessoa, texto ou IP é gravado: o resultado é um
 * inteiro agregado. Escopo, visibilidade e Group são aplicados antes da
 * contagem; qualquer impossibilidade de provar a publicidade devolve NULL em
 * vez de um número enganoso.
 *
 * Semântica da primeira resposta: primeira resposta pública ainda existente,
 * de autoria diferente da pessoa que abriu o tópico, em Comments legados ou
 * ForumPosts publicados. O retrato usa tópicos criados nos últimos 365 dias.
 */
final class ForumMetricsProvider implements MetricProviderInterface {

  /**
   * Janela de coorte da primeira resposta, em dias.
   */
  private const RESPONSE_COHORT_DAYS = 365;

  public function __construct(
    private readonly Connection $database,
    private readonly ForumAnalyticsScopeResolver $scopeResolver,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function provides(): array {
    return [
      'forum.topics.published_current',
      'forum.replies.published_current',
      'forum.categories.active',
      'forum.topics.unanswered',
      'forum.participants.engaged_7d',
      'forum.participants.engaged_30d',
      'forum.participants.new_contributors',
      'forum.response.first_median_minutes',
      'forum.response.first_p90_minutes',
      'forum.response.unanswered_over_72h',
      'forum.reactions.active',
      'forum.moderation.posts_pending',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function value(string $metric, int $day): ?int {
    $windowEnd = ($day + 1) * 86400;
    return match ($metric) {
      'forum.topics.published_current' => $this->countPublicTopics(),
      'forum.replies.published_current' => $this->countPublicReplies(),
      'forum.categories.active' => $this->countActiveCategories(),
      'forum.topics.unanswered' => $this->countUnanswered(NULL),
      'forum.response.unanswered_over_72h' => $this->countUnanswered(
        min($this->time->getRequestTime(), $windowEnd) - 72 * 3600,
      ),
      'forum.participants.engaged_7d' => $this->countEngaged($windowEnd - 7 * 86400, $windowEnd),
      'forum.participants.engaged_30d' => $this->countEngaged($windowEnd - 30 * 86400, $windowEnd),
      'forum.participants.new_contributors' => $this->countNewContributors($day * 86400, $windowEnd),
      'forum.response.first_median_minutes' => $this->responseDistribution($windowEnd - self::RESPONSE_COHORT_DAYS * 86400, $windowEnd, 50),
      'forum.response.first_p90_minutes' => $this->responseDistribution($windowEnd - self::RESPONSE_COHORT_DAYS * 86400, $windowEnd, 90),
      'forum.reactions.active' => $this->countActiveReactions(),
      'forum.moderation.posts_pending' => $this->countPendingPosts(),
      default => NULL,
    };
  }

  /**
   * Tópicos públicos publicados no estoque atual.
   */
  private function countPublicTopics(): ?int {
    $query = $this->database->select('node_field_data', 'n');
    if (!$this->scopeResolver->applyPublicTopicConditions($query, 'n')) {
      return NULL;
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Respostas públicas publicadas, somando Comments legados e ForumPosts.
   */
  private function countPublicReplies(): ?int {
    $schema = $this->database->schema();
    $total = 0;

    if ($schema->tableExists('comment_field_data')) {
      $comments = $this->database->select('comment_field_data', 'c');
      $comments->join('node_field_data', 'n', 'n.nid = c.entity_id');
      $comments->condition('c.status', 1);
      $comments->condition('c.comment_type', 'aculta_discussion_post');
      $comments->condition('c.default_langcode', 1);
      if (!$this->scopeResolver->applyPublicTopicConditions($comments, 'n')) {
        return NULL;
      }
      $total += (int) $comments->countQuery()->execute()->fetchField();
    }

    if ($schema->tableExists('aculta_forum_post_field_data')) {
      $posts = $this->database->select('aculta_forum_post_field_data', 'p');
      $posts->join('node_field_data', 'n', 'n.nid = p.topic');
      $posts->condition('p.status', 1);
      $posts->condition('p.default_langcode', 1);
      if (!$this->scopeResolver->applyPublicTopicConditions($posts, 'n')) {
        return NULL;
      }
      $total += (int) $posts->countQuery()->execute()->fetchField();
    }

    return $total;
  }

  /**
   * Categorias públicas que possuem ao menos um tópico público publicado.
   */
  private function countActiveCategories(): ?int {
    $schema = $this->database->schema();
    if (!$schema->tableExists('node__field_aculta_categories')) {
      return NULL;
    }
    $query = $this->database->select('node__field_aculta_categories', 'nc');
    $query->join('node_field_data', 'n', 'n.nid = nc.entity_id');
    $query->addField('nc', 'field_aculta_categories_target_id', 'tid');
    $query->distinct();
    if (!$this->scopeResolver->applyPublicTopicConditions($query, 'n')) {
      return NULL;
    }
    $restricted = $this->scopeResolver->restrictedCategoryIds();
    if ($restricted === NULL) {
      return NULL;
    }
    if ($restricted !== []) {
      $query->condition('nc.field_aculta_categories_target_id', $restricted, 'NOT IN');
    }
    return count($query->execute()->fetchCol());
  }

  /**
   * Tópicos públicos sem resposta de outra pessoa.
   *
   * @param int|null $createdBefore
   *   Quando informado, considera apenas tópicos criados até este instante.
   */
  private function countUnanswered(?int $createdBefore): ?int {
    $query = $this->database->select('node_field_data', 'n');
    if (!$this->scopeResolver->applyPublicTopicConditions($query, 'n')) {
      return NULL;
    }
    if ($createdBefore !== NULL) {
      $query->condition('n.created', $createdBefore, '<=');
    }

    $schema = $this->database->schema();
    if ($schema->tableExists('comment_field_data')) {
      $replied = $this->database->select('comment_field_data', 'c');
      $replied->join('node_field_data', 'cn', 'cn.nid = c.entity_id');
      $replied->fields('c', ['entity_id']);
      $replied->condition('c.status', 1);
      $replied->condition('c.comment_type', 'aculta_discussion_post');
      $replied->where('c.uid <> cn.uid');
      $query->condition('n.nid', $replied, 'NOT IN');
    }
    if ($schema->tableExists('aculta_forum_post_field_data')) {
      $replied = $this->database->select('aculta_forum_post_field_data', 'p');
      $replied->join('node_field_data', 'pn', 'pn.nid = p.topic');
      $replied->fields('p', ['topic']);
      $replied->condition('p.status', 1);
      $replied->where('p.uid <> pn.uid');
      $query->condition('n.nid', $replied, 'NOT IN');
    }

    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Pessoas distintas com contribuição pública na janela.
   */
  private function countEngaged(int $from, int $to): ?int {
    $uids = [];
    foreach ($this->contributionQueries($from, $to) as $query) {
      if ($query === NULL) {
        return NULL;
      }
      foreach ($query->execute()->fetchCol() as $uid) {
        $uid = (int) $uid;
        if ($uid > 0) {
          $uids[$uid] = TRUE;
        }
      }
    }
    return count($uids);
  }

  /**
   * Pessoas cuja primeira contribuição pública ocorreu no dia.
   */
  private function countNewContributors(int $dayStart, int $dayEnd): ?int {
    $first = [];
    foreach ($this->firstContributionQueries() as $query) {
      if ($query === NULL) {
        return NULL;
      }
      foreach ($query->execute() as $row) {
        $uid = (int) $row->uid;
        $created = (int) $row->first_created;
        if ($uid > 0) {
          $first[$uid] = isset($first[$uid]) ? min($first[$uid], $created) : $created;
        }
      }
    }
    $count = 0;
    foreach ($first as $created) {
      if ($created >= $dayStart && $created < $dayEnd) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Mediana (50) ou percentil 90 do tempo até a primeira resposta.
   */
  private function responseDistribution(int $from, int $to, int $percentile): ?int {
    $topics = $this->database->select('node_field_data', 'n');
    $topics->fields('n', ['nid', 'created', 'uid']);
    $topics->condition('n.created', $from, '>=');
    $topics->condition('n.created', $to, '<');
    if (!$this->scopeResolver->applyPublicTopicConditions($topics, 'n')) {
      return NULL;
    }
    $topicRows = [];
    foreach ($topics->execute() as $row) {
      $topicRows[(int) $row->nid] = $row;
    }

    $firstReplies = [];
    foreach ($this->firstReplyQueries($from, $to) as $query) {
      if ($query === NULL) {
        continue;
      }
      foreach ($query->execute() as $row) {
        $topicId = (int) $row->topic;
        $created = (int) $row->first_reply;
        $firstReplies[$topicId] = isset($firstReplies[$topicId])
          ? min($firstReplies[$topicId], $created)
          : $created;
      }
    }

    $minutes = [];
    foreach ($topicRows as $topicId => $row) {
      $reply = $firstReplies[(int) $topicId] ?? NULL;
      if ($reply === NULL) {
        continue;
      }
      $diff = (int) $reply - (int) $row->created;
      if ($diff >= 0) {
        $minutes[] = intdiv($diff, 60);
      }
    }
    if ($minutes === []) {
      return NULL;
    }
    sort($minutes);
    $index = (int) ceil(count($minutes) * $percentile / 100) - 1;
    $index = max(0, min($index, count($minutes) - 1));
    return $minutes[$index];
  }

  /**
   * Reações vigentes em conteúdo comprovadamente público.
   */
  private function countActiveReactions(): ?int {
    $schema = $this->database->schema();
    if (!$schema->tableExists(DiscussionReactionManager::TABLE_REACTIONS)) {
      return NULL;
    }
    $total = 0;

    $nodes = $this->database->select(DiscussionReactionManager::TABLE_REACTIONS, 'r');
    $nodes->join('node_field_data', 'n', 'n.nid = r.entity_id');
    $nodes->condition('r.entity_type', 'node');
    $nodes->condition('r.is_public_context', 1);
    if (!$this->scopeResolver->applyPublicTopicConditions($nodes, 'n')) {
      return NULL;
    }
    $total += (int) $nodes->countQuery()->execute()->fetchField();

    if ($schema->tableExists('comment_field_data')) {
      $comments = $this->database->select(DiscussionReactionManager::TABLE_REACTIONS, 'r');
      $comments->join('comment_field_data', 'c', 'c.cid = r.entity_id');
      $comments->join('node_field_data', 'n', 'n.nid = c.entity_id');
      $comments->condition('r.entity_type', 'comment');
      $comments->condition('r.is_public_context', 1);
      $comments->condition('c.status', 1);
      $comments->condition('c.default_langcode', 1);
      if (!$this->scopeResolver->applyPublicTopicConditions($comments, 'n')) {
        return NULL;
      }
      $total += (int) $comments->countQuery()->execute()->fetchField();
    }

    if ($schema->tableExists('aculta_forum_post_field_data')) {
      $posts = $this->database->select(DiscussionReactionManager::TABLE_REACTIONS, 'r');
      $posts->join('aculta_forum_post_field_data', 'p', 'p.id = r.entity_id');
      $posts->join('node_field_data', 'n', 'n.nid = p.topic');
      $posts->condition('r.entity_type', 'aculta_forum_post');
      $posts->condition('r.is_public_context', 1);
      $posts->condition('p.status', 1);
      $posts->condition('p.default_langcode', 1);
      if (!$this->scopeResolver->applyPublicTopicConditions($posts, 'n')) {
        return NULL;
      }
      $total += (int) $posts->countQuery()->execute()->fetchField();
    }

    return $total;
  }

  /**
   * Posts revisionáveis não publicados, aguardando moderação.
   */
  private function countPendingPosts(): ?int {
    $schema = $this->database->schema();
    if (!$schema->tableExists('aculta_forum_post_field_data')) {
      return NULL;
    }
    $query = $this->database->select('aculta_forum_post_field_data', 'p');
    $query->condition('p.status', 0);
    $query->condition('p.default_langcode', 1);
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Consultas distintas de UID por fonte de contribuição pública.
   *
   * @return array<int, \Drupal\Core\Database\Query\SelectInterface|null>
   *   Consultas por fonte; NULL quando a publicidade não pode ser provada.
   */
  private function contributionQueries(int $from, int $to): array {
    $schema = $this->database->schema();
    $queries = [];

    $topics = $this->database->select('node_field_data', 'n');
    $topics->addField('n', 'uid');
    $topics->distinct();
    $topics->condition('n.created', $from, '>=');
    $topics->condition('n.created', $to, '<');
    $queries[] = $this->scopeResolver->applyPublicTopicConditions($topics, 'n') ? $topics : NULL;

    if ($schema->tableExists('comment_field_data')) {
      $comments = $this->database->select('comment_field_data', 'c');
      $comments->join('node_field_data', 'n', 'n.nid = c.entity_id');
      $comments->addField('c', 'uid');
      $comments->distinct();
      $comments->condition('c.status', 1);
      $comments->condition('c.comment_type', 'aculta_discussion_post');
      $comments->condition('c.default_langcode', 1);
      $comments->condition('c.created', $from, '>=');
      $comments->condition('c.created', $to, '<');
      $queries[] = $this->scopeResolver->applyPublicTopicConditions($comments, 'n') ? $comments : NULL;
    }

    if ($schema->tableExists('aculta_forum_post_field_data')) {
      $posts = $this->database->select('aculta_forum_post_field_data', 'p');
      $posts->join('node_field_data', 'n', 'n.nid = p.topic');
      $posts->addField('p', 'uid');
      $posts->distinct();
      $posts->condition('p.status', 1);
      $posts->condition('p.default_langcode', 1);
      $posts->condition('p.created', $from, '>=');
      $posts->condition('p.created', $to, '<');
      $queries[] = $this->scopeResolver->applyPublicTopicConditions($posts, 'n') ? $posts : NULL;
    }

    return $queries;
  }

  /**
   * Primeira contribuição pública conhecida de cada UID, por fonte.
   *
   * @return array<int, \Drupal\Core\Database\Query\SelectInterface|null>
   *   Consultas com `uid` e `first_created`.
   */
  private function firstContributionQueries(): array {
    $schema = $this->database->schema();
    $queries = [];

    $topics = $this->database->select('node_field_data', 'n');
    $topics->addField('n', 'uid');
    $topics->addExpression('MIN(n.created)', 'first_created');
    $topics->groupBy('n.uid');
    $queries[] = $this->scopeResolver->applyPublicTopicConditions($topics, 'n') ? $topics : NULL;

    if ($schema->tableExists('comment_field_data')) {
      $comments = $this->database->select('comment_field_data', 'c');
      $comments->join('node_field_data', 'n', 'n.nid = c.entity_id');
      $comments->addField('c', 'uid');
      $comments->addExpression('MIN(c.created)', 'first_created');
      $comments->condition('c.status', 1);
      $comments->condition('c.comment_type', 'aculta_discussion_post');
      $comments->condition('c.default_langcode', 1);
      $comments->groupBy('c.uid');
      $queries[] = $this->scopeResolver->applyPublicTopicConditions($comments, 'n') ? $comments : NULL;
    }

    if ($schema->tableExists('aculta_forum_post_field_data')) {
      $posts = $this->database->select('aculta_forum_post_field_data', 'p');
      $posts->join('node_field_data', 'n', 'n.nid = p.topic');
      $posts->addField('p', 'uid');
      $posts->addExpression('MIN(p.created)', 'first_created');
      $posts->condition('p.status', 1);
      $posts->condition('p.default_langcode', 1);
      $posts->groupBy('p.uid');
      $queries[] = $this->scopeResolver->applyPublicTopicConditions($posts, 'n') ? $posts : NULL;
    }

    return $queries;
  }

  /**
   * Primeira resposta pública por outro autor, por tópico e fonte.
   *
   * @return array<int, \Drupal\Core\Database\Query\SelectInterface|null>
   *   Consultas com `topic` e `first_reply`.
   */
  private function firstReplyQueries(int $from, int $to): array {
    $schema = $this->database->schema();
    $queries = [];

    if ($schema->tableExists('comment_field_data')) {
      $comments = $this->database->select('comment_field_data', 'c');
      $comments->join('node_field_data', 'n', 'n.nid = c.entity_id');
      $comments->addField('c', 'entity_id', 'topic');
      $comments->addExpression('MIN(c.created)', 'first_reply');
      $comments->condition('c.status', 1);
      $comments->condition('c.comment_type', 'aculta_discussion_post');
      $comments->condition('c.default_langcode', 1);
      $comments->condition('n.created', $from, '>=');
      $comments->condition('n.created', $to, '<');
      $comments->where('c.uid <> n.uid');
      $comments->groupBy('c.entity_id');
      $queries[] = $this->scopeResolver->applyPublicTopicConditions($comments, 'n') ? $comments : NULL;
    }

    if ($schema->tableExists('aculta_forum_post_field_data')) {
      $posts = $this->database->select('aculta_forum_post_field_data', 'p');
      $posts->join('node_field_data', 'n', 'n.nid = p.topic');
      $posts->addField('p', 'topic');
      $posts->addExpression('MIN(p.created)', 'first_reply');
      $posts->condition('p.status', 1);
      $posts->condition('p.default_langcode', 1);
      $posts->condition('n.created', $from, '>=');
      $posts->condition('n.created', $to, '<');
      $posts->where('p.uid <> n.uid');
      $posts->groupBy('p.topic');
      $queries[] = $this->scopeResolver->applyPublicTopicConditions($posts, 'n') ? $posts : NULL;
    }

    return $queries;
  }

}
