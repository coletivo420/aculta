<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Layout;

/**
 * Validação, sanitização e padrões de layout de container por purpose.
 *
 * Três modos: Padrão (caixa centralizada com largura máxima), Largura Total
 * (100% da tela) e Estreito (largura máxima reduzida para leitura).
 *
 * Baseado no plano docs/architecture/PLANO-LAYOUT-CONTAINER-POR-PURPOSE.md.
 */
final class PurposeContainerLayout {

  /**
   * Modos de layout suportados.
   */
  public const MODE_DEFAULT = 'default';
  public const MODE_FULL = 'full';
  public const MODE_NARROW = 'narrow';

  /**
   * Lista permitida de modos válidos.
   *
   * @var list<string>
   */
  public const ALLOWED_MODES = [
    self::MODE_DEFAULT,
    self::MODE_FULL,
    self::MODE_NARROW,
  ];

  /**
   * Modos gravados antes desta revisão, convertidos para os modos atuais.
   *
   * @var array<string, string>
   */
  public const LEGACY_MODES = [
    'boxed' => self::MODE_DEFAULT,
    'unboxed' => self::MODE_FULL,
    'contained' => self::MODE_DEFAULT,
    'max_full_width' => self::MODE_DEFAULT,
  ];

  /**
   * Purposes canônicos suportados no tema, incluindo Revista e Wiki.
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
   * Largura da caixa no modo Padrão (px). Faixa usual de mercado: 1140 a 1280.
   */
  public const CONTAINER_WIDTH_MIN = 960;
  public const CONTAINER_WIDTH_MAX = 1600;
  public const CONTAINER_WIDTH_DEFAULT = 1200;

  /**
   * Largura máxima do bloco de conteúdo no modo Estreito (px).
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
      self::MODE_DEFAULT => 'Padrão (caixa centralizada com largura máxima)',
      self::MODE_FULL => 'Largura total (100% da tela)',
      self::MODE_NARROW => 'Estreito (leitura focada, mais respiro nas laterais)',
    ];
  }

  /**
   * Retorna os valores padrão de layout para um purpose.
   *
   * @return array{mode: string, container_width: int, narrow_width: int}
   */
  public static function getDefaultSettings(string $purpose = 'main'): array {
    return [
      'mode' => self::MODE_DEFAULT,
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
    if (is_string($mode) && isset(self::LEGACY_MODES[$mode])) {
      $mode = self::LEGACY_MODES[$mode];
    }
    if (!is_string($mode) || !in_array($mode, self::ALLOWED_MODES, TRUE)) {
      $mode = $defaults['mode'];
    }

    $containerWidth = $input['container_width'] ?? $defaults['container_width'];
    if (!is_numeric($containerWidth)) {
      $containerWidth = $defaults['container_width'];
    }
    else {
      $containerWidth = (int) $containerWidth;
      if ($containerWidth < self::CONTAINER_WIDTH_MIN || $containerWidth > self::CONTAINER_WIDTH_MAX) {
        $containerWidth = $defaults['container_width'];
      }
    }

    $narrowWidth = $input['narrow_width'] ?? $defaults['narrow_width'];
    if (!is_numeric($narrowWidth)) {
      $narrowWidth = $defaults['narrow_width'];
    }
    else {
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

    return [
      'mode' => $setting['mode'],
      'variables' => [
        '--aculta-container-width' => $setting['container_width'] . 'px',
        '--aculta-narrow-container-width' => $setting['narrow_width'] . 'px',
      ],
    ];
  }

}
