<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Learning;

use Drupal\Core\Url;

/**
 * Authorized course data for the account's learning section.
 *
 * Providers return this neutral DTO (or a subclass) with normalized status.
 */
readonly class AccountCourseSummary {

  public const STATUS_NOT_STARTED = 'not_started';

  public const STATUS_IN_PROGRESS = 'in_progress';

  public const STATUS_COMPLETED = 'completed';

  public const STATUS_FAILED = 'failed';

  public const STATUS_AWAITING_EVALUATION = 'awaiting_evaluation';

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
