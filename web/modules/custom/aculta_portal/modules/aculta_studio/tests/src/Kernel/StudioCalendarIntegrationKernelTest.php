<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Kernel;

use Composer\Autoload\ClassLoader;
use Drupal\aculta_portal\Domain\DomainSiteManager;
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
    if (!$loader instanceof ClassLoader) {
      throw new \RuntimeException('Autoloader inválido no teste Kernel.');
    }
    $loader->addPsr4('Drupal\\aculta_studio\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_studio/src');
    $loader->addPsr4('Drupal\\aculta_portal\\', $root . '/web/modules/custom/aculta_portal/src');

    $container->register('aculta_portal.saved_content_query', SavedContentQuery::class)
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('entity_type.manager'));

    $container->register('aculta_portal.domain_site', DomainSiteManager::class)
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('domain.negotiator', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('request_stack'));

    $container->register('aculta_studio.calendar_composer', StudioDashboardCalendarComposer::class)
      ->addArgument(new Reference('aculta_calendar.agenda', ContainerBuilder::NULL_ON_INVALID_REFERENCE))
      ->addArgument(new Reference('aculta_calendar.month_grid'))
      ->addArgument(new Reference('current_user'));

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

    $composer = $this->calendarComposer();
    $build = $composer->compose(2026, 11, 'cursos', '2026-11-06');
    $props = $this->props($build);
    self::assertSame('portal_component', $build['#theme']);
    self::assertSame('studio-calendar', $build['#name']);
    self::assertSame('Novembro de 2026', $props['current_month_label']);
    $filters = $props['filters'] ?? NULL;
    self::assertIsArray($filters);
    $firstFilter = $filters[0] ?? NULL;
    self::assertIsArray($firstFilter);
    self::assertTrue($firstFilter['active'] ?? FALSE);
    self::assertTrue($props['can_add_event'] ?? FALSE);
    self::assertSame('/culturas/propostas/evento/nova', $props['add_event_url'] ?? NULL);
    self::assertNotEmpty($props['weeks'] ?? []);
    self::assertCount(3, $filters);

    $controller = $this->container->get('aculta_studio.calendar_controller');
    $request = Request::create('/estudio/calendario-fragmento', 'GET', [
      'ano' => 2026,
      'mes' => 11,
      'filtro' => 'eventos',
      'dia' => '2026-11-10',
    ]);
    $fragBuild = $controller->fragment($request);
    $fragmentProps = $this->props($fragBuild);
    $filters = $fragmentProps['filters'] ?? NULL;
    self::assertIsArray($filters);
    $secondFilter = $filters[1] ?? NULL;
    self::assertIsArray($secondFilter);
    self::assertTrue($secondFilter['active'] ?? FALSE);
    self::assertSame('Novembro de 2026', $fragmentProps['current_month_label'] ?? NULL);
  }

  /**
   * Tests anonymous user has can_add_event set to false.
   */
  public function testAnonymousCalendarComposition(): void {
    $composer = $this->calendarComposer();
    $build = $composer->compose(2026, 11, 'cursos');
    $props = $this->props($build);
    self::assertFalse($props['can_add_event'] ?? TRUE);
    self::assertNull($props['add_event_url'] ?? NULL);
  }

  /**
   * Provides the props operation.
   *
   * @param array<array-key, mixed> $build
   *   Render array to modify.
   *
   * @return array<array-key, mixed>
   *   Render array returned by the method.
   */
  private function props(array $build): array {
    $props = $build['#props'] ?? NULL;
    if (!is_array($props)) {
      throw new \UnexpectedValueException('A composição do calendário não tem props.');
    }
    return $props;
  }

  /**
   * Provides the calendarComposer operation.
   */
  private function calendarComposer(): StudioDashboardCalendarComposer {
    return $this->container->get('aculta_studio.calendar_composer');
  }

}
