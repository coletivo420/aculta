<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Session\AccountInterface;

/**
 * Reads the authorized learner summary from the active learning provider.
 */
interface StudentLearningSummaryReaderInterface {

  /**
   * Loads courses, resumable course and certificates for one account.
   *
   * The implementation owns authorization and cacheability. Presentation
   * consumers receive ACULTA DTOs and never need LMS or Group entities.
   */
  public function getStudentLearningSummary(AccountInterface $account): StudentLearningSummary;

}
