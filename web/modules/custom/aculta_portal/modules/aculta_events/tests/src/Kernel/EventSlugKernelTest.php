<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Slug de evento: único entre eventos e estável depois de criado.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventSlugKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'options', 'text', 'filter', 'taxonomy', 'aculta_calendar', 'aculta_events'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('aculta_event');
  }

  /**
   * Títulos iguais ganham sufixo; editar o título não muda o slug já gravado.
   */
  public function testSlugUnicoEEstavel(): void {
    $a = Event::create(['title' => 'Oficina de Cerâmica']);
    $a->save();
    $b = Event::create(['title' => 'Oficina de Cerâmica']);
    $b->save();

    $this->assertSame('oficina-de-ceramica', $a->get('field_slug')->value);
    $this->assertSame('oficina-de-ceramica-2', $b->get('field_slug')->value);

    $a->set('title', 'Outro título completamente diferente');
    $a->save();
    $this->assertSame('oficina-de-ceramica', Event::load($a->id())->get('field_slug')->value);
  }

}
