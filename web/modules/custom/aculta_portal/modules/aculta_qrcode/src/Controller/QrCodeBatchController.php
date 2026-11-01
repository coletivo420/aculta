<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Controller;

use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodeBatchPlanner;
use Drupal\aculta_qrcode\Service\QrCodeExporter;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Exporta vários códigos QR em um ZIP, um arquivo por código, no formato pedido.
 *
 * A validação de ids e de formato fica em QrCodeBatchPlanner. Se qualquer código não existir, o lote inteiro
 * responde 404: nada é entregue pela metade. A resposta é privada e não fica em cache.
 */
final class QrCodeBatchController {

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QrCodeExporter $exporter,
    private readonly QrCodeBatchPlanner $planner,
  ) {}

  /**
   * Devolve o ZIP com os códigos pedidos em `ids` (lista separada por vírgula) e `formato`.
   */
  public function batch(Request $request): Response {
    try {
      $ids = $this->planner->parseIds((string) $request->query->get('ids', ''));
      $format = $this->planner->assertFormat((string) $request->query->get('formato', ''));
    }
    catch (InvalidQrCodeException $e) {
      throw new BadRequestHttpException($e->getMessage(), $e);
    }

    $storage = $this->entityTypeManager->getStorage('aculta_qr_code');
    $entities = $storage->loadMultiple($ids);
    if (count($entities) !== count($ids)) {
      throw new NotFoundHttpException('Um ou mais códigos do lote não existem.');
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'aculta-qr-lote-');
    if ($zipPath === FALSE) {
      throw new ServiceUnavailableHttpException(NULL, 'A exportação do lote está temporariamente indisponível.');
    }
    try {
      $zip = new \ZipArchive();
      if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== TRUE) {
        throw new ServiceUnavailableHttpException(NULL, 'A exportação do lote está temporariamente indisponível.');
      }
      foreach ($ids as $id) {
        /** @var \Drupal\aculta_qrcode\Entity\QrCode $entity */
        $entity = $entities[$id];
        $zip->addFromString($this->planner->entryName($entity->getCode(), $format), $this->render($entity, $format));
      }
      $zip->close();
      $bytes = (string) file_get_contents($zipPath);
    }
    catch (InvalidQrCodeException | \RuntimeException $e) {
      throw new ServiceUnavailableHttpException(NULL, 'A exportação do lote está temporariamente indisponível.', $e);
    }
    finally {
      @unlink($zipPath);
    }

    return new Response($bytes, Response::HTTP_OK, [
      'Content-Type' => 'application/zip',
      'Content-Disposition' => 'attachment; filename="qr-lote.zip"',
      'Cache-Control' => 'private, no-store, max-age=0',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  /**
   * Gera o conteúdo de um código no formato pedido, pelo exporter central.
   */
  private function render(QrCode $entity, string $format): string {
    return match ($format) {
      'svg' => $this->exporter->svg($entity),
      'pdf' => $this->exporter->pdf($entity),
      default => $this->exporter->raster($entity, QrCodeFormat::from($format)),
    };
  }

}
