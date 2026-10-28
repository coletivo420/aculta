<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Cache;

/**
 * Tags de cache das listagens de eventos e datas.
 *
 * Usada pela listagem e pelo hook que a invalida. Centralizada para que o nome não
 * divirja entre quem declara a tag e quem a invalida.
 */
final class EventCacheTags {

  /**
   * Listagens de eventos e datas (catálogo, página e views de data).
   */
  public const LISTA = 'aculta_event_list';

}
