<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Context\Provider\PublicForumContextProvider;
use Drupal\aculta_forum\Controller\ForumPostSubmissionController;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityFormBuilderInterface;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds the public submission form from the validated topic and parent.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostSubmissionControllerKernelTest extends KernelTestBase {

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
  private DomainPurposeManager $purpose;

  /**
   * Member account.
   */
  private User $member;

  /**
   * Published topic.
   */
  private Node $topic;

  /**
   * Request stack used by the controller.
   */
  private RequestStack $requestStack;

  /**
   * Captured entity passed to the form builder.
   */
  private ?ForumPost $builtPost = NULL;

  /**
   * Form builder double.
   */
  private EntityFormBuilderInterface $formBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('config.storage')->write('workflows.workflow.aculta_forum_post', [
      'langcode' => 'pt-br',
      'status' => TRUE,
      'id' => 'aculta_forum_post',
      'label' => 'Forum post submission workflow',
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
    Role::load(RoleInterface::ANONYMOUS_ID)->grantPermission('access content')->save();
    Role::load(RoleInterface::AUTHENTICATED_ID)
      ->grantPermission('access content')
      ->grantPermission('create aculta forum posts')
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
    $this->requestStack = new RequestStack();
    $this->formBuilder = $this->createMock(EntityFormBuilderInterface::class);
    $this->formBuilder->method('getForm')->willReturnCallback(
      function ($entity, $operation): array {
        $this->builtPost = $entity;
        return ['#markup' => 'form'];
      },
    );
  }

  /**
   * Missing topics and invalid parents return not found.
   */
  public function testInvalidContextsAreNotFound(): void {
    $this->pushRequest('');
    self::assertTrue($this->isNotFound());

    $this->pushRequest('00000000-0000-0000-0000-000000000000');
    self::assertTrue($this->isNotFound());

    $this->pushRequest($this->topic->uuid(), '00000000-0000-0000-0000-000000000000');
    self::assertTrue($this->isNotFound());
  }

  /**
   * A valid topic creates the draft post; a reply links the published parent.
   */
  public function testValidSubmissionBuildsDraftAndParent(): void {
    $parent = ForumPost::create([
      'uid' => $this->member->id(),
      'topic' => $this->topic->id(),
      'body' => ['value' => 'Pai', 'format' => 'plain_text'],
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $parent->save();

    $ids = $this->purpose->runInPurpose('forum', fn () => $this->container->get('entity_type.manager')->getStorage('aculta_forum_post')->getQuery()
      ->accessCheck(TRUE)
      ->condition('uuid', $parent->uuid())
      ->condition('status', 1)
      ->execute());
    self::assertNotEmpty($ids, 'Parent query finds the published parent in the Forum purpose.');
    $allowed = $this->purpose->runInPurpose('forum', fn () => $parent->access('view', $this->member, TRUE)->isAllowed());
    self::assertTrue($allowed, 'Parent is viewable in the Forum purpose.');
    $this->pushRequest($this->topic->uuid(), $parent->uuid());
    $build = $this->purpose->runInPurpose('forum', fn () => $this->controller()->form());

    self::assertSame('form', $build['#markup']);
    self::assertNotNull($this->builtPost);
    self::assertSame((int) $this->member->id(), (int) $this->builtPost->getOwnerId());
    self::assertSame((int) $this->topic->id(), (int) $this->builtPost->get('topic')->target_id);
    self::assertSame((int) $parent->id(), (int) $this->builtPost->get('parent')->target_id);
    self::assertSame('draft', $this->builtPost->get('moderation_state')->value);
  }

  /**
   * Asserts the controller throws not found.
   */
  private function isNotFound(): bool {
    try {
      $this->purpose->runInPurpose('forum', fn () => $this->controller()->form());
    }
    catch (NotFoundHttpException) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds the controller with the request stack double.
   */
  private function controller(): ForumPostSubmissionController {
    return new ForumPostSubmissionController(
      $this->container->get('entity_type.manager'),
      $this->formBuilder,
      $this->requestStack,
      $this->container->get('current_user'),
      new DiscussionContextResolver([new PublicForumContextProvider($this->purpose)]),
    );
  }

  /**
   * Pushes a request with the Form API query arguments.
   */
  private function pushRequest(string $topic_uuid, string $reply_uuid = ''): void {
    $this->requestStack = new RequestStack();
    $this->requestStack->push(Request::create('/forum/publicacao', 'GET', array_filter([
      'topico' => $topic_uuid,
      'resposta' => $reply_uuid,
    ], static fn (string $value): bool => $value !== '')));
  }

}
