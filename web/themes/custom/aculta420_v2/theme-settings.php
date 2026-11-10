<?php

/**
 * @file
 */

declare(strict_types=1);

use Drupal\aculta420_v2\Purpose\Purposes;
use Drupal\aculta420_v2\Palette\PurposePaletteSchemes;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Opções administrativas do tema ACULTA420 V2.
 *
 * As opções são salvas em aculta420_v2.settings e aplicadas como classes neutras
 * no body pelo hook SurfaceSettingsHooks. Não controlam modo de
 * cor, domínio, purpose nem regra de acesso: esses pertencem ao Portal.
 */

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function aculta420_v2_form_system_theme_settings_alter(array &$form, FormStateInterface $form_state): void {
  $form['aculta_superficies'] = [
    '#type' => 'details',
    '#title' => t('Superfícies'),
    '#description' => t('Raios e faixas de acento dos componentes. As opções são aplicadas como classes neutras no body.'),
    '#open' => TRUE,
  ];

  $form['aculta_superficies']['aculta_radius_button'] = [
    '#type' => 'select',
    '#title' => t('Raio dos botões'),
    '#options' => [
      'base' => t('Suave (8px)'),
      'lg' => t('Padrão (16px)'),
      'full' => t('Pastilha'),
    ],
    '#default_value' => theme_get_setting('aculta_radius_button') ?? 'lg',
  ];

  $form['aculta_superficies']['aculta_radius_card'] = [
    '#type' => 'select',
    '#title' => t('Raio dos cartões e blocos'),
    '#options' => [
      'base' => t('Suave (8px)'),
      'lg' => t('Padrão (16px)'),
    ],
    '#default_value' => theme_get_setting('aculta_radius_card') ?? 'lg',
  ];

  $form['aculta_superficies']['aculta_radius_field'] = [
    '#type' => 'select',
    '#title' => t('Raio dos campos de formulário'),
    '#options' => [
      'sm' => t('Discreto (4px)'),
      'base' => t('Padrão (8px)'),
    ],
    '#default_value' => theme_get_setting('aculta_radius_field') ?? 'base',
  ];

  $form['aculta_superficies']['aculta_accent_override'] = [
    '#type' => 'select',
    '#title' => t('Cor da faixa de acento'),
    '#description' => t('Padrão: a cor vem do mapeamento por tipo de conteúdo (evento, mapa e local; curso e aula; tópico e subtópico). Uma cor fixa para todos os cartões desfaz a diferença entre níveis vizinhos; use só se o responsável pedir.'),
    '#options' => [
      'mapping' => t('Padrão: pelo tipo de conteúdo'),
      'green' => t('Forçar verde para todos'),
      'yellow' => t('Forçar amarelo para todos'),
      'red' => t('Forçar vermelho para todos'),
    ],
    '#default_value' => theme_get_setting('aculta_accent_override') ?? 'mapping',
    '#states' => [
      'enabled' => [
        ':input[name="aculta_show_accent_stripe"]' => ['checked' => TRUE],
      ],
    ],
  ];

  $form['aculta_superficies']['aculta_show_accent_stripe'] = [
    '#type' => 'checkbox',
    '#title' => t('Mostrar faixa de acento nos cartões de área'),
    '#description' => t('A cor de cada área vem do Portal (contrato neutro), não do tema.'),
    '#default_value' => theme_get_setting('aculta_show_accent_stripe') ?? TRUE,
  ];

  $form['aculta_superficies']['aculta_revista_accent'] = [
    '#type' => 'ui_styles_styles',
    '#tree' => TRUE,
    '#title' => t('Faixa de acento da Revista'),
    '#description' => t('Uma escolha única para toda a Revista; prevalece sobre o accent do Portal.'),
    '#default_value' => theme_get_setting('aculta_revista_accent') ?? ['selected' => [], 'extra' => ''],
    '#drupal_theme' => 'aculta420_v2',
    '#weight' => 80,
  ];

  // ADR-054: shell paralelo com ativação controlada durante a migração.
  // O padrão é o shell clássico; o shell v2 só é usado quando selecionado.
  $form['aculta_shell'] = [
    '#type' => 'details',
    '#title' => t('Shell da página'),
    '#description' => t('Estrutura das regiões da página. O shell v2 usa as cinco áreas finais da ADR-054 e mantém as regiões antigas como ponte até o corte homologado.'),
    '#open' => FALSE,
  ];

  $form['aculta_shell']['aculta_shell_variant'] = [
    '#type' => 'select',
    '#title' => t('Variante do shell'),
    '#options' => [
      'classic' => t('Clássico (regiões atuais)'),
      'v2' => t('V2 (cinco áreas finais, em homologação)'),
    ],
    '#default_value' => theme_get_setting('aculta_shell_variant') ?? 'classic',
  ];

  $form['aculta_cores_purpose'] = [
    '#type' => 'details',
    '#title' => t('Cores por purpose'),
    '#description' => t('Personaliza a paleta de destaque (faixa e acento) por purpose. Fundo, texto e cabeçalho não mudam.'),
    '#open' => TRUE,
  ];

  // O tema usa a lista canônica de purposes que ele próprio define; não lê state do Portal.
  $activePurposes = Purposes::ALLOWED_PURPOSES;
  if ($activePurposes === []) {
    $form['aculta_cores_purpose']['empty_message'] = [
      '#markup' => '<p>' . t('Nenhum purpose ativo informado pelo Portal.') . '</p>',
    ];
    return;
  }

  $form['#attached']['library'][] = 'aculta420_v2/theme-settings';

  $variantOptions = PurposePaletteSchemes::getOptions();
  $purposeDefaults = PurposePaletteSchemes::getPurposeDefaults();
  $allSchemes = PurposePaletteSchemes::SCHEMES;

  $purposeLabels = [
    'main' => t('Principal (main)'),
    'account' => t('Conta (account)'),
    'support' => t('Apoio (support)'),
    'magazine' => t('Revista (magazine)'),
    'wiki' => t('Wiki (wiki)'),
    'shop' => t('Loja (shop)'),
    'courses' => t('Cursos (courses)'),
    'forum' => t('Fórum (forum)'),
    'eventos' => t('Eventos (eventos)'),
    'links' => t('Links (links)'),
  ];

  $savedPalette = theme_get_setting('aculta_purpose_palette') ?? [];
  if (!is_array($savedPalette)) {
    $savedPalette = [];
  }

  $form['aculta_cores_purpose']['aculta_purpose_palette'] = [
    '#type' => 'container',
    '#attributes' => ['class' => ['aculta-purpose-cards-grid']],
    '#tree' => TRUE,
  ];

  foreach ($activePurposes as $purpose) {
    if (!is_string($purpose)) {
      continue;
    }

    $defaultVariant = $purposeDefaults[$purpose] ?? 'verde-skunk';
    $currentVariant = $savedPalette[$purpose] ?? $defaultVariant;
    if (!isset($variantOptions[$currentVariant])) {
      $currentVariant = $defaultVariant;
    }

    $cardWrapper = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['aculta-purpose-card'],
        'data-purpose-card' => $purpose,
      ],
    ];

    $cardWrapper['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $purposeLabels[$purpose] ?? ucfirst($purpose),
      '#attributes' => ['class' => ['aculta-purpose-card__title']],
    ];

    $cardWrapper['control'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-purpose-card__control']],
    ];

    $cardWrapper['control']['select'] = [
      '#type' => 'select',
      '#title' => t('Esquema de cor'),
      '#options' => $variantOptions,
      '#default_value' => $currentVariant,
      '#parents' => ['aculta_purpose_palette', $purpose],
    ];

    // Renderiza a visualização da paleta para os esquemas disponíveis.
    // Sem JS, exibe a visualização. Com JS, alterna conforme o select.
    $cardWrapper['preview_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-purpose-card__preview-wrapper']],
    ];

    foreach ($allSchemes as $schemeKey => $scheme) {
      $isSelected = ($schemeKey === $currentVariant);
      $cardWrapper['preview_wrapper'][$schemeKey] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['aculta-palette-preview'],
          'data-scheme' => $schemeKey,
          'style' => $isSelected ? 'display: flex;' : 'display: none;',
        ],
      ];

      $modesMarkup = '<div class="aculta-palette-preview__modes">';

      // Modo Claro.
      $light = $scheme['light'];
      $modesMarkup .= '<div class="aculta-palette-mode aculta-palette-mode--light">';
      $modesMarkup .= '<div class="aculta-palette-mode__header">' . t('Claro') . '</div>';
      $modesMarkup .= '<div class="aculta-palette-mode__stripe">';
      $modesMarkup .= '<span class="aculta-palette-mode__scheme-name">' . htmlspecialchars($scheme['name'], ENT_QUOTES, 'UTF-8') . '</span>';
      $modesMarkup .= '<span class="aculta-palette-mode__hex">' . htmlspecialchars($light['accent'], ENT_QUOTES, 'UTF-8') . '</span>';
      $modesMarkup .= '</div>';
      $modesMarkup .= '<div class="aculta-palette-mode__subs">';
      foreach ($light['sub'] as $subHex) {
        $modesMarkup .= '<div class="aculta-palette-chip">';
        $modesMarkup .= '<span class="aculta-palette-chip__color"></span>';
        $modesMarkup .= '<span class="aculta-palette-chip__hex">' . htmlspecialchars($subHex, ENT_QUOTES, 'UTF-8') . '</span>';
        $modesMarkup .= '</div>';
      }
      $modesMarkup .= '</div>';
      $modesMarkup .= '<div class="aculta-palette-mode__contrast">';
      $modesMarkup .= 'Texto: ' . number_format($light['contrast_on_accent'], 2) . ':1<br>';
      $modesMarkup .= 'Destaque: ' . number_format($light['contrast_accent_canvas'], 2) . ':1';
      $modesMarkup .= '</div>';
      $modesMarkup .= '</div>';

      // Modo Escuro.
      $dark = $scheme['dark'];
      $modesMarkup .= '<div class="aculta-palette-mode aculta-palette-mode--dark">';
      $modesMarkup .= '<div class="aculta-palette-mode__header">' . t('Escuro') . '</div>';
      $modesMarkup .= '<div class="aculta-palette-mode__stripe">';
      $modesMarkup .= '<span class="aculta-palette-mode__scheme-name">' . htmlspecialchars($scheme['name'], ENT_QUOTES, 'UTF-8') . '</span>';
      $modesMarkup .= '<span class="aculta-palette-mode__hex">' . htmlspecialchars($dark['accent'], ENT_QUOTES, 'UTF-8') . '</span>';
      $modesMarkup .= '</div>';
      $modesMarkup .= '<div class="aculta-palette-mode__subs">';
      foreach ($dark['sub'] as $subHex) {
        $modesMarkup .= '<div class="aculta-palette-chip">';
        $modesMarkup .= '<span class="aculta-palette-chip__color"></span>';
        $modesMarkup .= '<span class="aculta-palette-chip__hex">' . htmlspecialchars($subHex, ENT_QUOTES, 'UTF-8') . '</span>';
        $modesMarkup .= '</div>';
      }
      $modesMarkup .= '</div>';
      $modesMarkup .= '<div class="aculta-palette-mode__contrast">';
      $modesMarkup .= 'Texto: ' . number_format($dark['contrast_on_accent'], 2) . ':1<br>';
      $modesMarkup .= 'Destaque: ' . number_format($dark['contrast_accent_canvas'], 2) . ':1';
      $modesMarkup .= '</div>';
      $modesMarkup .= '</div>';

      $modesMarkup .= '</div>';

      $cardWrapper['preview_wrapper'][$schemeKey]['markup'] = [
        '#markup' => $modesMarkup,
      ];
      unset($cardWrapper['preview_wrapper'][$schemeKey]['markup']);
      $cardWrapper['preview_wrapper'][$schemeKey]['modes'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-palette-preview__modes']],
      ];
      foreach (['light' => t('Claro'), 'dark' => t('Escuro')] as $modeKey => $modeLabel) {
        $mode = $scheme[$modeKey];
        $modeElement = [
          '#type' => 'container',
          '#attributes' => ['class' => ['aculta-palette-mode', 'aculta-palette-mode--' . $modeKey]],
          'header' => [
            '#type' => 'html_tag', '#tag' => 'div', '#value' => $modeLabel,
            '#attributes' => ['class' => ['aculta-palette-mode__header']],
          ],
          'stripe' => [
            '#type' => 'html_tag', '#tag' => 'div',
            '#attributes' => [
              'class' => ['aculta-palette-mode__stripe'],
              'style' => 'background-color: ' . $mode['accent'] . '; color: ' . $mode['on_accent'] . ';',
            ],
            'scheme_name' => [
              '#type' => 'html_tag', '#tag' => 'span', '#value' => $scheme['name'],
              '#attributes' => ['class' => ['aculta-palette-mode__scheme-name']],
            ],
            'hex' => [
              '#type' => 'html_tag', '#tag' => 'span', '#value' => $mode['accent'],
              '#attributes' => ['class' => ['aculta-palette-mode__hex']],
            ],
          ],
          'subs' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['aculta-palette-mode__subs']],
          ],
        ];
        foreach ($mode['sub'] as $idx => $subHex) {
          $modeElement['subs']['chip_' . $idx] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['aculta-palette-chip']],
            'color' => [
              '#type' => 'html_tag', '#tag' => 'span',
              '#attributes' => [
                'class' => ['aculta-palette-chip__color'],
                'style' => 'background-color: ' . $subHex . ';',
              ],
            ],
            'hex' => [
              '#type' => 'html_tag', '#tag' => 'span', '#value' => $subHex,
              '#attributes' => ['class' => ['aculta-palette-chip__hex']],
            ],
          ];
        }
        $cardWrapper['preview_wrapper'][$schemeKey]['modes'][$modeKey] = $modeElement;
      }
    }

    $form['aculta_cores_purpose']['aculta_purpose_palette'][$purpose] = $cardWrapper;
  }

  // ADR-054: o painel administrativo de container foi descontinuado.
  // A leitura dos valores antigos persiste no renderizador ate o corte homologado.
}
