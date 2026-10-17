<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;

/**
 * Política uniforme para caminhos internos de raiz de cada subdomínio.
 *
 * Regra: o caminho interno da raiz (PurposeFrontPathProcessor::FRONT_PATHS, ex.: /apoio, /wiki, /cursos) não é público.
 * A raiz do host serve esse conteúdo. Requisição direta ao caminho interno responde 404, sem redirecionamento 301,
 * em qualquer método. Os redirecionamentos 301 que continuam são só os de caminhos legados (ver docs/portal/DOMAIN-FRONT-PATHS.md).
 */
final class FrontPathPolicy {

  /**
   * TRUE quando a requisição deve responder 404 por ser o caminho interno da raiz do purpose atual.
   */
  public static function bloqueiaCaminhoInterno(?string $purpose, string $path, bool $servidoComoRaiz): bool {
    if ($purpose === NULL || $servidoComoRaiz) {
      return FALSE;
    }
    $caminho = PurposeFrontPathProcessor::FRONT_PATHS[$purpose] ?? NULL;
    return $caminho !== NULL && $path === $caminho;
  }

}
