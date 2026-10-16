<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Memorial coletivo das contas históricas do Fórum em /u/lendashistoricas.
 *
 * F2 estrutural: página pública sem dados. A lista de autores comprovados e o texto
 * aprovado do memorial são pendências da F2 e entram em fases posteriores.
 */
final class LegacyMemorialController extends ControllerBase {

  /**
   * Renderiza o memorial com o placeholder de pendência.
   */
  public function view(): array {
    return [
      '#cache' => ['max-age' => 3600],
      'texto' => [
        '#markup' => (string) $this->t('Memorial da comunidade. O texto e a lista de autores estão pendentes de aprovação.'),
      ],
    ];
  }

}
