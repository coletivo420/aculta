<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\GanjalabController;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ensures the Studio entrypoint fails closed without the LMS provider.
 */
#[CoversClass(GanjalabController::class)]
#[Group('aculta_studio')]
final class GanjalabControllerTest extends UnitTestCase {

  /**
   * The dashboard must fail closed when the optional LMS provider is absent.
   */
  public function testNoLmsReturnsNotFound(): void {
    $manager = new StudioExperienceManager();
    $account = $this->createMock(AccountInterface::class);
    $this->expectException(NotFoundHttpException::class);
    (new GanjalabController($manager, $account))->student();
  }

}
