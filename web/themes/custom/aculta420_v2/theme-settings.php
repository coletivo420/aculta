<?php

declare(strict_types=1);

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

  $form['aculta_cores_purpose'] = [
    '#type' => 'details',
    '#title' => t('Cores por purpose'),
    '#description' => t('Personaliza a paleta de destaque (faixa e acento) por purpose. Fundo, texto e cabeçalho não mudam.'),
    '#open' => TRUE,
  ];

  // Leitura exclusivamente neutra via Core State API; o tema não chama serviços do Portal.
  $activePurposes = \Drupal::state()->get('aculta_portal.active_purposes', []);
  if (!is_array($activePurposes) || empty($activePurposes)) {
    $form['aculta_cores_purpose']['empty_message'] = [
      '#markup' => '<p>' . t('Nenhum purpose ativo informado pelo Portal.') . '</p>',
    ];
    return;
  }

  $variantOptions = [
    'verde-skunk' => t('Skunk'),
    'verde-sativa' => t('Sativa'),
    'verde-ruderalis' => t('Ruderalis'),
    'verde-afghan' => t('Afghan'),
    'amarelo-acapulco-gold' => t('Acapulco Gold'),
    'amarelo-lemon-haze' => t('Lemon Haze'),
    'amarelo-golden-goat' => t('Golden Goat'),
    'vermelho-red-kush' => t('Red Kush'),
    'vermelho-panama-red' => t('Panama Red'),
    'vermelho-red-congolese' => t('Red Congolese'),
  ];

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

  $purposeDefaults = [
    'main' => 'verde-skunk',
    'wiki' => 'verde-sativa',
    'links' => 'verde-ruderalis',
    'account' => 'verde-afghan',
    'support' => 'amarelo-acapulco-gold',
    'shop' => 'amarelo-lemon-haze',
    'eventos' => 'amarelo-golden-goat',
    'magazine' => 'vermelho-red-kush',
    'courses' => 'vermelho-panama-red',
    'forum' => 'vermelho-red-congolese',
  ];

  $savedPalette = theme_get_setting('aculta_purpose_palette') ?? [];
  if (!is_array($savedPalette)) {
    $savedPalette = [];
  }

  $form['aculta_cores_purpose']['aculta_purpose_palette'] = [
    '#type' => 'container',
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

    $form['aculta_cores_purpose']['aculta_purpose_palette'][$purpose] = [
      '#type' => 'select',
      '#title' => $purposeLabels[$purpose] ?? ucfirst($purpose),
      '#options' => $variantOptions,
      '#default_value' => $currentVariant,
    ];
  }
}
