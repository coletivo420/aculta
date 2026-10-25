<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Plugin\views\field\ForumCategoryTopicCount;
use Drupal\aculta_forum_counter_test\Hook\CounterGrantsTestHooks;
use Drupal\Core\Database\Connection;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\ResultRow;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves a category counter over node relationships respects node grants.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumCategoryCounterAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'taxonomy',
    'views',
    'aculta_forum_counter_test',
  ];

  /**
   * Public category term.
   */
  private Term $publicCategory;

  /**
   * Restricted category term.
   */
  private Term $restrictedCategory;

  /**
   * Account that receives the restricted grant.
   */
  private AccountInterface $member;

  /**
   * Topic that carries the restricted grant.
   */
  private Node $restrictedTopic;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('language.default')->set(new Language([
      'id' => 'pt-br',
      'name' => 'Português (Brasil)',
      'direction' => LanguageInterface::DIRECTION_LTR,
      'weight' => 0,
    ]));
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'taxonomy', 'field', 'filter', 'text']);

    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    Role::load(RoleInterface::AUTHENTICATED_ID)
      ->grantPermission('access content')
      ->grantPermission('view restricted forum counter topics')
      ->save();

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    Vocabulary::create(['vid' => 'aculta_forum_categories', 'name' => 'Categorias'])->save();
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
      'label' => 'Categoria',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['aculta_forum_categories' => 'aculta_forum_categories'],
        ],
      ],
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->publicCategory = Term::create([
      'vid' => 'aculta_forum_categories',
      'name' => 'Pública',
    ]);
    $this->publicCategory->save();
    $this->restrictedCategory = Term::create([
      'vid' => 'aculta_forum_categories',
      'name' => 'Restrita',
    ]);
    $this->restrictedCategory->save();

    // Keep the member out of uid 1, which bypasses node access checks.
    User::create(['name' => 'autor-base', 'status' => 1])->save();
    $member = User::create(['name' => 'membro', 'status' => 1]);
    $member->save();
    $this->member = $member;

    $this->createTopic('Tópico público', (int) $this->publicCategory->id());
    $this->restrictedTopic = $this->createTopic('Tópico restrito', (int) $this->restrictedCategory->id(), TRUE);
    $this->counterView();
  }

  /**
   * The aggregate count hides restricted topics from anonymous visitors.
   */
  public function testCategoryCounterRespectsNodeGrants(): void {
    $this->container->get('state')->set(CounterGrantsTestHooks::ADD_TAG_STATE, TRUE);
    self::assertGreaterThan(1, (int) $this->member->id());
    $anonymous = $this->counts(new AnonymousUserSession());
    self::assertSame(['Pública' => 1], $anonymous);

    $member = $this->counts($this->member);
    self::assertSame(['Pública' => 1, 'Restrita' => 1], $member);
  }

  /**
   * Revoking the grant changes the member count on the next execution.
   */
  public function testRevokedGrantImmediatelyHidesRestrictedCount(): void {
    $this->container->get('state')->set(CounterGrantsTestHooks::ADD_TAG_STATE, TRUE);
    self::assertSame(['Pública' => 1, 'Restrita' => 1], $this->directCounts());

    // Removing the topic grant is what a membership revocation does.
    $database = $this->container->get('database');
    $database->delete('node_access')
      ->condition('nid', $this->restrictedTopic->id())
      ->execute();
    self::assertSame(['Pública' => 1], $this->directCounts());

    $database->insert('node_access')->fields([
      'nid' => $this->restrictedTopic->id(),
      'langcode' => 'pt-br',
      'fallback' => 1,
      'realm' => CounterGrantsTestHooks::REALM,
      'gid' => 1,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ])->execute();
    self::assertSame(['Pública' => 1, 'Restrita' => 1], $this->directCounts());
  }

  /**
   * Documents the Core gap: EntityReverse alone does not filter node grants.
   */
  public function testReverseRelationshipWithoutTagLeaksRestrictedCount(): void {
    $this->container->get('state')->set(CounterGrantsTestHooks::ADD_TAG_STATE, FALSE);
    $anonymous = $this->counts(new AnonymousUserSession());
    self::assertSame(['Pública' => 1, 'Restrita' => 1], $anonymous);
  }

  /**
   * The field plugin counts and renders only topics the account may view.
   */
  public function testFieldPluginRendersAccessAwareCounts(): void {
    $plugin = new ForumCategoryTopicCount(
      [],
      'aculta_forum_category_topic_count',
      [],
      $this->container->get('database'),
    );
    $plugin->aliases['tid'] = 'tid';
    $rows = [
      new ResultRow(['tid' => (int) $this->publicCategory->id()]),
      new ResultRow(['tid' => (int) $this->restrictedCategory->id()]),
    ];

    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $plugin->preRender($rows);
    self::assertSame('1 tópico', (string) $plugin->render($rows[0])['#value']);
    self::assertSame('Nenhum tópico', (string) $plugin->render($rows[1])['#value']);

    $this->container->get('current_user')->setAccount($this->member);
    $plugin->preRender($rows);
    self::assertSame('1 tópico', (string) $plugin->render($rows[1])['#value']);
  }

  /**
   * Creates a published topic with an explicit node access record.
   */
  private function createTopic(string $title, int $term_id, bool $restricted = FALSE): Node {
    $node = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'uid' => 1,
      'field_aculta_categories' => ['target_id' => $term_id],
    ]);
    $node->save();
    $database = $this->container->get('database');
    self::assertInstanceOf(Connection::class, $database);
    // Core records a default all:0 grant on insert; replace it with the grant
    // this scenario needs so anonymous access is decided only by the realm.
    $database->delete('node_access')->condition('nid', $node->id())->execute();
    $database->insert('node_access')->fields([
      'nid' => $node->id(),
      'langcode' => 'pt-br',
      'fallback' => 1,
      'realm' => $restricted ? CounterGrantsTestHooks::REALM : 'all',
      'gid' => $restricted ? 1 : 0,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ])->execute();
    return $node;
  }

  /**
   * Builds the category counter View used by the page.
   */
  private function counterView(): void {
    View::create([
      'id' => 'test_forum_category_counter',
      'label' => 'Category counter',
      'base_table' => 'taxonomy_term_field_data',
      'base_field' => 'tid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'group_by' => TRUE,
            'query' => [
              'type' => 'views_query',
              'options' => ['disable_sql_rewrite' => FALSE],
            ],
            'pager' => ['type' => 'none', 'options' => ['offset' => 0]],
            'style' => ['type' => 'default', 'options' => []],
            'row' => ['type' => 'fields', 'options' => []],
            'relationships' => [
              'node' => [
                'id' => 'reverse__node__field_aculta_categories',
                'table' => 'taxonomy_term_field_data',
                'field' => 'reverse__node__field_aculta_categories',
                'relationship' => 'none',
                'plugin_id' => 'entity_reverse',
              ],
            ],
            'fields' => [
              'name' => [
                'id' => 'name',
                'table' => 'taxonomy_term_field_data',
                'field' => 'name',
                'relationship' => 'none',
                'group_type' => 'group',
                'plugin_id' => 'field',
              ],
              'topic_count' => [
                'id' => 'nid',
                'table' => 'node_field_data',
                'field' => 'nid',
                'relationship' => 'node',
                'group_type' => 'count',
                'plugin_id' => 'numeric',
              ],
            ],
            'filters' => [
              'type' => [
                'id' => 'type',
                'table' => 'node_field_data',
                'field' => 'type',
                'relationship' => 'node',
                'group_type' => 'group',
                'plugin_id' => 'bundle',
                'entity_type' => 'node',
                'entity_field' => 'type',
                'value' => ['aculta_forum_topic' => 'aculta_forum_topic'],
              ],
              'status' => [
                'id' => 'status',
                'table' => 'node_field_data',
                'field' => 'status',
                'relationship' => 'node',
                'group_type' => 'group',
                'plugin_id' => 'boolean',
                'value' => 1,
              ],
            ],
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Runs the production counter query as the fixture member.
   *
   * @return array<string, int>
   *   Count of visible topics per category name.
   */
  private function directCounts(): array {
    $this->container->get('current_user')->setAccount($this->member);
    $field = new ForumCategoryTopicCount(
      [],
      'aculta_forum_category_topic_count',
      [],
      $this->container->get('database'),
    );
    $method = new \ReflectionMethod($field, 'countByTerm');
    $method->setAccessible(TRUE);
    $raw = $method->invoke($field, [(int) $this->publicCategory->id(), (int) $this->restrictedCategory->id()]);
    $names = [
      (int) $this->publicCategory->id() => 'Pública',
      (int) $this->restrictedCategory->id() => 'Restrita',
    ];
    $counts = [];
    foreach ($raw as $tid => $count) {
      $counts[$names[$tid]] = $count;
    }
    ksort($counts);
    return array_filter($counts);
  }

  /**
   * Executes the counter View as the given account and maps term to count.
   *
   * @return array<string, int>
   *   Count of visible topics per category name.
   */
  private function counts(AccountInterface $account): array {
    $this->container->get('current_user')->setAccount($account);
    $view = Views::getView('test_forum_category_counter');
    self::assertNotFalse($view);
    $view->execute();
    $counts = [];
    foreach ($view->result as $row) {
      // Views derives aliases from table and field, not from the config key.
      $counts[(string) $row->taxonomy_term_field_data_name]
        = (int) $row->field_aculta_categories_taxonomy_term_field_data_nid;
    }
    ksort($counts);
    return $counts;
  }

}
