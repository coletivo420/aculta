<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Controller;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Visitor\VisitorKey;
use Drupal\Core\Flood\FloodInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Registra uma leitura de verbete da Wiki enviada pelo beacon da página.
 *
 * Controles antiabuso, com identificador pseudonimo (ver `VisitorKey`):
 *   - uma leitura por visitante e verbete a cada 30 minutos; repetições respondem 204 sem contar;
 *   - no máximo 60 leituras contadas por visitante a cada hora; além disso, 429.
 *
 * Verbete que não é publicado, não é da Wiki ou não pode ser visto responde 404.
 */
final class WikiReadController {

  /**
   * Tipo de conteúdo da Wiki.
   */
  public const BUNDLE = 'wiki_entry';

  /**
   * Evento do limite por verbete.
   */
  public const FLOOD_NODE = 'aculta_statistics.wiki_read.node';

  /**
   * Janela do limite por verbete, em segundos.
   */
  public const NODE_WINDOW = 1800;

  /**
   * Evento do limite por visitante.
   */
  public const FLOOD_CLIENT = 'aculta_statistics.wiki_read.client';

  /**
   * Janela do limite por visitante, em segundos.
   */
  public const CLIENT_WINDOW = 3600;

  /**
   * Leituras contadas por visitante na janela.
   */
  public const CLIENT_LIMIT = 60;

  public function __construct(
    private readonly StatisticsRecorder $recorder,
    private readonly FloodInterface $flood,
    private readonly RequestStack $requestStack,
    private readonly VisitorKey $visitorKey,
  ) {}

  /**
   * Conta uma leitura do verbete, com os limites antiabuso.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Quando o verbete não está disponível para leitura pública.
   */
  public function record(NodeInterface $node): Response {
    if ($node->bundle() !== self::BUNDLE || !$node->isPublished() || !$node->access('view')) {
      throw new NotFoundHttpException();
    }

    $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? '';
    $clientKey = $this->visitorKey->identifier($ip, 'client');
    $nodeKey = $this->visitorKey->identifier($ip, 'node:' . $node->id());

    if (!$this->flood->isAllowed(self::FLOOD_CLIENT, self::CLIENT_LIMIT, self::CLIENT_WINDOW, $clientKey)) {
      return new Response('', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) self::CLIENT_WINDOW]);
    }
    if (!$this->flood->isAllowed(self::FLOOD_NODE, 1, self::NODE_WINDOW, $nodeKey)) {
      // Já contado nesta janela: resposta de sucesso sem nova contagem.
      return new Response('', Response::HTTP_NO_CONTENT);
    }

    $this->flood->register(self::FLOOD_NODE, self::NODE_WINDOW, $nodeKey);
    $this->flood->register(self::FLOOD_CLIENT, self::CLIENT_WINDOW, $clientKey);
    $this->recorder->record('wiki.entries.read', (string) $node->id());

    return new Response('', Response::HTTP_NO_CONTENT);
  }

}
