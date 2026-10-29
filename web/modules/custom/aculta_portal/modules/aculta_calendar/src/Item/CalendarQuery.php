<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Item;

/**
 * Consulta de itens de calendário: janela, referência e filtros do provedor.
 */
final class CalendarQuery {

  /**
   * Construtor.
   *
   * @param \DateTimeImmutable $de
   *   Início da janela, inclusivo.
   * @param \DateTimeImmutable $ate
   *   Fim da janela, inclusivo.
   * @param \DateTimeImmutable $agora
   *   Referência para o horizonte de série aberta.
   * @param array<string, int> $filtros
   *   Filtros do provedor, por exemplo tipo_local; suas chaves variam.
   */
  public function __construct(
    public readonly \DateTimeImmutable $de,
    public readonly \DateTimeImmutable $ate,
    public readonly \DateTimeImmutable $agora,
    public readonly array $filtros = [],
  ) {}

}
