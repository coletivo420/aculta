<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Unit;

use Drupal\aculta_qrcode\Controller\QrCodePreviewController;
use Drupal\aculta_qrcode\Service\QrCodeGenerator;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prévia administrativa: URL validada, SVG privado, nenhuma busca remota.
 */
#[CoversClass(QrCodePreviewController::class)]
#[Group('aculta_qrcode')]
class QrCodePreviewControllerTest extends UnitTestCase {

  /**
   * Monta o controlador com o validador, o registro de presets e o gerador reais (sem rede e sem banco).
   */
  private function controller(): QrCodePreviewController {
    return new QrCodePreviewController(new QrCodePayloadValidator(), new QrCodePresetRegistry(), new QrCodeGenerator());
  }

  /**
   * URL http/https válida gera SVG com cabeçalhos privados e sandbox.
   */
  public function testValidUrlReturnsPrivateSvg(): void {
    $response = $this->controller()->preview(Request::create('/x', 'GET', ['url' => 'https://aculta.org/x']));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('<svg', (string) $response->getContent());
    $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
  }

  /**
   * Esquemas perigosos e credenciais são recusados com 422, sem gerar nada.
   *
   * @return array<string, array{string}>
   *   URLs recusadas por nome.
   */
  public static function unsafeUrlProvider(): array {
    return [
      'javascript' => ['javascript:alert(1)'],
      'data' => ['data:text/html,<p>x</p>'],
      'file' => ['file:///etc/passwd'],
      'credenciais' => ['https://usuario:senha@aculta.org/'],
      'vazia' => [''],
    ];
  }

  /**
   * URLs inseguras são recusadas com 422 e não produzem SVG.
   */
  #[DataProvider('unsafeUrlProvider')]
  public function testUnsafeUrlIsRefused(string $url): void {
    $response = $this->controller()->preview(Request::create('/x', 'GET', ['url' => $url]));
    $this->assertSame(422, $response->getStatusCode());
    $this->assertStringNotContainsString('<svg', (string) $response->getContent());
  }

}
