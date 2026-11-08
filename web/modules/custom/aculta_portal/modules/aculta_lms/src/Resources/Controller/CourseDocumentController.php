<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Resources\Controller;

use Drupal\aculta_lms\Resources\Access\CourseDocumentAccessCheck;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds Group-owned Media forms inside the Ganjalab Educator workspace.
 */
final class CourseDocumentController {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFormBuilderInterface $entityFormBuilder,
    private readonly CourseDocumentAccessCheck $accessCheck,
    private readonly CurrentRouteMatch $routeMatcher,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Builds the owner Media form with a contextual post-save Group relation.
   */
  public function add(GroupInterface $course): array {
    if ($course->bundle() !== 'lms_course') {
      throw new NotFoundHttpException();
    }
    if (!$this->accessCheck->createAccess($course)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    if ($this->accessCheck->relationPluginId($course) === NULL) {
      throw new NotFoundHttpException();
    }

    $document = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'course_document',
    ]);
    // Keep the Group entity out of cached Form API state. Managed-file AJAX
    // rebuilds must serialize the form while an UploadedFile is in the request.
    $build = $this->entityFormBuilder->getForm($document, 'add');
    $build['contextual_return'] = [
      '#type' => 'link',
      '#title' => $this->t('Voltar aos materiais do curso'),
      '#url' => Url::fromRoute('aculta_lms.course_materials_edit', ['course' => $course->id()]),
    ];
    return $build;
  }

  /**
   * Static Form API callback, kept serializable across managed-file rebuilds.
   */
  public static function submitDocumentToCourse(array &$form, FormStateInterface $form_state): void {
    \Drupal::service('aculta_lms_resources.course_document_controller')
      ->attachDocumentToCourse($form, $form_state);
  }

  /**
   * Rechecks course access and attaches the saved Media after its owner form.
   */
  public function attachDocumentToCourse(array &$form, FormStateInterface $form_state): void {
    $course = $this->routeMatcher->getParameter('course');
    $form_object = $form_state->getFormObject();
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course'
      || !$form_object instanceof EntityFormInterface
      || !$this->accessCheck->createAccess($course)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $document = $form_object->getEntity();
    if (!$document instanceof MediaInterface || $document->bundle() !== 'course_document' || $document->isNew()) {
      throw new NotFoundHttpException();
    }
    $plugin_id = $this->accessCheck->relationPluginId($course);
    if ($plugin_id === NULL) {
      throw new NotFoundHttpException();
    }

    foreach ($course->getRelatedEntities($plugin_id) as $related) {
      if ($related instanceof MediaInterface && (int) $related->id() === (int) $document->id()) {
        $form_state->setRedirect('aculta_lms.course_materials_edit', ['course' => $course->id()]);
        return;
      }
    }
    $course->addRelationship($document, $plugin_id);
    $form_state->setRedirect('aculta_lms.course_materials_edit', ['course' => $course->id()]);
  }

  /**
   * Builds the owner Media edit form after rechecking course ancestry/access.
   */
  public function edit(GroupInterface $course, MediaInterface $media): array {
    if ($course->bundle() !== 'lms_course' || $media->bundle() !== 'course_document') {
      throw new NotFoundHttpException();
    }
    if (!$this->accessCheck->updateAccess($course, $media)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $build = $this->entityFormBuilder->getForm($media, 'edit');
    $build['contextual_return'] = [
      '#type' => 'link',
      '#title' => $this->t('Voltar aos materiais do curso'),
      '#url' => Url::fromRoute('aculta_lms.course_materials_edit', ['course' => $course->id()]),
    ];
    return $build;
  }

}
