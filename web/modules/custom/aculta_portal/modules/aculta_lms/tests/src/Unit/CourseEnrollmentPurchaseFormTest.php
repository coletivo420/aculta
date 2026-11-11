<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Form\CourseEnrollmentPurchaseForm;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Covers safe availability states for the learner purchase form.
 */
#[CoversClass(CourseEnrollmentPurchaseForm::class)]
final class CourseEnrollmentPurchaseFormTest extends UnitTestCase {

  /**
   * A course without published classes cannot start an enrollment checkout.
   */
  public function testUnpublishedClassesAreNotOffered(): void {
    $course = $this->course([
      $this->classRelationship($this->class('lms_class', '89', FALSE)),
    ]);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects(self::once())
      ->method('set')
      ->with('aculta_lms_course_id', '88');

    $form = $this->form()->buildForm([], $formState, $course);

    self::assertArrayNotHasKey('course_class', $form);
    self::assertSame(
      'Este curso ainda não possui turmas disponíveis para matrícula.',
      (string) $form['message']['#plain_text'],
    );
    self::assertArrayNotHasKey('actions', $form);
  }

  /**
   * A published class remains visible when online purchase is not configured.
   */
  public function testPublishedClassWithoutCourseProductHasNoCheckoutAction(): void {
    $course = $this->course([
      $this->classRelationship($this->class('lms_class', '89', TRUE)),
    ]);
    self::assertSame('lms_class', $course->getRelationships('lms_classes')[0]->getEntity()->bundle());
    self::assertTrue($course->getRelationships('lms_classes')[0]->getEntity()->isPublished());
    $course->expects(self::once())
      ->method('hasField')
      ->with('field_course_product')
      ->willReturn(FALSE);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects(self::once())
      ->method('set')
      ->with('aculta_lms_course_id', '88');

    $form = $this->form()->buildForm([], $formState, $course);

    self::assertArrayNotHasKey('course_class', $form);
    self::assertSame(
      'A inscrição online ainda não está disponível para este curso.',
      (string) $form['message']['#plain_text'],
    );
    self::assertArrayNotHasKey('actions', $form);
  }

  /**
   * An unpublished course cannot expose its enrollment purchase action.
   */
  public function testUnpublishedCourseHasNoCheckoutAction(): void {
    $course = $this->course([
      $this->classRelationship($this->class('lms_class', '89', TRUE)),
    ], FALSE);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects(self::never())->method('set');

    $form = $this->form()->buildForm([], $formState, $course);

    self::assertArrayNotHasKey('course_class', $form);
    self::assertSame(
      'A inscrição online não está disponível para este curso.',
      (string) $form['message']['#plain_text'],
    );
    self::assertArrayNotHasKey('actions', $form);
  }

  /**
   * Creates the form with collaborators unused by buildForm.
   */
  private function form(): CourseEnrollmentPurchaseForm {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      static fn(TranslatableMarkup $markup): string => $markup->getUntranslatedString(),
    );
    return new CourseEnrollmentPurchaseForm(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(CartProviderInterface::class),
      $this->createMock(CartManagerInterface::class),
      $this->createMock(CurrentStoreInterface::class),
      $this->createMock(CurrencyFormatterInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(MessengerInterface::class),
      $translation,
      new DomainSiteManager(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
    );
  }

  /**
   * Builds a cacheable course double with the supplied class relationships.
   */
  private function course(array $relationships, bool $published = TRUE): GroupInterface {
    $course = $this->createMock(GroupInterface::class);
    $course->method('id')->willReturn('88');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('isPublished')->willReturn($published);
    $course->method('getRelationships')->with('lms_classes')->willReturn($relationships);
    $course->method('hasField')->willReturn(FALSE);
    $course->method('getCacheTags')->willReturn(['group:88']);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);
    return $course;
  }

  /**
   * Builds a cacheable class group double.
   */
  private function class(string $bundle, string $id, bool $published): GroupInterface {
    $class = $this->createMock(GroupInterface::class);
    $class->method('bundle')->willReturn($bundle);
    $class->method('id')->willReturn($id);
    $class->method('label')->willReturn($published ? 'Turma publicada' : 'Turma fechada');
    $class->method('isPublished')->willReturn($published);
    $class->method('getCacheTags')->willReturn(['group:' . $id]);
    $class->method('getCacheContexts')->willReturn([]);
    $class->method('getCacheMaxAge')->willReturn(-1);
    return $class;
  }

  /**
   * Wraps a class group in its LMS course relationship.
   */
  private function classRelationship(GroupInterface $class): GroupRelationshipInterface {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getEntity')->willReturn($class);
    return $relationship;
  }

}
