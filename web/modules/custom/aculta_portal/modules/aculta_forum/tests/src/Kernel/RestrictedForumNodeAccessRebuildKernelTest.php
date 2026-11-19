<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGrantRebuilder;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prova o grant real do tópico restrito, o rebuild e a filtragem de listagem.
 *
 * Diferente de RestrictedForumGroupAclKernelTest, que chama os métodos de
 * produção diretamente, este teste registra os hooks de produção no fixture e
 * os deixa agir sobre o sistema de grants do Core: hook_node_grants,
 * hook_node_access_records, a tabela node_access, NodeAccessControlHandler e
 * consultas com a tag node_access. O rebuild global e o rebuild de um único
 * tópico são exercitados de ponta a ponta.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class RestrictedForumNodeAccessRebuildKernelTest extends KernelTestBase {

  /**
   * Tipo de Group configurado para o fórum restrito.
   */
  private const GROUP_TYPE = 'associado_forum';

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
    'entity',
    'flexible_permissions',
    'options',
    'group',
    'gnode',
    'aculta_forum_restricted_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installEntitySchema('aculta_associado');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'group']);

    // NodeAccessControlHandler::access() exige "access content" antes de
    // consultar grants; sem isso o anônimo é barrado pelo Core, não pelo Fórum.
    $anonRole = Role::load(RoleInterface::ANONYMOUS_ID);
    self::assertNotNull($anonRole);
    $anonRole->grantPermission('access content')->save();
    $authRole = Role::load(RoleInterface::AUTHENTICATED_ID);
    self::assertNotNull($authRole);
    $authRole->grantPermission('access content')->save();

    NodeType::create([
      'type' => 'aculta_forum_topic',
      'name' => 'Tópico de fórum',
    ])->save();
    NodeType::create([
      'type' => 'aculta_page',
      'name' => 'Página',
    ])->save();

    $group_type_storage = $this->container->get('entity_type.manager')
      ->getStorage('group_type');
    $group_type_storage->create([
      'id' => self::GROUP_TYPE,
      'label' => self::GROUP_TYPE,
      'creator_membership' => FALSE,
      'creator_wizard' => FALSE,
    ])->save();

    $relationship_type_storage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationship_type_storage);
    $groupType = $group_type_storage->load(self::GROUP_TYPE);
    self::assertInstanceOf(\Drupal\group\Entity\GroupTypeInterface::class, $groupType);
    $relationship_type_storage->save(
      $relationship_type_storage->createFromPlugin(
        $groupType,
        'group_node:aculta_forum_topic',
      ),
    );

    // O módulo Group proíbe a leitura de conteúdo agrupado sem uma permissão
    // de grupo. A ativação precisa conceder esta permissão à role de membro;
    // sem ela, nem o associado ativo com membership lê o tópico.
    $this->container->get('entity_type.manager')
      ->getStorage('group_role')
      ->create([
        'id' => self::GROUP_TYPE . '-membro',
        'label' => 'Membro',
        'group_type' => self::GROUP_TYPE,
        'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
        'permissions' => ['view group_node:aculta_forum_topic entity'],
      ])
      ->save();

    $this->container->get('config.factory')
      ->getEditable('aculta_forum.settings')
      ->set('restricted_group_type', self::GROUP_TYPE)
      ->save();
  }

  /**
   * O rebuild grava um grant de Group no restrito e o público no demais.
   */
  public function testRebuildWritesRealmGrantOnlyForRestrictedTopic(): void {
    $owner = $this->createUser('owner');
    $group = $this->createGroup('Associados', $owner);
    $restricted = $this->createTopic('Restrito', $owner);
    $public = $this->createTopic('Público', $owner);
    $this->restrictTopic($restricted, $group);

    $this->rebuild();

    self::assertSame([
      [
        'realm' => RestrictedForumAccessHooks::REALM,
        'gid' => (int) $group->id(),
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ],
    ], $this->grantsFor($restricted));

    self::assertSame([
      [
        'realm' => 'all',
        'gid' => 0,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ],
    ], $this->grantsFor($public));
  }

  /**
   * Só o associado ativo com membership lê o tópico restrito.
   */
  public function testActiveMemberReadsRestrictedTopicThroughEntityAccess(): void {
    $owner = $this->createUser('owner');
    $group = $this->createGroup('Associados', $owner);
    $restricted = $this->createTopic('Restrito', $owner);
    $public = $this->createTopic('Público', $owner);
    $this->restrictTopic($restricted, $group);

    $member = $this->createUser('membro_ativo');
    $this->addMember($group, $member);
    $this->setSituation($member, 'ativo');

    $active_without_membership = $this->createUser('ativo_sem_grupo');
    $this->setSituation($active_without_membership, 'ativo');

    $suspended_member = $this->createUser('suspenso_membro');
    $this->addMember($group, $suspended_member);
    $this->setSituation($suspended_member, 'suspenso');

    $anonymous = new AnonymousUserSession();

    $this->rebuild();

    self::assertTrue($restricted->access('view', $member, TRUE)->isAllowed());
    self::assertFalse($restricted->access('view', $active_without_membership, TRUE)->isAllowed());
    self::assertFalse($restricted->access('view', $suspended_member, TRUE)->isAllowed());
    self::assertFalse($restricted->access('view', $anonymous, TRUE)->isAllowed());

    foreach ([$member, $active_without_membership, $suspended_member, $anonymous] as $account) {
      self::assertTrue($public->access('view', $account, TRUE)->isAllowed());
    }
  }

  /**
   * A consulta taggeada filtra a listagem pela mesma regra do acesso.
   */
  public function testNodeAccessQueryFiltersListing(): void {
    $owner = $this->createUser('owner');
    $group = $this->createGroup('Associados', $owner);
    $restricted = $this->createTopic('Restrito', $owner);
    $public = $this->createTopic('Público', $owner);
    $this->restrictTopic($restricted, $group);

    $member = $this->createUser('membro_ativo');
    $this->addMember($group, $member);
    $this->setSituation($member, 'ativo');

    $active_without_membership = $this->createUser('ativo_sem_grupo');
    $this->setSituation($active_without_membership, 'ativo');

    $suspended_member = $this->createUser('suspenso_membro');
    $this->addMember($group, $suspended_member);
    $this->setSituation($suspended_member, 'suspenso');

    $this->rebuild();

    $restricted_nid = (int) $restricted->id();
    $public_nid = (int) $public->id();

    $member_visible = $this->visibleNids($member);
    self::assertContains($restricted_nid, $member_visible);
    self::assertContains($public_nid, $member_visible);

    foreach ([$active_without_membership, $suspended_member, new AnonymousUserSession()] as $account) {
      $visible = $this->visibleNids($account);
      self::assertNotContains($restricted_nid, $visible);
      self::assertContains($public_nid, $visible);
    }
  }

  /**
   * O rebuild de um tópico acompanha a inclusão e a remoção do Group.
   */
  public function testGrantRebuilderUpdatesSingleTopicWithoutFullRebuild(): void {
    $owner = $this->createUser('owner');
    $group = $this->createGroup('Associados', $owner);
    $topic = $this->createTopic('Tópico', $owner);

    $this->rebuild();
    $firstGrant = $this->grantsFor($topic)[0] ?? [];
    self::assertSame('all', $firstGrant['realm'] ?? NULL);

    $this->restrictTopic($topic, $group);
    $rebuilder = $this->container->get('aculta_forum_restricted_test.grant_rebuilder');
    self::assertTrue($rebuilder->rebuildForTopic($topic));

    self::assertSame([
      [
        'realm' => RestrictedForumAccessHooks::REALM,
        'gid' => (int) $group->id(),
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ],
    ], $this->grantsFor($topic));

    $relationship_storage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship');
    $relationship_ids = $relationship_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('gid', $group->id())
      ->condition('entity_id', $topic->id())
      ->execute();
    $relationship_storage->delete($relationship_storage->loadMultiple($relationship_ids));

    self::assertTrue($rebuilder->rebuildForTopic($topic));
    self::assertSame([
      [
        'realm' => 'all',
        'gid' => 0,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ],
    ], $this->grantsFor($topic));
  }

  /**
   * Executa o rebuild global de grants do Core.
   */
  private function rebuild(): void {
    $rebuild = $this->container->get(NodeAccessRebuild::class);
    $rebuild->rebuild();
  }

  /**
   * Devolve as linhas de node_access do tópico, ordenadas.
   *
   * @return list<array{realm: string, gid: int, grant_view: int, grant_update: int, grant_delete: int}>
   */
  private function grantsFor(NodeInterface $topic): array {
    $database = $this->container->get('database');
    $query = $database->select('node_access', 'na');
    $query->fields('na', ['realm', 'gid', 'grant_view', 'grant_update', 'grant_delete']);
    $query->condition('nid', $topic->id());
    $query->orderBy('realm');
    $query->orderBy('gid');
    $stmt = $query->execute();
    $rows = $stmt ? $stmt->fetchAll(FetchAs::Associative) : [];
    $result = [];
    foreach ($rows as $row) {
      if (is_array($row)) {
        $gid = $row['gid'] ?? 0;
        $gv = $row['grant_view'] ?? 0;
        $gu = $row['grant_update'] ?? 0;
        $gd = $row['grant_delete'] ?? 0;
        $realm = $row['realm'] ?? '';
        $result[] = [
          'realm' => is_scalar($realm) || $realm instanceof \Stringable ? (string) $realm : '',
          'gid' => is_numeric($gid) ? (int) $gid : 0,
          'grant_view' => is_numeric($gv) ? (int) $gv : 0,
          'grant_update' => is_numeric($gu) ? (int) $gu : 0,
          'grant_delete' => is_numeric($gd) ? (int) $gd : 0,
        ];
      }
    }
    return $result;
  }

  /**
   * NIDs que a listagem do Core devolveria para a conta.
   *
   * @return int[]
   *   IDs dos nós visíveis na consulta.
   */
  private function visibleNids(AccountInterface $account): array {
    $database = $this->container->get('database');
    $query = $database->select('node', 'n');
    $query->fields('n', ['nid']);
    $query->addTag('node_access');
    $query->addMetaData('account', $account);
    $stmt = $query->execute();
    $col = $stmt ? $stmt->fetchCol() : [];
    return array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $col);
  }

  /**
   * Cria uma conta ativa.
   */
  private function createUser(string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    return $user;
  }

  /**
   * Cria um tópico publicado.
   */
  private function createTopic(string $title, User $author): NodeInterface {
    $topic = $this->container->get('entity_type.manager')->getStorage('node')
      ->create([
        'type' => 'aculta_forum_topic',
        'title' => $title,
        'status' => 1,
        'uid' => $author->id(),
      ]);
    $topic->save();
    return $topic;
  }

  /**
   * Cria um Group do tipo configurado.
   */
  private function createGroup(string $label, User $owner): GroupInterface {
    $group = $this->container->get('entity_type.manager')->getStorage('group')
      ->create([
        'type' => self::GROUP_TYPE,
        'label' => $label,
        'uid' => $owner->id(),
      ]);
    self::assertInstanceOf(GroupInterface::class, $group);
    $group->save();
    return $group;
  }

  /**
   * Adiciona a conta ao Group com a role que lê o conteúdo agrupado.
   */
  private function addMember(GroupInterface $group, User $user): void {
    $group->addMember($user, [
      'group_roles' => [self::GROUP_TYPE . '-membro'],
    ]);
  }

  /**
   * Relaciona o tópico ao Group como conteúdo dele.
   */
  private function restrictTopic(NodeInterface $topic, GroupInterface $group): void {
    $group->addRelationship($topic, 'group_node:aculta_forum_topic')->save();
  }

  /**
   * Grava ou atualiza a situação de associado da conta.
   */
  private function setSituation(User $user, string $situation): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_associado');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $user->id())
      ->execute();
    if ($ids === []) {
      $storage->create([
        'uid' => $user->id(),
        'situacao' => $situation,
      ])->save();
      return;
    }
    $marker = $storage->load(reset($ids));
    self::assertInstanceOf(\Drupal\Core\Entity\FieldableEntityInterface::class, $marker);
    $marker->set('situacao', $situation);
    $marker->save();
  }

}
