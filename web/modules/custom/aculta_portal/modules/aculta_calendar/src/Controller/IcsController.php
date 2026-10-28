<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Controller;

use Drupal\aculta_calendar\Ics\CalendarIcsExporter;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exportação pública do período filtrado (/calendario/exportar.ics), em CA2.
 *
 * Período em dd/mm/aaaa; padrão: hoje a 30 dias. Sem dados pessoais.
 */
final class IcsController extends ControllerBase {

  /**
   * Maior período aceito: um ano.
   */
  private const MAXIMO_DIAS = 366;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly CalendarIcsExporter $exportador,
    private readonly CalendarItemSource $fonte,
    private readonly TimeInterface $tempo,
    private readonly RequestStack $pilha,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_calendar.ics_exporter'),
      $container->get('aculta_calendar.item_source'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
    );
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

    $filtros = [];
    foreach (array_keys($this->fonte->filtros()) as $chave) {
      $valor = $consulta->getInt($chave);
      if ($valor > 0) {
        $filtros[$chave] = $valor;
      }
    }

    $host = $this->pilha->getCurrentRequest()->getHost();
    $conteudo = $this->exportador->exportar(new CalendarQuery($de->setTime(0, 0), $ate->setTime(23, 59, 59), $agora, $filtros), $host);
    return new Response($conteudo, 200, [
      'Content-Type' => 'text/calendar; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="eventos-aculta.ics"',
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
