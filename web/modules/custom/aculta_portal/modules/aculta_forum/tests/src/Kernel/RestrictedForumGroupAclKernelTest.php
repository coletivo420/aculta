<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumAccessPolicy;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolver;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumMembershipSync;
use Drupal\aculta_user\Associado\AssociadoMarker;
use Drupal\aculta_user\Entity\AssociadoMarcador;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prova a ACL de Group do Fórum restrito e a revogação imediata.
 *
 * Os testes usam Group real e a marca de associado real, sem habilitar
 * `aculta_forum` nem `aculta_user`. As classes de produção são instanciadas
 * com os serviços do container e exercitadas pelos mesmos métodos chamados
 * pelos hooks, o que caracteriza o contrato sem alterar comportamento.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class RestrictedForumGroupAclKernelTest extends KernelTestBase {

  /**
   * Tipo de Group configurado para o fórum restrito.
   */
  private const GROUP_TYPE = 'associado_forum';

  /**
   * Tipo de Group que não restringe o fórum.
   */
  private const OTHER_GROUP_TYPE = 'outro_forum';

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
    foreach ([self::GROUP_TYPE, self::OTHER_GROUP_TYPE, 'lms_course'] as $type) {
      $group_type_storage->create([
        'id' => $type,
        'label' => $type,
        'creator_membership' => FALSE,
        'creator_wizard' => FALSE,
      ])->save();
    }

    $relationship_type_storage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $relationship_type_storage);
    foreach ([self::GROUP_TYPE, self::OTHER_GROUP_TYPE, 'lms_course'] as $type) {
      $relationship_type_storage->save(
        $relationship_type_storage->createFromPlugin(
          $group_type_storage->load($type),
          'group_node:aculta_forum_topic',
        ),
      );
    }

    $this->configureGroupType(self::GROUP_TYPE);
  }

  /**
   * Associado ativo e membro lê o tópico e recebe grant por Group.
   */
  public function testActiveMembershipKeepsNeutralAccessAndGrantsTheGroup(): void {
    $user = $this->createUser('associado_ativo');
    $this->setSituation($user, AssociadoMarker::ATIVO);
    $group = $this->createGroup(self::GROUP_TYPE, 'Fórum de associados', $user);
    $group->addMember($user);
    $topic = $this->createTopic('Tópico restrito', $user);
    $this->restrictTopic($topic, $group);
    $gid = (int) $group->id();

    $hooks = $this->hooks();
    $result = $hooks->entityAccess($topic, 'view', $user);

    self::assertInstanceOf(AccessResultInterface::class, $result);
    self::assertTrue($result->isNeutral());
    self::assertFalse($result->isForbidden());
    self::assertSame(
      ['user', 'domain'],
      $result->getCacheContexts(),
    );
    $tags = $result->getCacheTags();
    $expected_tags = [
      'config:aculta_forum.settings',
      'group_relationship_list',
      'node_list',
      'aculta_associado_list',
      'user:' . $user->id(),
      'node:' . $topic->id(),
    ];
    foreach ($expected_tags as $tag) {
      self::assertContains($tag, $tags);
    }

    self::assertSame(
      [RestrictedForumAccessHooks::REALM => [$gid]],
      $hooks->nodeGrants($user, 'view'),
    );

    self::assertSame(
      [[
        'realm' => RestrictedForumAccessHooks::REALM,
        'gid' => $gid,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ],
      ],
      $hooks->nodeAccessRecords($topic),
    );
  }

  /**
   * Associado ativo sem membership não lê o tópico.
   */
  public function testActiveAssociateWithoutMembershipIsForbidden(): void {
    $user = $this->createUser('associado_sem_grupo');
    $this->setSituation($user, AssociadoMarker::ATIVO);
    $owner = $this->createUser('dono_grupo');
    $group = $this->createGroup(self::GROUP_TYPE, 'Fórum de associados', $owner);
    $topic = $this->createTopic('Tópico restrito', $owner);
    $this->restrictTopic($topic, $group);

    $hooks = $this->hooks();
    self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isForbidden());
    self::assertSame([], $hooks->nodeGrants($user, 'view'));
  }

  /**
   * Membro de outro Group restrito não herda o acesso ao tópico alheio.
   */
  public function testMembershipInAnotherRestrictedGroupIsForbidden(): void {
    $user = $this->createUser('membro_outro_grupo');
    $this->setSituation($user, AssociadoMarker::ATIVO);
    $owner = $this->createUser('dono_grupos');
    $group_a = $this->createGroup(self::GROUP_TYPE, 'Grupo A', $owner);
    $group_b = $this->createGroup(self::GROUP_TYPE, 'Grupo B', $owner);
    $group_b->addMember($user);
    $topic = $this->createTopic('Tópico do grupo A', $owner);
    $this->restrictTopic($topic, $group_a);

    $hooks = $this->hooks();
    self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isForbidden());
    self::assertSame(
      [RestrictedForumAccessHooks::REALM => [(int) $group_b->id()]],
      $hooks->nodeGrants($user, 'view'),
    );
    self::assertSame(
      [(int) $group_a->id()],
      array_column($hooks->nodeAccessRecords($topic), 'gid'),
    );
  }

  /**
   * Revogação imediata: sem situação ativa, a membership não basta.
   */
  public function testRevocationIsImmediateWhileMembershipRemains(): void {
    $situations = [
      AssociadoMarker::PENDENTE,
      AssociadoMarker::CONVIDADO,
      AssociadoMarker::SUSPENSO,
      AssociadoMarker::DESLIGADO,
    ];
    $hooks = $this->hooks();
    foreach ($situations as $situation) {
      $user = $this->createUser('revogado_' . $situation);
      $marker = $this->setSituation($user, AssociadoMarker::ATIVO);
      $owner = $this->createUser('dono_' . $situation);
      $group = $this->createGroup(self::GROUP_TYPE, 'Grupo ' . $situation, $owner);
      $group->addMember($user);
      $topic = $this->createTopic('Tópico ' . $situation, $owner);
      $this->restrictTopic($topic, $group);

      self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isNeutral());

      $marker->set('situacao', $situation)->save();

      self::assertNotFalse($group->getMember($user), 'A membership permanece para provar que a revocation não depende dela.');
      self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isForbidden());
      self::assertSame([], $hooks->nodeGrants($user, 'view'));
    }

    $sem_marca = $this->createUser('sem_marca');
    $owner = $this->createUser('dono_sem_marca');
    $group = $this->createGroup(self::GROUP_TYPE, 'Grupo sem marca', $owner);
    $group->addMember($sem_marca);
    $topic = $this->createTopic('Tópico sem marca', $owner);
    $this->restrictTopic($topic, $group);

    self::assertTrue($hooks->entityAccess($topic, 'view', $sem_marca)?->isForbidden());
    self::assertSame([], $hooks->nodeGrants($sem_marca, 'view'));
  }

  /**
   * Reativação devolve o acesso sem exigir novo grant.
   */
  public function testReadmissionRestoresAccess(): void {
    $user = $this->createUser('readmitido');
    $marker = $this->setSituation($user, AssociadoMarker::SUSPENSO);
    $owner = $this->createUser('dono_readmitido');
    $group = $this->createGroup(self::GROUP_TYPE, 'Grupo readmissão', $owner);
    $group->addMember($user);
    $topic = $this->createTopic('Tópico readmissão', $owner);
    $this->restrictTopic($topic, $group);
    $gid = (int) $group->id();

    $hooks = $this->hooks();
    self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isForbidden());

    $marker->set('situacao', AssociadoMarker::ATIVO)->save();

    self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isNeutral());
    self::assertSame(
      [RestrictedForumAccessHooks::REALM => [$gid]],
      $hooks->nodeGrants($user, 'view'),
    );
  }

  /**
   * Anônimo é negado sem revelar o motivo, e os grants do tópico existem.
   */
  public function testAnonymousAccountIsForbidden(): void {
    $owner = $this->createUser('dono_anonimo');
    $this->setSituation($owner, AssociadoMarker::ATIVO);
    $group = $this->createGroup(self::GROUP_TYPE, 'Grupo anônimo', $owner);
    $topic = $this->createTopic('Tópico anônimo', $owner);
    $this->restrictTopic($topic, $group);

    $anonymous = new AnonymousUserSession();
    $hooks = $this->hooks();
    $result = $hooks->entityAccess($topic, 'view', $anonymous);

    self::assertTrue($result?->isForbidden());
    self::assertContains('user:0', $result->getCacheTags());
    self::assertSame([], $hooks->nodeGrants($anonymous, 'view'));
  }

  /**
   * Tópico sem Group restrito mantém a política pública do Core.
   */
  public function testTopicWithoutRestrictedGroupIsNeutral(): void {
    $user = $this->createUser('topico_publico');
    $topic = $this->createTopic('Tópico público', $user);
    $hooks = $this->hooks();

    self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isNeutral());
    self::assertSame([], $hooks->nodeGrants($user, 'view'));
    self::assertSame([], $hooks->nodeAccessRecords($topic));
  }

  /**
   * Group de outro tipo não restringe o tópico.
   */
  public function testGroupOfAnotherTypeDoesNotRestrict(): void {
    $user = $this->createUser('membro_outro_tipo');
    $this->setSituation($user, AssociadoMarker::ATIVO);
    $group = $this->createGroup(self::OTHER_GROUP_TYPE, 'Grupo de outro tipo', $user);
    $group->addMember($user);
    $topic = $this->createTopic('Tópico de outro tipo', $user);
    $this->restrictTopic($topic, $group);

    $hooks = $this->hooks();
    self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isNeutral());
    self::assertSame([], $hooks->nodeGrants($user, 'view'));
    self::assertSame([], $hooks->nodeAccessRecords($topic));
  }

  /**
   * Tipo de Group do LMS nunca é aceito como fórum restrito.
   */
  public function testLmsGroupTypeIsNeverAccepted(): void {
    $user = $this->createUser('membro_lms');
    $this->setSituation($user, AssociadoMarker::ATIVO);
    $group = $this->createGroup('lms_course', 'Curso', $user);
    $group->addMember($user);
    $topic = $this->createTopic('Tópico em curso', $user);
    $this->restrictTopic($topic, $group);

    $this->configureGroupType('lms_course');

    $resolver = $this->resolver();
    self::assertNull($resolver->configuredGroupType());

    $hooks = $this->hooks($resolver);
    self::assertTrue($hooks->entityAccess($topic, 'view', $user)?->isNeutral());
    self::assertSame([], $hooks->nodeGrants($user, 'view'));
    self::assertSame([], $hooks->nodeAccessRecords($topic));
  }

  /**
   * Falha do resolvedor nega o acesso e grava grant fechado de gid 0.
   */
  public function testResolverFailureFailsClosed(): void {
    $user = $this->createUser('falha_resolver');
    $this->setSituation($user, AssociadoMarker::ATIVO);
    $topic = $this->createTopic('Tópico com falha', $user);

    $failing = new class implements RestrictedForumGroupResolverInterface {

      /**
       * {@inheritdoc}
       */
      public function configuredGroupType(): ?string {
        return self::GROUP_TYPE;
      }

      /**
       * {@inheritdoc}
       */
      public function groupIdsForTopic(NodeInterface $topic): array {
        throw new \RuntimeException('Falha simulada de consulta.');
      }

      /**
       * {@inheritdoc}
       */
      public function groupsOfType(): array {
        throw new \RuntimeException('Falha simulada de consulta.');
      }

    };

    $hooks = $this->hooks($failing);
    $result = $hooks->entityAccess($topic, 'view', $user);

    self::assertTrue($result?->isForbidden());
    self::assertSame(0, $result->getCacheMaxAge());
    self::assertSame([], $hooks->nodeGrants($user, 'view'));
    self::assertSame(
      [[
        'realm' => RestrictedForumAccessHooks::REALM,
        'gid' => 0,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ],
      ],
      $hooks->nodeAccessRecords($topic),
    );
  }

  /**
   * Só `view` de tópico é decidido pelos hooks; o resto segue o Core.
   */
  public function testHooksOnlyHandleViewOfTopics(): void {
    $user = $this->createUser('escopo_hooks');
    $this->setSituation($user, AssociadoMarker::ATIVO);
    $topic = $this->createTopic('Tópico escopo', $user);
    $page = $this->container->get('entity_type.manager')->getStorage('node')
      ->create([
        'type' => 'aculta_page',
        'title' => 'Página comum',
        'status' => 1,
        'uid' => $user->id(),
      ]);
    $page->save();

    $hooks = $this->hooks();
    self::assertNull($hooks->entityAccess($topic, 'update', $user));
    self::assertNull($hooks->entityAccess($topic, 'delete', $user));
    self::assertNull($hooks->entityAccess($page, 'view', $user));
    self::assertSame([], $hooks->nodeGrants($user, 'update'));
    self::assertSame([], $hooks->nodeAccessRecords($page));
  }

  /**
   * Sincronização segue a situação e a membership reflete no Group real.
   */
  public function testMembershipSyncFollowsAssociateSituation(): void {
    $user = $this->createUser('sincronizado');
    $this->setSituation($user, AssociadoMarker::PENDENTE);
    $owner = $this->createUser('dono_sincronizado');
    $group = $this->createGroup(self::GROUP_TYPE, 'Grupo sincronização', $owner);
    $gid = (int) $group->id();

    $sync = $this->membershipSync();
    self::assertSame([$gid => 'unchanged'], $sync->syncAccount((int) $user->id()));
    self::assertFalse($group->getMember($user));

    $this->setSituation($user, AssociadoMarker::ATIVO);
    self::assertSame([$gid => 'added'], $sync->syncAccount((int) $user->id()));
    self::assertNotFalse($group->getMember($user));

    $this->setSituation($user, AssociadoMarker::SUSPENSO);
    self::assertSame([$gid => 'removed'], $sync->syncAccount((int) $user->id()));
    self::assertFalse($group->getMember($user));

    $this->setSituation($user, AssociadoMarker::ATIVO);
    self::assertSame([$gid => 'added'], $sync->syncAccount((int) $user->id()));
    self::assertNotFalse($group->getMember($user));

    self::assertSame([], $sync->syncAccount(0));
  }

  /**
   * Cria uma conta autenticada.
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
   * Cria um tópico do Fórum.
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
   * Cria um Group com membership criada por wizard desligada.
   */
  private function createGroup(string $type, string $label, User $owner): GroupInterface {
    $group = $this->container->get('entity_type.manager')->getStorage('group')
      ->create([
        'type' => $type,
        'label' => $label,
        'uid' => $owner->id(),
      ]);
    $group->save();
    return $group;
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
  private function setSituation(User $user, string $situation): AssociadoMarcador {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('uid', $user->id())
      ->execute();
    if ($ids === []) {
      $marker = $storage->create([
        'uid' => $user->id(),
        'situacao' => $situation,
      ]);
    }
    else {
      $marker = $storage->load(reset($ids));
      $marker->set('situacao', $situation);
    }
    $marker->save();
    return $marker;
  }

  /**
   * Define o tipo de Group configurado para o fórum restrito.
   */
  private function configureGroupType(string $type): void {
    $this->container->get('config.factory')
      ->getEditable('aculta_forum.settings')
      ->set('restricted_group_type', $type)
      ->save();
  }

  /**
   * Resolvedor real sobre os Groups desta instalação.
   */
  private function resolver(): RestrictedForumGroupResolver {
    return new RestrictedForumGroupResolver(
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->loggerChannel(),
    );
  }

  /**
   * Marca de associado real sobre as entidades desta instalação.
   */
  private function associado(): AssociadoMarker {
    return new AssociadoMarker(
      $this->container->get('entity_type.manager'),
      $this->container->get('datetime.time'),
    );
  }

  /**
   * Hooks de produção ligados ao resolvedor e à marca de associado.
   */
  private function hooks(
    ?RestrictedForumGroupResolverInterface $resolver = NULL,
  ): RestrictedForumAccessHooks {
    $resolver ??= $this->resolver();
    $associado = $this->associado();
    return new RestrictedForumAccessHooks(
      $resolver,
      new RestrictedForumAccessPolicy($resolver, $associado, $this->loggerChannel()),
      $associado,
      $this->loggerChannel(),
    );
  }

  /**
   * Sincronizador de membership de produção.
   */
  private function membershipSync(): RestrictedForumMembershipSync {
    return new RestrictedForumMembershipSync(
      $this->container->get('entity_type.manager'),
      $this->resolver(),
      $this->associado(),
      $this->container->get('lock'),
      $this->container->get('cache_tags.invalidator'),
      $this->loggerChannel(),
    );
  }

  /**
   * Canal de log do Fórum.
   */
  private function loggerChannel(): LoggerInterface {
    return $this->container->get('logger.factory')->get('aculta_forum');
  }

}
