<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Calendar\StudioDashboardCalendarComposer;
use Symfony\Component\HttpFoundation\Request;

/**
 * Endpoint de fragmento HTMX para o card de calendário do Studio (Fase 3).
 */
final class StudioCalendarController {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly StudioDashboardCalendarComposer $composer,
  ) {}

  /**
   * Retorna o componente studio-calendar renderizado.
   */
  public function fragment(Request $request): array {
    $ano = $request->query->has('ano') ? (int) $request->query->get('ano') : NULL;
    $mes = $request->query->has('mes') ? (int) $request->query->get('mes') : NULL;
    $filtro = $request->query->has('filtro') ? (string) $request->query->get('filtro') : NULL;
    $dia = $request->query->has('dia') ? (string) $request->query->get('dia') : NULL;

    return $this->composer->compose($ano, $mes, $filtro, $dia);
  }

}
