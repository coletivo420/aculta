<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies administrative related views are restricted by their role access.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventRelatedViewsAccessKernelTest extends KernelTestBase {

  /** {@inheritdoc} */
  protected static $modules = [
    'system', 'user', 'key', 'options', 'text', 'filter', 'taxonomy',
    'datetime', 'datetime_range', 'address', 'geofield', 'views',
    'aculta_calendar', 'aculta_events',
  ];

  public function testRelatedViewsRequireAdministratorRole(): void {
    $this->installConfig(['aculta_events']);
    $visitante = new UserSession(['uid' => 2, 'roles' => ['authenticated']]);

    foreach (['eventos_locais', 'eventos_datas_relacionadas'] as $id) {
      $view = View::load($id);
      $this->assertNotNull($view, "$id is installed.");
      $this->assertFalse($view->access('page', $visitante), "$id denies access to a regular user.");
    }
  }

  public function testAdministrativeViewsExposeOnlyMinimalFields(): void {
    $this->installConfig(['aculta_events']);
    $expected = [
      'eventos_locais' => ['title'],
      'eventos_datas_relacionadas' => ['title', 'field_quando'],
    ];

    foreach ($expected as $id => $allowedFields) {
      $view = View::load($id);
      $this->assertNotNull($view, "$id is installed.");
      $displays = $view->get('display');
      $fields = $displays['default']['display_options']['fields'] ?? [];
      $this->assertSame($allowedFields, array_keys($fields), "$id only exposes approved fields.");

      $serialized = serialize($view->toArray());
      foreach (['field_email', 'field_phone', 'field_address', 'field_address_data', 'field_endereco'] as $sensitiveField) {
        $this->assertStringNotContainsString($sensitiveField, $serialized, "$id does not expose $sensitiveField.");
      }
    }
  }

}
