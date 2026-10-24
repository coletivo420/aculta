<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Hides routes for notice adapters whose content modules are disabled.
 */
final class OptionalNoticeRouteSubscriber extends RouteSubscriberBase {

  /**
   * Maps route names to their source modules.
   *
   * @var array<string, string>
   */
  private const ROUTE_MODULES = [
    'aculta_messenger.lms_notice_open' => 'aculta_lms',
    'aculta_messenger.wiki_notice_open' => 'aculta_wiki',
    'aculta_messenger.commerce_notice_open' => 'aculta_commerce',
    'aculta_messenger.donation_notice_open' => 'aculta_donation',
  ];

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (self::ROUTE_MODULES as $routeName => $module) {
      if (!$this->moduleHandler->moduleExists($module)) {
        $collection->remove($routeName);
      }
    }
  }

}
