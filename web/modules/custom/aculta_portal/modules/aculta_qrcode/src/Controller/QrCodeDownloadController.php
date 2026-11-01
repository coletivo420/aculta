<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Controller;

use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodeExporter;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Entrega o download de um código QR dinâmico por formato.
 *
 * A resposta é privada e não fica em cache. Erros de exportação viram 503
 * com mensagem genérica; o detalhe fica fora da resposta.
 */
final class QrCodeDownloadController {

  /**
   * Cria o controlador.
   */
  public function __construct(private readonly QrCodeExporter $exporter) {}

  /**
   * Responde com o arquivo do formato pedido.
   */
  public function download(QrCode $aculta_qr_code, string $formato): Response {
    $name = 'qr-' . $aculta_qr_code->getCode();
    $headers = [
      'Cache-Control' => 'private, no-store, max-age=0',
      'X-Content-Type-Options' => 'nosniff',
      'Content-Disposition' => 'attachment; filename="' . $name . '.' . $formato . '"',
    ];
    try {
      return match ($formato) {
        'svg' => new Response($this->exporter->svg($aculta_qr_code), Response::HTTP_OK, $headers + [
          'Content-Type' => QrCodeFormat::Svg->mimeType(),
          'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]),
        'png', 'webp' => new Response(
          $this->exporter->raster($aculta_qr_code, QrCodeFormat::from($formato)),
          Response::HTTP_OK,
          $headers + ['Content-Type' => QrCodeFormat::from($formato)->mimeType()],
        ),
        'pdf' => new Response($this->exporter->pdf($aculta_qr_code), Response::HTTP_OK, $headers + [
          'Content-Type' => 'application/pdf',
        ]),
        default => throw new NotFoundHttpException(),
      };
    }
    catch (InvalidQrCodeException | \RuntimeException) {
      throw new ServiceUnavailableHttpException(NULL, 'A exportação do QR Code está temporariamente indisponível.');
    }
  }

}
