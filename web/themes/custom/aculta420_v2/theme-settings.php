<?php

/**
 * @file
 */

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

  $form['aculta_superficies']['aculta_revista_accent'] = [
    '#type' => 'ui_styles_styles',
    '#tree' => TRUE,
    '#title' => t('Faixa de acento da Revista'),
    '#description' => t('Uma escolha única para toda a Revista; prevalece sobre o accent do Portal.'),
    '#default_value' => theme_get_setting('aculta_revista_accent') ?? ['selected' => [], 'extra' => ''],
    '#drupal_theme' => 'aculta420_v2',
    '#weight' => 80,
  ];

  // UI Skins adds its official variable settings form to this page.

}
