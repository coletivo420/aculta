<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the number of published topics visible in each category.
 *
 * The counter starts from the node field table, so the node_access tag added
 * to this query filters restricted topics before counting. Viewing the same
 * data through the reverse relationship of the category View would not add
 * that tag, because entity_reverse does not copy its access tag.
 */
#[ViewsField('aculta_forum_category_topic_count')]
final class ForumCategoryTopicCount extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Counts per term ID, filled once per page render.
   *
   * @var array<int, int>
   */
  private array $counts = [];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->additional_fields['tid'] = [
      'table' => 'taxonomy_term_field_data',
      'field' => 'tid',
    ];
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    $term_ids = [];
    foreach ($values as $row) {
      $term_ids[] = (int) $this->getValue($row, 'tid');
    }
    $this->counts = $this->countByTerm(array_values(array_unique(array_filter($term_ids))));
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $term_id = (int) $this->getValue($values, 'tid');
    $count = $this->counts[$term_id] ?? 0;
    // Portuguese plural rules treat zero as singular, so the zero case needs
    // an explicit string instead of formatPlural().
    $label = $count === 0
      ? $this->t('Nenhum tópico')
      : $this->formatPlural($count, '1 tópico', '@count tópicos');
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => ['data-portal-ui' => 'forum-category-count'],
      '#value' => $label,
      '#cache' => [
        'contexts' => ['user.node_grants:view'],
      ],
    ];
  }

  /**
   * Counts visible topics per category in one access-aware query.
   *
   * @param int[] $term_ids
   *   Category term IDs from the current page.
   *
   * @return array<int, int>
   *   Topic count keyed by term ID.
   */
  private function countByTerm(array $term_ids): array {
    if ($term_ids === []) {
      return [];
    }
    $query = $this->database->select('node__field_aculta_categories', 'category');
    $query->join('node_field_data', 'node', 'node.nid = category.entity_id');
    $query->addField('category', 'field_aculta_categories_target_id', 'tid');
    $query->addExpression('COUNT(DISTINCT category.entity_id)', 'topic_count');
    $query->condition('category.deleted', 0);
    $query->condition('category.bundle', 'aculta_forum_topic');
    $query->condition('category.field_aculta_categories_target_id', $term_ids, 'IN');
    $query->condition('node.status', 1);
    $query->condition('node.type', 'aculta_forum_topic');
    $query->groupBy('category.field_aculta_categories_target_id');
    $query->addTag('node_access');
    $query->addMetaData('base_table', 'node_field_data');
    $counts = [];
    foreach ($query->execute() as $row) {
      $counts[(int) $row->tid] = (int) $row->topic_count;
    }
    return $counts;
  }

}
