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
use PHPUnit\Framework\MockObject\MockObject;
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
    self::assertArrayHasKey('message', $form);
    self::assertIsArray($form['message']);
    self::assertArrayHasKey('#plain_text', $form['message']);
    self::assertSame(
      'Este curso ainda não possui turmas disponíveis para matrícula.',
      $this->plainText($form['message']),
    );
    self::assertArrayNotHasKey('actions', $form);
  }

  /**
   * A published class remains visible when online purchase is not configured.
   */
  public function testPublishedClassWithoutCourseProductHasNoCheckoutAction(): void {
    $relationship = $this->classRelationship($this->class('lms_class', '89', TRUE));
    $course = $this->course([$relationship]);
    $class = $relationship->getEntity();
    self::assertInstanceOf(GroupInterface::class, $class);
    self::assertSame('lms_class', $class->bundle());
    self::assertTrue($class->isPublished());
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
    self::assertArrayHasKey('message', $form);
    self::assertIsArray($form['message']);
    self::assertArrayHasKey('#plain_text', $form['message']);
    self::assertSame(
      'A inscrição online ainda não está disponível para este curso.',
      $this->plainText($form['message']),
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
    self::assertArrayHasKey('message', $form);
    self::assertIsArray($form['message']);
    self::assertArrayHasKey('#plain_text', $form['message']);
    self::assertSame(
      'A inscrição online não está disponível para este curso.',
      $this->plainText($form['message']),
    );
    self::assertArrayNotHasKey('actions', $form);
  }

  /**
   * Reads plain text from a validated form message element.
   *
   * @param array<array-key, mixed> $element
   *   Render array element containing #plain_text.
   */
  private function plainText(array $element): string {
    $text = $element['#plain_text'] ?? NULL;
    self::assertInstanceOf(TranslatableMarkup::class, $text);
    return $text->getUntranslatedString();
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
   *
   * @param list<GroupRelationshipInterface> $relationships
   *   LMS class relations exposed by the course.
   *
   * @return GroupInterface&MockObject
   *   The course fixture.
   */
  private function course(array $relationships, bool $published = TRUE): GroupInterface&MockObject {
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
  private function class(string $bundle, string $id, bool $published): GroupInterface&MockObject {
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
  private function classRelationship(GroupInterface $class): GroupRelationshipInterface&MockObject {
    $relationship = $this->createMock(GroupRelationshipInterface::class);
    $relationship->method('getEntity')->willReturn($class);
    return $relationship;
  }

}
