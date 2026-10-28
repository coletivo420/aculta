<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Agenda;

use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Occurrence\EventDateOccurrences;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Agenda do período (/calendario): ocorrências de eventos publicados e cancelados, por dia (F5).
 *
 * Só ocorrências, nunca rascunho. Cancelado entra marcado. Saída agrupada por dia, em ordem.
 */
final class EventAgenda {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDateOccurrences $ocorrencias,
    private readonly EventFilter $filtro = new EventFilter(),
  ) {}

  /**
   * Ocorrências de [$de, $ate], agrupadas por dia (chave Y-m-d, horário de Brasília).
   *
   * @param int|null $tipo
   *   Termo de aculta_event_type; NULL não filtra.
   * @param int|null $tipoLocal
   *   Termo de aculta_location_type do local da data; NULL não filtra.
   *
   * @return array<string, array<int, array{titulo: string, inicio: \DateTimeImmutable, fim: \DateTimeImmutable, local: string, cancelada: bool}>>
   */
  public function dias(\DateTimeImmutable $de, \DateTimeImmutable $ate, \DateTimeImmutable $agora, ?int $tipo = NULL, ?int $tipoLocal = NULL): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event_date');
    $grupos = [];
    foreach ($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->execute()) as $data) {
      if (!$data instanceof EventDate) {
        continue;
      }
      $evento = $data->get('field_evento')->entity;
      if ($evento === NULL || !in_array((string) $evento->get('field_status')->value, ['publicado', 'cancelado'], TRUE)) {
        continue;
      }
      $local = $data->get('field_local')->entity;
      if (!$this->filtro->aceita($evento, $local instanceof EventLocation ? $local : NULL, $tipo, $tipoLocal)) {
        continue;
      }
      foreach ($this->ocorrencias->expandir($data, $de, $ate, $agora) as $o) {
        $cancelada = $o['cancelada'] || (string) $evento->get('field_status')->value === 'cancelado';
        $grupos[$o['inicio']->format('Y-m-d')][] = [
          'titulo' => (string) $evento->label(),
          'inicio' => $o['inicio'],
          'fim' => $o['fim'],
          'local' => $local ? (string) $local->label() : '',
          'cancelada' => $cancelada,
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
