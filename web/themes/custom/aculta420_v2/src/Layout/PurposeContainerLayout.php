<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Layout;

/**
 * Validação, sanitização e padrões de layout de container por purpose.
 *
 * Baseado no plano docs/architecture/PLANO-LAYOUT-CONTAINER-POR-PURPOSE.md.
 */
final class PurposeContainerLayout {

  /**
   * Modos de layout suportados.
   */
  public const MODE_BOXED = 'boxed';
  public const MODE_UNBOXED = 'unboxed';
  public const MODE_CONTAINED = 'contained';
  public const MODE_MAX_FULL_WIDTH = 'max_full_width';

  /**
   * Lista permitida de modos válidos.
   *
   * @var list<string>
   */
  public const ALLOWED_MODES = [
    self::MODE_BOXED,
    self::MODE_UNBOXED,
    self::MODE_CONTAINED,
    self::MODE_MAX_FULL_WIDTH,
  ];

  /**
   * Purposes canônicos suportados no tema.
   *
   * @var list<string>
   */
  public const ALLOWED_PURPOSES = [
    'main',
    'account',
    'support',
    'magazine',
    'wiki',
    'shop',
    'courses',
    'links',
    'forum',
    'eventos',
  ];

  /**
   * Limites de largura do container (px).
   */
  public const CONTAINER_WIDTH_MIN = 960;
  public const CONTAINER_WIDTH_MAX = 1600;
  public const CONTAINER_WIDTH_DEFAULT = 1200;

  /**
   * Padrões específicos por modo quando aplicável.
   */
  public const CONTAINER_WIDTH_CONTAINED = 1000;
  public const CONTAINER_WIDTH_MAX_FULL = 1400;

  /**
   * Limites de largura estreita (narrow) (px).
   */
  public const NARROW_WIDTH_MIN = 600;
  public const NARROW_WIDTH_MAX = 900;
  public const NARROW_WIDTH_DEFAULT = 750;

  /**
   * Retorna os rótulos legíveis de cada modo.
   *
   * @return array<string, string>
   */
  public static function getModeOptions(): array {
    return [
      self::MODE_BOXED => 'Boxed (padrão)',
      self::MODE_UNBOXED => 'Unboxed (largura total da tela)',
      self::MODE_CONTAINED => 'Contained (contido ao centro)',
      self::MODE_MAX_FULL_WIDTH => 'Max full width (máxima largura)',
    ];
  }

  /**
   * Retorna os valores padrão de layout para um purpose.
   *
   * @return array{mode: string, container_width: int, narrow_width: int}
   */
  public static function getDefaultSettings(string $purpose = 'main'): array {
    return [
      'mode' => self::MODE_BOXED,
      'container_width' => self::CONTAINER_WIDTH_DEFAULT,
      'narrow_width' => self::NARROW_WIDTH_DEFAULT,
    ];
  }

  /**
   * Sanitiza a configuração de um purpose específico.
   *
   * @param mixed $input
   *   Valores brutos de configuração.
   * @param string $purpose
   *   Purpose sendo sanitizado.
   *
   * @return array{mode: string, container_width: int, narrow_width: int}
   */
  public static function sanitizeSetting(mixed $input, string $purpose = 'main'): array {
    $defaults = self::getDefaultSettings($purpose);

    if (!is_array($input)) {
      return $defaults;
    }

    $mode = $input['mode'] ?? $defaults['mode'];
    if (!is_string($mode) || !in_array($mode, self::ALLOWED_MODES, TRUE)) {
      $mode = $defaults['mode'];
    }

    $containerWidth = $input['container_width'] ?? $defaults['container_width'];
    if (!is_numeric($containerWidth)) {
      $containerWidth = $defaults['container_width'];
    } else {
      $containerWidth = (int) $containerWidth;
      if ($containerWidth < self::CONTAINER_WIDTH_MIN || $containerWidth > self::CONTAINER_WIDTH_MAX) {
        $containerWidth = $defaults['container_width'];
      }
    }

    $narrowWidth = $input['narrow_width'] ?? $defaults['narrow_width'];
    if (!is_numeric($narrowWidth)) {
      $narrowWidth = $defaults['narrow_width'];
    } else {
      $narrowWidth = (int) $narrowWidth;
      if ($narrowWidth < self::NARROW_WIDTH_MIN || $narrowWidth > self::NARROW_WIDTH_MAX) {
        $narrowWidth = $defaults['narrow_width'];
      }
    }

    return [
      'mode' => $mode,
      'container_width' => $containerWidth,
      'narrow_width' => $narrowWidth,
    ];
  }

  /**
   * Sanitiza toda a árvore aculta_purpose_container.
   *
   * @param mixed $input
   *   Configuração bruta completa.
   *
   * @return array<string, array{mode: string, container_width: int, narrow_width: int}>
   */
  public static function sanitizeAll(mixed $input): array {
    $output = [];
    $inputArray = is_array($input) ? $input : [];

    foreach (self::ALLOWED_PURPOSES as $purpose) {
      $output[$purpose] = self::sanitizeSetting($inputArray[$purpose] ?? NULL, $purpose);
    }

    return $output;
  }

  /**
   * Resolve a configuração de container para HTML attributes e variáveis CSS.
   *
   * @param mixed $allSettings
   *   Configurações salvas (array ou theme_get_setting('aculta_purpose_container')).
   * @param mixed $purpose
   *   Purpose atual do request.
   *
   * @return array{mode: string, variables: array<string, string>}|null
   */
  public static function resolveContainerStyle(mixed $allSettings, mixed $purpose): ?array {
    if (!is_string($purpose) || !in_array($purpose, self::ALLOWED_PURPOSES, TRUE)) {
      return NULL;
    }

    $setting = is_array($allSettings) && isset($allSettings[$purpose])
      ? self::sanitizeSetting($allSettings[$purpose], $purpose)
      : self::getDefaultSettings($purpose);

    $containerPx = $setting['container_width'];
    $narrowPx = $setting['narrow_width'];

    // Se for modo contido ou max full width e o valor não foi customizado, usa as sugestões aprovadas
    if ($setting['mode'] === self::MODE_CONTAINED && $containerPx === self::CONTAINER_WIDTH_DEFAULT) {
      $containerPx = self::CONTAINER_WIDTH_CONTAINED;
    } elseif ($setting['mode'] === self::MODE_MAX_FULL_WIDTH && $containerPx === self::CONTAINER_WIDTH_DEFAULT) {
      $containerPx = self::CONTAINER_WIDTH_MAX_FULL;
    }

    $variables = [
      '--aculta-container-width' => $containerPx . 'px',
      '--aculta-narrow-container-width' => $narrowPx . 'px',
    ];

    return [
      'mode' => $setting['mode'],
      'variables' => $variables,
    ];
  }

}
