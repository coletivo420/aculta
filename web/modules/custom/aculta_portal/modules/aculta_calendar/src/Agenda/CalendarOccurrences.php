<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Agenda;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_calendar\Recurrence\RecurrenceExpander;

/**
 * Ocorrências de um item de calendário na janela (motor de recorrência, CA1).
 */
final class CalendarOccurrences {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly RecurrenceExpander $expansor = new RecurrenceExpander(),
  ) {}

  /**
   * Ocorrências do item em [$de, $ate].
   *
   * @return array<int, array{inicio: \DateTimeImmutable, fim: \DateTimeImmutable,
   *   original: \DateTimeImmutable, cancelada: bool}>
   *   Expanded or adjusted occurrences within the requested window.
   *
   * @throws \Drupal\aculta_calendar\Recurrence\RecurrenceException
   */
  public function expandir(CalendarItem $item, CalendarQuery $query): array {
    $fuso = new \DateTimeZone($item->fuso);
    $inicio = $item->inicio->setTimezone($fuso);
    $fim = $item->fim->setTimezone($fuso);

    if ($item->rrule === NULL || $item->rrule === '') {
      // Same overlap semantics as the providers: start <= ate and end >= de.
      if ($fim < $query->de || $inicio > $query->ate) {
        return [];
      }
      return [[
        'inicio' => $inicio,
        'fim' => $fim,
        'original' => $inicio,
        'cancelada' => $item->cancelado,
      ],
      ];
    }

    $duracao = $fim->getTimestamp() - $inicio->getTimestamp();
    $limite = $query->agora->setTimezone($fuso)->modify('+' . $item->limiteMeses . ' months');
    return $this->expansor->expandir($item->rrule, $inicio, $duracao, $query->de, $query->ate, $limite, $this->excecoes($item));
  }

  /**
   * Exceções do item, indexadas pelo timestamp do início original.
   *
   * @return array<int, array{inicio: ?\DateTimeImmutable,
   *   fim: ?\DateTimeImmutable}>
   *   Overrides indexed by the original occurrence timestamp.
   */
  private function excecoes(CalendarItem $item): array {
    $mapa = [];
    foreach ($item->excecoes as $cancelada) {
      $mapa[$cancelada->getTimestamp()] = ['inicio' => NULL, 'fim' => NULL];
    }
    foreach ($item->reagendadas as $reagendada) {
      $mapa[$reagendada['original']->getTimestamp()] = [
        'inicio' => $reagendada['inicio'],
        'fim' => $reagendada['fim'],
      ];
    }
    return $mapa;
  }

}
