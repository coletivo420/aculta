<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Agenda;

/**
 * Grade de um mês (F5), independente de entidade e de renderização.
 *
 * Semanas começam na segunda (ISO 8601). Dias de outros meses entram como célula
 * vazia, para a grade fechar em sete colunas, sem conteúdo fora do mês.
 */
final class MonthGrid {

  /**
   * Nomes dos meses em português, indexados de 1 a 12.
   */
  private const MESES = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];

  /**
   * Monta a grade do mês.
   *
   * @param int $ano
   *   Ano com quatro dígitos.
   * @param int $mes
   *   Mês de 1 a 12.
   * @param array<string, array<int, array<string, mixed>>> $porDia
   *   Itens por chave Y-m-d (saída de EventAgenda::dias()).
   * @param \DateTimeImmutable $hoje
   *   Data de referência em horário de Brasília, para marcar o dia de hoje.
   *
   * @return array{ano: int, mes: int, rotulo: string, semanas: array<int, array<int, array{dia: ?\DateTimeImmutable, itens: array, hoje: bool}>>, quantidade: int, anterior: array{ano: int, mes: int}, seguinte: array{ano: int, mes: int}}
   *   Grade pronta, com o total de ocorrências e os meses vizinhos.
   */
  public function build(int $ano, int $mes, array $porDia, \DateTimeImmutable $hoje): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $primeiro = new \DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes), $fuso);
    $ultimo = $primeiro->modify('last day of this month');

    // Recua até a segunda e avança até o domingo, para fechar as semanas.
    $cursor = $primeiro->modify('-' . ((int) $primeiro->format('N') - 1) . ' days');
    $fim = $ultimo->modify('+' . (7 - (int) $ultimo->format('N')) . ' days');
    $hojeChave = $hoje->setTimezone($fuso)->format('Y-m-d');

    $semanas = [];
    $quantidade = 0;
    while ($cursor <= $fim) {
      $semana = [];
      for ($coluna = 0; $coluna < 7; $coluna++) {
        $chave = $cursor->format('Y-m-d');
        $noMes = (int) $cursor->format('n') === $mes && (int) $cursor->format('Y') === $ano;
        $itens = $noMes ? ($porDia[$chave] ?? []) : [];
        $quantidade += count($itens);
        $semana[] = [
          'dia' => $noMes ? $cursor : NULL,
          'itens' => $itens,
          'hoje' => $chave === $hojeChave,
        ];
        $cursor = $cursor->modify('+1 day');
      }
      $semanas[] = $semana;
    }

    $anterior = $primeiro->modify('-1 month');
    $seguinte = $primeiro->modify('+1 month');
    return [
      'ano' => $ano,
      'mes' => $mes,
      'rotulo' => ucfirst(self::MESES[$mes]) . ' de ' . $ano,
      'semanas' => $semanas,
      'quantidade' => $quantidade,
      'anterior' => ['ano' => (int) $anterior->format('Y'), 'mes' => (int) $anterior->format('n')],
      'seguinte' => ['ano' => (int) $seguinte->format('Y'), 'mes' => (int) $seguinte->format('n')],
    ];
  }

}
