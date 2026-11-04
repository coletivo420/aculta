<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Confirms removal of an empty lesson from an educator's course. */
final class LessonDeleteForm extends ConfirmFormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CurrentRouteMatch $currentRouteMatch,
    protected readonly CourseAuthoringAccessCheck $accessCheck,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('aculta_lms.course_authoring_access'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_lms_lesson_delete';
  }

  /**
   * {@inheritdoc} */
  public function getQuestion(): string {
    [, $lesson] = $this->routeEntities();
    return $this->t('Excluir a aula “@title”?', ['@title' => $lesson->get('title')->value]);
  }

  /**
   * {@inheritdoc} */
  public function getDescription(): string {
    return $this->t('A exclusão é permanente. Remova as atividades desta aula primeiro; atividades com respostas não podem ser excluídas para preservar o histórico.');
  }

  /**
   * {@inheritdoc} */
  public function getConfirmText(): string {
    return $this->t('Excluir aula');
  }

  /**
   * {@inheritdoc} */
  public function getCancelUrl(): Url {
    [$course] = $this->routeEntities();
    return Url::fromRoute('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$course, $lesson] = $this->routeEntities();
    $activity_ids = $this->entityTypeManager->getStorage('aculta_lms_activity')->getQuery()
      ->accessCheck(FALSE)
      ->condition('lesson_id', (int) $lesson->id())
      ->range(0, 1)
      ->execute();
    $archive_ids = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('lesson_id', (string) $lesson->id())
      ->range(0, 1)
      ->execute();
    if ($activity_ids !== [] || $archive_ids !== []) {
      $this->messenger()->addError($this->t('A aula ainda possui atividades ou histórico de tentativas e não pode ser excluída.'));
    }
    else {
      $lesson->delete();
      $this->messenger()->addStatus($this->t('A aula foi excluída.'));
    }
    $form_state->setRedirect('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * Resolves the course and lesson and validates their relationship. */
  private function routeEntities(): array {
    $course = $this->currentRouteMatch->getParameter('course');
    $lesson = $this->currentRouteMatch->getParameter('lesson');
    if (!$course instanceof GroupInterface || !$lesson instanceof ContentEntityInterface
      || !$this->accessCheck->access($course, $lesson)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return [$course, $lesson];
  }

}
