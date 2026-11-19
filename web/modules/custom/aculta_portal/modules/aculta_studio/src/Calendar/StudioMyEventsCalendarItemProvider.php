<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Calendar;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Calendar\EventCalendarItemProvider;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_user\Organization\OrganizationMembershipManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provedor de itens de calendário para os eventos do usuário (Fase 1).
 *
 * Combina eventos organizados pelo usuário (como titular ou membro de organização)
 * e inscrições, sem duplicar eventos.
 */
final class StudioMyEventsCalendarItemProvider implements CalendarItemProviderInterface {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?EventCalendarItemProvider $eventsProvider,
    private readonly AccountInterface $currentUser,
    private readonly ?OrganizationMembershipManager $membershipManager = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'meus_eventos';
  }

  /**
   * {@inheritdoc}
   */
  public function items(CalendarQuery $query): iterable {
    if (!$this->currentUser->isAuthenticated()) {
      return [];
    }

    $eventIds = $this->myEventIds();
    if (empty($eventIds)) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('aculta_event_date');
    $dateQuery = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_evento', $eventIds, 'IN');

    $locationId = (int) ($query->filtros['local'] ?? 0);
    if ($locationId > 0) {
      $dateQuery->condition('field_local', $locationId);
    }

    $dateIds = $dateQuery->execute();
    if (empty($dateIds)) {
      return [];
    }

    $emittedDates = [];

    /** @var \Drupal\aculta_events\Entity\EventDate $data */
    foreach ($storage->loadMultiple($dateIds) as $data) {
      if (!$data instanceof EventDate) {
        continue;
      }
      $dateId = (int) $data->id();
      if (isset($emittedDates[$dateId])) {
        continue;
      }
      $emittedDates[$dateId] = TRUE;

      $evento = $data->get('field_evento')->entity;
      if (!$evento instanceof Event) {
        continue;
      }

      $status = (string) $evento->get('field_status')->value;
      if (!in_array($status, ['publicado', 'cancelado'], TRUE)) {
        continue;
      }

      $local = $data->get('field_local')->entity;
      $item = $this->eventsProvider?->itemDaData(
        $data,
        $evento,
        $local instanceof EventLocation ? $local : NULL,
        $this->currentUser,
      );

      if ($item === NULL) {
        $item = $this->buildFallbackItem($data, $evento, $local instanceof EventLocation ? $local : NULL);
      }

      if ($item !== NULL) {
        yield new CalendarItem(
          id: $item->id,
          titulo: $item->titulo,
          inicio: $item->inicio,
          fim: $item->fim,
          diaInteiro: $item->diaInteiro,
          fuso: $item->fuso,
          local: $item->local,
          link: $item->link,
          origem: $this->id(),
          rrule: $item->rrule,
          excecoes: $item->excecoes,
          reagendadas: $item->reagendadas,
          cancelado: $item->cancelado,
          limiteMeses: $item->limiteMeses,
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filtros(): array {
    return $this->eventsProvider?->filtros() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheTags(): array {
    return ['aculta_event_date_list', 'aculta_event_list'];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheContexts(): array {
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheMaxAge(): int {
    return 0;
  }

  /**
   * Constrói item quando o provedor de eventos não estiver injetado.
   */
  private function buildFallbackItem(EventDate $data, Event $evento, ?EventLocation $local): ?CalendarItem {
    $valores = $data->get('field_quando')->first()?->getValue();
    if ($valores === NULL) {
      return NULL;
    }

    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $utc = new \DateTimeZone('UTC');
    $inicio = (new \DateTimeImmutable((string) $valores['value'], $utc))->setTimezone($fuso);
    $fim = (new \DateTimeImmutable((string) $valores['end_value'], $utc))->setTimezone($fuso);
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
      link: $slug !== '' ? '/eventos/' . $slug : NULL,
      origem: $this->id(),
      rrule: $regra !== '' ? $regra : NULL,
      cancelado: (string) $evento->get('field_status')->value === 'cancelado',
      limiteMeses: (int) ($data->get('field_limite_meses')->value ?? 12),
    );
  }

  /**
   * Obtém os IDs dos eventos organizados pelo usuário (titular ou via organização).
   *
   * @return int[]
   *   IDs dos eventos únicos.
   */
  private function myEventIds(): array {
    $uid = (int) $this->currentUser->id();
    if ($uid <= 0) {
      return [];
    }

    $userOrgIds = [];
    if ($this->entityTypeManager->hasDefinition('aculta_event_organization')) {
      try {
        $orgStorage = $this->entityTypeManager->getStorage('aculta_event_organization');
        $titularOrgIds = $orgStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('titular', $uid)
          ->execute();
        foreach ($titularOrgIds as $orgId) {
          $userOrgIds[(int) $orgId] = (int) $orgId;
        }
      }
      catch (\Throwable) {
      }
    }

    if ($this->membershipManager !== NULL) {
      try {
        $memberships = $this->membershipManager->getMemberships($this->currentUser, 'approved');
        foreach ($memberships as $member) {
          $role = (string) ($member->get('role_in_org')->value ?? '');
          if (in_array($role, ['owner', 'co_owner', 'publisher', 'editor', 'admin'], TRUE)) {
            $orgId = (int) ($member->get('organization_id')->target_id ?? 0);
            if ($orgId > 0) {
              $userOrgIds[$orgId] = $orgId;
            }
          }
        }
      }
      catch (\Throwable) {
      }
    }

    $storage = $this->entityTypeManager->getStorage('aculta_event');
    $query = $storage->getQuery()->accessCheck(FALSE);

    $scopeCondition = $query->orConditionGroup();
    $scopeCondition->condition('field_organizador', $uid);

    if (!empty($userOrgIds)) {
      $scopeCondition->condition('field_organizacao', array_values($userOrgIds), 'IN');
      $scopeCondition->condition('field_coorganizadores', array_values($userOrgIds), 'IN');
    }

    $query->condition($scopeCondition);
    $ids = $query->execute();

    $uniqueIds = [];
    foreach ($ids as $id) {
      $uniqueIds[(int) $id] = (int) $id;
    }

    return array_values($uniqueIds);
  }

}
