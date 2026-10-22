<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_lms\Controller\LearningDashboardController;
use Drupal\aculta_studio\Controller\GanjaLab420Controller;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Keeps the student dashboard implementation in LMS while Studio owns entry.
 */
#[CoversClass(GanjaLab420Controller::class)]
#[Group('aculta_studio')]
final class GanjaLab420ControllerTest extends UnitTestCase {

  public function testDelegatesStudentDashboardToLms(): void {
    $expected = ['#type' => 'container', '#cache' => ['max-age' => 0]];
    $lms = $this->createMock(LearningDashboardController::class);
    $lms->expects(self::once())->method('student')->willReturn($expected);
    self::assertSame($expected, (new GanjaLab420Controller($lms))->student());
  }

  public function testNoLmsReturnsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    (new GanjaLab420Controller(NULL))->student();
  }

}
