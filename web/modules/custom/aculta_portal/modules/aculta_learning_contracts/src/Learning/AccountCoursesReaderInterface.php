<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Learning;

use Drupal\Core\Session\AccountInterface;

/**
 * Reads the authorized course summaries visible to one account.
 */
interface AccountCoursesReaderInterface {

  /**
   * Returns account-visible course summaries.
   *
   * The provider enforces Group/LMS access before returning any course
   * metadata. Implementations return neutral DTOs rather than provider
   * entities or render arrays.
   *
   * @return list<\Drupal\aculta_learning_contracts\Learning\AccountCourseSummary>
   *   The account's authorized courses.
   */
  public function getAccountCourses(AccountInterface $account): array;

}
