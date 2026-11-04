<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Order\CourseStructureOrderManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockAcquiringException;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Confirms moving one course item while explaining its effect on classes. */
final class CourseStructureMoveForm extends ConfirmFormBase {

  public function __construct(
    private readonly CurrentRouteMatch $currentRouteMatch,
    private readonly CourseAuthoringAccessCheck $accessCheck,
    private readonly CourseStructureOrderManager $orderManager,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('current_route_match'),
      $container->get('aculta_lms.course_authoring_access'),
      $container->get('aculta_lms.course_structure_order'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_lms_course_structure_move';
  }

  /**
   * {@inheritdoc} */
  public function getQuestion(): string {
    [, $lesson, $activity, $direction] = $this->routeEntities();
    $label = $activity ? $this->t('Atividade @id', ['@id' => $activity->id()]) : $lesson->get('title')->value;
    return $this->t('Mover “@item” @direction?', [
      '@item' => $label,
      '@direction' => $direction === 'up' ? $this->t('uma posição acima') : $this->t('uma posição abaixo'),
    ]);
  }

  /**
   * {@inheritdoc} */
  public function getDescription(): string {
    return $this->t('A nova ordem será aplicada imediatamente às turmas vinculadas ao curso. O conteúdo, as respostas e as notas não serão alterados.');
  }

  /**
   * {@inheritdoc} */
  public function getConfirmText(): string {
    return $this->t('Alterar ordem');
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
    [$course, $lesson, $activity, $direction] = $this->routeEntities();
    $entityType = $activity ? 'aculta_lms_activity' : 'aculta_lms_lesson';
    $parentId = (int) ($activity ? $lesson->id() : $course->id());
    $itemId = (int) ($activity ? $activity->id() : $lesson->id());

    try {
      if ($this->orderManager->move($entityType, $parentId, $itemId, $direction)) {
        $this->messenger()->addStatus($this->t('A ordem do conteúdo foi atualizada.'));
      }
      else {
        $this->messenger()->addWarning($this->t('O item já está nessa extremidade da lista.'));
      }
    }
    catch (LockAcquiringException) {
      $this->messenger()->addError($this->t('A estrutura está sendo alterada em outra solicitação. Tente novamente.'));
    }
    catch (\InvalidArgumentException) {
      $this->messenger()->addError($this->t('Este item já não pertence à estrutura atual do curso. Atualize a página e tente novamente.'));
    }
    $form_state->setRedirect('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * Resolves and revalidates the full course ancestry for the POST. */
  private function routeEntities(): array {
    $course = $this->currentRouteMatch->getParameter('course');
    $lesson = $this->currentRouteMatch->getParameter('lesson');
    $activity = $this->currentRouteMatch->getParameter('activity');
    $direction = $this->currentRouteMatch->getParameter('direction');
    if (!$course instanceof GroupInterface || !$lesson instanceof ContentEntityInterface
      || ($activity !== NULL && !$activity instanceof ContentEntityInterface)
      || !in_array($direction, ['up', 'down'], TRUE)
      || !$this->accessCheck->accessForReorder(
        $course,
        $lesson,
        $activity instanceof ContentEntityInterface ? $activity : NULL,
      )->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return [$course, $lesson, $activity, $direction];
  }

}
