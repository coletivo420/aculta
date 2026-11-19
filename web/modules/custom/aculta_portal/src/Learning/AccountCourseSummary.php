<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Learning;

use Drupal\Core\Url;

/**
 * Authorized course data for the account's learning section.
 *
 * Providers return this neutral DTO (or a subclass) with normalized status.
 */
final readonly class AccountCourseSummary {

  public const STATUS_NOT_STARTED = 'not_started';

  public const STATUS_IN_PROGRESS = 'in_progress';

  public const STATUS_COMPLETED = 'completed';

  public const STATUS_FAILED = 'failed';

  public const STATUS_AWAITING_EVALUATION = 'awaiting_evaluation';

  /**
   * Creates a neutral course summary for the account's learning section.
   *
   * @param string $id
   *   The provider's course identifier.
   * @param string $label
   *   The course title shown to the account.
   * @param string $description
   *   The course description, already filtered by the provider.
   * @param string $descriptionFormat
   *   The text format of the description, if any.
   * @param string $status
   *   One of the STATUS_* constants of this class.
   * @param int|float|null $score
   *   The final score, when the provider has one.
   * @param bool $finished
   *   Whether the account has finished the course.
   * @param \Drupal\Core\Url|null $url
   *   The canonical course URL, or NULL when it must not be linked.
   * @param array<int, string> $cacheTags
   *   Cache tags the provider needs to invalidate this summary.
   */
  public function __construct(
    public string $id,
    public string $label,
    public string $description,
    public string $descriptionFormat,
    public string $status,
    public int|float|null $score,
    public bool $finished,
    public ?Url $url,
    public array $cacheTags = [],
  ) {}

}
