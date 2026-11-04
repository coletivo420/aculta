<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Order\CourseStructureOrderManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockAcquiringException;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Saves a drag-and-drop order for one existing course collection.
 */
final class CourseStructureOrderForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly CurrentRouteMatch $routeMatcher,
    private readonly CourseAuthoringAccessCheck $accessCheck,
    private readonly CourseStructureOrderManager $orderManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('current_route_match'),
      $container->get('aculta_lms.course_authoring_access'),
      $container->get('aculta_lms.course_structure_order'),
      $container->get('entity_type.manager'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_course_structure_order_form';
  }

  /**
   * {@inheritdoc}
   *
   * Extra build arguments are read from the form state's build info because
   * Form API's buildForm() contract accepts only the form and form state.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $arguments = array_pad($form_state->getBuildInfo()['args'] ?? [], 4, NULL);
    [$course, $collection, $parent_id, $ordered_ids] = $arguments;
    if (!$course instanceof GroupInterface || !is_string($collection)
      || !is_int($parent_id) || !is_array($ordered_ids)) {
      throw new AccessDeniedHttpException();
    }
    $this->assertAccess($course, $collection, $parent_id);
    $form_state->set('course_id', (int) $course->id());
    $form_state->set('collection', $collection);
    $form_state->set('parent_id', $parent_id);
    $form_state->set('ordered_ids', $ordered_ids);

    $form['#attributes'] = [
      'id' => 'aculta-course-order-' . $collection . '-' . $parent_id,
      'class' => ['aculta-lms-course-sort-form'],
      'data-aculta-sort-form' => $collection,
    ];
    $form['order'] = [
      '#type' => 'hidden',
      '#default_value' => implode(',', $ordered_ids),
      '#attributes' => ['data-aculta-sort-order' => TRUE],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar ordem'),
      '#attributes' => ['data-aculta-sort-submit' => TRUE],
    ];
    return $form;
  }

  /**
   * Saves a validated exact permutation after rechecking educator access.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $course = $this->course();
    $collection = (string) $form_state->get('collection');
    $parentId = (int) $form_state->get('parent_id');
    $this->assertAccess($course, $collection, $parentId);

    $raw = trim((string) $form_state->getValue('order'));
    $ids = $raw === '' ? [] : array_map('intval', explode(',', $raw));
    $entityType = $collection === 'lessons' ? 'aculta_lms_lesson' : 'aculta_lms_activity';
    try {
      if ($this->orderManager->reorder($entityType, $parentId, $ids)) {
        $this->messenger()->addStatus($this->t('A ordem do conteúdo foi atualizada.'));
      }
      else {
        $this->messenger()->addStatus($this->t('A ordem do conteúdo já estava atualizada.'));
      }
    }
    catch (\InvalidArgumentException | LockAcquiringException) {
      $this->messenger()->addError($this->t('A estrutura mudou desde que a página foi carregada. Atualize e tente novamente.'));
    }
    $form_state->setRedirect('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * Revalidates the course or lesson scope for this reorder operation.
   */
  private function assertAccess(GroupInterface $course, string $collection, int $parentId): void {
    if ($collection === 'lessons' && $parentId === (int) $course->id()) {
      $access = $this->accessCheck->accessForReorder($course);
    }
    elseif ($collection === 'activities' && $parentId > 0) {
      $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->load($parentId);
      $access = $lesson ? $this->accessCheck->accessForReorder($course, $lesson) : NULL;
    }
    else {
      $access = NULL;
    }
    if ($access === NULL || !$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Gets the route's course after Form API rebuild.
   */
  private function course(): GroupInterface {
    $course = $this->routeMatcher->getParameter('course');
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      throw new AccessDeniedHttpException();
    }
    return $course;
  }

}
