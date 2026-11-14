<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Learning;

use Drupal\Core\Session\AccountInterface;

/**
 * Reads the authorized course summaries visible to one account.
 *
 * The provider enforces access before returning any course metadata. Portal
 * consumers depend on this contract, not on the LMS implementation.
 */
interface AccountCoursesReaderInterface {

  /**
   * Returns account-visible course summaries.
   *
   * @return list<\Drupal\aculta_learning_contracts\Learning\AccountCourseSummary>
   *   The account's authorized courses.
   */
  public function getAccountCourses(AccountInterface $account): array;

}
