<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_content_admin\Unit;

use Drupal\aculta_content_admin\Moderation\ModeratableCollectionRegistry;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\views\ViewsData;
use Drupal\Tests\UnitTestCase;

/**
 * Verifies the Events contribution queue is filtered by native route access.
 *
 * @group aculta_content_admin
 */
final class ModeratableCollectionRegistryTest extends UnitTestCase {

  /**
   * Confirms all Events proposal queues follow native Events route access.
   */
  public function testOrganizationProposalQueueUsesNativeRouteAccess(): void {
    $denied_account = $this->createMock(AccountInterface::class);
    $allowed_account = $this->createMock(AccountInterface::class);
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkNamedRoute')
      ->willReturnCallback(static function (string $route, array $parameters, AccountInterface $account) use ($denied_account): AccessResult {
        if (in_array($route, [
          'aculta_events.admin_organization_suggestions',
          'aculta_events.admin_location_contributions',
          'aculta_events.admin_event_contributions',
        ], TRUE)) {
          return AccessResult::allowedIf($account !== $denied_account);
        }
        return AccessResult::forbidden();
      });
    $registry = new ModeratableCollectionRegistry(
      $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class),
      $access_manager,
      $this->createMock(ViewsData::class),
    );
    $view_storage = $this->createMock(EntityStorageInterface::class);
    $view_storage->method('loadMultiple')->willReturn([]);
    $entity_type_manager->method('getStorage')->with('view')->willReturn($view_storage);

    $denied_routes = array_column($registry->forAccount($denied_account)['collections'], 'route');
    self::assertNotContains('aculta_events.admin_organization_suggestions', $denied_routes);
    self::assertNotContains('aculta_events.admin_location_contributions', $denied_routes);
    self::assertNotContains('aculta_events.admin_event_contributions', $denied_routes);

    $allowed_routes = array_column($registry->forAccount($allowed_account)['collections'], 'route');
    self::assertContains('aculta_events.admin_organization_suggestions', $allowed_routes);
    self::assertContains('aculta_events.admin_location_contributions', $allowed_routes);
    self::assertContains('aculta_events.admin_event_contributions', $allowed_routes);
  }

}
