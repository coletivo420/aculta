<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Página provisória do purpose eventos (F1). A F3 substitui pelo catálogo.
 *
 * O noindex do ambiente de teste vem da política do deployer e do vhost, não
 * desta página. Em produção a página segue indexável, como a política define.
 */
final class EventosController extends ControllerBase {

  /**
   * Página inicial provisória do host de eventos.
   */
  public function home(): array {
    return [
      '#markup' => '<p>' . $this->t('Eventos da ACULTA em breve.') . '</p>',
    ];
  }

}
