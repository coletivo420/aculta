<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\GanjaLab420Controller;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ensures the Studio entrypoint fails closed without the LMS provider.
 */
#[CoversClass(GanjaLab420Controller::class)]
#[Group('aculta_studio')]
final class GanjaLab420ControllerTest extends UnitTestCase {

  public function testNoLmsReturnsNotFound(): void {
    $manager = new StudioExperienceManager();
    $account = $this->createMock(AccountInterface::class);
    $this->expectException(NotFoundHttpException::class);
    (new GanjaLab420Controller($manager, $account))->student();
  }

}
