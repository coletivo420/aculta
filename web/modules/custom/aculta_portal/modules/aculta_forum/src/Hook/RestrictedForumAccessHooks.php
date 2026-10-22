<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\RestrictedForum\RestrictedForumAccessPolicy;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Acesso e grants de leitura dos tópicos de fórum restrito.
 *
 * Três camadas, todas no servidor e sem depender de link escondido, tema ou
 * JavaScript:
 *
 * - `entity_access`: nega leitura de tópico restrito a quem não é associado
 *   `ativo` com membership no Group do tópico, inclusive em URL direta. Não
 *   concede: a conta autorizada recebe neutro e o Core decide o resto.
 * - `node_grants`: informa os Groups do tipo configurado de que a conta é
 *   membro ativa, para as consultas do Core filtrarem listagens e buscas.
 * - `node_access_records`: grava, por tópico restrito, um grant de leitura por
 *   Group. Sem Group restrito devolve vazio, e o Core grava o grant público
 *   padrão. Atualizar e apagar continuam delegados às permissões existentes.
 *
 * O realm é próprio do Fórum e o gid é o ID do Group, então não colide com
 * grants de LMS ou de outro módulo.
 *
 * Operação de instalação/ativação: como o Core passa a usar o sistema de
 * grants assim que existe implementação de `node_grants`, ativar o recurso
 * exige `node_access_rebuild()` para que grants por nó substituam o grant
 * global `nid = 0` e as listagens passem a ser filtradas. Documentado em
 * `docs/RESTRICTED-FORUMS.md`.
 */
final class RestrictedForumAccessHooks {

  /**
   * Realm dos grants do Fórum restrito. */
  public const REALM = 'aculta_forum_restricted';

  /**
   * Categoria de tópico do Fórum. */
  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  public function __construct(
    private readonly RestrictedForumGroupResolverInterface $groupResolver,
    private readonly RestrictedForumAccessPolicy $accessPolicy,
    private readonly ActiveAssociateInterface $activeAssociate,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== self::TOPIC_BUNDLE || $operation !== 'view') {
      return NULL;
    }
    return $this->accessPolicy->access($entity, $account, $operation);
  }

  /**
   * Implements hook_node_grants().
   *
   * Só `view`: atualizar/apagar tópico continua nas permissões existentes.
   * Sem tipo de Group configurado, sem associação ativa ou sem membership, não
   * há grant. Falha de consulta também não concede (fail closed).
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $operation): array {
    if ($operation !== 'view' || $account->isAnonymous()) {
      return [];
    }
    try {
      if (!$this->activeAssociate->isActiveAssociate($account)) {
        return [];
      }
      $groups = $this->groupResolver->groupsOfType();
      if ($groups === []) {
        return [];
      }
      $gids = [];
      foreach ($groups as $gid => $group) {
        if ($group->getMember($account) !== FALSE) {
          $gids[(int) $gid] = (int) $gid;
        }
      }
      if ($gids === []) {
        return [];
      }
      ksort($gids);
      return [self::REALM => array_values($gids)];
    }
    catch (\Throwable $e) {
      $this->logger->error('Falha ao calcular grants de fórum restrito do uid @uid: @message', [
        '@uid' => (string) $account->id(),
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Implements hook_node_access_records().
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    if ($node->bundle() !== self::TOPIC_BUNDLE) {
      return [];
    }
    try {
      $groupIds = $this->groupResolver->groupIdsForTopic($node);
    }
    catch (\Throwable $e) {
      $this->logger->error('Falha ao gravar grants do tópico @nid; conteúdo fica sem leitura até nova gravação: @message', [
        '@nid' => (string) $node->id(),
        '@message' => $e->getMessage(),
      ]);
      // Grant de gid 0 não é devolvido por node_grants(): ninguém casa, e o
      // tópico não recebe o grant público padrão. Fail closed.
      return [[
        'realm' => self::REALM,
        'gid' => 0,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ],
      ];
    }
    $grants = [];
    foreach ($groupIds as $gid) {
      $grants[] = [
        'realm' => self::REALM,
        'gid' => (int) $gid,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ];
    }
    return $grants;
  }

}
