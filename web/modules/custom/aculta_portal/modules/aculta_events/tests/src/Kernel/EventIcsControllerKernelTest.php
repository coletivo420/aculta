<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventIcsController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Adaptador da rota de .ics por data: 200 com o slug certo e 404 com outro.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventIcsControllerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'key', 'address', 'geofield', 'options', 'text', 'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar', 'aculta_events'];

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
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * Converte horário de Brasília para o valor guardado (UTC, sem sufixo).
   */
  private function utc(string $brasilia): string {
    return (new \DateTimeImmutable($brasilia, new \DateTimeZone('America/Sao_Paulo')))
      ->setTimezone(new \DateTimeZone('UTC'))
      ->format('Y-m-d\TH:i:s');
  }

  /**
   * A rota de uma data responde 200 com o slug certo e 404 com outro slug.
   */
  public function testDataExigeSlugDoEvento(): void {
    $evento = Event::create(['title' => 'Oficina', 'field_status' => 'publicado']);
    $evento->save();
    $data = EventDate::create([
      'title' => 'Oficina',
      'field_evento' => $evento->id(),
      'field_quando' => ['value' => $this->utc('2026-10-28 19:00:00'), 'end_value' => $this->utc('2026-10-28 21:00:00')],
    ]);
    $data->save();
    $slug = (string) $evento->get('field_slug')->value;

    $pilha = $this->container->get('request_stack');
    $controlador = EventIcsController::create($this->container);
    $pilha->push(Request::create('/eventos/' . $slug . '/data/' . $data->id() . '.ics'));
    $resposta = $controlador->data($slug, (int) $data->id());
    $this->assertSame(200, $resposta->getStatusCode());
    $this->assertStringContainsString('SUMMARY:Oficina', (string) $resposta->getContent());
    $pilha->pop();

    $this->expectException(NotFoundHttpException::class);
    $controlador->data('outro-evento', (int) $data->id());
  }

}
