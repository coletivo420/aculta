<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\Form\GroupRelationshipDeleteForm;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms_classes\ClassHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Keeps course enrollment attached to a class that belongs to that course. */
final class EnrollmentFormHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Require a class selection on native course membership forms.
   */
  #[Hook('form_group_relationship_form_alter')]
  #[Hook('form_group_relationship_confirm_form_alter')]
  public function groupRelationshipFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface || $form_object instanceof GroupRelationshipDeleteForm) {
      return;
    }

    $relationship = $form_object->getEntity();
    if (!$relationship instanceof GroupRelationshipInterface || $relationship->getPluginId() !== 'group_membership') {
      return;
    }

    $course = $relationship->getGroup();
    if (!$course instanceof CourseInterface) {
      return;
    }

    // Keep the original course available after LMS Classes converts the
    // unsaved relationship to a membership on the selected class.
    $form_state->set('aculta_lms_enrollment_course_id', (string) $course->id());
    $form['#validate'][] = [$this, 'validateCourseClassSelection'];

    if (!isset($form['class'])) {
      return;
    }

    $form['class']['#required'] = TRUE;
    if (ClassHelper::getClasses($course) === [] && $relationship->getOwnerId() === $relationship->getEntityId()) {
      $form['class'] = [
        '#type' => 'item',
        '#title' => $this->translation->translate('Turma'),
        '#markup' => $this->translation->translate('Este curso ainda não tem turma. Entre em contato com a equipe.'),
      ];
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#access'] = FALSE;
      }
    }
  }

  /** Reject direct course-only joins and selections from other courses. */
  public function validateCourseClassSelection(array &$form, FormStateInterface $form_state): void {
    $course_id = $form_state->get('aculta_lms_enrollment_course_id');
    if (!is_string($course_id) || $course_id === '') {
      return;
    }

    $course = $this->entityTypeManager->getStorage('group')->load($course_id);
    if (!$course instanceof CourseInterface) {
      $form_state->setErrorByName('class', $this->translation->translate('Não foi possível validar a turma deste curso.'));
      return;
    }

    $class_id = (string) $form_state->getValue('class');
    $selected_roles = array_filter(array_map(static function (mixed $value): string {
      if (is_array($value)) {
        $value = $value['target_id'] ?? $value['value'] ?? '';
      }
      return is_string($value) || is_int($value) ? (string) $value : '';
    }, (array) $form_state->getValue('group_roles', [])));
    $course_manager_roles = [
      'lms_course-teacher',
      'lms_course-course_creator',
      'lms_course-lms_admin',
      'lms_course-lms_admin_member',
    ];
    $is_course_manager = array_intersect($course_manager_roles, $selected_roles) !== [];
    $available_class_ids = array_map(
      static fn (GroupRelationshipInterface $relationship): string => (string) $relationship->getEntityId(),
      $course->getRelationships('lms_classes'),
    );

    if ($is_course_manager) {
      if ($class_id !== '0') {
        $form_state->setErrorByName('class', $this->translation->translate('Educadores e administradores devem manter a membership no curso, não na turma.'));
      }
      return;
    }

    if ($available_class_ids === []) {
      $form_state->setErrorByName('class', $this->translation->translate('Este curso ainda não tem turma. Entre em contato com a equipe.'));
      return;
    }

    if ($class_id === '' || $class_id === '0' || !in_array($class_id, $available_class_ids, TRUE)) {
      $form_state->setErrorByName('class', $this->translation->translate('Selecione uma turma disponível deste curso.'));
    }
  }

}
