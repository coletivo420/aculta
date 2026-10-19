<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\CourseRoomBuilder;
use Drupal\aculta_lms\Form\CourseEnrollmentPurchaseForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\Tests\UnitTestCase;

/** @coversDefaultClass \Drupal\aculta_lms\CourseRoomBuilder */
final class CourseRoomBuilderTest extends UnitTestCase {

  /** Ensures the course page can build the public enrollment form class. */
  public function testUsesEnrollmentPurchaseForm(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $course = $this->createMock(CourseInterface::class);
    $course->method('takeAccess')->with($account)->willReturn(AccessResult::forbidden());
    $course->method('hasPermission')->with('administer members', $account)->willReturn(FALSE);
    $course->method('getCacheTags')->willReturn([]);
    $course->method('getCacheContexts')->willReturn([]);
    $course->method('getCacheMaxAge')->willReturn(-1);

    $form = ['#plain_text' => 'enrollment form'];
    $formBuilder = $this->createMock(FormBuilderInterface::class);
    $formBuilder->expects(self::once())
      ->method('getForm')
      ->with(CourseEnrollmentPurchaseForm::class, $course)
      ->willReturn($form);

    $builder = new CourseRoomBuilder(
      $account,
      $this->createMock(TranslationInterface::class),
      $formBuilder,
    );

    self::assertSame($form, $builder->build($course)['enrollment']);
  }

}
