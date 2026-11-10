<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Busca executada no índice público do Fórum.
 *
 * Carrega apenas a quantidade de resultados. Nenhum termo, sessão, IP ou
 * identificador de pessoa é transportado; o consumidor decide se a coleta
 * está autorizada.
 */
final class ForumSearchEvent extends Event {

  /**
   * Nome do evento de busca executada.
   */
  public const EXECUTED = 'aculta_forum.search_executed';

  public function __construct(
    public readonly int $resultCount,
  ) {}

}
