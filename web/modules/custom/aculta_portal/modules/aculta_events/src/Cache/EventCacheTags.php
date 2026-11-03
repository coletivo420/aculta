<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Cache;

/**
 * Tags de cache das listagens de eventos e datas.
 *
 * Compartilhada pela listagem e pelo hook de invalidação para manter o nome
 * consistente.
 */
final class EventCacheTags {

  /**
   * Listagens de eventos e datas (catálogo, página e views de data).
   */
  public const LISTA = 'aculta_event_list';

}
