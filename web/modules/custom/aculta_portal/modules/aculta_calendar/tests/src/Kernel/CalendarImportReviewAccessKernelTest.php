<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Entity\CalendarImportSource;
use Drupal\aculta_calendar\Form\CalendarImportReviewForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks that the import review queue is restricted to authorized staff. */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class CalendarImportReviewAccessKernelTest extends KernelTestBase {

  /**
   * Modules enabled by this Kernel test.
   *
   * @var string[]
   */

  protected static $modules = ['system', 'user', 'aculta_calendar'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_calendar_imported_item');
  }

  /**
   * A dangling source reference must not break the whole review queue.
   */
  public function testDanglingSourceDoesNotBreakReviewQueue(): void {
    $source = CalendarImportSource::create([
      'id' => 'fonte_orfa',
      'label' => 'Fonte órfã',
      'sourceUrl' => 'https://example.invalid/calendar.ics',
      'status' => TRUE,
    ]);
    $source->save();
    $repository = $this->container->get('aculta_calendar.import_repository');
    $repository->loadOrCreate($source, 'uid-orfao@example.invalid');
    $source->delete();

    $form = CalendarImportReviewForm::create($this->container);
    $build = $form->buildForm([], new FormState());
    $rows = array_filter(array_keys($build['items']), 'is_int');

    self::assertSame([], array_values($rows));
  }

  /**
   * Verifies regular user cannot read review queue.
   */
  public function testRegularUserCannotReadReviewQueue(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_calendar.import_review');
    $request = Request::create('/painel-administrativo/calendario/importacao');
    $request->attributes->set('_route', 'aculta_calendar.import_review');
    $request->attributes->set('_route_object', $route);
    $result = $this->container->get('access_manager')->check(
      RouteMatch::createFromRequest($request),
      new UserSession(['uid' => 2, 'roles' => ['authenticated']]),
      $request,
      TRUE,
    );

    self::assertFalse($result->isAllowed());
  }

}
