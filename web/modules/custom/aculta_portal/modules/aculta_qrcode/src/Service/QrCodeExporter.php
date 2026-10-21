<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_image\GeneratedImageFileManager;
use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\aculta_qrcode\Value\QrCodePreset;
use Drupal\Core\File\FileSystemInterface;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;

/**
 * Exporta um código QR dinâmico em SVG, PNG, WebP ou PDF.
 *
 * O QR codifica a URL pública `/qr/{codigo}`, não o destino: trocar o destino
 * não exige nova exportação. Regras de arquivo:
 * - SVG é gerado em memória e nunca vira raster.
 * - PNG e WebP são derivados públicos registrados como temporários pelo
 *   `aculta_image`, com nome derivado do conteúdo. A mesma exportação reutiliza
 *   o arquivo; o `aculta_image` remove o que vencer.
 * - PDF é gerado pelo Entity Print e entregue sem gravação. O `aculta_image`
 *   aceita apenas imagens, então o PDF não passa por ele.
 *
 * Sem o `aculta_image`, a exportação de arquivo é recusada (falha fechada).
 */
final class QrCodeExporter {

  /**
   * Diretório público dos derivados temporários de QR.
   */
  public const DIRECTORY = 'public://aculta-qrcode';

  /**
   * Cria o exportador.
   */
  public function __construct(
    private readonly QrCodeGenerator $generator,
    private readonly QrCodePresetRegistry $presets,
    private readonly QrCodeUrlResolver $urlResolver,
    private readonly FileSystemInterface $fileSystem,
    private readonly ?GeneratedImageFileManager $generatedFiles,
    private readonly ?EntityPrintPluginManagerInterface $printEngines,
  ) {}

  /**
   * Gera o SVG em memória.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   * @throws \RuntimeException
   */
  public function svg(QrCode $entity): string {
    return $this->generator->generate($this->payload($entity), $this->preset($entity), QrCodeFormat::Svg);
  }

  /**
   * Retorna o conteúdo do PNG ou WebP, gravado como derivado temporário.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   * @throws \RuntimeException
   */
  public function raster(QrCode $entity, QrCodeFormat $format): string {
    if ($format === QrCodeFormat::Svg) {
      throw new InvalidQrCodeException('SVG não é exportado como arquivo raster.');
    }
    if ($this->generatedFiles === NULL) {
      throw new \RuntimeException('aculta_image indisponível: exportação de arquivo recusada.');
    }
    $payload = $this->payload($entity);
    $preset = $this->preset($entity);
    $key = hash('sha256', implode("\n", [
      $payload,
      $preset->id,
      $preset->foreground,
      $preset->background,
      (string) $preset->size,
      (string) $preset->margin,
      $preset->errorCorrection->value,
      $format->value,
    ]));
    $uri = self::DIRECTORY . '/' . $key . '.' . $format->value;

    $realpath = $this->fileSystem->realpath($uri);
    if ($realpath === FALSE || !is_file($realpath)) {
      $this->fileSystem->prepareDirectory(self::DIRECTORY, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $bytes = $this->generator->generate($payload, $preset, $format);
      if ($this->fileSystem->saveData($bytes, $uri, FileSystemInterface::EXISTS_REPLACE) === FALSE) {
        throw new \RuntimeException('Não foi possível gravar o derivado do QR Code.');
      }
      $realpath = $this->fileSystem->realpath($uri);
    }
    if ($this->generatedFiles->registerTemporaryDerivative($uri) === NULL) {
      throw new \RuntimeException('O aculta_image recusou o derivado do QR Code.');
    }
    $content = $realpath === FALSE ? FALSE : file_get_contents($realpath);
    if ($content === FALSE) {
      throw new \RuntimeException('Derivado do QR Code ilegível.');
    }
    return $content;
  }

  /**
   * Gera o PDF com o QR e a URL pública, sem gravação.
   *
   * O HTML não carrega recursos remotos nem PHP embutido, e o registro de log
   * do motor fica desligado.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   * @throws \RuntimeException
   */
  public function pdf(QrCode $entity): string {
    if ($this->printEngines === NULL) {
      throw new \RuntimeException('Entity Print indisponível: PDF recusado.');
    }
    $payload = $this->payload($entity);
    $png = $this->generator->generate($payload, $this->preset($entity), QrCodeFormat::Png);
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><style>'
      . '@page{size:A4;margin:20mm}body{font-family:DejaVu Sans,sans-serif;text-align:center;color:#1b1b1b}'
      . 'img{width:90mm;height:90mm}.url{font-size:11pt;word-break:break-all}</style><main>'
      . '<img alt="QR Code" src="data:image/png;base64,' . base64_encode($png) . '">'
      . '<p class="url">' . $escape($payload) . '</p></main></html>';

    $engine = $this->printEngines->createInstance('dompdf', [
      'enable_remote' => FALSE,
      'embedded_php' => FALSE,
      'disable_log' => TRUE,
    ]);
    $engine->addPage($html);
    return $engine->getBlob();
  }

  /**
   * Conteúdo codificado: a URL pública estável do código.
   *
   * @throws \RuntimeException
   */
  private function payload(QrCode $entity): string {
    $url = $this->urlResolver->publicUrl($entity);
    if ($url === NULL) {
      throw new \RuntimeException('URL pública do código QR indisponível neste ambiente.');
    }
    return $url->setAbsolute()->toString();
  }

  /**
   * Preset do código.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  private function preset(QrCode $entity): QrCodePreset {
    return $this->presets->get($entity->getPresetId());
  }

}
