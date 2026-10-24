<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Context\Provider\PublicForumContextProvider;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Plugin\Field\FieldFormatter\ForumPostSearchTopicLinkFormatter;
use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves Forum post search results link to the anchored topic page.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostSearchTopicLinkFormatterKernelTest extends KernelTestBase {

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
    'domain',
    'aculta_forum_post_test',
  ];

  /**
   * Purpose resolver.
   */
  private DomainPurposeManager $purpose;

  /**
   * Current user used by the formatter.
   */
  private User $member;

  /**
   * Published public topic.
   */
  private Node $topic;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node', 'domain']);
    foreach ([
      'aculta_org' => 'main.example.test',
      'forum_aculta_org' => 'forum.example.test',
    ] as $id => $hostname) {
      Domain::create([
        'id' => $id,
        'hostname' => $hostname,
        'name' => $id,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
      ])->save();
    }
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    Role::load(RoleInterface::AUTHENTICATED_ID)->grantPermission('access content')->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->member = User::create(['name' => 'membro', 'status' => 1]);
    $this->member->save();
    $this->container->get('current_user')->setAccount($this->member);
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico',
      'status' => 1,
      'uid' => $this->member->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();
    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
  }

  /**
   * A published post links to its anchored position in the topic.
   */
  public function testPublishedPostLinksToAnchoredTopicPage(): void {
    $post = $this->post();
    $build = $this->purpose->runInPurpose('forum', fn () => $this->renderLink($post));

    self::assertNotEmpty($build);
    self::assertSame('entity.node.canonical', $build[0]['#url']->getRouteName());
    self::assertSame('forum-post-' . $post->id(), $build[0]['#url']->getOption('fragment'));
    self::assertArrayHasKey('page', $build[0]['#url']->getOption('query'));
  }

  /**
   * Unpublished posts and outside-purpose requests render no link.
   */
  public function testUnpublishedAndOutsidePurposeRenderNothing(): void {
    $post = $this->post();
    $post->setUnpublished()->save();
    $unpublished = $this->purpose->runInPurpose('forum', fn () => $this->renderLink($post));
    self::assertTrue($unpublished[0]['#access'] === FALSE);

    $post->setPublished()->save();
    $outside = $this->purpose->runInPurpose('main', fn () => $this->renderLink($post));
    self::assertSame([], $outside);
  }

  /**
   * Renders the topic field with the search formatter.
   */
  private function renderLink(ForumPost $post): array {
    $builder = new ForumPostThreadBuilder(
      $this->container->get('entity_type.manager'),
      $this->member,
      $this->purpose,
      new DiscussionContextResolver([new PublicForumContextProvider($this->purpose)]),
      $this->container->get('pager.manager'),
    );
    $formatter = new ForumPostSearchTopicLinkFormatter(
      'forum_post_search_topic_link',
      ['id' => 'forum_post_search_topic_link'],
      $post->getFieldDefinition('topic'),
      [],
      'hidden',
      'search_result',
      [],
      $this->container->get('current_user'),
      $this->purpose,
      $builder,
    );
    return $formatter->viewElements($post->get('topic'), 'en');
  }

  /**
   * Creates a published post with the indexing markers.
   */
  private function post(): ForumPost {
    $post = ForumPost::create([
      'uid' => $this->member->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => 1,
      'thread_position' => 10,
      'created' => 1700000000,
      'first_published' => 1700000000,
    ]);
    $post->save();
    return $post;
  }

}
