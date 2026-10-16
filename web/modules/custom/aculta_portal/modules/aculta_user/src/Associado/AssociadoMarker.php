<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Associado;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Marca e desmarca associados na tabela própria (ACULTA Associados, marcador puro, 2026-10-16).
 *
 * Idempotente: marcar uma conta já marcada, ou desmarcar uma conta sem marca, não faz nada.
 */
final class AssociadoMarker {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Verdadeiro se a conta tem a marca de associado. */
  public function isAssociado(int $uid): bool {
    return $this->marcaDe($uid) !== NULL;
  }

  /** Uids marcados, em ordem crescente. */
  public function uids(): array {
    $ids = $this->entityTypeManager->getStorage('aculta_associado')->getQuery()->accessCheck(FALSE)->sort('uid')->execute();
    $marcas = $this->entityTypeManager->getStorage('aculta_associado')->loadMultiple($ids);
    return array_values(array_map(static fn ($m) => (int) $m->get('uid')->target_id, $marcas));
  }

  /** Marca a conta. Retorna TRUE se a marca foi criada agora. */
  public function mark(int $uid, int $concedidoPor): bool {
    if ($uid <= 0 || $this->isAssociado($uid)) {
      return FALSE;
    }
    $this->entityTypeManager->getStorage('aculta_associado')->create([
      'uid' => $uid,
      'concedido_por' => $concedidoPor,
    ])->save();
    $this->invalidar($uid);
    return TRUE;
  }

  /** Remove a marca. Retorna TRUE se havia marca. */
  public function unmark(int $uid): bool {
    $marca = $this->marcaDe($uid);
    if ($marca === NULL) {
      return FALSE;
    }
    $marca->delete();
    $this->invalidar($uid);
    return TRUE;
  }

  /** Marca mudou: listagens e páginas que dependem do acesso da conta são recalculadas. */
  private function invalidar(int $uid): void {
    Cache::invalidateTags(['user:' . $uid, 'node_list', 'taxonomy_term_list']);
  }

  private function marcaDe(int $uid): ?object {
    if ($uid <= 0) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uid', $uid)->range(0, 1)->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

}
