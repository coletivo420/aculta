<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Form;

use Drupal\aculta_portal_cep\Settings\CepSources;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the default CEP source and lookup order.
 *
 * The default source runs first. Other enabled sources follow their tabledrag
 * order. Disabled sources are skipped by lookups and cron checks.
 */
final class CepSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config,
    private readonly CepSources $sources,
  ) {
    parent::__construct($config_factory, $typed_config);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_portal_cep.sources'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_portal_cep_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [CepSources::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(CepSources::SETTINGS);
    $stored = $config->get('sources') ?? CepSources::DEFAULTS['sources'];

    $form['intro'] = [
      '#type' => 'item',
      '#title' => $this->t('Consulta de CEP'),
      '#description' => $this->t('A fonte padrão é consultada primeiro. Fontes desativadas são ignoradas.'),
    ];

    $options = [];
    foreach (array_keys(CepSources::LABELS) as $id) {
      $options[$id] = $this->sources->label($id);
    }
    $form['default_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Banco de dados padrão'),
      '#description' => $this->t('Primeira fonte consultada. Precisa estar ativa.'),
      '#options' => $options,
      '#default_value' => $config->get('default_source') ?? CepSources::DEFAULTS['default_source'],
    ];

    // Keep disabled sources in their saved order in the table.
    $ids = array_keys(CepSources::LABELS);
    usort($ids, static fn (string $a, string $b): int => ((int) ($stored[$a]['weight'] ?? 0)) <=> ((int) ($stored[$b]['weight'] ?? 0)));

    $rows = [];
    foreach ($ids as $position => $id) {
      $rows[$id] = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $position,
        'label' => ['#plain_text' => $this->sources->label($id)],
        'enabled' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Ativa'),
          '#title_display' => 'invisible',
          '#default_value' => (bool) ($stored[$id]['enabled'] ?? FALSE),
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Ordem'),
          '#title_display' => 'invisible',
          '#delta' => count(CepSources::LABELS),
          '#default_value' => $position,
          '#attributes' => ['class' => ['role-weight']],
        ],
      ];
    }
    $form['sources'] = [
      '#type' => 'table',
      '#header' => [$this->t('Fonte'), $this->t('Ativa'), $this->t('Ordem')],
      '#tabledrag' => [
        ['action' => 'order', 'relationship' => 'sibling', 'group' => 'role-weight'],
      ],
      '#attributes' => ['data-portal-ui' => 'cep-sources'],
    ] + $rows;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $enabled = [];
    foreach (array_keys(CepSources::LABELS) as $id) {
      if ((bool) $form_state->getValue(['sources', $id, 'enabled'])) {
        $enabled[] = $id;
      }
    }
    if ($enabled === []) {
      $form_state->setErrorByName('sources', $this->t('Mantenha pelo menos uma fonte ativa.'));
      return;
    }
    $default = (string) $form_state->getValue('default_source');
    if (!in_array($default, $enabled, TRUE)) {
      $form_state->setErrorByName('default_source', $this->t('O banco de dados padrão precisa estar ativo.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sources = [];
    foreach (array_keys(CepSources::LABELS) as $id) {
      $sources[$id] = [
        'enabled' => (bool) $form_state->getValue(['sources', $id, 'enabled']),
        'weight' => (int) $form_state->getValue(['sources', $id, 'weight']),
      ];
    }
    $this->config(CepSources::SETTINGS)
      ->set('default_source', (string) $form_state->getValue('default_source'))
      ->set('sources', $sources)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
