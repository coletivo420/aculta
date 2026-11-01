<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies category target IDs survive indexing and filter Search API results.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCategorySearchKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'search_api',
    'search_api_db',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * The test Search API index.
   */
  private IndexInterface $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'node', 'taxonomy', 'filter', 'text', 'search_api', 'search_api_db']);

    Vocabulary::create(['vid' => 'aculta_forum_categories', 'name' => 'Forum categories'])->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_aculta_categories',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_aculta_categories',
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'label' => 'Forum category',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['aculta_forum_categories' => 'aculta_forum_categories']],
      ],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_search_body',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_search_body',
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'label' => 'Search body',
    ])->save();

    Server::create([
      'id' => 'forum_category_test',
      'name' => 'Forum category test',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
        'min_chars' => 1,
        'matching' => 'words',
        'phrase' => 'bigram',
      ],
      'status' => TRUE,
    ])->save();
    $this->index = Index::create([
      'id' => 'forum_category_test',
      'name' => 'Forum category test',
      'status' => TRUE,
      'server' => 'forum_category_test',
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => ['default' => FALSE, 'selected' => ['aculta_forum_topic']],
          'languages' => ['default' => TRUE, 'selected' => []],
        ],
      ],
      'tracker_settings' => ['default' => []],
      'field_settings' => [
        'title' => [
          'label' => 'Title',
          'type' => 'text',
          'datasource_id' => 'entity:node',
          'property_path' => 'title',
          'boost' => 5.0,
        ],
        'body' => [
          'label' => 'Body',
          'type' => 'text',
          'datasource_id' => 'entity:node',
          'property_path' => 'field_search_body:value',
        ],
        'forum_category' => [
          'label' => 'Forum category',
          'type' => 'integer',
          'datasource_id' => 'entity:node',
          'property_path' => 'field_aculta_categories:target_id',
        ],
      ],
      'options' => ['index_directly' => FALSE, 'cron_limit' => -1],
    ]);
    $this->index->save();
  }

  /**
   * Category filter matches only Nodes assigned to the selected term ID.
   */
  public function testCategoryTargetIdFiltersIndexedTopics(): void {
    $parent = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Cultivo']);
    $parent->save();
    $child = Term::create([
      'vid' => 'aculta_forum_categories',
      'name' => 'Ambiente externo',
      'parent' => [$parent->id()],
    ]);
    $child->save();
    $other = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Cultura']);
    $other->save();

    $matching = $this->createTopic('Tópico de ambiente externo', (int) $child->id());
    $this->createTopic('Tópico geral de cultivo', (int) $parent->id());
    $this->createTopic('Tópico de cultura', (int) $other->id());
    self::assertSame(3, $this->index->indexItems());

    $result = $this->index->query()->addCondition('forum_category', (int) $child->id())->execute();
    $items = $result->getResultItems();

    self::assertCount(1, $items);
    $item = reset($items);
    self::assertSame((int) $matching->id(), (int) $item->getOriginalObject()->getValue()->id());
  }

  /**
   * Fulltext combines with category filtering and defaults to relevance order.
   */
  public function testFulltextCombinesWithCategoryAndUsesRelevanceByDefault(): void {
    $category = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Cultivo']);
    $category->save();
    $otherCategory = Term::create(['vid' => 'aculta_forum_categories', 'name' => 'Cultura']);
    $otherCategory->save();

    $titleMatch = $this->createTopic('Cultivo medicinal', (int) $category->id());
    $bodyMatch = $this->createTopic('Discussão recente', (int) $category->id(), 'Cultivo medicinal');
    $otherCategoryMatch = $this->createTopic('Cultivo medicinal', (int) $otherCategory->id());
    self::assertSame(3, $this->index->indexItems());

    $combined = $this->index->query()
      ->keys('medicinal')
      ->addCondition('forum_category', (int) $category->id())
      ->execute()
      ->getResultItems();
    $combinedIds = array_map(
      static fn ($item): int => (int) $item->getOriginalObject()->getValue()->id(),
      array_values($combined),
    );
    sort($combinedIds);
    $expectedIds = [(int) $titleMatch->id(), (int) $bodyMatch->id()];
    sort($expectedIds);
    self::assertSame($expectedIds, $combinedIds);

    $unfiltered = $this->index->query()->keys('medicinal')->execute()->getResultItems();
    $first = reset($unfiltered);
    $scores = [];
    foreach ($unfiltered as $item) {
      $nodeId = (int) $item->getOriginalObject()->getValue()->id();
      $scores[$nodeId] = $item->getScore();
    }
    self::assertGreaterThan(
      $scores[(int) $bodyMatch->id()],
      $scores[(int) $titleMatch->id()],
      'A title match with its configured boost scores above a body-only match.',
    );
    self::assertContains(
      (int) $first->getOriginalObject()->getValue()->id(),
      [(int) $titleMatch->id(), (int) $otherCategoryMatch->id()],
      'The default relevance order puts a title match ahead of the body-only result.',
    );
  }

  /**
   * Creates a public forum topic assigned to the provided category term.
   */
  private function createTopic(string $title, int $termId, string $body = ''): Node {
    $values = [
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'field_aculta_categories' => ['target_id' => $termId],
    ];
    if ($body !== '') {
      $values['field_search_body'] = ['value' => $body];
    }
    $node = Node::create($values);
    $node->save();
    return $node;
  }

}
