<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Calendar;

/**
 * Transforma entradas da agenda (AgendaEntriesProvider) em séries do IcsEventBuilder::buildMany().
 *
 * Chave estável por entrada (tipo, turma, título e início), para o UID não mudar entre exportações.
 */
final class PersonalCalendarSeries {

  /**
   * Séries para o .ics, uma por entrada.
   *
   * @param array<int, array{tipo: string, titulo: string, inicio: \DateTimeImmutable, fim: \DateTimeImmutable, cancelada: bool, curso: int|string, turma: int|string}> $entradas
   *
   * @return array<int, array<string, mixed>>
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
