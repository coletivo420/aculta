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
   * UI Patterns library routes use their own full-width documentation layout. */
  private const COMPONENT_LIBRARY_ROUTES = [
    'ui_patterns_library.overview',
    'ui_patterns_library.provider',
    'ui_patterns_library.single',
  ];

  /**
   * Modos de layout suportados.
   */
  public const MODE_DEFAULT = 'default';
  public const MODE_FULL = 'full';
  public const MODE_NARROW = 'narrow';

  /**
   * Estilos do container (Astra: Default, Boxed, Unboxed). Valem só para Padrão e Estreito.
   */
  public const STYLE_BOXED = 'boxed';
  public const STYLE_UNBOXED = 'unboxed';

  /**
   * Lista permitida de estilos.
   *
   * @var list<string>
   */
  public const ALLOWED_STYLES = [
    self::STYLE_BOXED,
    self::STYLE_UNBOXED,
  ];

  /**
   * Modos que aceitam estilo. Todos os modos aceitam Caixa ou Sem caixa.
   *
   * @var list<string>
   */
  public const MODES_WITH_STYLE = [
    self::MODE_DEFAULT,
    self::MODE_FULL,
    self::MODE_NARROW,
  ];

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
   * Whether a route belongs to the UI Patterns component documentation.
   */
  public static function isComponentLibraryRoute(?string $routeName): bool {
    return $routeName !== NULL && in_array($routeName, self::COMPONENT_LIBRARY_ROUTES, TRUE);
  }

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
   * Purposes cujo layout é definido só pelo código (não aparece em Layout de container).
   *
   * @return array{mode: string, style: string, container_width: int, narrow_width: int}|null
   *   Configuração fixa do purpose, ou NULL quando o purpose é configurável.
   */
  public static function codeSettings(string $purpose): ?array {
    return match ($purpose) {
      'forum' => [
        'mode' => self::MODE_DEFAULT,
        'style' => self::STYLE_UNBOXED,
        'container_width' => self::CONTAINER_WIDTH_DEFAULT,
        'narrow_width' => self::NARROW_WIDTH_DEFAULT,
      ],
      default => NULL,
    };
  }

  /**
   * Indica se o layout do purpose é controlado só pelo código.
   */
  public static function isCodeControlled(string $purpose): bool {
    return self::codeSettings($purpose) !== NULL;
  }

  /**
   * Retorna os valores padrão de layout para um purpose.
   *
   * @return array{mode: string, style: string, container_width: int, narrow_width: int}
   */
  public static function getDefaultSettings(string $purpose = 'main'): array {
    $fixed = self::codeSettings($purpose);
    if ($fixed !== NULL) {
      return $fixed;
    }
    return [
      'mode' => self::MODE_DEFAULT,
      'style' => self::STYLE_BOXED,
      'container_width' => self::CONTAINER_WIDTH_DEFAULT,
      'narrow_width' => self::NARROW_WIDTH_DEFAULT,
    ];
  }

  /**
   * Retorna os rótulos dos estilos do container.
   *
   * @return array<string, string>
   */
  public static function getStyleOptions(): array {
    return [
      self::STYLE_BOXED => 'Caixa (conteúdo dentro de uma caixa com fundo e borda)',
      self::STYLE_UNBOXED => 'Sem caixa (conteúdo ocupa todo o espaço do container)',
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
   * @return array{mode: string, style: string, container_width: int, narrow_width: int}
   */
  public static function sanitizeSetting(mixed $input, string $purpose = 'main'): array {
    $defaults = self::getDefaultSettings($purpose);
    // Purpose controlado pelo código: o valor salvo é ignorado.
    if (self::codeSettings($purpose) !== NULL) {
      return $defaults;
    }

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

    $style = $input['style'] ?? $defaults['style'];
    if (!is_string($style) || !in_array($style, self::ALLOWED_STYLES, TRUE)) {
      $style = $defaults['style'];
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
      'style' => $style,
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
   * @return array<string, array{mode: string, style: string, container_width: int, narrow_width: int}>
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
   * @return array{mode: string, style: string|null, variables: array<string, string>}|null
   */
  public static function resolveContainerStyle(mixed $allSettings, mixed $purpose): ?array {
    if (!is_string($purpose) || !in_array($purpose, self::ALLOWED_PURPOSES, TRUE)) {
      return NULL;
    }

    $setting = is_array($allSettings) && isset($allSettings[$purpose])
      ? self::sanitizeSetting($allSettings[$purpose], $purpose)
      : self::getDefaultSettings($purpose);

    // Estilo vale para todos os modos (decisão do responsável em 2026-11-07).
    $style = in_array($setting['mode'], self::MODES_WITH_STYLE, TRUE) ? $setting['style'] : NULL;

    return [
      'mode' => $setting['mode'],
      'style' => $style,
      'variables' => [
        '--aculta-container-width' => $setting['container_width'] . 'px',
        '--aculta-narrow-container-width' => $setting['narrow_width'] . 'px',
      ],
    ];
  }

}
