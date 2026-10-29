<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Agenda;

use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

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
    private readonly ?LoggerChannelFactoryInterface $logger = NULL,
  ) {}

  /**
   * Ocorrências da janela, agrupadas por dia no fuso do item.
   *
   * @return array<string, array<int, array{titulo: string, inicio: \DateTimeImmutable,
   *   fim: \DateTimeImmutable, local: string, diaInteiro: bool, cancelada: bool}>>
   *   Occurrences grouped by local calendar date.
   */
  public function dias(CalendarQuery $query): array {
    $grupos = [];
    foreach ($this->fonte->items($query) as $item) {
      try {
        $ocorrencias = $this->ocorrencias->expandir($item, $query);
      }
      catch (\Throwable $e) {
        // One broken series must not take down the whole agenda. The
        // interactive view already isolates the same failure per item.
        $this->logger?->get('aculta_calendar')->warning('Item @id não expandido: @mensagem', [
          '@id' => $item->id,
          '@mensagem' => $e->getMessage(),
        ]);
        continue;
      }
      foreach ($ocorrencias as $ocorrencia) {
        $grupos[$ocorrencia['inicio']->format('Y-m-d')][] = [
          'titulo' => $item->titulo,
          'inicio' => $ocorrencia['inicio'],
          'fim' => $ocorrencia['fim'],
          'local' => $item->local,
          'diaInteiro' => $item->diaInteiro,
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
