<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Integration;

use Drupal\aculta_portal\Integration\PopupReturnPage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Página padrão de retorno de integrações: destino seguro, script do popup e ausência de cache.
 */
#[Group('aculta_portal')]
final class PopupReturnPageTest extends UnitTestCase {

  /**
   * Destino que o script da página vai abrir.
   */
  private function targetFor(string $target): string {
    $request = Request::create('https://aculta.example.test/integracoes/teste/retorno');
    $html = (string) PopupReturnPage::response($request, $target)->getContent();
    self::assertSame(1, preg_match('/var redirect = ("(?:[^"\\\\]|\\\\.)*");/', $html, $matches));
    return json_decode($matches[1]);
  }

  /**
   * Caminho do site e URL de mesma origem passam; o resto cai na página inicial.
   */
  #[DataProvider('targetProvider')]
  public function testTargetIsSameSiteOnly(string $target, string $expected): void {
    self::assertSame($expected, $this->targetFor($target));
  }

  /**
   * Casos de destino.
   *
   * @return array<string, array{string, string}>
   *   Destino pedido e destino esperado.
   */
  public static function targetProvider(): array {
    return [
      'caminho do site' => ['/admin/commerce/config/melhorenvio', '/admin/commerce/config/melhorenvio'],
      'mesma origem' => ['https://aculta.example.test/loja', 'https://aculta.example.test/loja'],
      'vazio' => ['', '/'],
      'outro host' => ['https://evil.example/x', '/'],
      'protocolo relativo' => ['//evil.example/x', '/'],
      'barra invertida' => ['/\\evil.example', '/'],
      'javascript' => ['javascript:alert(1)', '/'],
      'tabulação' => ["/\t/evil.example", '/'],
    ];
  }

  /**
   * Com popup, a janela de origem vai ao destino e o popup fecha; sem popup, a própria aba vai ao destino.
   */
  public function testScriptHandlesPopupAndDirectAccess(): void {
    $html = (string) PopupReturnPage::response(Request::create('/r'), '/destino')->getContent();
    self::assertStringContainsString('window.opener.location.href = redirect', $html);
    self::assertStringContainsString('window.close()', $html);
    self::assertStringContainsString('window.location.href = redirect', $html);
  }

  /**
   * A página não entra em cache.
   */
  public function testResponseIsNeverCached(): void {
    $response = PopupReturnPage::response(Request::create('/r'), '/');
    self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
  }

  /**
   * Um destino que fecha o elemento script continua dentro da string JavaScript.
   */
  public function testScriptBreakoutIsEncoded(): void {
    $html = (string) PopupReturnPage::response(Request::create('/r'), '/</script><script>alert(1)</script>')->getContent();
    self::assertStringNotContainsString('</script><script>alert(1)', $html);
  }

}
