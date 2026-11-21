<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Context\DiscussionContext;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Origin\CoreDiscussionOriginClassifier;
use Drupal\aculta_forum\Origin\DiscussionOriginResolver;
use Drupal\aculta_forum\Service\DiscussionReplyFormBuilder;
use Drupal\aculta_forum\Service\DiscussionThreadDepthResolver;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\TopicAuthorDeletion;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises ForumPost reply parent validation against real stored entities.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class DiscussionReplyForumPostKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system', 'user', 'field', 'filter', 'text', 'node', 'domain',
    'workflows', 'content_moderation', 'options', 'aculta_forum_post_test', 'aculta_cgu',
  ];

  /**
   * Registra o serviço de estado de tópico do Fórum, que depende do ReportService do CGU.
   *
   * O módulo do Fórum não é habilitado neste teste; o serviço é declarado aqui com as
   * mesmas dependências de produção, para o DiscussionReplyAccess ser montado pelo container.
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // O controller Marley do CGU referencia aculta_portal.domain_site sem habilitar o aculta_portal;
    // o serviço sintético só satisfaz a referência do container.
    $container->register('aculta_portal.domain_site', \stdClass::class)
      ->setSynthetic(TRUE)
      ->setPublic(TRUE);
    $container->register('aculta_forum.topic_author_deletion', TopicAuthorDeletion::class)
      ->addArgument(new Reference('keyvalue'))
      ->addArgument(new Reference('aculta_cgu.report_service'))
      ->addArgument(new Reference('cache_tags.invalidator'));
  }

  /**
   * The public Forum topic used by the tests.
   */
  private Node $topic;

  /**
   * The current topic author.
   */
  private User $author;

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
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->topic = $this->createTopic($this->author->id());
  }

  /**
   * A published ForumPost from the same topic can be replied to.
   */
  public function testPublishedParentFromSameTopicIsAccepted(): void {
    $post = $this->post($this->topic);
    $account = new UserSession(['uid' => 1, 'permissions' => ['administer aculta forum posts']]);

    $result = $this->replyAccess()->checkAccess($this->topic, $account, (int) $post->id());

    self::assertTrue($result->isAllowed());
  }

  /**
   * A ForumPost from a different topic cannot be used as the reply parent.
   */
  public function testParentFromDifferentTopicIsRejected(): void {
    $otherTopic = $this->createTopic($this->author->id(), 'Outro tópico');
    $post = $this->post($otherTopic);
    $account = new UserSession(['uid' => 1, 'permissions' => ['administer aculta forum posts']]);

    $result = $this->replyAccess()->checkAccess($this->topic, $account, (int) $post->id());

    self::assertFalse($result->isAllowed());
  }

  /**
   * The public submission form receives the persisted ForumPost parent.
   */
  public function testReplyComposerStoresForumPostParentReference(): void {
    $parent = $this->post($this->topic);
    $account = new UserSession(['uid' => 1, 'permissions' => ['administer aculta forum posts']]);
    $entityFormBuilder = $this->createMock(EntityFormBuilderInterface::class);
    $entityFormBuilder->expects(self::once())
      ->method('getForm')
      ->with(self::callback(function (mixed $entity) use ($parent): bool {
        self::assertInstanceOf(ForumPost::class, $entity);
        self::assertSame((string) $parent->id(), (string) $entity->get('parent')->target_id);
        self::assertSame((string) $this->topic->id(), (string) $entity->get('topic')->target_id);
        return TRUE;
      }), 'public_submission')
      ->willReturn([]);
    $builder = new DiscussionReplyFormBuilder(
      new DiscussionContextResolver([]),
      $this->container->get('entity_type.manager'),
      $entityFormBuilder,
      $this->replyAccess(),
      new ForumQuoteBuilder(),
      $this->createMock(DiscussionThreadDepthResolver::class),
    );

    $result = $builder->buildReplyForm($this->topic, $account, (int) $parent->id());

    self::assertSame('editando', $result['#status']);
  }

  /**
   * Creates the reply access service with a public test context provider.
   */
  private function replyAccess(): DiscussionReplyAccess {
    $provider = new class implements DiscussionContextProviderInterface {

      /**
       * {@inheritdoc}
       */
      public function supports(EntityInterface $entity): bool {
        return $entity instanceof Node && $entity->bundle() === 'aculta_forum_topic';
      }

      /**
       * {@inheritdoc}
       */
      public function getContext(EntityInterface $entity, AccountInterface $account): DiscussionContext {
        return new DiscussionContext(
          $entity,
          'public_forum',
          'public_forum:node:' . $entity->id(),
          AccessResult::allowed(),
          AccessResult::allowed(),
          AccessResult::neutral(),
          Url::fromRoute('entity.node.canonical', ['node' => $entity->id()]),
          'public_if_entity_accessible',
          'public_if_entity_accessible',
          'public_if_entity_accessible',
        );
      }

    };
    $contextResolver = new DiscussionContextResolver([$provider]);
    $originResolver = new DiscussionOriginResolver([
      new CoreDiscussionOriginClassifier($contextResolver),
    ]);
    return new DiscussionReplyAccess(
      $contextResolver,
      $this->container->get('entity_type.manager'),
      $originResolver,
      $this->container->get('aculta_forum.topic_author_deletion'),
    );
  }

  /**
   * Creates a published Forum topic with a current owner.
   */
  private function createTopic(int|string|null $ownerId, string $title = 'Tópico'): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => $title,
      'status' => 1,
      'uid' => $ownerId,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Creates a published ForumPost attached to a topic.
   */
  private function post(Node $topic): ForumPost {
    $post = ForumPost::create([
      'uid' => 1,
      'topic' => $topic->id(),
      'body' => ['value' => 'Resposta publicada', 'format' => 'plain_text'],
    ]);
    $post->setPublished();
    $post->save();
    return $post;
  }

}
