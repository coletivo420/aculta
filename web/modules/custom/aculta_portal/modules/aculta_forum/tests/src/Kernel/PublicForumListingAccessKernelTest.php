<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum_counter_test\Hook\CounterGrantsTestHooks;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the public topic listing View filters node grants before paging.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class PublicForumListingAccessKernelTest extends KernelTestBase {

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
    'views',
    'aculta_forum_counter_test',
  ];

  /**
   * Account that receives the restricted realm.
   */
  private AccountInterface $member;

  /**
   * Public topic.
   */
  private Node $publicTopic;

  /**
   * Restricted topic.
   */
  private Node $restrictedTopic;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'views']);

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

    $this->publicTopic = $this->topic('Tópico público', FALSE);
    $this->restrictedTopic = $this->topic('Tópico restrito', TRUE);
    $this->listingView();
  }

  /**
   * An anonymous visitor sees only the public topic, even with a one-row page.
   */
  public function testAnonymousListingFiltersBeforePaging(): void {
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $view = Views::getView('test_public_forum_listing');
    self::assertNotFalse($view);
    $view->execute();

    self::assertCount(1, $view->result);
    self::assertSame((int) $this->publicTopic->id(), (int) $view->result[0]->nid);
    self::assertSame(1, (int) $view->total_rows);
  }

  /**
   * The member sees both topics and the total reflects the grant.
   */
  public function testMemberListingIncludesRestrictedTopic(): void {
    $this->container->get('current_user')->setAccount($this->member);
    $view = Views::getView('test_public_forum_listing');
    self::assertNotFalse($view);
    $view->setCurrentPage(1);
    $view->execute();

    self::assertSame(2, (int) $view->total_rows);
    self::assertCount(1, $view->result);
    self::assertSame((int) $this->restrictedTopic->id(), (int) $view->result[0]->nid);
  }

  /**
   * Creates a published topic with an explicit node access record.
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
      'langcode' => 'en',
      'fallback' => 1,
      'realm' => $restricted ? CounterGrantsTestHooks::REALM : 'all',
      'gid' => $restricted ? 1 : 0,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ])->execute();
    return $topic;
  }

  /**
   * Builds a node-based listing with a one-row pager.
   */
  private function listingView(): void {
    View::create([
      'id' => 'test_public_forum_listing',
      'label' => 'Public forum listing',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [
            'query' => [
              'type' => 'views_query',
              'options' => ['disable_sql_rewrite' => FALSE],
            ],
            'pager' => [
              'type' => 'mini',
              'options' => ['items_per_page' => 1, 'offset' => 0],
            ],
            'style' => ['type' => 'default', 'options' => []],
            'row' => ['type' => 'fields', 'options' => []],
            'fields' => [
              'nid' => [
                'id' => 'nid',
                'table' => 'node_field_data',
                'field' => 'nid',
                'relationship' => 'none',
                'group_type' => 'group',
                'plugin_id' => 'numeric',
              ],
            ],
            'filters' => [
              'type' => [
                'id' => 'type',
                'table' => 'node_field_data',
                'field' => 'type',
                'relationship' => 'none',
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
                'relationship' => 'none',
                'group_type' => 'group',
                'plugin_id' => 'boolean',
                'value' => 1,
              ],
            ],
            'sorts' => [
              'nid' => [
                'id' => 'nid',
                'table' => 'node_field_data',
                'field' => 'nid',
                'relationship' => 'none',
                'group_type' => 'group',
                'plugin_id' => 'standard',
                'order' => 'ASC',
              ],
            ],
          ],
        ],
      ],
    ])->save();
  }

}
