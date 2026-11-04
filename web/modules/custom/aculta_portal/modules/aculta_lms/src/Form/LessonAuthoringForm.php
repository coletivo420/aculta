<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Creates or edits a course lesson using the existing ACULTA entity fields.
 */
final class LessonAuthoringForm extends FormBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CurrentRouteMatch $currentRouteMatch,
    protected readonly CourseAuthoringAccessCheck $accessCheck,
  ) {}

  /**
   * Creates the form from the Drupal service container.
   */
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
   * Returns the stable form identifier.
   */
  public function getFormId(): string {
    return 'aculta_lms_lesson_authoring';
  }

  /**
   * Builds the lesson authoring form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    [$course, $lesson] = $this->routeEntities();
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título da aula'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => $lesson?->get('title')->value ?? '',
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Posição no curso'),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
      '#default_value' => $lesson?->get('weight')->value ?? $this->nextWeight((int) $course->id()),
    ];
    $form['published'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disponibilizar aos estudantes'),
      '#default_value' => (bool) ($lesson?->get('published')->value ?? FALSE),
      '#description' => $this->t('A aula só aparece na jornada de estudantes quando estiver publicada.'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $lesson ? $this->t('Salvar aula') : $this->t('Criar aula'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Voltar à estrutura do curso'),
      '#url' => Url::fromRoute('aculta_lms.course_structure', ['course' => $course->id()]),
    ];
    return $form;
  }

  /**
   * Validates lesson fields before save.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('title')) === '') {
      $form_state->setErrorByName('title', $this->t('Informe o título da aula.'));
    }
    $weight = filter_var($form_state->getValue('weight'), FILTER_VALIDATE_INT);
    if ($weight === FALSE || $weight < 0) {
      $form_state->setErrorByName('weight', $this->t('A posição precisa ser um inteiro não negativo.'));
    }
  }

  /**
   * Saves the lesson and returns to course structure.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$course, $lesson] = $this->routeEntities();
    $values = [
      'course_id' => (int) $course->id(),
      'title' => trim((string) $form_state->getValue('title')),
      'published' => (bool) $form_state->getValue('published'),
      'weight' => (int) $form_state->getValue('weight'),
    ];
    if ($lesson === NULL) {
      $lesson = $this->entityTypeManager->getStorage('aculta_lms_lesson')->create($values);
    }
    else {
      foreach ($values as $field => $value) {
        $lesson->set($field, $value);
      }
    }
    $lesson->save();
    $this->messenger()->addStatus($this->t('A aula foi salva.'));
    $form_state->setRedirect('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * Resolves and authorizes the route entities.
   *
   * @return array{0: \Drupal\group\Entity\GroupInterface, 1: ?\Drupal\Core\Entity\ContentEntityInterface}
   *   The course and optional lesson.
   */
  private function routeEntities(): array {
    $course = $this->currentRouteMatch->getParameter('course');
    $lesson = $this->currentRouteMatch->getParameter('lesson');
    if (!$course instanceof GroupInterface || ($lesson !== NULL && !$lesson instanceof ContentEntityInterface)
      || !$this->accessCheck->access($course, $lesson instanceof ContentEntityInterface ? $lesson : NULL)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return [$course, $lesson];
  }

  /**
   * Finds the next lesson position in the course.
   */
  private function nextWeight(int $courseId): int {
    $lessons = $this->entityTypeManager->getStorage('aculta_lms_lesson')->loadByProperties(['course_id' => $courseId]);
    $weights = array_map(static fn($lesson): int => (int) $lesson->get('weight')->value, $lessons);
    return $weights === [] ? 0 : max($weights) + 1;
  }

}
