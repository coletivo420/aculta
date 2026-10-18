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
 * Configuração das consultas de CEP: banco de dados padrão e ordem das fontes (tabledrag do Drupal).
 *
 * A primeira fonte consultada é a padrão. As demais seguem pelo peso. Uma fonte desativada não é consultada e não
 * é verificada pelo cron. Ver CepSources e docs/portal/CEP-LOCAL.md.
 */
final class CepSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config,
    private readonly CepSources $sources,
  ) {
    parent::__construct($config_factory, $typed_config);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_portal_cep.sources'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_cep_settings';
  }

  protected function getEditableConfigNames(): array {
    return [CepSources::SETTINGS];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(CepSources::SETTINGS);
    $stored = $config->get('sources') ?? CepSources::DEFAULTS['sources'];

    $form['intro'] = [
      '#type' => 'item',
      '#title' => $this->t('Consulta de CEP'),
      '#description' => $this->t('A primeira fonte ativa consultada é a padrão. As demais seguem a ordem da tabela (arraste pelas alças). Fontes desativadas não são consultadas nem verificadas pelo cron.'),
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

    // Ordem atual: pelo peso salvo. As fontes desativadas mantêm a sua posição na tabela.
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
          '#attributes' => ['class' => ['cep-source-weight']],
        ],
      ];
    }
    $form['sources'] = [
      '#type' => 'table',
      '#header' => [$this->t('Fonte'), $this->t('Ativa'), $this->t('Ordem')],
      '#tabledrag' => [
        ['action' => 'order', 'relationship' => 'sibling', 'group' => 'cep-source-weight'],
      ],
      '#attributes' => ['data-portal-ui' => 'cep-sources'],
    ] + $rows;

    return parent::buildForm($form, $form_state);
  }

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
