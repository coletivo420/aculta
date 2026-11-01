<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agenda para incorporação por iframe (/calendario/incorporar), em CA3.
 *
 * Sem menu nem rodapé do site. A política de incorporação é aplicada pelo
 * módulo que publica a rota, a partir da allowlist de aculta_calendar.
 */
final class EmbedController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly RendererInterface $renderizador,
    private readonly AgendaController $agendaPagina,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('renderer'), AgendaController::create($container));
  }

  /**
   * Documento HTML com a agenda, pronto para ser incorporado.
   */
  public function page(): Response {
    $agenda = $this->agendaPagina->conteudo(TRUE);
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

    $resposta = new Response($html, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store, max-age=0',
    ]);
    // O publisher aplica a política de incorporação (sites ACULTA).
    $resposta->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    return $resposta;
  }

}
