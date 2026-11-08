<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Resources\Controller\CourseDocumentController;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Adds the course relationship callback before the Media form is cached.
 */
final class CourseDocumentFormHooks {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Adds the post-save relation callback to the contextual course form only.
   */
  #[Hook('form_media_course_document_add_form_alter')]
  public function addCourseRelation(array &$form, FormStateInterface $form_state): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_lms.course_document_add') {
      return;
    }
    if (!isset($form['actions']['submit']['#submit'])) {
      throw new \LogicException('The course document Media form has no save handlers.');
    }

    $form['actions']['submit']['#submit'][] = [CourseDocumentController::class, 'submitDocumentToCourse'];
  }

}
