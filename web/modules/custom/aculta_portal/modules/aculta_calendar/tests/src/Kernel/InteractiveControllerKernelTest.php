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
  public function testPaginaTemContainerELibrary(): void {
    $build = InteractiveController::create($this->container)->page();

    $this->assertArrayHasKey('componente', $build);
    $this->assertStringContainsString('aculta-calendar-interactive', (string) $build['componente']['#markup']);
    $this->assertSame(['aculta_calendar/dhtmlx'], $build['#attached']['library']);
    $this->assertArrayHasKey('acultaCalendar', $build['#attached']['drupalSettings']);
    $this->assertArrayHasKey('items', $build['#attached']['drupalSettings']['acultaCalendar']);
    $this->assertArrayHasKey('date', $build['#attached']['drupalSettings']['acultaCalendar']);
    $this->assertSame('', (string) $build['alternativa']['#markup'], 'sem rota de agenda informada, não há link');
  }

}
