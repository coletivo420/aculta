<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Notice;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Educational eligibility policy owned by the LMS domain.
 *
 * Both the LMS producers and the Messenger consumer consult this service, so
 * the rules are not duplicated between the two modules.
 */
interface LearningEligibilityInterface {

  /**
   * Resolves an answer's current course without typed getters on stale refs.
   */
  public function courseForAnswer(EntityInterface $answer): ?GroupInterface;

  /**
   * Finds published courses that currently reference the released content.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Course groups that contain the lesson or activity.
   */
  public function coursesForContent(EntityInterface $content): array;

  /**
   * Whether ACULTA content is currently available to enrolled learners.
   */
  public function isContentAvailable(EntityInterface $content): bool;

  /**
   * Checks active take-course permission in any class linked to the course.
   */
  public function hasCurrentEnrollment(GroupInterface $course, AccountInterface $account): bool;

  /**
   * Checks an active take-course membership in the specific class.
   */
  public function memberCanTakeCourseInClass(GroupInterface $class, AccountInterface $account): bool;

  /**
   * Resolves the course of a class through the LMS deadline rules.
   */
  public function courseForClass(GroupInterface $class): ?GroupInterface;

  /**
   * Whether the activity currently belongs to the course.
   */
  public function activityBelongsToCourse(ContentEntityInterface $activity, GroupInterface $course): bool;

}
