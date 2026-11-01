<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Rsvp;

/**
 * Immutable, validated policy for a potential free RSVP.
 *
 * This value object does not register participants or reserve capacity.
 */
final class RsvpPolicy {

  /**
   * Constructs an RSVP policy.
   *
   * @param string $capacityScope
   *   One of evento, data, or lote.
   * @param int|null $capacity
   *   Configured nominal capacity, or NULL when unlimited.
   * @param int $perPersonLimit
   *   Maximum number of registrations per participant.
   * @param bool $allowDuplicates
   *   Whether duplicate registrations are allowed.
   * @param int|null $reservationMinutes
   *   Temporary reservation duration, or NULL when not configured.
   * @param bool $waitlistEnabled
   *   Whether a waitlist is enabled.
   * @param string $cancellationPolicy
   *   The configured cancellation policy.
   * @param string $seriesScope
   *   Whether registration applies to one occurrence or the series.
   * @param int|null $dateId
   *   Contextual EventDate ID, when applicable.
   * @param int|null $offerId
   *   Contextual EventOffer ID, when applicable.
   */
  public function __construct(
    public readonly string $capacityScope,
    public readonly ?int $capacity,
    public readonly int $perPersonLimit,
    public readonly bool $allowDuplicates,
    public readonly ?int $reservationMinutes,
    public readonly bool $waitlistEnabled,
    public readonly string $cancellationPolicy,
    public readonly string $seriesScope,
    public readonly ?int $dateId,
    public readonly ?int $offerId,
  ) {}

}
