<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Notice;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Entity\CourseInterface;

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
  public function courseForAnswer(AnswerInterface $answer): ?CourseInterface;

  /**
   * Finds published courses that currently reference the released content.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Course groups that contain the lesson or activity.
   */
  public function coursesForContent(EntityPublishedInterface $content): array;

  /**
   * Checks active take-course permission in any class linked to the course.
   */
  public function hasCurrentEnrollment(CourseInterface $course, AccountInterface $account): bool;

  /**
   * Checks an active take-course membership in the specific class.
   */
  public function memberCanTakeCourseInClass(GroupInterface $class, AccountInterface $account): bool;

  /**
   * Resolves the course of a class through the LMS deadline rules.
   */
  public function courseForClass(GroupInterface $class): ?CourseInterface;

  /**
   * Whether the activity currently belongs to the course.
   */
  public function activityBelongsToCourse(ActivityInterface $activity, CourseInterface $course): bool;

}
