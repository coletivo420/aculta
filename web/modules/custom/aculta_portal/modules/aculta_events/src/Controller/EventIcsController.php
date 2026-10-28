<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Calendar\EventIcsExporter;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exportação pública (/calendario/exportar.ics). Período em dd/mm/aaaa; padrão: hoje a 30 dias.
 *
 * Sem dados pessoais: é o calendário público dos eventos. O .ics pessoal (token por usuário)
 * não está aqui e exige decisão própria.
 */
final class EventIcsController extends ControllerBase {

  /**
   * Maior período aceito: um ano, como o teto de ocorrências (M3).
   */
  private const MAXIMO_DIAS = 366;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EventIcsExporter $exportador,
    private readonly TimeInterface $tempo,
    private readonly RequestStack $pilha,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_events.ics_exporter'), $container->get('datetime.time'), $container->get('request_stack'));
  }

  /**
   * Arquivo .ics do período pedido.
   */
  public function periodo(): Response {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    $consulta = $this->pilha->getCurrentRequest()->query;
    $de = $this->dataBr((string) $consulta->get('de'), $fuso) ?? $agora->setTime(0, 0);
    $ate = $this->dataBr((string) $consulta->get('ate'), $fuso) ?? $de->modify('+30 days');
    if ($ate < $de) {
      return new Response('Período inválido: a data final é anterior à inicial.', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    if ($de->diff($ate)->days > self::MAXIMO_DIAS) {
      return new Response('Período acima de ' . self::MAXIMO_DIAS . ' dias.', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    $host = $this->pilha->getCurrentRequest()->getHost();
    $tipo = (int) $this->pilha->getCurrentRequest()->query->get('tipo', 0) ?: NULL;
    $tipoLocal = (int) $this->pilha->getCurrentRequest()->query->get('tipo_local', 0) ?: NULL;
    $conteudo = $this->exportador->exportar($de->setTime(0, 0), $ate->setTime(23, 59, 59), $agora, $host, $tipo, $tipoLocal);
    return new Response($conteudo, 200, [
      'Content-Type' => 'text/calendar; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="eventos-aculta.ics"',
    ]);
  }

  /**
   * Arquivo .ics de uma data do evento, identificado por slug e id (/eventos/{slug}/data/{id}.ics).
   *
   * 404 quando o id não existe, é de outro evento, ou o evento não é público (rascunho).
   */
  public function data(string $slug, int $id): Response {
    $data = EventDate::load($id);
    $evento = $data?->get('field_evento')->entity;
    if (!$data instanceof EventDate
      || $evento === NULL
      || (string) $evento->get('field_slug')->value !== $slug
      || !in_array((string) $evento->get('field_status')->value, ['publicado', 'cancelado'], TRUE)) {
      throw new NotFoundHttpException();
    }

    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    $host = $this->pilha->getCurrentRequest()->getHost();
    $conteudo = $this->exportador->exportarData($data, $agora, $host);
    return new Response($conteudo, 200, [
      'Content-Type' => 'text/calendar; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="' . $slug . '.ics"',
    ]);
  }

  /**
   * Data em dd/mm/aaaa, de Brasília; vazio vira NULL; inválido também (usa o padrão).
   */
  private function dataBr(string $valor, \DateTimeZone $fuso): ?\DateTimeImmutable {
    if ($valor === '') {
      return NULL;
    }
    $data = \DateTimeImmutable::createFromFormat('!d/m/Y', $valor, $fuso);
    return ($data === FALSE || $data->format('d/m/Y') !== $valor) ? NULL : $data;
  }

}
