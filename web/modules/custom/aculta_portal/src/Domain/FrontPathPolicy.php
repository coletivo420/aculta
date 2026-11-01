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
   * Canonical Studio path prefixes and their owning domain purposes.
   */
  private const CANONICAL_STUDIO_PATHS = [
    'shop' => '/studio/green-stash',
    'wiki' => '/studio/we-sativa',
  ];

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
      // `/conta` is both the canonical Conta entry route and the internal
      // target used to serve the ACCOUNT root.
      if ($purpose === 'account' && $path === '/conta') {
        return FALSE;
      }
      return TRUE;
    }
    if (in_array($path, self::CAMINHOS_LEGADOS[$purpose] ?? [], TRUE)) {
      return TRUE;
    }
    // O endereço do Node da loja também é interno; a raiz o serve.
    $noDaRaiz = self::NO_DA_RAIZ[$purpose] ?? NULL;
    return $noDaRaiz !== NULL && $path === $noDaRaiz;
  }

  /**
   * Whether a Studio-prefixed path belongs to a real canonical route.
   *
   * Route matching and access requirements remain authoritative after this
   * narrow purpose/path guard. Unknown descendants still resolve to 404.
   */
  public static function caminhoStudioCanonicoPermitido(?string $purpose, string $path): bool {
    $prefix = self::CANONICAL_STUDIO_PATHS[$purpose ?? ''] ?? NULL;
    return $prefix !== NULL && ($path === $prefix || str_starts_with($path, $prefix . '/'));
  }

  /**
   * Ação da guarda da entrada da Conta: levar o visitante anônimo ao login.
   */
  public const ACAO_LOGIN = 'login';

  /**
   * Decide a guarda da entrada da Conta.
   *
   * A política cobre `aculta_studio.account` no Domain ACCOUNT.
   *
   * A raiz `/` é resolvida como entrada `/conta`; `/studio` não é uma rota.
   * Visitantes anônimos na raiz vão ao login do host de conta. Fora do purpose
   * ACCOUNT ou de outra rota, não há ação (NULL).
   */
  public static function acaoDaContaNaRaiz(?string $purpose, string $path, string $route, bool $autenticado): ?string {
    if ($purpose !== 'account' || $route !== 'aculta_studio.account') {
      return NULL;
    }
    if ($path !== '/') {
      return NULL;
    }
    return $autenticado ? NULL : self::ACAO_LOGIN;
  }

}
