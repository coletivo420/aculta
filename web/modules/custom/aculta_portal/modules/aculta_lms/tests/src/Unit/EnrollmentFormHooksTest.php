<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Hook\EnrollmentFormHooks;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PaymentStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
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
   * Manual enrollments remain removable without Commerce provenance.
   */
  public function testManualEnrollmentDeletionIsUnrestrictedByPurchasePolicy(): void {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getPluginId')->willReturn('group_membership');
    $relationship->method('getGroup')->willReturn($this->classGroup());
    $relationship->method('hasField')->with('field_course_order')->willReturn(FALSE);

    $result = $this->hooks()->protectPaidEnrollment($relationship, 'delete', $this->createMock(UserInterface::class));

    self::assertTrue($result->isNeutral());
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
   * Builds the hook and its payment storage for a specific order state.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $order
   *   The provenance order, or NULL to simulate a broken reference.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface[] $payments
   *   Payments associated with the order.
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
  private function hooks(): EnrollmentFormHooks {
    return new EnrollmentFormHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->getStringTranslationStub(),
      $this->createMock(AccountProxyInterface::class),
    );
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
