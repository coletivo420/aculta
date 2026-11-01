<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Agenda;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;

/**
 * Filtro compartilhado da agenda e da exportação .ics (F5).
 *
 * Usa os mesmos parâmetros do catálogo: `tipo` (termo de aculta_event_type) e
 * `tipo_local` (termo de aculta_location_type). Sem filtro, tudo passa.
 */
final class EventFilter {

  /**
   * O evento e o local atendem aos filtros pedidos?
   *
   * Local sem tipo não passa quando o filtro de tipo de local está ativo.
   */
  public function aceita(Event $evento, ?EventLocation $local, ?int $tipo, ?int $tipoLocal): bool {
    if ($tipo !== NULL && (int) $evento->get('field_tipo')->target_id !== $tipo) {
      return FALSE;
    }
    if ($tipoLocal !== NULL && (int) ($local?->get('field_tipo')->target_id ?? 0) !== $tipoLocal) {
      return FALSE;
    }
    return TRUE;
  }

}
