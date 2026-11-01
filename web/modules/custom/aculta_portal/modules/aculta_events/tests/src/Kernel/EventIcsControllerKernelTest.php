<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventIcsController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\user\Entity\User;
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
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar',
    'aculta_events',
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

  /**
   * Agenda contextual exporta somente os itens associados ao Local autorizado.
   */
  public function testLocalAgendaIcsFiltersEventsByLocation(): void {
    $location = EventLocation::create(['title' => 'Local público', 'field_status' => 'publicado']);
    $location->save();
    $otherLocation = EventLocation::create(['title' => 'Outro Local', 'field_status' => 'publicado']);
    $otherLocation->save();
    $start = (new \DateTimeImmutable('@' . $this->container->get('datetime.time')->getCurrentTime()))
      ->setTimezone(new \DateTimeZone('America/Sao_Paulo'))
      ->setTime(18, 0)
      ->modify('+2 days');
    $event = Event::create([
      'title' => 'Evento no Local escolhido',
      'field_slug' => 'evento-local-escolhido',
      'field_status' => 'publicado',
    ]);
    $event->save();
    $date = EventDate::create([
      'title' => 'Data no local escolhido',
      'field_evento' => $event->id(),
      'field_local' => $location->id(),
      'field_quando' => [
        'value' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
        'end_value' => $start->modify('+2 hours')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
      ],
    ]);
    $date->save();

    $otherEvent = Event::create([
      'title' => 'Evento de outro Local',
      'field_slug' => 'evento-outro-local',
      'field_status' => 'publicado',
    ]);
    $otherEvent->save();
    EventDate::create([
      'title' => 'Data de outro Local',
      'field_evento' => $otherEvent->id(),
      'field_local' => $otherLocation->id(),
      'field_quando' => [
        'value' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
        'end_value' => $start->modify('+2 hours')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
      ],
    ])->save();

    $pilha = $this->container->get('request_stack');
    $pilha->push(Request::create('/locais/' . $location->id() . '/agenda.ics', server: ['HTTP_HOST' => 'eventos.example.test']));
    $resposta = EventIcsController::create($this->container)->local($location);
    $pilha->pop();

    $this->assertSame(200, $resposta->getStatusCode());
    $this->assertStringContainsString('SUMMARY:Evento no Local escolhido', (string) $resposta->getContent());
    $this->assertStringNotContainsString('SUMMARY:Evento de outro Local', (string) $resposta->getContent());
    $this->assertSame('max-age=0, no-store, private', $resposta->headers->get('Cache-Control'));
    $this->assertSame('noindex, nofollow, noarchive', $resposta->headers->get('X-Robots-Tag'));
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_events.location_ics');
    $this->assertSame('eventos', $route->getOption('_aculta_domain_purpose'));
    $this->assertSame('entity:aculta_event_location', $route->getOption('parameters')['aculta_event_location']['type']);
  }

  /**
   * Agenda de Local privado só pode ser exportada pelo usuário autorizado.
   */
  public function testAgendaDeLocalPrivadoOcultaConteudoDeOutraConta(): void {
    $owner = User::create(['name' => 'local-owner', 'status' => 1]);
    $owner->save();
    $viewer = User::create(['name' => 'other-viewer', 'status' => 1]);
    $viewer->save();
    $location = EventLocation::create([
      'title' => 'Local privado',
      'field_status' => 'privado',
      'field_organizador' => $owner->id(),
    ]);
    $location->save();
    $start = (new \DateTimeImmutable('@' . $this->container->get('datetime.time')->getCurrentTime()))
      ->setTimezone(new \DateTimeZone('America/Sao_Paulo'))
      ->setTime(18, 0)
      ->modify('+2 days');
    $event = Event::create([
      'title' => 'Evento privado do Local',
      'field_slug' => 'evento-privado-local',
      'field_status' => 'publicado',
    ]);
    $event->save();
    EventDate::create([
      'title' => 'Data privada do Local',
      'field_evento' => $event->id(),
      'field_local' => $location->id(),
      'field_quando' => [
        'value' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
        'end_value' => $start->modify('+2 hours')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s'),
      ],
    ])->save();
    $pilha = $this->container->get('request_stack');
    $pilha->push(Request::create('/locais/' . $location->id() . '/agenda.ics'));
    $this->container->get('current_user')->setAccount($owner);
    $ownerResponse = EventIcsController::create($this->container)->local($location);
    $pilha->pop();
    $this->assertStringContainsString('SUMMARY:Evento privado do Local', (string) $ownerResponse->getContent());

    $this->container->get('current_user')->setAccount($viewer);

    $this->expectException(NotFoundHttpException::class);
    EventIcsController::create($this->container)->local($location);
  }

}
