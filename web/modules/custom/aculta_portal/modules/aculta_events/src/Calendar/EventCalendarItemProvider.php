<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Calendar;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Agenda\EventFilter;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;

/**
 * Provedor de datas de evento para o aculta_calendar (CA5, D-20).
 *
 * Entrega séries como CalendarItem; a expansão da regra fica no calendário.
 * Rascunho não entra. Evento cancelado entra marcado.
 */
final class EventCalendarItemProvider implements CalendarItemProviderInterface {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventFilter $filtro,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'aculta_events';
  }

  /**
   * {@inheritdoc}
   */
  public function items(CalendarQuery $query): iterable {
    $storage = $this->entityTypeManager->getStorage('aculta_event_date');
    $anonymous = new AnonymousUserSession();
    $locationId = (int) ($query->filtros['local'] ?? 0);
    $locationViewer = $locationId > 0 ? $this->currentUser : $anonymous;
    $dateQuery = $storage->getQuery()->accessCheck(FALSE);
    if ($locationId > 0) {
      $dateQuery->condition('field_local', $locationId);
    }
    foreach ($storage->loadMultiple($dateQuery->execute()) as $data) {
      if (!$data instanceof EventDate) {
        continue;
      }
      $evento = $data->get('field_evento')->entity;
      if (!$evento instanceof Event || !in_array((string) $evento->get('field_status')->value, ['publicado', 'cancelado'], TRUE)) {
        continue;
      }
      // Events linked to LMS classes are private to enrolled members; the LMS
      // provider supplies them after rechecking membership and course access.
      if ($evento->hasField('field_turma') && !$evento->get('field_turma')->isEmpty()) {
        continue;
      }
      $eventViewer = $locationId > 0 ? $this->currentUser : $anonymous;
      if (!$evento->access('view', $eventViewer)) {
        continue;
      }
      $local = $data->get('field_local')->entity;
      if ($locationId > 0 && (!$local instanceof EventLocation
        || (int) $local->id() !== $locationId
        || !$local->access('view', $locationViewer))) {
        continue;
      }
      // The shared calendar is public and does not vary by signed-in user.
      // Never expose a private local's name through agenda or ICS.
      if ($local instanceof EventLocation && !$local->access('view', $anonymous)) {
        $local = NULL;
      }
      if (!$this->filtro->aceita($evento, $local instanceof EventLocation ? $local : NULL, $query->filtros['tipo'] ?? NULL, $query->filtros['tipo_local'] ?? NULL)) {
        continue;
      }
      $item = $this->itemDaData($data, $evento, $local instanceof EventLocation ? $local : NULL, $locationViewer);
      if ($item !== NULL) {
        yield $item;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filtros(): array {
    return [
      'tipo' => ['label' => 'Tipo de evento', 'opcoes' => $this->termos('aculta_event_type')],
      'tipo_local' => ['label' => 'Tipo de local', 'opcoes' => $this->termos('aculta_location_type')],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheTags(): array {
    return ['aculta_event_date_list', 'aculta_event_list', 'aculta_event_location_list'];
  }

  /**
   * {@inheritdoc} */
  public function cacheContexts(): array {
    // A Local contextual agenda may include private data visible only to the
    // current owner/editor. Public global views still query as anonymous.
    return ['user'];
  }

  /**
   * {@inheritdoc} */
  public function cacheMaxAge(): int {
    return 900;
  }

  /**
   * Item de uma data, com regra, exceções e reagendamentos.
   */
  public function itemDaData(
    EventDate $data,
    ?Event $evento = NULL,
    ?EventLocation $local = NULL,
    ?AccountInterface $locationViewer = NULL,
  ): ?CalendarItem {
    if ($evento === NULL) {
      $loadedEvent = $data->get('field_evento')->entity;
      $evento = $loadedEvent instanceof Event ? $loadedEvent : NULL;
    }
    if ($evento === NULL) {
      return NULL;
    }
    if ($local === NULL) {
      $loadedLocal = $data->get('field_local')->entity;
      $local = $loadedLocal instanceof EventLocation ? $loadedLocal : NULL;
    }
    if ($local instanceof EventLocation && !$local->access('view', $locationViewer ?? new AnonymousUserSession())) {
      $local = NULL;
    }
    $valores = $data->get('field_quando')->first()?->getValue();
    if ($valores === NULL) {
      return NULL;
    }

    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $utc = new \DateTimeZone('UTC');
    $inicio = (new \DateTimeImmutable((string) $valores['value'], $utc))->setTimezone($fuso);
    $fim = (new \DateTimeImmutable((string) $valores['end_value'], $utc))->setTimezone($fuso);

    [$excecoes, $reagendadas] = $this->excecoes($data, $fuso, $utc);
    $regra = (string) $data->get('field_regra')->value;
    $slug = (string) $evento->get('field_slug')->value;

    return new CalendarItem(
      id: 'eventdate:' . $data->id(),
      titulo: (string) $evento->label(),
      inicio: $inicio,
      fim: $fim,
      diaInteiro: (bool) $data->get('field_dia_inteiro')->value,
      fuso: 'America/Sao_Paulo',
      local: $local instanceof EventLocation ? (string) $local->label() : '',
      // O path é da rota aculta_events.page. fromUserInput evita depender do
      // router na geração do item para exportação e testes Kernel.
      link: $slug !== '' ? Url::fromUserInput('/eventos/' . $slug)->toString() : NULL,
      origem: 'aculta_events',
      rrule: $regra !== '' ? $regra : NULL,
      excecoes: $excecoes,
      reagendadas: $reagendadas,
      cancelado: (string) $evento->get('field_status')->value === 'cancelado',
      limiteMeses: (int) ($data->get('field_limite_meses')->value ?? 12),
    );
  }

  /**
   * Exceções da data: canceladas e reagendadas.
   *
   * @return array{0: \DateTimeImmutable[], 1: array<int, array{original: \DateTimeImmutable, inicio: \DateTimeImmutable, fim: ?\DateTimeImmutable}>}
   *   Exceções canceladas e reagendadas.
   */
  private function excecoes(EventDate $data, \DateTimeZone $fuso, \DateTimeZone $utc): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event_override');
    $ids = $storage->getQuery()
      ->condition('field_data_pai', $data->id())
      ->accessCheck(FALSE)
      ->execute();

    $canceladas = [];
    $reagendadas = [];
    foreach ($storage->loadMultiple($ids) as $excecao) {
      if (!$excecao instanceof ContentEntityInterface) {
        continue;
      }
      $original = (new \DateTimeImmutable((string) $excecao->get('field_inicio_original')->value, $utc))->setTimezone($fuso);
      if ($excecao->get('field_inicio')->isEmpty()) {
        $canceladas[] = $original;
        continue;
      }
      $reagendadas[] = [
        'original' => $original,
        'inicio' => (new \DateTimeImmutable((string) $excecao->get('field_inicio')->value, $utc))->setTimezone($fuso),
        'fim' => $excecao->get('field_fim')->isEmpty() ? NULL : (new \DateTimeImmutable((string) $excecao->get('field_fim')->value, $utc))->setTimezone($fuso),
      ];
    }
    return [$canceladas, $reagendadas];
  }

  /**
   * Opções de um vocabulário (id => nome).
   *
   * @return array<int, string>
   *   Opções do vocabulário, indexadas pelo ID do termo.
   */
  private function termos(string $vid): array {
    $opcoes = [];
    foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid) as $termo) {
      $opcoes[(int) $termo->tid] = (string) $termo->name;
    }
    return $opcoes;
  }

}
