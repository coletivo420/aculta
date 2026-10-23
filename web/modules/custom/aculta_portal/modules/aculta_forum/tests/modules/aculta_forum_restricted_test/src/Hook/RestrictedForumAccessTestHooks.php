<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_restricted_test\Hook;

use Drupal\aculta_forum\Hook\RestrictedForumAccessHooks;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Expõe os hooks de produção a partir do fixture, sem habilitar aculta_forum.
 *
 * O coletor de hooks descobre classes pelo caminho do arquivo em `src/Hook`,
 * então uma classe de `Drupal\aculta_forum` não seria encontrada quando só o
 * fixture está instalado. Este adaptador registra os três hooks no módulo do
 * fixture e delega integralmente para `RestrictedForumAccessHooks`, que
 * continua sendo a única implementação exercitada.
 */
final class RestrictedForumAccessTestHooks {

  public function __construct(
    private readonly RestrictedForumAccessHooks $delegate,
  ) {}

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    return $this->delegate->entityAccess($entity, $operation, $account);
  }

  /**
   * Implements hook_node_grants().
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $operation): array {
    return $this->delegate->nodeGrants($account, $operation);
  }

  /**
   * Implements hook_node_access_records().
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    return $this->delegate->nodeAccessRecords($node);
  }

}
