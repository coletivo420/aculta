<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Sales;

/**
 * Filtros do relatório de vendas: intervalo de datas, loja e granularidade.
 *
 * Os parâmetros da URL são em português (de, ate, loja, granularidade). Datas inválidas ou intervalos
 * maiores que MAX_DIAS voltam ao padrão (últimos 30 dias) e registram o erro em $erros, sem exceção.
 */
final class SalesReportFilters {

  public const MAX_DIAS = 366;

  public const GRANULARIDADES = ['dia', 'semana', 'mes'];

  /**
   * @param \DateTimeImmutable $inicio
   *   Início do intervalo, no fuso do site, às 00:00.
   * @param \DateTimeImmutable $fim
   *   Fim do intervalo, no fuso do site, às 00:00 do último dia (incluído).
   */
  public function __construct(
    public readonly \DateTimeImmutable $inicio,
    public readonly \DateTimeImmutable $fim,
    public readonly ?int $lojaId,
    public readonly string $granularidade,
    public readonly array $erros = [],
  ) {}

  /**
   * Monta os filtros a partir da query string.
   */
  public static function fromQuery(array $query, \DateTimeZone $fuso, \DateTimeImmutable $hoje): self {
    $erros = [];
    $fim = self::data($query['ate'] ?? NULL, $fuso);
    $inicio = self::data($query['de'] ?? NULL, $fuso);

    if ($fim === NULL) {
      $fim = $hoje->setTimezone($fuso)->setTime(0, 0);
    }
    if ($inicio === NULL) {
      $inicio = $fim->modify('-29 days');
    }
    if ($inicio > $fim) {
      $erros[] = 'intervalo_invertido';
      $inicio = $fim->modify('-29 days');
    }
    // Diferença em dias entre início e fim, incluído o último dia.
    if ($inicio->diff($fim)->days + 1 > self::MAX_DIAS) {
      $erros[] = 'intervalo_grande';
      $inicio = $fim->modify('-' . (self::MAX_DIAS - 1) . ' days');
    }

    $loja = isset($query['loja']) && ctype_digit((string) $query['loja']) && (int) $query['loja'] > 0
      ? (int) $query['loja']
      : NULL;
    $granularidade = in_array($query['granularidade'] ?? '', self::GRANULARIDADES, TRUE)
      ? (string) $query['granularidade']
      : 'dia';

    return new self($inicio, $fim, $loja, $granularidade, $erros);
  }

  /**
   * Timestamps inclusivos do intervalo (início do primeiro dia até o fim do último).
   *
   * @return array{0: int, 1: int}
   */
  public function timestamps(): array {
    return [
      $this->inicio->getTimestamp(),
      $this->fim->modify('+1 day')->getTimestamp() - 1,
    ];
  }

  /**
   * Deslocamento do fuso em segundos, usado para agrupar por dia local no banco.
   *
   * Usa o desvio do fim do intervalo. Fusos com horário de verão mudam o desvio dentro do intervalo;
   * o site opera em fuso sem horário de verão, e isso fica registrado no CHANGELOG.
   */
  public function deslocamentoSegundos(): int {
    return $this->fim->getOffset();
  }

  /**
   * Converte a data "AAAA-MM-DD" no fuso do site; NULL se for inválida.
   */
  private static function data(?string $valor, \DateTimeZone $fuso): ?\DateTimeImmutable {
    if ($valor === NULL || $valor === '') {
      return NULL;
    }
    $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor, $fuso);
    if ($data === FALSE || $data->format('Y-m-d') !== $valor) {
      return NULL;
    }
    return $data;
  }

}
