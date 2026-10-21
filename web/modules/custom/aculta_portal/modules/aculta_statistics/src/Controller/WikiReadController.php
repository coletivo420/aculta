<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Controller;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Registra uma leitura de verbete da Wiki enviada pelo beacon da página.
 *
 * Grava `wiki.entries.read` com o nid como dimensão. Não recebe nem grava IP, usuário,
 * referrer ou cookie. Verbete que não é publicado, não é da Wiki ou não pode ser visto
 * responde 404, sem revelar que existe.
 */
final class WikiReadController {

  /**
   * Tipo de conteúdo da Wiki.
   */
  public const BUNDLE = 'wiki_entry';

  public function __construct(
    private readonly StatisticsRecorder $recorder,
  ) {}

  /**
   * Conta uma leitura do verbete e responde sem conteúdo.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Quando o verbete não está disponível para leitura pública.
   */
  public function record(NodeInterface $node): Response {
    if ($node->bundle() !== self::BUNDLE || !$node->isPublished() || !$node->access('view')) {
      throw new NotFoundHttpException();
    }

    $this->recorder->record('wiki.entries.read', (string) $node->id());

    return new Response('', Response::HTTP_NO_CONTENT);
  }

}
