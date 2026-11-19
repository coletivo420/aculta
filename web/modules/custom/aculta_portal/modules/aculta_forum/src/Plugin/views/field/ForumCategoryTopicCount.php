<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Component\Utility\Html;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\Markup;
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
  protected array $counts = [];

  /**
   * Constructs a ForumCategoryTopicCount object.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
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
    if ($this->view !== NULL) {
      if (!isset($this->view->element['#cache']) || !is_array($this->view->element['#cache'])) {
        $this->view->element['#cache'] = [];
      }
      if (!isset($this->view->element['#cache']['contexts']) || !is_array($this->view->element['#cache']['contexts'])) {
        $this->view->element['#cache']['contexts'] = [];
      }
      $this->view->element['#cache']['contexts'][] = 'user.node_grants:view';
    }
    $term_ids = [];
    foreach ($values as $row) {
      $val = $this->getValue($row, 'tid');
      if (is_numeric($val)) {
        $term_ids[] = (int) $val;
      }
    }
    $this->counts = $this->countByTerm(array_values(array_unique(array_filter($term_ids))));
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): MarkupInterface|string {
    $val = $this->getValue($values, 'tid');
    $term_id = is_numeric($val) ? (int) $val : 0;
    $count = $this->counts[$term_id] ?? 0;
    // Portuguese plural rules treat zero as singular, so the zero case needs
    // an explicit string instead of formatPlural().
    $label = $count === 0
      ? $this->t('Nenhum tópico')
      : $this->formatPlural($count, '1 tópico', '@count tópicos');
    return Markup::create('<span data-portal-ui="forum-category-count">' . Html::escape((string) $label) . '</span>');
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
    $statement = $query->execute();
    if ($statement) {
      foreach ($statement as $row) {
        if (is_object($row) && isset($row->tid, $row->topic_count) && is_numeric($row->tid) && is_numeric($row->topic_count)) {
          $counts[(int) $row->tid] = (int) $row->topic_count;
        }
      }
    }
    return $counts;
  }

}
