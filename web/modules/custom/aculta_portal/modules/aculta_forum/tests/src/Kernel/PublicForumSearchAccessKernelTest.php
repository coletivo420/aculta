<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum_counter_test\Hook\CounterGrantsTestHooks;
use Drupal\Core\Database\Connection;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves Search API applies node grants before counting or paging results.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class PublicForumSearchAccessKernelTest extends KernelTestBase {

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
    'search_api',
    'search_api_db',
    'aculta_forum_counter_test',
  ];

  /**
   * Account that receives the restricted realm.
   */
  private AccountInterface $member;

  /**
   * Index over the Forum topics.
   */
  private Index $index;

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
    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'search_api', 'search_api_db']);

    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    Role::load(RoleInterface::AUTHENTICATED_ID)
      ->grantPermission('access content')
      ->grantPermission('view restricted forum counter topics')
      ->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();

    User::create(['name' => 'autor-base', 'status' => 1])->save();
    $member = User::create(['name' => 'membro', 'status' => 1]);
    $member->save();
    $this->member = $member;

    $this->topic('Tópico público', FALSE);
    $this->topic('Tópico restrito', TRUE);

    Server::create([
      'id' => 'test_forum_server',
      'name' => 'Forum test server',
      'backend' => 'search_api_db',
      'backend_config' => ['database' => 'default:default', 'min_chars' => 1],
    ])->save();
    $this->index = Index::create([
      'id' => 'test_forum_search',
      'name' => 'Forum search',
      'server' => 'test_forum_server',
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => [
            'default' => FALSE,
            'selected' => ['aculta_forum_topic' => 'aculta_forum_topic'],
          ],
          'languages' => ['default' => TRUE, 'selected' => []],
        ],
      ],
      'field_settings' => [
        'title' => [
          'label' => 'Title',
          'datasource_id' => 'entity:node',
          'property_path' => 'title',
          'type' => 'text',
        ],
      ],
      'processor_settings' => [
        'content_access' => [
          'weights' => ['preprocess_index' => -30, 'preprocess_query' => -30],
        ],
        'entity_status' => [
          'weights' => ['preprocess_index' => -20],
        ],
      ],
      'tracker_settings' => ['default' => []],
      'options' => ['index_directly' => FALSE],
    ]);
    $this->index->save();
    $this->index->indexItems();
    self::assertGreaterThan(0, $this->index->getTrackerInstance()->getIndexedItemsCount());
  }

  /**
   * Anonymous results exclude the restricted topic before paging.
   */
  public function testAnonymousSearchFiltersBeforePaging(): void {
    $results = $this->search(new AnonymousUserSession(), 1);
    self::assertSame(1, (int) $results->getResultCount());
    self::assertSame(1, count($results->getResultItems()));
  }

  /**
   * The member sees both topics and the total reflects the grant.
   */
  public function testMemberSearchIncludesRestrictedTopic(): void {
    $results = $this->search($this->member, 2);
    self::assertSame(2, (int) $results->getResultCount());
    $items = $results->getResultItems();
    self::assertCount(2, $items);
    $ids = array_map(
      static fn ($item): string => $item->getId(),
      $items,
    );
    self::assertContains('entity:node/' . $this->topicId('Tópico restrito') . ':pt-br', $ids);
  }

  /**
   * Runs one query as the given account.
   */
  private function search(AccountInterface $account, int $limit, int $offset = 0): ResultSetInterface {
    $query = $this->index->query(['limit' => $limit, 'offset' => $offset]);
    $query->setOption('search_api_access_account', $account);
    return $query->execute();
  }

  /**
   * Returns the topic ID by title.
   */
  private function topicId(string $title): int {
    $ids = $this->container->get('entity_type.manager')->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'aculta_forum_topic')
      ->condition('title', $title)
      ->execute();
    return (int) reset($ids);
  }

  /**
   * Creates a published topic with its explicit node access grant.
   */
  private function topic(string $title, bool $restricted): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'uid' => 1,
    ]);
    $topic->save();
    $database = $this->container->get('database');
    self::assertInstanceOf(Connection::class, $database);
    $database->delete('node_access')->condition('nid', $topic->id())->execute();
    $database->insert('node_access')->fields([
      'nid' => $topic->id(),
      'langcode' => 'pt-br',
      'fallback' => 1,
      'realm' => $restricted ? CounterGrantsTestHooks::REALM : 'all',
      'gid' => $restricted ? 1 : 0,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ])->execute();
    return $topic;
  }

}
