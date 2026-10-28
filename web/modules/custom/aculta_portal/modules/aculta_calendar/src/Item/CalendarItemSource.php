<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Item;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Agrega os provedores de itens registrados (tag aculta_calendar.item_provider).
 *
 * Um provedor com defeito não derruba a agenda: a falha é registrada no log e
 * os demais provedores continuam.
 */
final class CalendarItemSource {

  /**
   * Construtor.
   *
   * @param iterable<CalendarItemProviderInterface> $provedores
   *   Provedores coletados pela tag.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface|null $logger
   *   Fábrica de log; opcional para uso isolado em testes.
   */
  public function __construct(
    private readonly iterable $provedores,
    private readonly ?LoggerChannelFactoryInterface $logger = NULL,
  ) {}

  /**
   * Todos os itens dos provedores para a consulta.
   *
   * @return array<int, \Drupal\aculta_calendar\Item\CalendarItem>
   */
  public function items(CalendarQuery $query): array {
    $itens = [];
    foreach ($this->provedores as $provedor) {
      try {
        foreach ($provedor->items($query) as $item) {
          $itens[] = $item;
        }
      }
      catch (\Throwable $e) {
        $this->registrarFalha($provedor, $e);
      }
    }
    return $itens;
  }

  /**
   * Definições de filtro agregadas.
   *
   * Chaves iguais entre provedores são mescladas, com as opções de todos.
   *
   * @return array<string, array{label: string, opcoes: array<int, string>}>
   */
  public function filtros(): array {
    $filtros = [];
    foreach ($this->provedores as $provedor) {
      try {
        foreach ($provedor->filtros() as $chave => $definicao) {
          if (!isset($filtros[$chave])) {
            $filtros[$chave] = $definicao;
            continue;
          }
          $filtros[$chave]['opcoes'] = array_replace($filtros[$chave]['opcoes'], $definicao['opcoes'] ?? []);
        }
      }
      catch (\Throwable $e) {
        $this->registrarFalha($provedor, $e);
      }
    }
    return $filtros;
  }

  /**
   * Tags de cache agregadas dos provedores.
   *
   * @return string[]
   */
  public function cacheTags(): array {
    $tags = [];
    foreach ($this->provedores as $provedor) {
      try {
        $tags = array_merge($tags, $provedor->cacheTags());
      }
      catch (\Throwable $e) {
        $this->registrarFalha($provedor, $e);
      }
    }
    return array_values(array_unique($tags));
  }

  /**
   * Registra a falha de um provedor sem interromper os demais.
   */
  private function registrarFalha(CalendarItemProviderInterface $provedor, \Throwable $e): void {
    $this->logger?->get('aculta_calendar')->warning('Provedor de itens @id falhou: @mensagem', [
      '@id' => $provedor->id(),
      '@mensagem' => $e->getMessage(),
    ]);
  }

}
