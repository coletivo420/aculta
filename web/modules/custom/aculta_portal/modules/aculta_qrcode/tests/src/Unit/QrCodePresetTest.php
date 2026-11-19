<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Unit;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodePresetRegistry;
use Drupal\aculta_qrcode\Value\QrCodePreset;
use Drupal\Tests\UnitTestCase;
use Endroid\QrCode\ErrorCorrectionLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa a validação de presets: contraste, dimensões, cores e identificador.
 */
#[Group('aculta_qrcode')]
final class QrCodePresetTest extends UnitTestCase {

  /**
   * Aceita contraste igual ou superior ao mínimo.
   */
  public function testAcceptsContrastAtOrAboveMinimum(): void {
    // #767676 sobre branco tem razão de 4,54:1.
    $preset = $this->preset(foreground: '#767676');
    self::assertSame('#767676', $preset->foreground);
  }

  /**
   * Rejeita contraste abaixo do mínimo de 4,5:1.
   */
  public function testRejectsContrastBelowMinimum(): void {
    // #777777 sobre branco tem razão de 4,48:1, abaixo do limite.
    $this->expectException(InvalidQrCodeException::class);
    $this->expectExceptionMessage('Contraste insuficiente');
    $this->preset(foreground: '#777777');
  }

  /**
   * Sobrescreve cores do preset somente quando informadas, com validação.
   */
  public function testCustomizedOverridesColorsWithContrastCheck(): void {
    $registry = new QrCodePresetRegistry();
    $base = $registry->get('aculta_padrao');

    self::assertEquals($base, $registry->customized('aculta_padrao', NULL, NULL));
    self::assertSame('#000000', $registry->customized('aculta_padrao', '#000000', NULL)->foreground);
    $this->expectException(InvalidQrCodeException::class);
    $registry->customized('aculta_padrao', '#777777', NULL);
  }

  /**
   * Calcula a razão WCAG de pares conhecidos.
   */
  public function testContrastRatioMatchesWcagReference(): void {
    self::assertEqualsWithDelta(21.0, QrCodePreset::contrastRatio('#000000', '#ffffff'), 0.01);
    self::assertEqualsWithDelta(1.0, QrCodePreset::contrastRatio('#ffffff', '#ffffff'), 0.01);
  }

  /**
   * Rejeita valores fora do formato e do intervalo permitido.
   *
   * @param array<string, mixed> $overrides
   */
  #[DataProvider('invalidPresetProvider')]
  public function testRejectsInvalidValues(array $overrides, string $message): void {
    $this->expectException(InvalidQrCodeException::class);
    $this->expectExceptionMessage($message);
    $foreground = isset($overrides['foreground']) && is_string($overrides['foreground']) ? $overrides['foreground'] : NULL;
    $background = isset($overrides['background']) && is_string($overrides['background']) ? $overrides['background'] : NULL;
    $size = isset($overrides['size']) && is_int($overrides['size']) ? $overrides['size'] : NULL;
    $margin = isset($overrides['margin']) && is_int($overrides['margin']) ? $overrides['margin'] : NULL;
    $id = isset($overrides['id']) && is_string($overrides['id']) ? $overrides['id'] : NULL;
    $this->preset($foreground, $background, $size, $margin, $id);
  }

  /**
   * Casos inválidos com a mensagem esperada.
   *
   * @return array<string, array<int, mixed>>
   *   Argumentos de sobrescrita e mensagem esperada, por caso.
   */
  public static function invalidPresetProvider(): array {
    return [
      'cor sem cerquilha' => [['foreground' => '000000'], 'formato #RRGGBB'],
      'cor curta' => [['background' => '#fff'], 'formato #RRGGBB'],
      'tamanho pequeno demais' => [['size' => 64], 'Tamanho fora do intervalo'],
      'tamanho grande demais' => [['size' => 4096], 'Tamanho fora do intervalo'],
      'margem negativa' => [['margin' => -1], 'Margem fora do intervalo'],
      'identificador com maiúscula' => [['id' => 'Aculta'], 'Identificador de preset inválido'],
    ];
  }

  /**
   * Cria um preset com valores válidos, sobrescritos pelos argumentos.
   */
  private function preset(
    ?string $foreground = NULL,
    ?string $background = NULL,
    ?int $size = NULL,
    ?int $margin = NULL,
    ?string $id = NULL,
  ): QrCodePreset {
    return new QrCodePreset(
      id: $id ?? 'aculta_teste',
      foreground: $foreground ?? '#1b1b1b',
      background: $background ?? '#ffffff',
      size: $size ?? 320,
      margin: $margin ?? 12,
      errorCorrection: ErrorCorrectionLevel::Medium,
    );
  }

}
