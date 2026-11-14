<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel\CoAuthor;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\CoAuthor\CoAuthorAuditService;
use Drupal\aculta_user\CoAuthor\CoAuthorManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Kernel tests for ACULTA node co-authorship core functionality.
 */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class AcultaNodeCoAuthorKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'path_alias',
    'file',
    'profile',
    'aculta_user',
  ];

  /**
   * The owner user.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $owner;

  /**
   * First coauthor user.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $coauthor1;

  /**
   * Second coauthor user.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $coauthor2;

  /**
   * Stranger user.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $stranger;

  /**
   * Blocked user.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $blockedUser;

  /**
   * The coauthor manager.
   *
   * @var \Drupal\aculta_user\CoAuthor\CoAuthorManagerInterface
   */
  private CoAuthorManagerInterface $coAuthorManager;

  /**
   * The audit service.
   *
   * @var \Drupal\aculta_user\CoAuthor\CoAuthorAuditService
   */
  private CoAuthorAuditService $auditService;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('aculta_image.catalog', \stdClass::class);
    $container->register('aculta_image.render', \stdClass::class);
    $container->register('aculta_image.file_remover', \Drupal\aculta_image\FileRemover::class)
      ->addArgument(new Reference('file.usage'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('file_system'))
      ->addArgument(new Reference('logger.factory'));
    $container->register('aculta_portal.email_confirmation_policy', EmailConfirmationPolicy::class)
      ->addArgument(new Reference('user.data'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('module_handler'));
    $container->register('aculta_portal.domain_site', \stdClass::class);
    $container->register('aculta_file_uploader.unused_file_cleaner', \stdClass::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Composer\Autoload\ClassLoader $loader */
    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_user\\', dirname(__DIR__, 5) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_portal\\', dirname(__DIR__, 7) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_image\\', dirname(__DIR__, 7) . '/modules/aculta_image/src', TRUE);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('profile');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('aculta_user', ['aculta_user_nickname', 'aculta_coauthor_audit']);
    $this->installConfig(['system', 'user', 'filter', 'node']);

    ProfileType::create(['id' => 'authenticated', 'label' => 'Perfil autenticado'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'profile',
      'field_name' => 'field_nickname',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'profile',
      'bundle' => 'authenticated',
      'field_name' => 'field_nickname',
      'label' => 'Apelido',
    ])->save();

    NodeType::create([
      'type' => 'revista',
      'name' => 'Revista',
    ])->save();

    NodeType::create([
      'type' => 'aculta_forum_topic',
      'name' => 'Tópico do Fórum',
    ])->save();

    Role::create([
      'id' => 'editor_revista',
      'label' => 'Editor Revista',
      'permissions' => [
        'access content',
        'edit own revista content',
        'delete own revista content',
        'view own unpublished content',
      ],
    ])->save();

    Role::create([
      'id' => 'coauthor_editor',
      'label' => 'Coauthor Editor',
      'permissions' => [
        'edit aculta coauthors of own content',
        'edit aculta coauthors of coauthored content',
      ],
    ])->save();

    $this->owner = User::create([
      'name' => 'owner',
      'mail' => 'owner@example.com',
      'status' => 1,
      'roles' => ['editor_revista', 'coauthor_editor'],
    ]);
    $this->owner->save();

    $this->coauthor1 = User::create([
      'name' => 'coauthor1',
      'mail' => 'co1@example.com',
      'status' => 1,
      'roles' => ['editor_revista', 'coauthor_editor'],
    ]);
    $this->coauthor1->save();

    $this->coauthor2 = User::create([
      'name' => 'coauthor2',
      'mail' => 'co2@example.com',
      'status' => 1,
      'roles' => ['editor_revista'],
    ]);
    $this->coauthor2->save();

    $this->stranger = User::create(['name' => 'stranger', 'mail' => 'stranger@example.com', 'status' => 1]);
    $this->stranger->save();

    $this->blockedUser = User::create(['name' => 'blocked', 'mail' => 'blocked@example.com', 'status' => 0]);
    $this->blockedUser->save();

    $this->coAuthorManager = $this->container->get('aculta_user.coauthor_manager');
    $this->auditService = $this->container->get('aculta_user.coauthor_audit');
  }

  /**
   * Tests the CoAuthorManager service and aculta_coauthors field read/write.
   */
  public function testCoAuthorManagerAndField(): void {
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Artigo colaborativo',
      'uid' => $this->owner->id(),
    ]);
    $node->save();

    self::assertTrue($node->hasField('aculta_coauthors'));
    self::assertSame([], $this->coAuthorManager->getCoAuthorIds($node));
    self::assertFalse($this->coAuthorManager->isCoAuthor($node, $this->coauthor1));

    $node->set('aculta_coauthors', [
      ['target_id' => $this->coauthor1->id()],
      ['target_id' => $this->coauthor2->id()],
    ]);
    $node->save();

    self::assertSame([(int) $this->coauthor1->id(), (int) $this->coauthor2->id()], $this->coAuthorManager->getCoAuthorIds($node));
    self::assertTrue($this->coAuthorManager->isCoAuthor($node, $this->coauthor1));
    self::assertTrue($this->coAuthorManager->isCoAuthor($node, $this->coauthor2));
    self::assertFalse($this->coAuthorManager->isCoAuthor($node, $this->stranger));
    self::assertFalse($this->coAuthorManager->isCoAuthor($node, $this->owner));
  }

  /**
   * Tests node access control for coauthors.
   */
  public function testNodeAccessForCoAuthor(): void {
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Publicação Revista',
      'uid' => $this->owner->id(),
      'status' => 0,
      'aculta_coauthors' => [
        ['target_id' => $this->coauthor1->id()],
      ],
    ]);
    $node->save();

    $accessHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('node');

    // Coauthor com edit own revista content e view own unpublished content pode update e view:
    self::assertTrue($accessHandler->access($node, 'view', $this->coauthor1));
    self::assertTrue($accessHandler->access($node, 'update', $this->coauthor1));
    self::assertTrue($accessHandler->access($node, 'delete', $this->coauthor1));

    // Stranger não pode:
    self::assertFalse($accessHandler->access($node, 'view', $this->stranger));
    self::assertFalse($accessHandler->access($node, 'update', $this->stranger));
    self::assertFalse($accessHandler->access($node, 'delete', $this->stranger));
  }

  /**
   * Tests that forum topics are excluded from coauthorship permissions.
   */
  public function testForumTopicNeverHasCoauthorshipAccess(): void {
    $forumTopic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Discussão do fórum',
      'uid' => $this->owner->id(),
      'status' => 0,
      'aculta_coauthors' => [
        ['target_id' => $this->coauthor1->id()],
      ],
    ]);
    $forumTopic->save();

    $accessHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('node');

    // Fórum não concede acesso de coautoria:
    self::assertFalse($accessHandler->access($forumTopic, 'view', $this->coauthor1));
    self::assertFalse($accessHandler->access($forumTopic, 'update', $this->coauthor1));
    self::assertFalse($accessHandler->access($forumTopic, 'delete', $this->coauthor1));

    // Field access para fórum é forbidden:
    $field = $forumTopic->get('aculta_coauthors');
    self::assertFalse($accessHandler->fieldAccess('edit', $field->getFieldDefinition(), $this->owner, $field));
  }

  /**
   * Tests field validation constraints (owner, duplicate, blocked users).
   */
  public function testFieldValidationConstraint(): void {
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Teste validação',
      'uid' => $this->owner->id(),
    ]);

    // Tentativa 1: Dono como coautor.
    $node->set('aculta_coauthors', [['target_id' => $this->owner->id()]]);
    $violations = $node->validate();
    self::assertGreaterThan(0, count($violations));

    // Tentativa 2: Usuário bloqueado.
    $node->set('aculta_coauthors', [['target_id' => $this->blockedUser->id()]]);
    $violations = $node->validate();
    self::assertGreaterThan(0, count($violations));

    // Tentativa 3: Coautor duplicado.
    $node->set('aculta_coauthors', [
      ['target_id' => $this->coauthor1->id()],
      ['target_id' => $this->coauthor1->id()],
    ]);
    $violations = $node->validate();
    self::assertGreaterThan(0, count($violations));

    // Válido: coautores válidos e distintos.
    $node->set('aculta_coauthors', [
      ['target_id' => $this->coauthor1->id()],
      ['target_id' => $this->coauthor2->id()],
    ]);
    $violations = $node->validate();
    self::assertCount(0, $violations);
  }

  /**
   * Tests the audit service recording coauthorship changes.
   */
  public function testCoAuthorAuditService(): void {
    $node = Node::create([
      'type' => 'revista',
      'title' => 'Artigo Auditado',
      'uid' => $this->owner->id(),
    ]);
    $node->save();

    $this->auditService->record((int) $node->id(), (int) $this->owner->id(), 'added', [(int) $this->coauthor1->id()]);

    $statement = $this->container->get('database')
      ->select('aculta_coauthor_audit', 'a')
      ->fields('a')
      ->condition('nid', $node->id())
      ->execute();
    self::assertNotNull($statement);
    /** @var array<int, object{action: string, actor_uid: int|string, coauthors: string}> $records */
    $records = $statement->fetchAll();

    self::assertCount(1, $records);
    self::assertSame('added', $records[0]->action);
    self::assertSame((string) $this->owner->id(), (string) $records[0]->actor_uid);
    self::assertSame((string) $this->coauthor1->id(), $records[0]->coauthors);
  }

}
