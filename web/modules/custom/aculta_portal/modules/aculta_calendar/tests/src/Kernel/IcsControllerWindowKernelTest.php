<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Controller\IcsController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the inclusive one-year window of the .ics period export.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class IcsControllerWindowKernelTest extends KernelTestBase {

  /**
   * Modules enabled by this Kernel test.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_calendar'];

  /**
   * A 365-day difference (366 inclusive days) is accepted.
   */
  public function testInclusiveOneYearWindowIsAccepted(): void {
    self::assertSame(200, $this->response('01/01/2026', '01/01/2027')->getStatusCode());
  }

  /**
   * A 366-day difference (367 inclusive days) is rejected.
   */
  public function test367DayWindowIsRejected(): void {
    self::assertSame(400, $this->response('01/01/2026', '02/01/2027')->getStatusCode());
  }

  /**
   * Runs the period action with the given query dates.
   */
  private function response(string $de, string $ate): Response {
    $request = Request::create('/calendario/exportar.ics', 'GET', ['de' => $de, 'ate' => $ate]);
    $stack = $this->container->get('request_stack');
    $stack->push($request);
    try {
      return IcsController::create($this->container)->periodo();
    }
    finally {
      $stack->pop();
    }
  }

}
