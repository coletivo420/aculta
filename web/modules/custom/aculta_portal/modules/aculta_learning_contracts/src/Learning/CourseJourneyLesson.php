<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Learning;

use Drupal\Core\Url;

/**
 * One authorized lesson in an ACULTA course journey.
 *
 * This is a presentation contract. It carries only data that the learning
 * provider already authorized and never references Drupal LMS or Group
 * entities, so the Ganjalab experience can be composed and tested without
 * loading the contrib LMS classes.
 */
final readonly class CourseJourneyLesson {

  /**
   * Builds one lesson view model.
   *
   * @param string $id
   *   Stable lesson identifier from the learning provider.
   * @param string $label
   *   Human-readable lesson label.
   * @param int $delta
   *   Curriculum position used by native learning routes.
   * @param string $state
   *   One of finished, current, in_progress or not_started.
   * @param bool $isCurrent
   *   Whether the provider records this lesson as the current position.
   * @param bool $allowed
   *   Whether the provider allows opening this lesson.
   * @param \Drupal\Core\Url|null $url
   *   Authorized destination, or NULL when the lesson cannot be opened.
   */
  public function __construct(
    public string $id,
    public string $label,
    public int $delta,
    public string $state,
    public bool $isCurrent,
    public bool $allowed,
    public ?Url $url,
  ) {}

}
