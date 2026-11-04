<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\Controller;

use Drupal\aculta_lms_resources\Access\CourseDocumentAccessCheck;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Builds the owner Media form with Group's create-and-relate context.
   */
  public function add(GroupInterface $course): array {
    if ($course->bundle() !== 'lms_course') {
      throw new NotFoundHttpException();
    }
    if (!$this->accessCheck->createAccess($course)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $pluginId = $this->accessCheck->relationPluginId($course);
    if ($pluginId === NULL) {
      throw new NotFoundHttpException();
    }

    $document = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'course_document',
    ]);
    $build = $this->entityFormBuilder->getForm($document, 'add', [
      'group__create_form_should_enhance' => TRUE,
      'group_relation' => $pluginId,
      'group' => $course,
    ]);
    $build['contextual_return'] = [
      '#type' => 'link',
      '#title' => $this->t('Voltar aos materiais do curso'),
      '#url' => Url::fromRoute('aculta_lms.course_materials_edit', ['course' => $course->id()]),
    ];
    return $build;
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
