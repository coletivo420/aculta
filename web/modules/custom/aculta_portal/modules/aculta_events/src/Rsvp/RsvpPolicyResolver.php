<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Rsvp;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventOffer;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Resolves the validated D-60 policy without performing registration writes.
 */
final class RsvpPolicyResolver {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Resolves a free RSVP policy for an event and its optional context.
   *
   * Invalid, incomplete, paid, or disabled configuration fails closed.
   *
   * @param \Drupal\aculta_events\Entity\Event $event
   *   The owning event.
   * @param \Drupal\aculta_events\Entity\EventDate|null $date
   *   The occurrence context when required by capacity or series policy.
   * @param \Drupal\aculta_events\Entity\EventOffer|null $offer
   *   The offer context when capacity is scoped to a lot.
   *
   * @return \Drupal\aculta_events\Rsvp\RsvpPolicy|null
   *   A complete policy, or NULL when registration must remain unavailable.
   */
  public function resolve(Event $event, ?EventDate $date = NULL, ?EventOffer $offer = NULL): ?RsvpPolicy {
    if (!$this->boolean($event, 'field_rsvp_ativo')) {
      return NULL;
    }

    $scope = $this->string($event, 'field_rsvp_escopo_capacidade');
    if (!in_array($scope, ['evento', 'data', 'lote'], TRUE)) {
      return NULL;
    }

    $dateId = $date ? (int) $date->id() : NULL;
    $offerId = $offer ? (int) $offer->id() : NULL;
    if ($date && !$this->references($date, 'field_evento', (int) $event->id())) {
      return NULL;
    }
    if ($offer && !$this->references($offer, 'field_evento', (int) $event->id())) {
      return NULL;
    }

    if ($scope === 'data' && !$date) {
      return NULL;
    }
    if ($scope === 'lote' && (!$offer || !$this->isFreeActiveOffer($offer))) {
      return NULL;
    }
    if ($offer && !$this->isFreeActiveOffer($offer)) {
      return NULL;
    }
    if (
      $offer
      && !$offer->get('field_data')->isEmpty()
      && (!$date || (int) $offer->get('field_data')->target_id !== (int) $date->id())
    ) {
      return NULL;
    }

    $capacity = match ($scope) {
      'evento' => $this->integerField($event, 'field_rsvp_capacidade'),
      'data' => $this->integerField($date, 'field_rsvp_capacidade'),
      'lote' => $this->integerField($offer, 'field_capacidade'),
    };

    $limit = $this->integerField($event, 'field_rsvp_limite_pessoa');
    $reservation = $this->integerField($event, 'field_rsvp_reserva_minutos');
    $duplicate = $this->string($event, 'field_rsvp_duplicidade');
    $cancellation = $this->string($event, 'field_rsvp_cancelamento');
    $series = $this->string($event, 'field_rsvp_serie');
    if (
      $limit === NULL
      || $limit < 1
      || ($capacity !== NULL && $capacity < 1)
      || !in_array($duplicate, ['impedir', 'permitir'], TRUE)
      || !in_array($cancellation, ['impedir', 'permitir'], TRUE)
      || !in_array($series, ['ocorrencia', 'serie'], TRUE)
      || ($reservation !== NULL && $reservation < 1)
      || ($series === 'ocorrencia' && !$date && $this->isRecurring($event))
    ) {
      return NULL;
    }

    return new RsvpPolicy(
      $scope,
      $capacity,
      $limit,
      $duplicate === 'permitir',
      $reservation,
      $this->boolean($event, 'field_rsvp_lista_espera'),
      $cancellation,
      $series,
      $dateId,
      $scope === 'lote' ? $offerId : NULL,
    );
  }

  /**
   * Checks that an offer is active and free. */
  private function isFreeActiveOffer(EventOffer $offer): bool {
    return $this->boolean($offer, 'field_ativa')
      && $this->string($offer, 'field_modalidade') === 'gratuita';
  }

  /**
   * Checks whether the event has a saved recurrence rule.
   */
  private function isRecurring(Event $event): bool {
    if (!$event->isNew()) {
      $dateIds = $this->entityTypeManager->getStorage('aculta_event_date')->getQuery()
        ->accessCheck(FALSE)
        ->condition('field_evento', (int) $event->id())
        ->condition('field_regra', '', '<>')
        ->range(0, 1)
        ->execute();
      return $dateIds !== [];
    }
    return FALSE;
  }

  /**
   * Checks an entity reference without loading its target. */
  private function references(object $entity, string $field, int $targetId): bool {
    return method_exists($entity, 'hasField')
      && $entity->hasField($field)
      && !$entity->get($field)->isEmpty()
      && (int) $entity->get($field)->target_id === $targetId;
  }

  /**
   * Reads an optional integer field while preserving invalid non-empty values. */
  private function integerField(?object $entity, string $field): ?int {
    if (!$entity || !method_exists($entity, 'hasField') || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return NULL;
    }
    return (int) $entity->get($field)->value;
  }

  /**
   * Reads a scalar string field, returning an empty string when unavailable. */
  private function string(object $entity, string $field): string {
    return method_exists($entity, 'hasField') && $entity->hasField($field) && !$entity->get($field)->isEmpty()
      ? (string) $entity->get($field)->value
      : '';
  }

  /**
   * Reads a boolean field using its normalized stored scalar. */
  private function boolean(object $entity, string $field): bool {
    return $this->string($entity, $field) === '1';
  }

}
