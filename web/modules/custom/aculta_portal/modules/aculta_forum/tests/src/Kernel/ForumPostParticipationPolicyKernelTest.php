<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_game\ForumParticipationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the Game ForumPost trigger only fires on a live public first reply.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostParticipationPolicyKernelTest extends KernelTestBase {

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
   * Policy under test with the real Domain purpose mapping.
   */
  private ForumParticipationPolicy $policy;

  /**
   * Topic author.
   */
  private User $author;

  /**
   * Forum category topic used by the posts.
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
    $this->installConfig([
      'system', 'user', 'field', 'filter', 'text', 'node', 'domain',
      'workflows', 'content_moderation',
    ]);

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
    $this->legacyAuthorField('node', 'aculta_forum_topic');
    $this->legacyAuthorField('aculta_forum_post', 'aculta_forum_post');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico público',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();

    $this->policy = new ForumParticipationPolicy(new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    ));
  }

  /**
   * A published first reply in the Forum Domain is eligible.
   */
  public function testPublishedPostOnForumDomainIsEligible(): void {
    self::assertTrue($this->policy->isEligiblePost($this->post()));
  }

  /**
   * A pending post does not count until it is published.
   */
  public function testPendingPostIsNotEligible(): void {
    self::assertFalse($this->policy->isEligiblePost($this->post(published: FALSE)));
  }

  /**
   * Only the default revision represents the current post.
   */
  public function testNonDefaultRevisionIsNotEligible(): void {
    $post = $this->post();
    $first_revision = $post->getRevisionId();
    $post->setNewRevision(TRUE);
    $post->set('body', ['value' => 'Revisão nova', 'format' => 'plain_text']);
    $post->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_forum_post');
    self::assertInstanceOf(EntityStorageInterface::class, $storage);
    $old = $storage->loadRevision($first_revision);
    self::assertNotNull($old);
    self::assertFalse($old->isDefaultRevision());
    self::assertFalse($this->policy->isEligiblePost($old));
  }

  /**
   * A topic outside the Forum Domain does not earn the trigger.
   */
  public function testNonForumDomainIsNotEligible(): void {
    $this->topic->set('field_domain_source', ['target_id' => 'aculta_org']);
    $this->topic->save();
    self::assertFalse($this->policy->isEligiblePost($this->post()));
  }

  /**
   * Entities marked by Migrate as synchronized do not earn achievements.
   */
  public function testSynchronizedPostIsNotEligible(): void {
    $post = $this->post();
    $post->setSyncing(TRUE);
    self::assertFalse($this->policy->isEligiblePost($post));
  }

  /**
   * Editing or approving an already published post does not repeat the award.
   */
  public function testPublishedOriginalIsNotEligibleAgain(): void {
    $post = $this->post();
    $original = clone $post;
    $post->setOriginal($original);
    self::assertFalse($this->policy->isEligiblePost($post));
  }

  /**
   * Historical authorship on the post or its topic blocks the trigger.
   */
  public function testLegacyAuthorExcludesPostAndTopic(): void {
    $post = $this->post();
    $post->set('field_autor_legado', 'Autor antigo');
    self::assertFalse($this->policy->isEligiblePost($post));

    $this->topic->set('field_autor_legado', 'Autor antigo');
    $this->topic->save();
    self::assertFalse($this->policy->isEligiblePost($this->post()));
  }

  /**
   * A post without a topic cannot feed the public Forum trigger.
   */
  public function testMissingTopicIsNotEligible(): void {
    $post = $this->post();
    $post->set('topic', NULL);
    self::assertFalse($this->policy->isEligiblePost($post));
  }

  /**
   * Builds a ForumPost in the test topic.
   */
  private function post(bool $published = TRUE): ForumPost {
    $post = ForumPost::create([
      'uid' => $this->author->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Primeira resposta', 'format' => 'plain_text'],
    ]);
    if ($published) {
      $post->setPublished();
    }
    $post->save();
    return $post;
  }

  /**
   * Declares the historical author marker checked by the policy.
   */
  private function legacyAuthorField(string $entity_type, string $bundle): void {
    FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => 'field_autor_legado',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'field_name' => 'field_autor_legado',
      'label' => 'Autor legado',
    ])->save();
  }

}
