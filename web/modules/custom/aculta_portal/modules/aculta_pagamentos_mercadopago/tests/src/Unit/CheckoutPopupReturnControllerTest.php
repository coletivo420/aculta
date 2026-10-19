<?php

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit;

use Drupal\aculta_pagamentos_mercadopago\Controller\CheckoutPopupReturnController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the redirect target written into the popup return page.
 */
#[Group('aculta_pagamentos_mercadopago')]
class CheckoutPopupReturnControllerTest extends UnitTestCase {

  /**
   * Returns the redirect target that the page script would navigate to.
   */
  private function redirectFor(string $value): string {
    $request = Request::create('/commerce-mercado-pago/checkout-return', 'GET', ['redirect' => $value]);
    $html = (new CheckoutPopupReturnController())->returnPage($request)->getContent();
    $this->assertMatchesRegularExpression('/var redirect = ("(?:[^"\\\\]|\\\\.)*");/', $html);
    preg_match('/var redirect = ("(?:[^"\\\\]|\\\\.)*");/', $html, $matches);
    return json_decode($matches[1]);
  }

  /**
   * Same-site paths are kept; anything else falls back to the home page.
   */
  #[DataProvider('redirectProvider')]
  public function testRedirectTarget(string $value, string $expected): void {
    $this->assertSame($expected, $this->redirectFor($value));
  }

  /**
   * Cases for the redirect target.
   */
  public static function redirectProvider(): array {
    return [
      'home' => ['/', '/'],
      'same-site path' => ['/loja/carrinho', '/loja/carrinho'],
      'protocol-relative host' => ['//evil.example', '/'],
      'backslash host' => ['/\\evil.example', '/'],
      'javascript scheme' => ['javascript:alert(1)', '/'],
      'external https' => ['https://evil.example/', '/'],
      'no leading slash' => ['loja', '/'],
    ];
  }

  /**
   * A value that closes the script element stays inside the JS string.
   */
  public function testScriptBreakoutIsEncoded(): void {
    $request = Request::create('/commerce-mercado-pago/checkout-return', 'GET', ['redirect' => '/</script><script>alert(1)</script>']);
    $html = (new CheckoutPopupReturnController())->returnPage($request)->getContent();
    $this->assertStringNotContainsString('</script><script>alert(1)', $html);
    $this->assertStringContainsString('</script', $html);
  }

}
