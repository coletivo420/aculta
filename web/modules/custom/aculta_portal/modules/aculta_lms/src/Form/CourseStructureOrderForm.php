<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Order\CourseStructureOrderManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
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
    protected readonly CurrentRouteMatch $routeMatcher,
    protected readonly CourseAuthoringAccessCheck $accessCheck,
    protected readonly CourseStructureOrderManager $orderManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
   *
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   *
   * @return array<array-key, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $buildArguments = $form_state->getBuildInfo()['args'] ?? [];
    if (!is_array($buildArguments)) {
      throw new AccessDeniedHttpException();
    }
    $arguments = array_pad(array_values($buildArguments), 4, NULL);
    [$course, $collection, $parent_id, $ordered_ids] = $arguments;
    $normalizedIds = self::orderedIds($ordered_ids);
    if (!$course instanceof GroupInterface || !is_string($collection)
      || !is_int($parent_id) || $normalizedIds === NULL) {
      throw new AccessDeniedHttpException();
    }
    $this->assertAccess($course, $collection, $parent_id);
    $form_state->set('course_id', self::positiveInteger($course->id()) ?? 0);
    $form_state->set('collection', $collection);
    $form_state->set('parent_id', $parent_id);
    $form_state->set('ordered_ids', $normalizedIds);

    $form['#attributes'] = [
      'id' => 'aculta-course-order-' . $collection . '-' . $parent_id,
      'data-portal-ui' => 'lms-course-sort-form',
      'data-aculta-sort-form' => $collection,
      'data-aculta-unsaved-changes' => TRUE,
    ];
    $form['order'] = [
      '#type' => 'hidden',
      '#default_value' => implode(',', $normalizedIds),
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
   *
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $course = $this->course();
    $collection = $form_state->get('collection');
    $parentId = $form_state->get('parent_id');
    if (!is_string($collection) || !is_int($parentId)) {
      throw new AccessDeniedHttpException();
    }
    $this->assertAccess($course, $collection, $parentId);

    $rawOrder = $form_state->getValue('order');
    if (!is_string($rawOrder)) {
      $this->messenger()->addError($this->t('A ordem enviada está inválida. Atualize e tente novamente.'));
      return;
    }
    $raw = trim($rawOrder);
    $ids = [];
    if ($raw !== '') {
      foreach (explode(',', $raw) as $rawId) {
        $id = filter_var(trim($rawId), FILTER_VALIDATE_INT);
        if ($id === FALSE || $id < 1) {
          $this->messenger()->addError($this->t('A ordem enviada está inválida. Atualize e tente novamente.'));
          return;
        }
        $ids[] = $id;
      }
    }
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
      $access = $lesson instanceof ContentEntityInterface ? $this->accessCheck->accessForReorder($course, $lesson) : NULL;
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

  /**
   * Validates and normalizes entity IDs from the Form API build arguments.
   *
   * @return list<int>|null
   *   Positive IDs in source order, or NULL for malformed input.
   */
  private static function orderedIds(mixed $value): ?array {
    if (!is_array($value) || !array_is_list($value)) {
      return NULL;
    }
    $ids = [];
    foreach ($value as $item) {
      $id = self::positiveInteger($item);
      if ($id === NULL) {
        return NULL;
      }
      $ids[] = $id;
    }
    return $ids;
  }

  /**
   * Parses a positive integer ID without coercing arbitrary mixed values.
   */
  private static function positiveInteger(mixed $value): ?int {
    if (is_int($value)) {
      return $value > 0 ? $value : NULL;
    }
    if (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1) {
      return (int) $value;
    }
    return NULL;
  }

}
