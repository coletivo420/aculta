<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Badge;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\user\UserInterface;

/**
 * Concede e revoga troféus. Idempotente: não duplica concessão ativa.
 */
final class BadgeService {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly AchievementDefinitions $definitions,
  ) {}

  /** Concede o troféu se a pessoa ainda não o tem ativo. Retorna TRUE se concedeu agora. */
  public function award(int $uid, string $badge, ?int $grantedBy = NULL, string $reason = ''): bool {
    $definition = $this->definitions->all()[$badge] ?? NULL;
    if ($definition === NULL || empty($definition['enabled']) || !$this->isEligible($uid)
      || ($grantedBy !== NULL && ($grantedBy <= 0 || !in_array('manual', $definition['triggers'], TRUE)))
      || ($grantedBy === NULL && $definition['triggers'] === ['manual'])) {
      return FALSE;
    }
    return $this->serialized($uid, $badge, function () use ($uid, $badge, $grantedBy, $definition, $reason): bool {
      if ($this->hasActive($uid, $badge)) {
        return FALSE;
      }
      $this->storage()->create([
        'uid' => $uid,
        'badge' => $badge,
        'origem' => $grantedBy !== NULL ? BadgeCatalog::MANUAL : $definition['origem'],
        'concedido_em' => $this->time->getCurrentTime(),
        'concedido_por' => $grantedBy,
        'motivo_concessao' => $grantedBy !== NULL ? mb_substr(trim($reason), 0, 2000) : NULL,
      ])->save();
      return TRUE;
    });
  }

  /** Revoga o troféu manual ativo. Automáticas não são revogadas. Retorna TRUE se revogou. */
  public function revoke(int $uid, string $badge, int $revokedBy, string $reason = ''): bool {
    if ($uid <= 0 || $revokedBy <= 0) {
      return FALSE;
    }
    return $this->serialized($uid, $badge, function () use ($uid, $badge, $revokedBy, $reason): bool {
      $changed = FALSE;
      foreach ($this->activeAwards($uid, $badge) as $award) {
        if ($award->get('origem')->value !== BadgeCatalog::MANUAL) {
          continue;
        }
        $award->set('revogado_em', $this->time->getCurrentTime());
        $award->set('revogado_por', $revokedBy);
        $award->set('motivo_revogacao', mb_substr(trim($reason), 0, 2000));
        $award->save();
        $changed = TRUE;
      }
      return $changed;
    });
  }

  /** Critérios configurados, com defaults estáveis para conquistas existentes. */
  public function definitions(): array {
    return $this->definitions->all();
  }

  /** Cada evento já validado pelo dono da fonte chama a mesma API idempotente. */
  public function awardForTrigger(int $uid, string $trigger): void {
    if ($trigger === 'manual' || !isset(AchievementDefinitions::TRIGGERS[$trigger])) {
      return;
    }
    foreach ($this->definitions->all() as $id => $definition) {
      if (in_array($trigger, $definition['triggers'], TRUE)) {
        $this->award($uid, $id);
      }
    }
  }

  /** Contas históricas ou bloqueadas não participam de novas concessões. */
  public function isEligible(int $uid): bool {
    $user = $uid > 0 ? $this->entityTypeManager->getStorage('user')->load($uid) : NULL;
    return $user instanceof UserInterface && $user->isActive() && !$user->hasRole('usuarios_legados_forum');
  }

  /**
   * Serializa mutações pela API, mantendo o lock até o commit externo.
   *
   * Hooks de entidade já executam em transação. Liberar ao sair de award()
   * permitiria outra requisição consultar antes do commit do comentário/perfil.
   * Escritas diretas no storage não fazem parte deste contrato.
   */
  private function serialized(int $uid, string $badge, callable $operation): bool {
    $name = 'aculta_user:badge:' . $uid . ':' . $badge;
    if (!$this->lock->acquire($name, 120.0)) {
      $this->lock->wait($name, 1);
      if (!$this->lock->acquire($name, 120.0)) {
        throw new \RuntimeException('Concessão ocupada; tente novamente.');
      }
    }
    $manager = $this->database->transactionManager();
    $deferred = $manager->inTransaction();
    if ($deferred) {
      $manager->addPostTransactionCallback(function (bool $success) use ($name): void {
        $this->lock->release($name);
      });
    }
    try {
      return $operation();
    }
    finally {
      if (!$deferred) {
        $this->lock->release($name);
      }
    }
  }

  public function hasActive(int $uid, string $badge): bool {
    return $this->activeAwards($uid, $badge) !== [];
  }

  /** Troféus ativos da pessoa, mais antigos primeiro. @return \Drupal\aculta_user\Entity\BadgeAward[] */
  public function activeFor(int $uid): array {
    $ids = $this->storage()->getQuery()->accessCheck(FALSE)->condition('uid', $uid)->condition('revogado_em', NULL, 'IS NULL')->sort('concedido_em')->execute();
    return array_values($this->storage()->loadMultiple($ids));
  }

  /** @return \Drupal\aculta_user\Entity\BadgeAward[] */
  private function activeAwards(int $uid, string $badge): array {
    $ids = $this->storage()->getQuery()->accessCheck(FALSE)
      ->condition('uid', $uid)->condition('badge', $badge)->condition('revogado_em', NULL, 'IS NULL')->execute();
    return array_values($this->storage()->loadMultiple($ids));
  }

  private function storage() {
    return $this->entityTypeManager->getStorage('aculta_badge_award');
  }

}
