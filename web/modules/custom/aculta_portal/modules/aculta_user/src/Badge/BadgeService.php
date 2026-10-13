<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Badge;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_user\Entity\BadgeAward;

/**
 * Concede e revoga troféus. Idempotente: não duplica concessão ativa.
 */
final class BadgeService {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Concede o troféu se a pessoa ainda não o tem ativo. Retorna TRUE se concedeu agora. */
  public function award(int $uid, string $badge, ?int $grantedBy = NULL): bool {
    if ($uid <= 0 || !isset(BadgeCatalog::all()[$badge]) || $this->hasActive($uid, $badge)) {
      return FALSE;
    }
    $this->storage()->create([
      'uid' => $uid,
      'badge' => $badge,
      'origem' => BadgeCatalog::all()[$badge]['origem'],
      'concedido_em' => time(),
      'concedido_por' => $grantedBy,
    ])->save();
    return TRUE;
  }

  /** Revoga o troféu manual ativo. Automáticas não são revogadas. Retorna TRUE se revogou. */
  public function revoke(int $uid, string $badge, int $revokedBy): bool {
    if (!BadgeCatalog::isManual($badge)) {
      return FALSE;
    }
    $changed = FALSE;
    foreach ($this->activeAwards($uid, $badge) as $award) {
      $award->set('revogado_em', time());
      $award->set('revogado_por', $revokedBy);
      $award->save();
      $changed = TRUE;
    }
    return $changed;
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
