<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Access\ForumPostPublicEditAccess;
use Drupal\aculta_forum\Controller\ForumPostEditController;
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
 * Proves the public edit form opens only for the author's published post.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostEditControllerKernelTest extends KernelTestBase {

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
   * Author account.
   */
  private User $author;

  /**
   * Other member account.
   */
  private User $other;

  /**
   * Forum topic.
   */
  private Node $topic;

  /**
   * Published post owned by the author.
   */
  private ForumPost $post;

  /**
   * Request stack used by the controller and access check.
   */
  private RequestStack $requestStack;

  /**
   * Captured entity passed to the form builder.
   */
  private ?ForumPost $builtPost = NULL;

  /**
   * Captured form operation.
   */
  private ?string $operation = NULL;

  /**
   * Form builder double.
   */
  private EntityFormBuilderInterface $formBuilder;

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

    // Reserve uid 1 so the author is never treated as the superuser.
    User::create(['name' => 'base', 'status' => 1])->save();
    $this->author = User::create(['name' => 'autora', 'status' => 1]);
    $this->author->save();
    $this->other = User::create(['name' => 'outra', 'status' => 1]);
    $this->other->save();
    $this->container->get('current_user')->setAccount($this->author);
    $this->topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico',
      'status' => 1,
      'uid' => $this->author->id(),
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $this->topic->save();
    $this->post = ForumPost::create([
      'uid' => $this->author->id(),
      'topic' => $this->topic->id(),
      'subject' => 'Publicação',
      'body' => ['value' => 'Corpo', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $this->post->save();

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
        $this->operation = $operation;
        return ['#markup' => 'form'];
      },
    );
  }

  /**
   * The author opens the public form for the published post.
   */
  public function testAuthorOpensPublicForm(): void {
    $this->pushRequest($this->post->uuid());
    $build = $this->purpose->runInPurpose('forum', fn () => $this->controller()->edit());

    self::assertSame('form', $build['#markup']);
    self::assertNotNull($this->builtPost);
    self::assertSame((int) $this->post->id(), (int) $this->builtPost->id());
    self::assertSame('public_submission', $this->operation);
  }

  /**
   * Missing, unknown, foreign, unpublished or out-of-purpose posts are denied.
   */
  public function testInvalidPostsAreNotFound(): void {
    $this->pushRequest('');
    self::assertTrue($this->isNotFound());

    $this->pushRequest('00000000-0000-0000-0000-000000000000');
    self::assertTrue($this->isNotFound());

    $this->pushRequest($this->post->uuid());
    $this->container->get('current_user')->setAccount($this->other);
    self::assertTrue($this->isNotFound());

    $this->container->get('current_user')->setAccount($this->author);
    self::assertFalse($this->isNotFound());

    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('aculta_forum_post')
      ->resetCache();
    $outside = $this->purpose->runInPurpose('main', fn () => $this->throwsNotFound());
    self::assertTrue($outside, 'Outside the Forum purpose the edit form is refused.');

    $this->post->setUnpublished()->save();
    self::assertTrue($this->isNotFound());
  }

  /**
   * Asserts the controller throws not found in the Forum purpose.
   */
  private function isNotFound(): bool {
    return $this->purpose->runInPurpose('forum', fn () => $this->throwsNotFound());
  }

  /**
   * Asserts the controller throws not found in the active purpose.
   */
  private function throwsNotFound(): bool {
    try {
      $this->controller()->edit();
    }
    catch (NotFoundHttpException) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Builds the controller with production access and request double.
   */
  private function controller(): ForumPostEditController {
    return new ForumPostEditController(
      $this->formBuilder,
      new ForumPostPublicEditAccess(
        $this->container->get('entity_type.manager'),
        $this->requestStack,
      ),
    );
  }

  /**
   * Pushes a request carrying the post UUID.
   */
  private function pushRequest(string $uuid): void {
    $this->requestStack = new RequestStack();
    $this->requestStack->push(Request::create('/forum/editar', 'GET', array_filter(
      ['post' => $uuid],
      static fn (string $value): bool => $value !== '',
    )));
  }

}
