<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

/**
 * Política uniforme para caminhos internos de raiz de cada subdomínio.
 *
 * Regra: o caminho interno da raiz não é público. A raiz do host serve esse
 * conteúdo; acesso direto responde 404 sem redirecionamento. Consulte
 * docs/portal/DOMAIN-FRONT-PATHS.md para a política completa.
 */
final class FrontPathPolicy {

  /**
   * TRUE se a requisição deve responder 404 por ser caminho interno.
   */
  public static function bloqueiaCaminhoInterno(?string $purpose, string $path, bool $servidoComoRaiz): bool {
    if ($purpose === NULL || $servidoComoRaiz) {
      return FALSE;
    }
    $caminho = DomainSurfaceMap::FRONT_PATHS[$purpose] ?? NULL;
    if ($caminho !== NULL && $path === $caminho) {
      return TRUE;
    }
    if (in_array($path, DomainSurfaceMap::CAMINHOS_LEGADOS[$purpose] ?? [], TRUE)) {
      return TRUE;
    }
    // O endereço do Node da loja também é interno; a raiz o serve.
    $noDaRaiz = DomainSurfaceMap::NO_DA_RAIZ[$purpose] ?? NULL;
    return $noDaRaiz !== NULL && $path === $noDaRaiz;
  }

  /**
   * Ação da guarda da entrada da Conta: levar o visitante anônimo ao login.
   */
  public const ACAO_LOGIN = 'login';

  /**
   * Decide a guarda da entrada da Conta.
   *
   * A política cobre `aculta_studio.dashboard` no Domain ACCOUNT.
   *
   * A raiz `/` é resolvida como entrada `/conta`; `/studio` não é uma rota.
   * Visitantes anônimos na raiz vão ao login do host de conta. Fora do purpose
   * ACCOUNT ou de outra rota, não há ação (NULL).
   */
  public static function acaoDaContaNaRaiz(?string $purpose, string $path, string $route, bool $autenticado): ?string {
    if ($purpose !== 'account' || $route !== 'aculta_studio.dashboard') {
      return NULL;
    }
    if ($path !== '/') {
      return NULL;
    }
    return $autenticado ? NULL : self::ACAO_LOGIN;
  }

}
