<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_lms\Form\CourseEnrollmentPurchaseForm;
use Drupal\aculta_lms\Learning\CourseJourneyReaderInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Builds course enrollment and participant roster elements for a Group course.
 *
 * Studio composes the learner-facing course journey from the neutral contract.
 */
final class CourseRoomBuilder {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly FormBuilderInterface $formBuilder,
    private readonly ClassRosterManager $classRosters,
    private readonly CourseJourneyReaderInterface $journeys,
  ) {}

  /**
   * Creates authorized enrollment and roster elements from Group state.
   *
   * @return array<string,mixed>
   *   Render array containing enrollment or participant roster elements.
   */
  public function build(GroupInterface $course): array {
    $journey = $this->journeys->getCourseJourney((string) $course->id(), $this->currentUser);
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($course)
      ->addCacheContexts(['user', 'user.group_permissions']);

    $build = [
      '#type' => 'container',
      '#cache' => [],
    ];

    if ($journey === NULL) {
      $cacheability->applyTo($build);
      return $build;
    }
    $cacheability->addCacheableDependency($journey->cache);

    if (!$journey->hasAccess()) {
      if (!$course->hasPermission('administer members', $this->currentUser)) {
        $build['enrollment'] = $this->formBuilder->getForm(CourseEnrollmentPurchaseForm::class, $course);
      }
      $cacheability->applyTo($build);
      return $build;
    }

    // Studio adds the student-facing journey during entity view alteration.
    $rosters = $this->classRosters->build($course, $this->currentUser);
    if (isset($rosters['classes'])) {
      $build['participant_rosters'] = $rosters;
    }
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($rosters));
    $cacheability->applyTo($build);
    return $build;
  }

}
