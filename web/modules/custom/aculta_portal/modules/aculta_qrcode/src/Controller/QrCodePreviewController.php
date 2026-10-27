<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Controller;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodeGenerator;
use Drupal\aculta_qrcode\Service\QrCodePayloadValidator;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prévia administrativa de um QR Code para uma URL, sem gravar nada.
 *
 * Usa o validador de payload e o gerador central. A entrada é só a URL
 * informada; nenhum recurso remoto é buscado. A resposta é SVG privado e não
 * fica em cache.
 */
final class QrCodePreviewController {

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly QrCodePayloadValidator $payloadValidator,
    private readonly QrCodePresetRegistry $presetRegistry,
    private readonly QrCodeGenerator $generator,
  ) {}

  /**
   * Devolve a prévia SVG da URL informada no parâmetro `url`.
   */
  public function preview(Request $request): Response {
    $headers = [
      'Cache-Control' => 'private, no-store, max-age=0',
      'X-Content-Type-Options' => 'nosniff',
      'Content-Security-Policy' => "default-src 'none'; sandbox",
    ];
    try {
      $payload = $this->payloadValidator->validateUrl((string) $request->query->get('url', ''));
      $svg = $this->generator->generate($payload, $this->presetRegistry->default(), QrCodeFormat::Svg);
    }
    catch (InvalidQrCodeException $e) {
      return new Response(
        'URL inválida para a prévia: use http ou https, sem credenciais.',
        Response::HTTP_UNPROCESSABLE_ENTITY,
        $headers + ['Content-Type' => 'text/plain; charset=UTF-8'],
      );
    }
    catch (\RuntimeException) {
      return new Response('A prévia do QR Code está temporariamente indisponível.', Response::HTTP_SERVICE_UNAVAILABLE, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    return new Response($svg, Response::HTTP_OK, $headers + ['Content-Type' => QrCodeFormat::Svg->mimeType()]);
  }

}
