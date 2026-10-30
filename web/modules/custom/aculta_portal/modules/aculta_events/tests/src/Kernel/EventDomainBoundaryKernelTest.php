<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Plugin\Block\UpcomingEventsBlock;
use Drupal\aculta_portal\Domain\ContentPurposeResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Enforces the eventos domain boundary and the read-only cross-domain block.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDomainBoundaryKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'key', 'options', 'link', 'text', 'filter',
    'taxonomy', 'datetime', 'datetime_range', 'address', 'geofield', 'domain',
    'aculta_calendar', 'aculta_maps', 'aculta_events', 'aculta_events_openstreetmap',
  ];

  /**
   * Editorial routes that must live only in the eventos purpose.
   */
  private const EDITORIAL_ROUTES = [
    'aculta_events.event_add',
    'aculta_events.event_edit',
    'aculta_events.event_date_add',
    'aculta_events.event_date_edit',
    'aculta_events.event_date_delete',
    'aculta_events.event_date_order',
    'aculta_events.locations',
    'aculta_events.location_add',
    'aculta_events.location_edit',
    'aculta_events.location_delete',
    'aculta_events.admin_overview',
    'aculta_events.settings',
    'aculta_events.location_modal',
    'aculta_events.location_widget_clear',
    'aculta_events.date_rule',
    'aculta_events.occurrence',
    'aculta_events.admin_export',
  ];

  /** Organizer tools are writable only in the Eventos purpose. */
  private const ORGANIZER_ROUTES = [
    'aculta_events.organizer_dashboard',
    'aculta_events.organizer_event_add',
    'aculta_events.organizer_event_edit',
    'aculta_events.organizer_event_date_add',
    'aculta_events.organizer_event_date_edit',
    'aculta_events.organizer_event_date_delete',
    'aculta_events.organizer_event_date_order',
    'aculta_events.organizer_location_add',
    'aculta_events.organizer_location_edit',
    'aculta_events.organizer_location_delete',
    'aculta_events.organizer_location_modal',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('domain');
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
  }

  /**
   * Every /painel-administrativo route declares the MAIN purpose.
   */
  public function testEditorialRoutesBelongToMainPurpose(): void {
    $routeProvider = $this->container->get('router.route_provider');
    foreach (self::EDITORIAL_ROUTES as $name) {
      $route = $routeProvider->getRouteByName($name);
      self::assertSame('main', $route->getOption('_aculta_domain_purpose'), $name);
      self::assertTrue((bool) $route->getOption('_admin_route'), $name);
      self::assertFalse(DomainRoutePolicy::allowsSharedPurpose($route, 'main', 'eventos'), $name);
      self::assertTrue(DomainRoutePolicy::allowsSharedPurpose($route, 'main', 'main'), $name);
    }
  }

  /**
   * Organizer routes use owner permissions and never become admin routes.
   */
  public function testOrganizerRoutesBelongOnlyToEventsPurpose(): void {
    $routeProvider = $this->container->get('router.route_provider');
    foreach (self::ORGANIZER_ROUTES as $name) {
      $route = $routeProvider->getRouteByName($name);
      self::assertSame('eventos', $route->getOption('_aculta_domain_purpose'), $name);
      self::assertFalse((bool) $route->getOption('_admin_route'), $name);
      self::assertTrue(DomainRoutePolicy::allowsSharedPurpose($route, 'eventos', 'eventos'), $name);
      self::assertFalse(DomainRoutePolicy::allowsSharedPurpose($route, 'eventos', 'main'), $name);
      self::assertTrue((bool) $route->getOption('no_cache'), $name);
    }
  }

  /**
   * The OpenStreetMap integration belongs to MAIN like the editorial area.
   */
  public function testOpenStreetMapIntegrationBelongsToMainPurpose(): void {
    $routeProvider = $this->container->get('router.route_provider');

    foreach (['aculta_events_openstreetmap.status', 'aculta_events_openstreetmap.import'] as $name) {
      $route = $routeProvider->getRouteByName($name);
      self::assertSame('main', $route->getOption('_aculta_domain_purpose'), $name);
      self::assertFalse(DomainRoutePolicy::allowsSharedPurpose($route, 'main', 'eventos'), $name);
      self::assertTrue(DomainRoutePolicy::allowsSharedPurpose($route, 'main', 'main'), $name);
    }

    foreach (['aculta_events_openstreetmap.oauth_authorize', 'aculta_events_openstreetmap.oauth_callback'] as $name) {
      $route = $routeProvider->getRouteByName($name);
      self::assertSame('main', $route->getOption('_aculta_domain_purpose'), $name);
      self::assertFalse(DomainRoutePolicy::allowsSharedPurpose($route, 'main', 'eventos'), $name);
      self::assertTrue(DomainRoutePolicy::allowsSharedPurpose($route, 'main', 'main'), $name);
    }
  }

  /**
   * Administrative routes resolve to MAIN; public event routes to eventos.
   */
  public function testResolverKeepsAdministrativeRoutesOnMain(): void {
    $resolver = new ContentPurposeResolver(
      $this->container->get('entity_type.manager'),
      $this->purposes(),
    );
    $routeProvider = $this->container->get('router.route_provider');

    $admin = $routeProvider->getRouteByName('aculta_events.event_add');
    self::assertSame('main', $resolver->requiredPurpose($admin, 'aculta_events.event_add', new Request()));

    $public = $routeProvider->getRouteByName('aculta_events.page');
    self::assertSame('eventos', $resolver->requiredPurpose($public, 'aculta_events.page', new Request()));

    $plainAdmin = new Route('/painel-administrativo/exemplo', [], [], ['_admin_route' => TRUE]);
    self::assertSame('main', $resolver->requiredPurpose($plainAdmin, 'exemplo', new Request()));
  }

  /**
   * The read-only block is discoverable by the block plugin manager.
   */
  public function testUpcomingBlockIsDiscoverable(): void {
    $definitions = $this->container->get('plugin.manager.block')->getDefinitions();
    self::assertArrayHasKey('aculta_events_upcoming', $definitions);
    self::assertSame('ACULTA Eventos', (string) $definitions['aculta_events_upcoming']['category']);
  }

  /**
   * The block lists only published, viewable events.
   */
  public function testUpcomingBlockListsOnlyPublishedAndViewableEvents(): void {
    $published = Event::create(['title' => 'Evento público', 'field_status' => 'publicado']);
    $published->save();
    $this->date($published, '2026-12-01T19:00:00');

    $draft = Event::create(['title' => 'Evento em rascunho', 'field_status' => 'rascunho']);
    $draft->save();
    $this->date($draft, '2026-12-02T19:00:00');

    $build = $this->block()->build();

    self::assertCount(1, $build['#items']);
    self::assertStringContainsString('Evento público', (string) $build['#items'][0]['#title']);
    self::assertContains('aculta_event_list', $build['#cache']['tags']);
    self::assertContains('user', $build['#cache']['contexts']);
    self::assertContains('url.site', $build['#cache']['contexts']);
  }

  /**
   * The block exposes no editorial action in the rendered tree.
   */
  public function testUpcomingBlockHasNoEditorialActions(): void {
    $event = Event::create(['title' => 'Evento somente leitura', 'field_status' => 'publicado']);
    $event->save();
    $this->date($event, '2026-12-03T19:00:00');

    $build = $this->block()->build();
    $links = $this->collectLinks($build);

    $forbiddenRoutes = [
      'aculta_events.event_edit',
      'aculta_events.event_date_add',
      'aculta_events.event_date_edit',
      'aculta_events.location_add',
      'aculta_events.event_add',
    ];
    foreach ($forbiddenRoutes as $forbidden) {
      self::assertNotContains($forbidden, $links, 'O bloco não deve oferecer ' . $forbidden);
    }
    $hasEventLink = in_array('aculta_events.page', $links, TRUE)
      || array_filter($links, static fn (string $link): bool => str_contains($link, '/eventos/')) !== [];
    self::assertTrue($hasEventLink, 'O bloco deve apontar para a página pública do Evento.');
  }

  /**
   * Builds the domain purpose manager without enabling all of aculta_portal.
   */
  private function purposes(): DomainPurposeManager {
    return new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
  }

  /**
   * Builds the block with the container services.
   */
  private function block(): UpcomingEventsBlock {
    return new UpcomingEventsBlock(
      [],
      'aculta_events_upcoming',
      [
        'id' => 'aculta_events_upcoming',
        'provider' => 'aculta_events',
        'class' => UpcomingEventsBlock::class,
        'admin_label' => 'Próximos eventos',
      ],
      $this->container->get('aculta_events.catalogue'),
      $this->purposes(),
      $this->container->get('datetime.time'),
    );
  }

  /**
   * Creates a published upcoming Date for an Event.
   */
  private function date(Event $event, string $start): void {
    $end = (new \DateTimeImmutable($start))->modify('+2 hours')->format('Y-m-d\TH:i:s');
    EventDate::create([
      'title' => $event->label() . ' — sessão',
      'field_evento' => $event->id(),
      'field_quando' => ['value' => $start, 'end_value' => $end],
    ])->save();
  }

  /**
   * Recursively collects route names or URIs used by links.
   *
   * @return string[]
   *   Route names (for route URLs) or absolute URIs (for external URLs).
   */
  private function collectLinks(array $element): array {
    $links = [];
    if (isset($element['#url']) && $element['#url'] instanceof Url) {
      $links[] = $element['#url']->isExternal()
        ? (string) $element['#url']->getUri()
        : (string) $element['#url']->getRouteName();
    }
    foreach ($element as $child) {
      if (is_array($child)) {
        $links = array_merge($links, $this->collectLinks($child));
      }
    }
    return $links;
  }

}
