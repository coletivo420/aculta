<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Value\QrCodePreset;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * Presets visuais disponíveis na versão 0.2.0.
 *
 * Os presets vêm do código nesta fase. A migração para configuração exportável
 * ocorre quando o painel administrativo entrar (0.4.0).
 */
final class QrCodePresetRegistry {

  /**
   * Identificador do preset padrão.
   */
  public const DEFAULT_ID = 'aculta_padrao';

  /**
   * Retorna o preset pelo identificador.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   *   Quando o preset não existe.
   */
  public function get(string $id): QrCodePreset {
    return $this->presets()[$id] ?? throw new InvalidQrCodeException('Preset de QR Code desconhecido.');
  }

  /**
   * Retorna o preset padrão.
   */
  public function default(): QrCodePreset {
    return $this->get(self::DEFAULT_ID);
  }

  /**
   * Lista os identificadores disponíveis.
   *
   * @return string[]
   *   Identificadores dos presets.
   */
  public function ids(): array {
    return array_keys($this->presets());
  }

  /**
   * Monta os presets da versão 0.2.0.
   *
   * @return array<string, \Drupal\aculta_qrcode\Value\QrCodePreset>
   *   Presets indexados pelo identificador.
   */
  private function presets(): array {
    return [
      self::DEFAULT_ID => new QrCodePreset(
        id: self::DEFAULT_ID,
        foreground: '#1b1b1b',
        background: '#ffffff',
        size: 320,
        margin: 12,
        errorCorrection: ErrorCorrectionLevel::Medium,
      ),
      'aculta_impressao' => new QrCodePreset(
        id: 'aculta_impressao',
        foreground: '#000000',
        background: '#ffffff',
        size: 1024,
        margin: 32,
        errorCorrection: ErrorCorrectionLevel::High,
      ),
    ];
  }

}
