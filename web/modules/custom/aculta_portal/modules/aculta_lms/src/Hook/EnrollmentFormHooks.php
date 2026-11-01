<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\CourseOrderEnrollmentManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\Form\GroupRelationshipDeleteForm;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms_classes\ClassHelper;
use Drupal\user\UserInterface;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps course enrollment attached to a class that belongs to that course.
 */
final class EnrollmentFormHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'aculta_lms.course_order_enrollment')]
    private readonly CourseOrderEnrollmentManagerInterface $enrollments,
    #[Autowire(service: 'aculta_portal.checkout_settings')]
    private readonly ?CheckoutSettings $checkoutSettings = NULL,
  ) {}

  /**
   * Prevents deleting a class membership granted by a still-paid course order.
   */
  #[Hook('entity_access')]
  public function protectPaidEnrollment(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'delete'
      || !$entity instanceof GroupRelationshipInterface
      || $entity->getPluginId() !== 'group_membership') {
      return AccessResult::neutral();
    }

    $class = $entity->getGroup();
    if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class') {
      return AccessResult::neutral();
    }
    if (!$entity->hasField('field_course_order') || $entity->get('field_course_order')->isEmpty()) {
      $student = $entity->getEntity();
      if (!$student instanceof UserInterface) {
        return AccessResult::neutral()->addCacheableDependency($entity)->setCacheMaxAge(0);
      }
      $result = AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheableDependency($class)
        ->addCacheableDependency($student)
        ->setCacheMaxAge(0);
      if ($this->enrollments->hasActivePurchaseForClass($class, $student)) {
        return AccessResult::forbidden('Esta matrícula não pode ser removida enquanto houver uma compra válida para a turma.')
          ->addCacheableDependency($entity)
          ->addCacheableDependency($class)
          ->addCacheableDependency($student)
          ->setCacheMaxAge(0);
      }
      return $result;
    }

    $order = $entity->get('field_course_order')->entity;
    if (!$order instanceof OrderInterface) {
      // Missing provenance is not permission to revoke access.
      return AccessResult::forbidden('A matrícula está vinculada a um pedido; a origem não pôde ser validada.')
        ->addCacheableDependency($entity);
    }
    if (!in_array($order->bundle(), $this->checkoutSettings?->lmsOrderTypes() ?? [CheckoutSettings::LEGACY_LMS_ORDER_TYPE], TRUE)) {
      return AccessResult::forbidden('Matrículas concedidas por compras pagas não podem ser removidas manualmente.')
        ->addCacheableDependency($entity)
        ->addCacheableDependency($order);
    }

    $access = AccessResult::neutral()->addCacheableDependency($entity)->addCacheableDependency($order);
    $payments = $this->entityTypeManager->getStorage('commerce_payment')->loadMultipleByOrder($order);
    $has_payment = $payments !== [];
    if (!$has_payment && $order->getState()->getId() === 'canceled') {
      return $access;
    }
    foreach ($payments as $payment) {
      if ($payment instanceof PaymentInterface) {
        $access->addCacheableDependency($payment);
      }
      // A partial refund does not revoke a course enrollment. Only an order
      // whose every payment is fully refunded releases the manual-delete lock.
      if (!$payment instanceof PaymentInterface || $payment->getState()->getId() !== 'refunded') {
        $denied = AccessResult::forbidden('Matrículas concedidas por compras pagas não podem ser removidas manualmente.')
          ->addCacheableDependency($entity)
          ->addCacheableDependency($order);
        if ($payment instanceof CacheableDependencyInterface) {
          $denied->addCacheableDependency($payment);
        }
        return $denied;
      }
    }

    if (!$has_payment) {
      // Commerce treats placed zero-value orders as paid; they also grant
      // access and remain protected from manual deletion.
      return AccessResult::forbidden('Matrículas concedidas por compras pagas não podem ser removidas manualmente.')
        ->addCacheableDependency($entity)
        ->addCacheableDependency($order);
    }

    return $access;
  }

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

    if ($relationship->getOwnerId() === $relationship->getEntityId()
      && !$course->hasPermission('administer members', $this->currentUser)) {
      $form['class'] = [
        '#type' => 'item',
        '#title' => $this->translation->translate('Matrícula'),
        '#markup' => $this->translation->translate('Para entrar no curso, escolha uma turma e finalize a inscrição pela página do curso, inclusive quando ele for gratuito.'),
      ];
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#access'] = FALSE;
      }
      return;
    }

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

  /**
   * Rejects direct course-only joins and selections from other courses.
   */
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

    $form_object = $form_state->getFormObject();
    $relationship = $form_object instanceof ContentEntityFormInterface ? $form_object->getEntity() : NULL;
    if ($relationship instanceof GroupRelationshipInterface
      && $relationship->getOwnerId() === $relationship->getEntityId()
      && !$course->hasPermission('administer members', $this->currentUser)) {
      $form_state->setErrorByName('class', $this->translation->translate('A autoinscrição é feita pelo checkout do curso.'));
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
