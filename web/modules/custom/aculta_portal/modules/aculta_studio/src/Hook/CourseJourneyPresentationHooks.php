<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Hook;

use Drupal\aculta_learning_contracts\Learning\CourseJourneyReaderInterface;
use Drupal\aculta_studio\Learning\CourseJourneyComposer;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Adds Studio-owned presentation to LMS-owned course and activity routes.
 *
 * The provider remains responsible for access and learning state. This hook
 * only composes the authorized contract data into the Ganjalab experience.
 */
final class CourseJourneyPresentationHooks {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountProxyInterface $currentUser,
    private readonly ?CourseJourneyReaderInterface $journeys,
    private readonly CourseJourneyComposer $composer,
  ) {}

  /**
   * Adds the learner journey to the canonical course page.
   */
  #[Hook('entity_view_alter')]
  public function addCourseJourney(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if ($this->journeys === NULL
      || !$entity instanceof GroupInterface
      || $display->getMode() !== 'default'
      || $this->routeMatch->getRouteName() !== 'entity.group.canonical') {
      return;
    }

    $route_group = $this->routeMatch->getParameter('group');
    if (!$route_group instanceof GroupInterface || (string) $route_group->id() !== (string) $entity->id()) {
      return;
    }

    $journey = $this->journeys->getCourseJourney((string) $entity->id(), $this->currentUser);
    if ($journey === NULL || !$journey->hasAccess()) {
      return;
    }

    $build['aculta_learning_journey'] = $this->composer->build($journey);
    $build['aculta_learning_journey']['#weight'] = -20;
  }

  /**
   * Wraps the native LMS activity form in the Studio learning-room shell.
   */
  #[Hook('preprocess_page')]
  public function preprocessLearningRoom(array &$variables): void {
    if ($this->journeys === NULL
      || $this->routeMatch->getRouteName() !== 'lms.group.answer_form'
      || !isset($variables['page']['content'])
      || !is_array($variables['page']['content'])) {
      return;
    }

    $course = $this->routeMatch->getParameter('group');
    if (!$course instanceof GroupInterface) {
      return;
    }

    $journey = $this->journeys->getCourseJourney((string) $course->id(), $this->currentUser);
    if ($journey === NULL || !$journey->hasAccess()) {
      return;
    }

    $variables['page']['content'] = $this->composer->buildRoom(
      $journey,
      (int) $this->routeMatch->getParameter('lesson_delta'),
      (int) $this->routeMatch->getParameter('activity_delta'),
      $variables['page']['content'],
    );
  }

}
