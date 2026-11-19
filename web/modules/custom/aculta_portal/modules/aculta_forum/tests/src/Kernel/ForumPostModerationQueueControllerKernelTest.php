<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Controller\ForumPostModerationQueueController;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the moderation queue lists only review posts of the Forum purpose.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostModerationQueueControllerKernelTest extends KernelTestBase {

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
    'workflows',
    'content_moderation',
    'aculta_forum_post_test',
  ];

  /**
   * Purpose resolver.
   */
  private DomainSiteManager $purpose;

  /**
   * Moderator account.
   */
  private User $member;

  /**
   * Forum topic.
   */
  private Node $forumTopic;

  /**
   * Topic living on the main purpose.
   */
  private Node $mainTopic;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('config.storage')->write('workflows.workflow.aculta_forum_post', [
      'langcode' => 'pt-br',
      'status' => TRUE,
      'id' => 'aculta_forum_post',
      'label' => 'Forum post moderation workflow',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'review' => ['label' => 'Review', 'weight' => 1, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 2, 'published' => TRUE, 'default_revision' => TRUE],
          'rejected' => ['label' => 'Rejected', 'weight' => 3, 'published' => FALSE, 'default_revision' => TRUE],
        ],
        'transitions' => [
          'approve' => ['label' => 'Approve', 'from' => ['review'], 'to' => 'published', 'weight' => 0],
          'edit_published' => ['label' => 'Edit published', 'from' => ['published'], 'to' => 'published', 'weight' => 1],
          'reject' => ['label' => 'Reject', 'from' => ['review'], 'to' => 'rejected', 'weight' => 2],
        ],
        'entity_types' => ['aculta_forum_post' => ['aculta_forum_post']],
        'default_moderation_state' => 'draft',
      ],
    ]);
    $this->container->get('config.factory')->reset('workflows.workflow.aculta_forum_post');
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'system',
      'user',
      'field',
      'filter',
      'text',
      'node',
      'domain',
      'workflows',
      'content_moderation',
    ]);
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
    $anonymousRole = Role::load(RoleInterface::ANONYMOUS_ID);
    self::assertInstanceOf(Role::class, $anonymousRole);
    $anonymousRole->grantPermission('access content')->save();
    $authenticatedRole = Role::load(RoleInterface::AUTHENTICATED_ID);
    self::assertInstanceOf(Role::class, $authenticatedRole);
    $authenticatedRole
      ->grantPermission('access content')
      ->grantPermission('view any unpublished content')
      ->save();
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

    $this->member = User::create(['name' => 'moderador', 'status' => 1]);
    $this->member->save();
    $this->container->get('current_user')->setAccount($this->member);
    $this->forumTopic = $this->topic('Tópico do Fórum', 'forum_aculta_org');
    $this->mainTopic = $this->topic('Tópico principal', 'aculta_org');

    $this->purpose = new DomainSiteManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
  }

  /**
   * Only review posts of an accessible Forum topic are listed.
   */
  public function testQueueListsOnlyForumReviewPosts(): void {
    $review = $this->post($this->forumTopic, 'Aguardando revisão', 'review');
    $this->post($this->mainTopic, 'Fora do Fórum', 'review');
    $this->post($this->forumTopic, 'Já publicada', 'published');

    $build = $this->purpose->runInSite('forum', fn () => $this->controller()->queue());
    self::assertIsArray($build);

    self::assertSame('table', $this->stringEntry($build, '#type'));
    $rows = $this->arrayEntry($build, '#rows');
    self::assertCount(1, $rows);
    $row = $this->arrayEntry($rows, 0);
    $titleData = $this->arrayEntry($this->arrayEntry($row, 0), 'data');
    self::assertSame('Aguardando revisão', $this->stringEntry($titleData, '#plain_text'));
    $topicData = $this->arrayEntry($this->arrayEntry($row, 1), 'data');
    self::assertSame('Tópico do Fórum', $this->stringEntry($topicData, '#plain_text'));
    $operations = $this->arrayEntry($this->arrayEntry($row, 4), 'data');
    self::assertSame('operations', $this->stringEntry($operations, '#type'));
    $links = $this->arrayEntry($operations, '#links');
    $reviewLink = $this->arrayEntry($links, 'review');
    $reviewUrl = $reviewLink['url'] ?? NULL;
    self::assertInstanceOf(Url::class, $reviewUrl);
    self::assertSame('aculta_forum.post_moderate', $reviewUrl->getRouteName());
    $revisionId = $review->getRevisionId();
    self::assertNotNull($revisionId);
    self::assertSame((string) $revisionId, $this->stringEntry($reviewUrl->getRouteParameters(), 'revision_id'));
    $historyLink = $this->arrayEntry($links, 'history');
    $historyUrl = $historyLink['url'] ?? NULL;
    self::assertInstanceOf(Url::class, $historyUrl);
    self::assertSame('aculta_forum.post_revision_history', $historyUrl->getRouteName());
    $empty = $build['#empty'] ?? NULL;
    self::assertInstanceOf(TranslatableMarkup::class, $empty);
    self::assertSame('Não há publicações aguardando moderação.', (string) $empty);
  }

  /**
   * Without the review state the queue stays empty.
   */
  public function testQueueEmptyWithoutReviewPosts(): void {
    $this->post($this->forumTopic, 'Rascunho', 'draft');
    $build = $this->purpose->runInSite('forum', fn () => $this->controller()->queue());
    self::assertIsArray($build);
    self::assertSame([], $this->arrayEntry($build, '#rows'));
  }

  /**
   * Builds the controller with production services.
   */
  private function controller(): ForumPostModerationQueueController {
    return new ForumPostModerationQueueController(
      $this->container->get('entity_type.manager'),
      $this->container->get('current_user'),
      $this->container->get('date.formatter'),
      $this->purpose,
    );
  }

  /**
   * Creates a published topic on the given domain.
   */
  private function topic(string $title, string $domain): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'uid' => $this->member->id(),
      'field_domain_source' => ['target_id' => $domain],
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Creates a post in the requested moderation state.
   */
  private function post(Node $topic, string $body, string $state): ForumPost {
    $post = ForumPost::create([
      'uid' => $this->member->id(),
      'topic' => $topic->id(),
      'subject' => $body,
      'body' => ['value' => $body, 'format' => 'plain_text'],
      'moderation_state' => $state,
    ]);
    $post->save();
    return $post;
  }

  /**
   * Reads and verifies a render array item.
   *
   * @param array<array-key, mixed> $values
   *   Values to read from.
   * @param int|string $key
   *   Key to read.
   *
   * @return array<array-key, mixed>
   *   The item after confirming that it is an array.
   */
  private function arrayEntry(array $values, int|string $key): array {
    $value = $values[$key] ?? NULL;
    self::assertIsArray($value);
    return $value;
  }

  /**
   * Reads and verifies a string render array item.
   *
   * @param array<array-key, mixed> $values
   *   Values to read from.
   * @param string $key
   *   Key to read.
   *
   * @return string
   *   The item after confirming that it is a string.
   */
  private function stringEntry(array $values, string $key): string {
    $value = $values[$key] ?? NULL;
    self::assertIsString($value);
    return $value;
  }

}
