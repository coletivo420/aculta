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

  /** Ação da guarda do hub do Studio na raiz do ACCOUNT: responder 404. */
  public const ACAO_NAO_ENCONTRADO = 'nao_encontrado';

  /** Ação da guarda do hub do Studio na raiz do ACCOUNT: levar o visitante ao login. */
  public const ACAO_LOGIN = 'login';

  /**
   * Decide a guarda do hub do Studio (`aculta_studio.hub`) no Domain ACCOUNT.
   *
   * O hub é servido na raiz `/` (reescrita interna para `/studio`). O caminho técnico responde 404, e o visitante
   * anônimo na raiz vai ao login do host de conta. Fora do purpose ACCOUNT ou de outra rota, não há ação (NULL).
   */
  public static function acaoDoHubNaConta(?string $purpose, string $path, string $route, bool $autenticado): ?string {
    if ($purpose !== 'account' || $route !== 'aculta_studio.hub') {
      return NULL;
    }
    if ($path !== '/') {
      return self::ACAO_NAO_ENCONTRADO;
    }
    return $autenticado ? NULL : self::ACAO_LOGIN;
  }

}
