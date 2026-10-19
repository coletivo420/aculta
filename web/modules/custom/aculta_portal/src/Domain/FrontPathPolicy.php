<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;

/**
 * Política uniforme para caminhos internos de raiz de cada subdomínio.
 *
 * Regra: o caminho interno da raiz não é público. A raiz do host serve esse
 * conteúdo; acesso direto responde 404 sem redirecionamento. Consulte
 * docs/portal/DOMAIN-FRONT-PATHS.md para a política completa.
 */
final class FrontPathPolicy {

  /**
   * Caminho do nó que é a página inicial do subdomínio (não público).
   */
  private const NO_DA_RAIZ = [
    'shop' => '/node/372',
  ];

  /**
   * Caminhos internos legados que permanecem fechados após a troca da raiz.
   */
  private const CAMINHOS_LEGADOS = [
    'forum' => ['/forum'],
  ];

  /**
   * TRUE se a requisição deve responder 404 por ser caminho interno.
   */
  public static function bloqueiaCaminhoInterno(?string $purpose, string $path, bool $servidoComoRaiz): bool {
    if ($purpose === NULL || $servidoComoRaiz) {
      return FALSE;
    }
    $caminho = PurposeFrontPathProcessor::FRONT_PATHS[$purpose] ?? NULL;
    if ($caminho !== NULL && $path === $caminho) {
      return TRUE;
    }
    if (in_array($path, self::CAMINHOS_LEGADOS[$purpose] ?? [], TRUE)) {
      return TRUE;
    }
    // O endereço do Node da loja também é interno; a raiz o serve.
    $noDaRaiz = self::NO_DA_RAIZ[$purpose] ?? NULL;
    return $noDaRaiz !== NULL && $path === $noDaRaiz;
  }

}
