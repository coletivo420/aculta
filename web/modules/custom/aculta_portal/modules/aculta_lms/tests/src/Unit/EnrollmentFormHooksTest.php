<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\CourseOrderEnrollmentManagerInterface;
use Drupal\aculta_lms\Hook\EnrollmentFormHooks;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the manual deletion policy for class enrollments.
 */
#[CoversClass(EnrollmentFormHooks::class)]
#[Group('aculta_lms')]
final class EnrollmentFormHooksTest extends UnitTestCase {

  /**
   * A related Group class is accepted as a membership target.
   */
  public function testCourseClassSelectionUsesGroupRelationships(): void {
    [$hooks, $form_state] = $this->classSelectionContext('30');
    $form_state->expects(self::never())->method('setErrorByName');

    $form = [];
    $hooks->validateCourseClassSelection($form, $form_state);
  }

  /**
   * A Group that is not related to the course is rejected.
   */
  public function testCourseClassSelectionRejectsUnrelatedGroup(): void {
    [$hooks, $form_state] = $this->classSelectionContext('99');
    $form_state->expects(self::once())->method('setErrorByName')->with('class', self::anything());

    $form = [];
    $hooks->validateCourseClassSelection($form, $form_state);
  }

  /**
   * Manual enrollments remain removable without Commerce provenance.
   */
  public function testManualEnrollmentDeletionIsUnrestrictedByPurchasePolicy(): void {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $this->makeCacheable($relationship);
    $relationship->method('getPluginId')->willReturn('group_membership');
    $class = $this->classGroup();
    $relationship->method('getGroup')->willReturn($class);
    $student = $this->createMock(UserInterface::class);
    $this->makeCacheable($student);
    $relationship->method('getEntity')->willReturn($student);
    $relationship->method('hasField')->with('field_course_order')->willReturn(TRUE);
    $reference = $this->createMock(FieldItemListInterface::class);
    $reference->method('isEmpty')->willReturn(TRUE);
    $relationship->method('get')->with('field_course_order')->willReturn($reference);
    $enrollments = $this->createMock(CourseOrderEnrollmentManagerInterface::class);
    $enrollments->expects(self::once())->method('hasActivePurchaseForClass')->with($class, $student)->willReturn(FALSE);

    $result = $this->hooks($enrollments)->protectPaidEnrollment($relationship, 'delete', $this->createMock(UserInterface::class));

    self::assertTrue($result->isNeutral());
    self::assertSame(0, $result->getCacheMaxAge());
  }

  /**
   * A manual enrollment cannot be deleted while its student has paid.
   */
  public function testManualEnrollmentDeletionIsBlockedByActivePurchase(): void {
    $class = $this->classGroup();
    $student = $this->createMock(UserInterface::class);
    $this->makeCacheable($student);
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $this->makeCacheable($relationship);
    $relationship->method('getPluginId')->willReturn('group_membership');
    $relationship->method('getGroup')->willReturn($class);
    $relationship->method('getEntity')->willReturn($student);
    $relationship->method('hasField')->with('field_course_order')->willReturn(TRUE);
    $reference = $this->createMock(FieldItemListInterface::class);
    $reference->method('isEmpty')->willReturn(TRUE);
    $relationship->method('get')->with('field_course_order')->willReturn($reference);
    $enrollments = $this->createMock(CourseOrderEnrollmentManagerInterface::class);
    $enrollments->expects(self::once())->method('hasActivePurchaseForClass')->with($class, $student)->willReturn(TRUE);

    $result = $this->hooks($enrollments)->protectPaidEnrollment($relationship, 'delete', $this->createMock(UserInterface::class));

    self::assertTrue($result->isForbidden());
    self::assertSame(0, $result->getCacheMaxAge());
  }

  /**
   * A paid enrollment stays protected until all payments are refunded.
   */
  public function testPaidAndPartiallyRefundedEnrollmentCannotBeRemoved(): void {
    foreach (['completed', 'partially_refunded'] as $payment_state) {
      $payment = $this->createMock(PaymentInterface::class);
      $this->makeCacheable($payment);
      $payment->method('getState')->willReturn($this->state($payment_state));
      $result = $this->deleteResult($this->order('placed'), [$payment]);
      self::assertTrue($result->isForbidden(), sprintf('Payment state %s must preserve the enrollment.', $payment_state));
    }
  }

  /**
   * A fully refunded purchase permits manual removal of its enrollment.
   */
  public function testFullyRefundedEnrollmentCanBeRemoved(): void {
    $payment = $this->createMock(PaymentInterface::class);
    $this->makeCacheable($payment);
    $payment->method('getState')->willReturn($this->state('refunded'));

    self::assertTrue($this->deleteResult($this->order('placed'), [$payment])->isNeutral());
  }

  /**
   * A completed zero-value order still protects its enrollment.
   */
  public function testCompletedFreeOrderEnrollmentCannotBeRemoved(): void {
    self::assertTrue($this->deleteResult($this->order('completed'), [])->isForbidden());
  }

