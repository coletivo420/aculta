<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Agenda;

use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;

/**
 * Agenda do período: ocorrências dos itens, agrupadas por dia (CA4).
 *
 * Saída agrupada por dia em ordem; cancelados ficam marcados. A fonte decide
 * o conteúdo público; a agenda não conhece entidades de outras áreas.
 */
final class CalendarAgenda {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly CalendarItemSource $fonte,
    private readonly CalendarOccurrences $ocorrencias,
  ) {}

  /**
   * Ocorrências da janela, agrupadas por dia no fuso do item.
   *
   * @return array<string, array<int, array{titulo: string, inicio: \DateTimeImmutable,
   *   fim: \DateTimeImmutable, local: string, cancelada: bool}>>
   *   Occurrences grouped by local calendar date.
   */
  public function dias(CalendarQuery $query): array {
    $grupos = [];
    foreach ($this->fonte->items($query) as $item) {
      foreach ($this->ocorrencias->expandir($item, $query) as $ocorrencia) {
        $grupos[$ocorrencia['inicio']->format('Y-m-d')][] = [
          'titulo' => $item->titulo,
          'inicio' => $ocorrencia['inicio'],
          'fim' => $ocorrencia['fim'],
          'local' => $item->local,
          'cancelada' => $ocorrencia['cancelada'] || $item->cancelado,
        ];
      }
    }
    ksort($grupos);
    foreach ($grupos as &$itens) {
      usort($itens, static fn (array $a, array $b): int => $a['inicio'] <=> $b['inicio']);
    }
    return $grupos;
  }

}
