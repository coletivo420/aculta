<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Palette;

/**
 * Fonte única dos esquemas oficiais da Paleta ACULTA Skunk Family.
 *
 * Baseada estritamente em web/themes/custom/aculta420_v2/docs/paletas-por-purpose.md.
 */
final class PurposePaletteSchemes {

  /**
   * Os dez esquemas oficiais da Paleta ACULTA Skunk Family.
   *
   * @var array<string, array{
   *   name: string,
   *   key: string,
   *   tone: string,
   *   purpose_default: string,
   *   light: array{
   *     accent: string,
   *     on_accent: string,
   *     sub: list<string>,
   *     contrast_accent_canvas: float,
   *     contrast_on_accent: float,
   *   },
   *   dark: array{
   *     accent: string,
   *     on_accent: string,
   *     sub: list<string>,
   *     contrast_accent_canvas: float,
   *     contrast_on_accent: float,
   *   }
   * }>
   */
  public const SCHEMES = [
    'verde-skunk' => [
      'name' => 'Skunk',
      'key' => 'verde-skunk',
      'tone' => 'profunda',
      'purpose_default' => 'main',
      'light' => [
        'accent' => '#5a8022',
        'on_accent' => '#ffffff',
        'sub' => ['#f0c41e', '#c03d27'],
        'contrast_accent_canvas' => 4.24,
        'contrast_on_accent' => 4.63,
      ],
      'dark' => [
        'accent' => '#6c9e22',
        'on_accent' => '#000000',
        'sub' => ['#fbd132', '#dd4329'],
        'contrast_accent_canvas' => 5.30,
        'contrast_on_accent' => 6.53,
      ],
    ],
    'verde-sativa' => [
      'name' => 'Sativa',
      'key' => 'verde-sativa',
      'tone' => 'fiel',
      'purpose_default' => 'wiki',
      'light' => [
        'accent' => '#689427',
        'on_accent' => '#000000',
        'sub' => ['#f2ca36', '#d4452d'],
        'contrast_accent_canvas' => 3.28,
        'contrast_on_accent' => 5.85,
      ],
      'dark' => [
        'accent' => '#7ab327',
        'on_accent' => '#000000',
        'sub' => ['#fcd64b', '#e1563f'],
        'contrast_accent_canvas' => 6.72,
        'contrast_on_accent' => 8.27,
      ],
    ],
    'verde-ruderalis' => [
      'name' => 'Ruderalis',
      'key' => 'verde-ruderalis',
      'tone' => 'suave',
      'purpose_default' => 'links',
      'light' => [
        'accent' => '#76a82c',
        'on_accent' => '#000000',
        'sub' => ['#f4d04e', '#d85842'],
        'contrast_accent_canvas' => 2.59,
        'contrast_on_accent' => 7.42,
      ],
      'dark' => [
        'accent' => '#89c72c',
        'on_accent' => '#000000',
        'sub' => ['#fcdc64', '#e46a55'],
        'contrast_accent_canvas' => 8.37,
        'contrast_on_accent' => 10.31,
      ],
    ],
    'verde-afghan' => [
      'name' => 'Afghan',
      'key' => 'verde-afghan',
      'tone' => 'profunda',
      'purpose_default' => 'account',
      'light' => [
        'accent' => '#08271a',
        'on_accent' => '#ffffff',
        'sub' => ['#5a8022', '#f0c41e', '#c03d27'],
        'contrast_accent_canvas' => 14.64,
        'contrast_on_accent' => 16.00,
      ],
      'dark' => [
        'accent' => '#059e61',
        'on_accent' => '#000000',
        'sub' => ['#6c9e22', '#fbd132', '#dd4329'],
        'contrast_accent_canvas' => 4.92,
        'contrast_on_accent' => 6.06,
      ],
    ],
    'amarelo-acapulco-gold' => [
      'name' => 'Acapulco Gold',
      'key' => 'amarelo-acapulco-gold',
      'tone' => 'profunda',
      'purpose_default' => 'support',
      'light' => [
        'accent' => '#f0c41e',
        'on_accent' => '#000000',
        'sub' => ['#5a8022', '#c03d27'],
        'contrast_accent_canvas' => 1.52,
        'contrast_on_accent' => 12.61,
      ],
      'dark' => [
        'accent' => '#fbd132',
        'on_accent' => '#000000',
        'sub' => ['#6c9e22', '#dd4329'],
        'contrast_accent_canvas' => 11.56,
        'contrast_on_accent' => 14.24,
      ],
    ],
    'amarelo-lemon-haze' => [
      'name' => 'Lemon Haze',
      'key' => 'amarelo-lemon-haze',
      'tone' => 'fiel',
      'purpose_default' => 'shop',
      'light' => [
        'accent' => '#f2ca36',
        'on_accent' => '#000000',
        'sub' => ['#689427', '#d4452d'],
        'contrast_accent_canvas' => 1.45,
        'contrast_on_accent' => 13.28,
      ],
      'dark' => [
        'accent' => '#fcd64b',
        'on_accent' => '#000000',
        'sub' => ['#7ab327', '#e1563f'],
        'contrast_accent_canvas' => 12.08,
        'contrast_on_accent' => 14.88,
      ],
    ],
    'amarelo-golden-goat' => [
      'name' => 'Golden Goat',
      'key' => 'amarelo-golden-goat',
      'tone' => 'suave',
      'purpose_default' => 'eventos',
      'light' => [
        'accent' => '#f4d04e',
        'on_accent' => '#000000',
        'sub' => ['#76a82c', '#d85842'],
        'contrast_accent_canvas' => 1.37,
        'contrast_on_accent' => 13.99,
      ],
      'dark' => [
        'accent' => '#fcdc64',
        'on_accent' => '#000000',
        'sub' => ['#89c72c', '#e46a55'],
        'contrast_accent_canvas' => 12.63,
        'contrast_on_accent' => 15.56,
      ],
    ],
    'vermelho-red-kush' => [
      'name' => 'Red Kush',
      'key' => 'vermelho-red-kush',
      'tone' => 'profunda',
      'purpose_default' => 'magazine',
      'light' => [
        'accent' => '#c03d27',
        'on_accent' => '#ffffff',
        'sub' => ['#5a8022', '#f0c41e'],
        'contrast_accent_canvas' => 4.86,
        'contrast_on_accent' => 5.32,
      ],
      'dark' => [
        'accent' => '#dd4329',
        'on_accent' => '#000000',
        'sub' => ['#6c9e22', '#fbd132'],
        'contrast_accent_canvas' => 3.99,
        'contrast_on_accent' => 4.91,
      ],
    ],
    'vermelho-panama-red' => [
      'name' => 'Panama Red',
      'key' => 'vermelho-panama-red',
      'tone' => 'fiel',
      'purpose_default' => 'courses',
      'light' => [
        'accent' => '#d4452d',
        'on_accent' => '#000000',
        'sub' => ['#689427', '#f2ca36'],
        'contrast_accent_canvas' => 4.10,
        'contrast_on_accent' => 4.69,
      ],
      'dark' => [
        'accent' => '#e1563f',
        'on_accent' => '#000000',
        'sub' => ['#7ab327', '#fcd64b'],
        'contrast_accent_canvas' => 4.54,
        'contrast_on_accent' => 5.60,
      ],
    ],
    'vermelho-red-congolese' => [
      'name' => 'Red Congolese',
      'key' => 'vermelho-red-congolese',
      'tone' => 'suave',
      'purpose_default' => 'forum',
      'light' => [
        'accent' => '#d85842',
        'on_accent' => '#000000',
        'sub' => ['#76a82c', '#f4d04e'],
        'contrast_accent_canvas' => 3.56,
        'contrast_on_accent' => 5.40,
      ],
      'dark' => [
        'accent' => '#e46a55',
        'on_accent' => '#000000',
        'sub' => ['#89c72c', '#fcdc64'],
        'contrast_accent_canvas' => 5.26,
        'contrast_on_accent' => 6.48,
      ],
    ],
  ];

  /**
   * Retorna os nomes das opções para formulários [chave => Nome].
   *
   * @return array<string, string>
   */
  public static function getOptions(): array {
    $options = [];
    foreach (self::SCHEMES as $key => $scheme) {
      $options[$key] = $scheme['name'];
    }
    return $options;
  }

  /**
   * Retorna o mapeamento padrão [purpose => chave].
   *
   * @return array<string, string>
   */
  public static function getPurposeDefaults(): array {
    $defaults = [];
    foreach (self::SCHEMES as $key => $scheme) {
      $defaults[$scheme['purpose_default']] = $key;
    }
    return $defaults;
  }

}