  /**
   * Missing purchase provenance fails closed for deletion.
   */
  public function testMissingOrderCannotUnlockEnrollmentDeletion(): void {
    self::assertTrue($this->deleteResult(NULL, [])->isForbidden());
  }

  /**
   * Malformed payment storage results deny deletion without causing an error.
   */
  public function testUnexpectedPaymentRecordFailsClosed(): void {
    self::assertTrue($this->deleteResult($this->order('placed'), [new \stdClass()])->isForbidden());
  }

  /**
   * Builds the hook and its payment storage for a specific order state.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $order
   *   The provenance order, or NULL to simulate a broken reference.
   * @param array<array-key, mixed> $payments
   *   Payment storage results, including malformed values for fail-closed
   *   tests.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The deletion access decision.
   */
  private function deleteResult(?OrderInterface $order, array $payments): AccessResultInterface {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $this->makeCacheable($relationship);
    $relationship->method('getPluginId')->willReturn('group_membership');
    $relationship->method('getGroup')->willReturn($this->classGroup());
    $relationship->method('hasField')->with('field_course_order')->willReturn(TRUE);
    $reference = $this->createMock(FieldItemListInterface::class);
    $reference->method('isEmpty')->willReturn(FALSE);
    $reference->method('__get')->with('entity')->willReturn($order);
    $relationship->method('get')->with('field_course_order')->willReturn($reference);

    $storage = $this->createMock(PaymentStorageInterface::class);
    $storage->method('loadMultipleByOrder')->willReturn($payments);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $hooks = new EnrollmentFormHooks(
      $entityTypeManager,
      $this->getStringTranslationStub(),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(CourseOrderEnrollmentManagerInterface::class),
    );

    return $hooks->protectPaidEnrollment($relationship, 'delete', $this->createMock(UserInterface::class));
  }

  /**
   * Creates a course order with a controllable workflow state.
   */
  private function order(string $state): OrderInterface {
    $order = $this->createMock(OrderInterface::class);
    $this->makeCacheable($order);
    $order->method('bundle')->willReturn('curso');
    $order->method('getState')->willReturn($this->state($state));
    return $order;
  }

  /**
   * Creates a workflow-state stub.
   */
  private function state(string $state): object {
    return new class ($state) {

      public function __construct(private readonly string $state) {}

      /**
       * Gets the state identifier.
       */
      public function getId(): string {
        return $this->state;
      }

    };
  }

  /**
   * Creates a Group class used by the enrollment deletion policy.
   */
  private function classGroup(): GroupInterface {
    $group = $this->createMock(GroupInterface::class);
    $this->makeCacheable($group);
    $group->method('bundle')->willReturn('lms_class');
    return $group;
  }

  /**
   * Builds hooks for testing a non-eligible entity operation.
   */
  private function hooks(?CourseOrderEnrollmentManagerInterface $enrollments = NULL): EnrollmentFormHooks {
    return new EnrollmentFormHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->getStringTranslationStub(),
      $this->createMock(AccountProxyInterface::class),
      $enrollments ?? $this->createMock(CourseOrderEnrollmentManagerInterface::class),
    );
  }

  /**
   * Creates a course selection form with one related class Group.
   *
   * @return array{\Drupal\aculta_lms\Hook\EnrollmentFormHooks,\Drupal\Core\Form\FormStateInterface}
   *   Hooks de inscrição e estado do formulário montados.
   */
  private function classSelectionContext(string $selected_class): array {
    $class = $this->createMock(GroupInterface::class);
    $class->method('bundle')->willReturn('lms_class');
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getEntityId')->willReturn('30');
    $relationship->method('getEntity')->willReturn($class);
    $course = $this->createMock(GroupInterface::class);
    $course->method('bundle')->willReturn('lms_course');
    $course->method('id')->willReturn('10');
    $course->method('getRelationships')->with('lms_classes')->willReturn([$relationship]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with('10')->willReturn($course);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('group')->willReturn($storage);
    $member_relationship = $this->createMock(GroupRelationshipInterface::class);
    $member_relationship->method('getOwnerId')->willReturn('2');
    $member_relationship->method('getEntityId')->willReturn('3');
    $form_object = $this->createMock(ContentEntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($member_relationship);
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('aculta_lms_enrollment_course_id')->willReturn('10');
    $form_state->method('getFormObject')->willReturn($form_object);
    $form_state->method('getValue')->willReturnCallback(static fn(string $key): mixed => match ($key) {
      'class' => $selected_class,
      'group_roles' => [],
      default => NULL,
    });

    $hooks = new EnrollmentFormHooks(
      $entity_type_manager,
      $this->getStringTranslationStub(),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(CourseOrderEnrollmentManagerInterface::class),
    );
    return [$hooks, $form_state];
  }

  /**
   * Makes an entity mock safe to add to cacheable access results.
   */
  private function makeCacheable(CacheableDependencyInterface $dependency): void {
    $dependency->method('getCacheContexts')->willReturn([]);
    $dependency->method('getCacheTags')->willReturn([]);
    $dependency->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
  }

}
