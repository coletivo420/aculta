<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Integration;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Página padrão de retorno de integrações externas (Mercado Pago, Melhor Envio, Google e futuras).
 *
 * A integração abre a plataforma externa num popup. No retorno, esta página manda a janela de origem para o destino
 * e fecha o popup, deixando uma aba só. Sem popup (acesso direto), a própria aba vai para o destino. O resultado para o
 * usuário vem das mensagens do Drupal, gravadas antes desta resposta e exibidas pelo tema na página de destino.
 *
 * Só aceita destino no mesmo site; qualquer outro valor cai na página inicial. A resposta nunca entra em cache.
 */
final class PopupReturnPage {

  /**
   * Monta a página que fecha o popup e leva a janela de origem ao destino.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Requisição atual, usada para conferir se o destino é do mesmo site.
   * @param string $target
   *   Caminho relativo ou URL absoluta do mesmo site.
   */
  public static function response(Request $request, string $target): Response {
    if ($target === '' || !self::isAllowedTarget($target, $request)) {
      $target = '/';
    }
    $target_html = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    $target_js = self::jsString($target);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex">
<title>Concluindo…</title>
</head>
<body>
<p>Concluindo…</p>
<script>
(function() {
  var redirect = {$target_js};
  if (window.opener && !window.opener.closed) {
    window.opener.location.href = redirect;
    window.close();
  } else {
    window.location.href = redirect;
  }
})();
</script>
<noscript><a href="{$target_html}">Continuar</a></noscript>
</body>
</html>
HTML;

    return new Response($html, Response::HTTP_OK, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store',
    ]);
  }

  /**
   * Verdadeiro para caminho do próprio site ou URL absoluta de mesma origem.
   *
   * Navegadores tratam "//host" e "/\host" como URL de outro host, então o caminho relativo precisa de um segundo
   * caractere que não seja barra nem barra invertida. Esquemas como javascript: nunca passam.
   */
  public static function isAllowedTarget(string $url, Request $request): bool {
    // Navegadores descartam tabulações e quebras de linha dentro da URL ("/\t/host" vira "//host"): recusa controles
    // e espaços.
    if (preg_match('/[\x00-\x20\x7F]/', $url)) {
      return FALSE;
    }
    if ($url === '/' || preg_match('#^/[^/\\\\]#', $url)) {
      return TRUE;
    }
    if (preg_match('#^https?://#i', $url) !== 1) {
      return FALSE;
    }
    $parsed = parse_url($url);
    return strtolower((string) ($parsed['scheme'] ?? '')) === $request->getScheme()
      && ($parsed['host'] ?? '') === $request->getHost();
  }

  /**
   * Literal de string JavaScript seguro dentro de um bloco script.
   *
   * JSON_HEX_* também escapa '<', '>', '&', aspas e apóstrofos: um valor como "</script>" não fecha o elemento.
   */
  private static function jsString(string $value): string {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '""';
  }

}
