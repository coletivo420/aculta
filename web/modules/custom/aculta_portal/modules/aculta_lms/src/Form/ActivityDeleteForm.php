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
 * Confirms removal of an activity that has no submitted answers. */
final class ActivityDeleteForm extends ConfirmFormBase {

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
    return 'aculta_lms_activity_delete';
  }

  /**
   * {@inheritdoc} */
  public function getQuestion(): string {
    $this->routeEntities();
    return $this->t('Excluir esta atividade?');
  }

  /**
   * {@inheritdoc} */
  public function getDescription(): string {
    return $this->t('A exclusão é permanente. Uma atividade com respostas não pode ser removida para preservar as avaliações e o histórico dos estudantes.');
  }

  /**
   * {@inheritdoc} */
  public function getConfirmText(): string {
    return $this->t('Excluir atividade');
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
    [$course, , $activity] = $this->routeEntities();
    $answer_ids = $this->entityTypeManager->getStorage('aculta_lms_answer')->getQuery()
      ->accessCheck(FALSE)
      ->condition('activity_id', (int) $activity->id())
      ->range(0, 1)
      ->execute();
    $archive_ids = $this->entityTypeManager->getStorage('aculta_lms_attempt_archive')->getQuery()
      ->accessCheck(FALSE)
      ->condition('activity_id', (string) $activity->id())
      ->range(0, 1)
      ->execute();
    if ($answer_ids !== [] || $archive_ids !== []) {
      $this->messenger()->addError($this->t('Esta atividade já possui respostas ou histórico de tentativas e não pode ser excluída.'));
    }
    else {
      $activity->delete();
      $this->messenger()->addStatus($this->t('A atividade foi excluída.'));
    }
    $form_state->setRedirect('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * Resolves and authorizes course, lesson and activity ancestry. */
  private function routeEntities(): array {
    $course = $this->currentRouteMatch->getParameter('course');
    $lesson = $this->currentRouteMatch->getParameter('lesson');
    $activity = $this->currentRouteMatch->getParameter('activity');
    if (!$course instanceof GroupInterface || !$lesson instanceof ContentEntityInterface
      || !$activity instanceof ContentEntityInterface
      || !$this->accessCheck->access($course, $lesson, $activity)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return [$course, $lesson, $activity];
  }

}
