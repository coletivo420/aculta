<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentingStatus;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\domain\DomainInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared test fixture for Forum Content Index access checks.
 */
abstract class ForumAccessKernelTestBase extends KernelTestBase {

  use CommentTestTrait;

  protected const GROUP_TYPE = 'associado_forum';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'comment',
    'entity',
    'flexible_permissions',
    'options',
    'group',
    'gnode',
    'domain',
    'aculta_index',
    'aculta_index_forum_test',
    'aculta_index_forum_access_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturnCallback(static fn (string $id): ?Domain => Domain::load($id));
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('domain')->willReturn($domainStorage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturnCallback(
      static fn (bool $reset = FALSE): ?DomainInterface => \Drupal::service('domain.negotiator')->getActiveDomain($reset),
    );
    $negotiator->method('setActiveDomain')->willReturnCallback(
      static function (DomainInterface $domain): void {
        \Drupal::service('domain.negotiator')->setActiveDomain($domain);
      },
    );
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $entityTypeManager,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (getenv('ACULTA_INDEX_FORUM_ACCESS_BENCHMARK') === '1') {
      $databaseParts = parse_url(getenv('SIMPLETEST_DB') ?: '');
      if (!is_array($databaseParts)
        || ($databaseParts['host'] ?? '') !== 'localhost'
        || trim((string) ($databaseParts['path'] ?? ''), '/') !== 'aculta_simpletest') {
        throw new \RuntimeException('The Forum access benchmark requires the dedicated localhost aculta_simpletest database.');
      }
    }
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('aculta_associado');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['system', 'user', 'node', 'comment', 'text', 'group', 'domain']);
    aculta_index_install();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    $this->addDefaultCommentField(
      'node',
      'aculta_forum_topic',
      'field_aculta_discussion',
      CommentingStatus::Open,
      'aculta_discussion_post',
    );
    $this->installDomainField();
    $this->createDomains();
    $this->createRestrictedGroupType();
    $this->container->get('config.factory')->getEditable('aculta_forum.settings')
      ->set('restricted_group_type', self::GROUP_TYPE)
      ->save();
    $authenticated = Role::load('authenticated');
    self::assertInstanceOf(Role::class, $authenticated);
    $authenticated->grantPermission('access content')->grantPermission('access comments')->save();
  }

  /**
   * Creates an authenticated test account.
   */
  protected function createUser(string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $user->save();
    return $user;
  }

  /**
   * Stores the production associate marker as active.
   */
  protected function setAssociateActive(User $user): void {
    $this->container->get('entity_type.manager')->getStorage('aculta_associado')->create([
      'uid' => $user->id(),
      'situacao' => 'ativo',
    ])->save();
  }

  /**
   * Installs the Forum topic's Domain reference field.
   */
  protected function installDomainField(): void {
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
      'settings' => ['handler' => 'default:domain', 'handler_settings' => []],
    ])->save();
  }

  /**
   * Creates main and canonical Forum Domains.
   */
  protected function createDomains(): void {
    foreach (['aculta_org' => 'main', 'forum_aculta_org' => 'forum'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }
  }

  /**
   * Creates the restricted Forum Group type and topic relationship plugin.
   */
  protected function createRestrictedGroupType(): void {
    $groupTypes = $this->container->get('entity_type.manager')->getStorage('group_type');
    $groupTypes->create([
      'id' => self::GROUP_TYPE,
      'label' => 'Restricted forum',
      'creator_membership' => FALSE,
      'creator_wizard' => FALSE,
    ])->save();
    $relationshipTypes = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationshipTypes);
    $relationshipTypes->save($relationshipTypes->createFromPlugin(
      $groupTypes->load(self::GROUP_TYPE),
      'group_node:aculta_forum_topic',
    ));
  }

}
