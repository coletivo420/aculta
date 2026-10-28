<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Ics;

use Drupal\aculta_calendar\Agenda\CalendarOccurrences;
use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;

/**
 * Exporta itens de calendário em .ics, a partir dos provedores (CA2).
 *
 * Série sai como regra (RRULE, EXDATE e RECURRENCE-ID), sem expandir no arquivo
 * (RECORRENCIA.md, M5). Item cancelado sai com prefixo "[Cancelado]".
 */
final class CalendarIcsExporter {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly CalendarItemSource $fonte,
    private readonly CalendarOccurrences $ocorrencias,
  ) {}

  /**
   * Conteúdo do .ics para o período da consulta (UID com $dominioUid).
   */
  public function exportar(CalendarQuery $query, string $dominioUid): string {
    $series = [];
    foreach ($this->fonte->items($query) as $item) {
      if ($this->ocorrencias->expandir($item, $query) === []) {
        continue;
      }
      $series[] = $this->serie($item, $query->agora);
    }
    // O domínio do UID vem do host da requisição: é estável por ambiente e não fica fixo no código.
    return (new IcsEventBuilder($dominioUid))->buildMany($series);
  }

  /**
   * Conteúdo do .ics de um item, com a regra da série quando houver.
   */
  public function exportarItem(CalendarItem $item, \DateTimeImmutable $agora, string $dominioUid): string {
    return (new IcsEventBuilder($dominioUid))->buildMany([$this->serie($item, $agora)]);
  }

  /**
   * Série pronta para o IcsEventBuilder.
   *
   * @return array<string, mixed>
   */
  private function serie(CalendarItem $item, \DateTimeImmutable $agora): array {
    $fuso = new \DateTimeZone($item->fuso);
    $titulo = $item->cancelado ? '[Cancelado] ' . $item->titulo : $item->titulo;
    return [
      'key' => $item->id,
      'summary' => $titulo,
      'location' => $item->local,
      'start' => $item->inicio->setTimezone($fuso),
      'end' => $item->fim->setTimezone($fuso),
      'allDay' => $item->diaInteiro,
      'stamp' => $agora,
      'rrule' => $item->rrule !== '' ? $item->rrule : NULL,
      'excecoes' => $item->excecoes,
      'reagendadas' => $item->reagendadas,
    ];
  }

}
