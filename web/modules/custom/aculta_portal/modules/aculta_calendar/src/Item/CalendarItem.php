<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Item;

/**
 * Item de calendário, independente do módulo dono do dado (ADR-031).
 *
 * Com `rrule`, o item é uma série; sem ela, é uma ocorrência única.
 * O calendário
 * expande a série na leitura, usando o motor de recorrência.
 */
final class CalendarItem {

  /**
   * Construtor.
   *
   * @param string $id
   *   Chave estável do item (compõe o UID do .ics). Ex.: "eventdate:123".
   * @param string $titulo
   *   Título exibido.
   * @param \DateTimeImmutable $inicio
   *   Início da primeira ocorrência.
   * @param \DateTimeImmutable $fim
   *   Fim da primeira ocorrência.
   * @param bool $diaInteiro
   *   TRUE quando o horário não importa.
   * @param string $fuso
   *   Fuso do item; o site usa America/Sao_Paulo.
   * @param string $local
   *   Local em texto; vazio omite.
   * @param string|null $link
   *   URL da página do item, quando houver.
   * @param string $origem
   *   Identificador do provedor que gerou o item.
   * @param string|null $rrule
   *   Regra RRULE normalizada; NULL em ocorrência única.
   * @param \DateTimeImmutable[] $excecoes
   *   Inícios originais cancelados (EXDATE).
   * @param array<int, array{original: \DateTimeImmutable, inicio: \DateTimeImmutable, fim: ?\DateTimeImmutable}> $reagendadas
   *   Ocorrências com novo horário (RECURRENCE-ID).
   * @param bool $cancelado
   *   TRUE quando o dono do dado marcou o item como cancelado.
   * @param int $limiteMeses
   *   Horizonte de série aberta, em meses a partir de agora.
   */
  public function __construct(
    public readonly string $id,
    public readonly string $titulo,
    public readonly \DateTimeImmutable $inicio,
    public readonly \DateTimeImmutable $fim,
    public readonly bool $diaInteiro = FALSE,
    public readonly string $fuso = 'America/Sao_Paulo',
    public readonly string $local = '',
    public readonly ?string $link = NULL,
    public readonly string $origem = '',
    public readonly ?string $rrule = NULL,
    public readonly array $excecoes = [],
    public readonly array $reagendadas = [],
    public readonly bool $cancelado = FALSE,
    public readonly int $limiteMeses = 12,
  ) {}

}
