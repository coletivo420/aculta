<?php

declare(strict_types=1);

namespace Drupal\aculta_learning_contracts\Learning;

use Drupal\Core\Session\AccountInterface;

/**
 * Reads an authorized course journey for the Ganjalab experience.
 *
 * The contract belongs to the consuming ACULTA layer so the experience never
 * depends on a concrete LMS implementation. The current adapter reads Drupal
 * LMS and Group; a future ACULTA provider can implement the same contract
 * without changing the Studio composers.
 */
interface CourseJourneyReaderInterface {

  /**
   * Loads the authorized journey of one course for an account.
   *
   * Access is decided on the server by the implementation. Callers must not
   * infer permission from the presence of lessons or URLs.
   *
   * @param string $course_id
   *   Course identifier owned by the learning provider.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account whose access and progress are resolved.
   *
   * @return \Drupal\aculta_learning_contracts\Learning\CourseJourney|null
   *   The journey, including a denied result when the course exists but the
   *   account cannot take it, or NULL when the identifier is not a course.
   */
  public function getCourseJourney(string $course_id, AccountInterface $account): ?CourseJourney;

}
