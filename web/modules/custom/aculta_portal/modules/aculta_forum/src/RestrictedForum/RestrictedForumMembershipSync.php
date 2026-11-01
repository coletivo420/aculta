<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\RestrictedForum;

use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Sincroniza membership de associados nos Groups restritos do Fórum.
 *
 * A situação `ativo` manda: ativação adiciona membership; suspensão,
 * desligamento e recusa removem. A operação é idempotente, protegida por lock
 * por conta e registrada no log do Fórum. Uma falha de Group não impede a
 * revogação de acesso, porque a checagem de acesso revalida a situação em
 * tempo real e nega associação não ativa mesmo com membership remanescente.
 *
 * Sem tipo de Group configurado, nada é sincronizado (recurso desligado).
 */
final class RestrictedForumMembershipSync {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RestrictedForumGroupResolverInterface $groupResolver,
    private readonly ActiveAssociateInterface $activeAssociate,
    private readonly LockBackendInterface $lock,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Reconcilia a membership de uma conta com a situação atual.
   *
   * @return array<int, string>
   *   Ação por Group: `added`, `removed`, `unchanged` ou `error`.
   */
  public function syncAccount(int $uid): array {
    if ($uid <= 0) {
      return [];
    }
    $groups = $this->groupResolver->groupsOfType();
    if ($groups === []) {
      return [];
    }
    $lockId = 'aculta_forum_membership_sync:' . $uid;
    if (!$this->lock->acquire($lockId, 5.0)) {
      $this->logger->warning('Sincronização de membership do fórum restrito já em andamento para o uid @uid; nova tentativa fica para a próxima alteração ou reconciliação.', ['@uid' => $uid]);
      return [];
    }

    $actions = [];
    try {
      $account = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!$account instanceof UserInterface) {
        $this->logger->error('Não foi possível sincronizar o fórum restrito: uid @uid não existe.', ['@uid' => $uid]);
        return [];
      }
      $active = $this->activeAssociate->isActiveAssociate($account);
      foreach ($groups as $gid => $group) {
        try {
          // getMember() devolve FALSE (nunca NULL) quando não há membership.
          $membership = $group->getMember($account);
          if ($active && $membership === FALSE) {
            $group->addMember($account);
            $actions[(int) $gid] = 'added';
            $this->logger->notice('Associado ativo uid @uid adicionado ao Group restrito @gid do Fórum.', [
              '@uid' => $uid,
              '@gid' => (string) $gid,
            ]);
          }
          elseif (!$active && $membership !== FALSE) {
            $group->removeMember($account);
            $actions[(int) $gid] = 'removed';
            $this->logger->notice('Membership do uid @uid removida do Group restrito @gid do Fórum por situação não ativa.', [
              '@uid' => $uid,
              '@gid' => (string) $gid,
            ]);
          }
          else {
            $actions[(int) $gid] = 'unchanged';
          }
        }
        catch (\Throwable $e) {
          $actions[(int) $gid] = 'error';
          $this->logger->error('Falha ao sincronizar uid @uid com o Group restrito @gid do Fórum: @message', [
            '@uid' => $uid,
            '@gid' => (string) $gid,
            '@message' => $e->getMessage(),
          ]);
        }
      }
      $changed = array_filter($actions, static fn (string $action): bool => in_array($action, ['added', 'removed'], TRUE));
      if ($changed !== []) {
        $this->cacheTagsInvalidator->invalidateTags($this->cacheTags($uid, array_keys($changed)));
      }
      return $actions;
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * Reconcilia todas as contas com linha de associação.
   *
   * @return array{accounts: int, changed: int, errors: int}
   *   Total de contas verificadas, memberships alteradas e falhas.
   */
  public function syncAll(): array {
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $summary = ['accounts' => 0, 'changed' => 0, 'errors' => 0];
    foreach ($storage->loadMultiple($ids) as $marca) {
      if (!$marca instanceof FieldableEntityInterface || !$marca->hasField('uid')) {
        continue;
      }
      $summary['accounts']++;
      foreach ($this->syncAccount($this->uid($marca)) as $action) {
        if (in_array($action, ['added', 'removed'], TRUE)) {
          $summary['changed']++;
        }
        elseif ($action === 'error') {
          $summary['errors']++;
        }
      }
    }
    return $summary;
  }

  /**
   * UID referenciado pela marca de associado.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $marca
   *   Marca de associado carregada.
   *
   * @return int
   *   UID da conta, ou 0 quando o campo está vazio.
   */
  private function uid(FieldableEntityInterface $marca): int {
    $values = $marca->get('uid')->getValue();
    return isset($values[0]['target_id']) ? (int) $values[0]['target_id'] : 0;
  }

  /**
   * Tags invalidadas quando a membership muda (acesso e listagens do Fórum).
   *
   * @param int $uid
   *   Conta cujo acesso foi recalculado.
   * @param int[] $groupIds
   *   Groups cuja membership mudou.
   *
   * @return string[]
   *   Cache tags a invalidar.
   */
  private function cacheTags(int $uid, array $groupIds): array {
    $tags = [
      'user:' . $uid,
      'aculta_associado_list',
      'group_relationship_list',
      'node_list',
    ];
    foreach ($groupIds as $gid) {
      $tags[] = 'group_relationship_list:plugin:group_membership:group:' . (int) $gid;
    }
    return array_values(array_unique($tags));
  }

}
