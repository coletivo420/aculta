<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Public contract for user-controlled notice email channels.
 */
interface AvisoPreferenciasInterface {

  /**
   * Checks whether the account includes a notice type in its digest. */
  public function tipoNoResumo(int $uid, string $tipo): bool;

  /**
   * Gets the account's email mode for a notice category. */
  public function emailMode(int $uid, string $category): string;

  /**
   * Saves direct, digest, or disabled email delivery for a category. */
  public function saveEmailMode(int $uid, string $category, string $mode): void;

}
