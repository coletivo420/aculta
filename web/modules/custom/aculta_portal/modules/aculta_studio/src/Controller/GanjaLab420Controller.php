<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_lms\Controller\LearningDashboardController;

/**
 * ACULTA Studio entrypoint for the GanjaLab420 learner experience.
 *
 * LMS remains responsible for enrollment, progress, assessment, certificates
 * and the current dashboard render contract. This adapter is the first
 * incremental migration step; it never duplicates access or learner data.
 */
final class GanjaLab420Controller {

  public function __construct(
    private readonly LearningDashboardController $lmsDashboard,
  ) {}

  /**
   * Presents the student's LMS dashboard without altering its cache policy.
   */
  public function student(): array {
    return $this->lmsDashboard->student();
  }

}
