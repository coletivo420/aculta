<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_messenger\Aviso\AvisosResumo;

/**
 * Cron e mensagem do resumo diário de avisos (ACULTA Messenger, F5.2).
 */
final class AvisosResumoHooks {

  public function __construct(
    private readonly AvisosResumo $resumo,
  ) {}

  /** Implements hook_cron(). */
  #[Hook('cron')]
  public function cron(): void {
    $this->resumo->enviarResumos();
  }

}
