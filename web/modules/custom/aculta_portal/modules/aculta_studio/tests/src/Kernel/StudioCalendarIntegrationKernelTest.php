<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Kernel;

use Drupal\aculta_calendar\Agenda\CalendarAgenda;
use Drupal\aculta_calendar\Agenda\MonthGrid;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Saved\SavedContentQuery;
use Drupal\aculta_studio\Calendar\StudioDashboardCalendarComposer;
use Drupal\aculta_studio\Controller\StudioCalendarController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel test for Studio dashboard calendar composer and fragment controller.
 */
#[CoversClass(StudioDashboardCalendarComposer::class)]
#[CoversClass(StudioCalendarController::class)]
#[Group('aculta_studio')]
#[RunTestsInSeparateProcesses]
final class StudioCalendarIntegrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'datetime',
    'datetime_range',
    'domain',
    'aculta_calendar',
    'aculta_studio',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $root = dirname(__DIR__, 9);
    $loader = require $root . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_studio\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_studio/src');
    $loader->addPsr4('Drupal\\aculta_portal\\', $root . '/web/modules/custom/aculta_portal/src');

    $container->register('aculta_portal.saved_content_query', SavedContentQuery::class)
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('entity_type.manager'));

    $container->register('aculta_portal.domain_purpose', DomainPurposeManager::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('domain.negotiator', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('request_stack'));

    $container->register('aculta_studio.calendar_composer', StudioDashboardCalendarComposer::class)
      ->addArgument(new Reference('aculta_calendar.agenda', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('aculta_calendar.month_grid'))
      ->addArgument(new Reference('current_user'))
      ->addArgument(new Reference('url_generator', ContainerBuilder::NULL_ON_INVALID_REFERENCE));

    $container->register('aculta_studio.calendar_controller', StudioCalendarController::class)
      ->addArgument(new Reference('aculta_studio.calendar_composer'));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Tests composer and controller under authenticated user.
   */
  public function testAuthenticatedCalendarComposition(): void {
    $user = User::create([
      'name' => 'calendartester',
      'mail' => 'calendartester@example.com',
      'status' => 1,
    ]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    /** @var \Drupal\aculta_studio\Calendar\StudioDashboardCalendarComposer $composer */
    $composer = $this->container->get('aculta_studio.calendar_composer');
    self::assertInstanceOf(StudioDashboardCalendarComposer::class, $composer);

    $build = $composer->compose(2026, 11, 'cursos', '2026-11-06');
    self::assertSame('component', $build['#type']);
    self::assertSame('aculta420_v2:studio-calendar', $build['#component']);
    self::assertSame('Novembro de 2026', $build['#props']['current_month_label']);
    self::assertSame('cursos', $build['#props']['active_filter']);
    self::assertTrue($build['#props']['can_add_event']);
    self::assertSame('/culturas/propostas/evento/nova', $build['#props']['add_event_url']);
    self::assertNotEmpty($build['#props']['weeks']);
    self::assertCount(3, $build['#props']['filters']);

    /** @var \Drupal\aculta_studio\Controller\StudioCalendarController $controller */
    $controller = $this->container->get('aculta_studio.calendar_controller');
    self::assertInstanceOf(StudioCalendarController::class, $controller);

    $request = Request::create('/estudio/calendario-fragmento', 'GET', [
      'ano' => 2026,
      'mes' => 11,
      'filtro' => 'eventos',
      'dia' => '2026-11-10',
    ]);
    $fragBuild = $controller->fragment($request);
    self::assertSame('eventos', $fragBuild['#props']['active_filter']);
    self::assertSame('Novembro de 2026', $fragBuild['#props']['current_month_label']);
  }

  /**
   * Tests anonymous user has can_add_event set to false.
   */
  public function testAnonymousCalendarComposition(): void {
    /** @var \Drupal\aculta_studio\Calendar\StudioDashboardCalendarComposer $composer */
    $composer = $this->container->get('aculta_studio.calendar_composer');
    $build = $composer->compose(2026, 11, 'cursos');
    self::assertFalse($build['#props']['can_add_event']);
    self::assertNull($build['#props']['add_event_url']);
  }

}
