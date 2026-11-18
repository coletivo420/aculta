<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Controller\InteractiveController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Visão interativa: container e library DHTMLX anexados.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class InteractiveControllerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'aculta_calendar'];

  /**
   * A página tem o container e a library; sem rota de agenda, não inventa link.
   */
  public function testPaginaTemContainerElibrary(): void {
    $build = InteractiveController::create($this->container)->page();

    $this->assertArrayHasKey('#slots', $build);
    $slots = $build['#slots'];
    $this->assertIsArray($slots);
    $this->assertArrayHasKey('content', $slots);
    $content = $slots['content'];
    $this->assertIsArray($content);
    $this->assertArrayHasKey('componente', $content);
    $component = $content['componente'];
    $this->assertIsArray($component);
    $attributes = $component['#attributes'] ?? NULL;
    $this->assertIsArray($attributes);
    $this->assertSame('aculta-calendar-interactive', $attributes['id'] ?? NULL);
    $this->assertSame('calendar-interactive', $attributes['data-portal-ui'] ?? NULL);
    $attached = $content['#attached'] ?? NULL;
    $this->assertIsArray($attached);
    $this->assertSame(['aculta_calendar/dhtmlx'], $attached['library'] ?? NULL);
    $drupalSettings = $attached['drupalSettings'] ?? NULL;
    $this->assertIsArray($drupalSettings);
    $calendarSettings = $drupalSettings['acultaCalendar'] ?? NULL;
    $this->assertIsArray($calendarSettings);
    $this->assertArrayHasKey('items', $calendarSettings);
    $this->assertArrayHasKey('date', $calendarSettings);
    $this->assertArrayHasKey('agenda', $content, 'a agenda em HTML é a alternativa em telas menores');
    $alternative = $content['alternativa'] ?? NULL;
    $this->assertIsArray($alternative);
    $this->assertFalse($alternative['#access'] ?? NULL, 'sem rota de agenda informada, não há link');
  }

}
