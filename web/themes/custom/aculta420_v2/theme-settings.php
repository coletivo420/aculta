<?php

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Opções administrativas do tema ACULTA420 V2.
 *
 * Estado: scaffold. As opções são salvas em aculta420_v2.settings, mas ainda não
 * são aplicadas ao CSS (ver docs/configuracao-do-tema.md). Não controlam modo de
 * cor, domínio, purpose nem regra de acesso: esses pertencem ao Portal.
 */

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function aculta420_v2_form_system_theme_settings_alter(array &$form, FormStateInterface $form_state): void {
  $form['aculta_superficies'] = [
    '#type' => 'details',
    '#title' => t('Superfícies'),
    '#description' => t('Raios e faixas de acento dos componentes. Ainda não aplicados ao CSS: esta seção é um scaffold.'),
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

  $form['aculta_superficies']['aculta_show_accent_stripe'] = [
    '#type' => 'checkbox',
    '#title' => t('Mostrar faixa de acento nos cartões de área'),
    '#description' => t('A cor de cada área vem do Portal (contrato neutro), não do tema.'),
    '#default_value' => theme_get_setting('aculta_show_accent_stripe') ?? TRUE,
  ];
}
