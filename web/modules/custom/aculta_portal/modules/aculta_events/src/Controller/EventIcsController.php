<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_calendar\Ics\CalendarIcsExporter;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Calendar\EventCalendarItemProvider;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adaptador de publisher para o .ics de uma data de evento.
 *
 * A exportação é do aculta_calendar (CA2); aqui ficam a rota com o slug do
 * evento e a validação de que a data pertence a ele.
 */
final class EventIcsController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly CalendarIcsExporter $exportador,
    private readonly EventCalendarItemProvider $provedor,
    private readonly TimeInterface $tempo,
    private readonly RequestStack $pilha,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_calendar.ics_exporter'),
      $container->get('aculta_events.item_provider'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
    );
  }

  /**
   * Arquivo .ics de uma data do evento (/eventos/{slug}/data/{id}.ics).
   *
   * 404 quando o id não existe, é de outro evento, ou o evento não é público.
   */
  public function data(string $slug, int $id): Response {
    $data = EventDate::load($id);
    $evento = $data?->get('field_evento')->entity;
    if (!$data instanceof EventDate
      || !$evento instanceof Event
      || (string) $evento->get('field_slug')->value !== $slug
      || !in_array((string) $evento->get('field_status')->value, ['publicado', 'cancelado'], TRUE)) {
      throw new NotFoundHttpException();
    }
    $item = $this->provedor->itemDaData($data, $evento);
    if ($item === NULL) {
      throw new NotFoundHttpException();
    }

    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    $host = $this->pilha->getCurrentRequest()->getHost();
    $conteudo = $this->exportador->exportarItem($item, $agora, $host);
    return new Response($conteudo, 200, [
      'Content-Type' => 'text/calendar; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="' . $slug . '.ics"',
    ]);
  }

  /**
   * Agenda ICS contextual de um Local, usando o provider compartilhado.
   *
   * A consulta mantém o horizonte de 30 dias da página do Local e limita a
   * fonte a Eventos. O acesso do Local é revalidado para cada requisição.
   */
  public function local(EventLocation $aculta_event_location): Response {
    if (!$aculta_event_location->access('view', $this->currentUser())) {
      throw new NotFoundHttpException();
    }

    $timezone = new \DateTimeZone('America/Sao_Paulo');
    $now = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($timezone);
    $from = $now->setTime(0, 0);
    $to = $from->modify('+30 days')->setTime(23, 59, 59);
    $query = new CalendarQuery(
      $from,
      $to,
      $now,
      ['local' => (int) $aculta_event_location->id()],
      ['aculta_events'],
    );

    $host = $this->pilha->getCurrentRequest()->getHost();
    $conteudo = $this->exportador->exportar($query, $host);
    return new Response($conteudo, 200, [
      'Content-Type' => 'text/calendar; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="local-' . (int) $aculta_event_location->id() . '-agenda.ics"',
      'Cache-Control' => 'private, no-store, max-age=0',
      'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ]);
  }

}
