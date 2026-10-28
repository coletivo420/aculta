<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Calendar;

use Drupal\aculta_calendar\Ics\IcsEventBuilder;
use Drupal\aculta_events\Agenda\EventFilter;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Occurrence\EventDateOccurrences;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Exporta, em um .ics, as datas de eventos publicados ou cancelados com ocorrência no período (F5).
 *
 * Série sai como regra (RRULE, EXDATE e RECURRENCE-ID), sem expandir no arquivo (RECORRENCIA.md, M5).
 * Evento cancelado sai com prefixo "[Cancelado]". Rascunho não sai.
 */
final class EventIcsExporter {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDateOccurrences $ocorrencias,
    private readonly EventFilter $filtro = new EventFilter(),
  ) {}

  /**
   * Conteúdo do .ics para o período [$de, $ate], inclusivo, em horário de Brasília (UID com $dominioUid).
   *
   * @param int|null $tipo
   *   Termo de aculta_event_type; NULL não filtra.
   * @param int|null $tipoLocal
   *   Termo de aculta_location_type do local da data; NULL não filtra.
   */
  public function exportar(\DateTimeImmutable $de, \DateTimeImmutable $ate, \DateTimeImmutable $agora, string $dominioUid, ?int $tipo = NULL, ?int $tipoLocal = NULL): string {
    $series = [];
    foreach ($this->datas() as $data) {
      $evento = $data->get('field_evento')->entity;
      if ($evento === NULL || !in_array((string) $evento->get('field_status')->value, ['publicado', 'cancelado'], TRUE)) {
        continue;
      }
      $local = $data->get('field_local')->entity;
      if (!$this->filtro->aceita($evento, $local instanceof EventLocation ? $local : NULL, $tipo, $tipoLocal)) {
        continue;
      }
      $serie = $this->serie($data, $evento, $de, $ate, $agora);
      if ($serie !== NULL) {
        $series[] = $serie;
      }
    }
    // O domínio do UID vem do host da requisição: é estável por ambiente e não fica fixo no código.
    return (new IcsEventBuilder($dominioUid))->buildMany($series);
  }

  /**
   * Conteúdo do .ics de uma data (/eventos/{slug}/data/{id}.ics), com a regra da série quando houver.
   *
   * Não depende de janela: a data pedida é exportada inteira, como a série.
   */
  public function exportarData(EventDate $data, \DateTimeImmutable $agora, string $dominioUid): string {
    $evento = $data->get('field_evento')->entity;
    if ($evento === NULL || !in_array((string) $evento->get('field_status')->value, ['publicado', 'cancelado'], TRUE)) {
      return (new IcsEventBuilder($dominioUid))->buildMany([]);
    }
    return (new IcsEventBuilder($dominioUid))->buildMany([$this->serieDe($data, $evento, $agora)]);
  }

  /**
   * Série de uma data, ou NULL quando não tem ocorrência no período.
   *
   * @return array<string, mixed>|null
   */
  private function serie(EventDate $data, object $evento, \DateTimeImmutable $de, \DateTimeImmutable $ate, \DateTimeImmutable $agora): ?array {
    $ocorrenciasNoPeriodo = $this->ocorrencias->expandir($data, $de, $ate, $agora);
    if ($ocorrenciasNoPeriodo === []) {
      return NULL;
    }
    return $this->serieDe($data, $evento, $agora);
  }

  /**
   * Monta a série (VEVENT mestre, RRULE e exceções) sem checar janela.
   *
   * @return array<string, mixed>
   */
  private function serieDe(EventDate $data, object $evento, \DateTimeImmutable $agora): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $utc = new \DateTimeZone('UTC');
    $item = $data->get('field_quando')->first()->getValue();
    $inicio = (new \DateTimeImmutable((string) $item['value'], $utc))->setTimezone($fuso);
    $fim = (new \DateTimeImmutable((string) $item['end_value'], $utc))->setTimezone($fuso);
    $titulo = (string) $evento->label();
    if ((string) $evento->get('field_status')->value === 'cancelado') {
      $titulo = '[Cancelado] ' . $titulo;
    }
    $local = $data->get('field_local')->entity;

    $cancelados = [];
    $reagendadas = [];
    $ids = $this->entityTypeManager->getStorage('aculta_event_override')->getQuery()
      ->condition('field_data_pai', $data->id())->accessCheck(FALSE)->execute();
    foreach ($this->entityTypeManager->getStorage('aculta_event_override')->loadMultiple($ids) as $excecao) {
      $original = (new \DateTimeImmutable((string) $excecao->get('field_inicio_original')->value, $utc))->setTimezone($fuso);
      if ($excecao->get('field_inicio')->isEmpty()) {
        $cancelados[] = $original;
        continue;
      }
      $reagendadas[] = [
        'original' => $original,
        'inicio' => (new \DateTimeImmutable((string) $excecao->get('field_inicio')->value, $utc))->setTimezone($fuso),
        'fim' => $excecao->get('field_fim')->isEmpty() ? NULL : (new \DateTimeImmutable((string) $excecao->get('field_fim')->value, $utc))->setTimezone($fuso),
      ];
    }

    $regra = (string) $data->get('field_regra')->value;
    return [
      'key' => 'eventdate:' . $data->id(),
      'summary' => $titulo,
      'location' => $local ? (string) $local->label() : '',
      'start' => $inicio,
      'end' => $fim,
      'allDay' => (bool) $data->get('field_dia_inteiro')->value,
      'stamp' => $agora,
      'rrule' => $regra !== '' ? $regra : NULL,
      'excecoes' => $cancelados,
      'reagendadas' => $reagendadas,
    ];
  }

  /**
   * Todas as datas (EventDate).
   *
   * @return \Drupal\aculta_events\Entity\EventDate[]
   */
  private function datas(): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event_date');
    return array_values(array_filter($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->execute()), static fn ($d) => $d instanceof EventDate));
  }

}
