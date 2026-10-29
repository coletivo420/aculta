<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Calendar;

/**
 * Transforms agenda entries into event series for the ICS builder.
 *
 * Stable entry keys keep UIDs unchanged between exports.
 */
final class PersonalCalendarSeries {

  /**
   * Converts entries into one ICS event series each.
   *
   * @param array<int, array{tipo: string, titulo: string, inicio: \DateTimeImmutable, fim: \DateTimeImmutable, cancelada: bool, curso: int|string, turma: int|string}> $entradas
   *   Agenda entries projected by the provider.
   * @param \DateTimeImmutable $agora
   *   Timestamp used for the generated ICS event.
   *
   * @return array<int, array<string, mixed>>
   *   ICS event series.
   */
  public static function paraIcs(array $entradas, \DateTimeImmutable $agora): array {
    $series = [];
    foreach ($entradas as $entrada) {
      $titulo = $entrada['cancelada'] ? '[Cancelado] ' . $entrada['titulo'] : $entrada['titulo'];
      $series[] = [
        'key' => 'agenda:' . $entrada['tipo'] . ':' . $entrada['turma'] . ':' . md5($entrada['titulo']) . ':' . $entrada['inicio']->getTimestamp(),
        'summary' => $titulo,
        'location' => '',
        'start' => $entrada['inicio'],
        'end' => $entrada['tipo'] === 'evento' ? $entrada['fim'] : NULL,
        'allDay' => FALSE,
        'stamp' => $agora,
      ];
    }
    return $series;
  }

}
