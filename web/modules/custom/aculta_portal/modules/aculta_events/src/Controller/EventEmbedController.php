<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agenda para incorporação por iframe (/calendario/incorporar), sem menu nem rodapé do site.
 *
 * Incorporável só por sites da própria ACULTA (EmbedFrameSubscriber). Leva noindex.
 * O restante do site continua sem permissão de incorporação.
 */
final class EventEmbedController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly RendererInterface $renderizador,
    private readonly EventAgendaController $agendaPagina,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('renderer'), EventAgendaController::create($container));
  }

  /**
   * Documento HTML com a agenda, pronto para ser incorporado.
   */
  public function page(): Response {
    $agenda = $this->agendaPagina->page();
    $corpo = (string) $this->renderizador->renderInIsolation($agenda);
    $titulo = (string) $this->t('Calendário de eventos da ACULTA');
    $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
      . '<meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<meta name="robots" content="noindex, nofollow, noarchive">'
      . '<title>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</title>'
      . '<style>body{font-family:sans-serif;margin:16px;color:#1d1d1d}h2{font-size:1.1rem;margin:16px 0 4px}ul{margin:0 0 8px;padding-left:20px}</style>'
      . '</head><body><main><h1>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>'
      . $corpo
      . '</main></body></html>';

    $resposta = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    // A política de incorporação (só sites da ACULTA) é aplicada por EmbedFrameSubscriber.
    $resposta->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    return $resposta;
  }

}
