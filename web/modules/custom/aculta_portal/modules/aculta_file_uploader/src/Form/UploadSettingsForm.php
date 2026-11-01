<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Form;

use Drupal\aculta_file_uploader\UploadPolicy;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Painel administrativo de upload de imagens por integração.
 *
 * Cada integração pode herdar a política global ou restringi-la. Nenhuma
 * opção amplia o catálogo central de imagens nem o limite de 10 MB.
 */
final class UploadSettingsForm extends ConfigFormBase {

  /**
   * Integrações que usam o aculta_file_uploader, com rótulo para o painel.
   *
   * @var array<string, string>
   */
  public const CONSUMERS = [
    'wiki' => 'Wiki',
    'forum' => 'Fórum',
    'revista' => 'Revista',
    'lms' => 'LMS',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_file_uploader_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_file_uploader.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_file_uploader.settings');
    $form['#tree'] = TRUE;
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Cada integração herda a política global ou a restringe. Nenhuma opção amplia o catálogo de imagens e documentos nem o limite de 10 MB.') . '</p>',
    ];

    $form['global'] = ['#type' => 'details', '#title' => $this->t('Política global'), '#open' => TRUE];
    $form['global']['allowed_extensions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Extensões permitidas (imagens e documentos)'),
      '#options' => $this->extensionOptions(),
      '#default_value' => $this->selectedExtensions((string) $config->get('allowed_extensions')),
      '#required' => TRUE,
    ];
    $form['global']['max_size_mb'] = [
      '#type' => 'number',
      '#title' => $this->t('Tamanho máximo por arquivo (MB)'),
      '#default_value' => (int) ($config->get('max_size_mb') ?: 10),
      '#min' => 1,
      '#max' => 10,
      '#step' => 1,
      '#required' => TRUE,
    ];

    foreach (self::CONSUMERS as $key => $label) {
      $override = $config->get('consumers.' . $key) ?? [];
      $form['consumers'][$key] = [
        '#type' => 'details',
        '#title' => $this->t('Integração: @label', ['@label' => $label]),
        '#open' => FALSE,
      ];
      $form['consumers'][$key]['inherit_global'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Usar a política global'),
        '#default_value' => (bool) ($override['inherit_global'] ?? TRUE),
      ];
      $form['consumers'][$key]['allowed_extensions'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Extensões desta integração (só valem sem herança)'),
        '#options' => $this->extensionOptions(),
        '#default_value' => $this->selectedExtensions((string) ($override['allowed_extensions'] ?? $config->get('allowed_extensions'))),
      ];
      $form['consumers'][$key]['max_size_mb'] = [
        '#type' => 'number',
        '#title' => $this->t('Tamanho máximo desta integração (MB, só valem sem herança)'),
        '#default_value' => (int) ($override['max_size_mb'] ?? $config->get('max_size_mb') ?: 10),
        '#min' => 1,
        '#max' => 10,
        '#step' => 1,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $global = $this->checkedExtensions($form_state->getValue(['global', 'allowed_extensions']));
    if ($global === []) {
      $form_state->setErrorByName('global][allowed_extensions', $this->t('Escolha ao menos uma extensão.'));
    }
    foreach (array_keys(self::CONSUMERS) as $key) {
      $values = $form_state->getValue(['consumers', $key]);
      if (empty($values['inherit_global']) && $this->checkedExtensions($values['allowed_extensions'] ?? []) === []) {
        $form_state->setErrorByName('consumers][' . $key . '][allowed_extensions', $this->t('Escolha ao menos uma extensão ou marque a herança da política global.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $consumers = [];
    foreach (array_keys(self::CONSUMERS) as $key) {
      $values = $form_state->getValue(['consumers', $key]);
      $consumers[$key] = [
        'inherit_global' => (bool) $values['inherit_global'],
        'allowed_extensions' => implode(' ', $this->checkedExtensions($values['allowed_extensions'] ?? [])),
        'max_size_mb' => $this->limit((int) $values['max_size_mb']),
      ];
    }
    $this->config('aculta_file_uploader.settings')
      ->set('allowed_extensions', implode(' ', $this->checkedExtensions($form_state->getValue(['global', 'allowed_extensions']))))
      ->set('max_size_mb', $this->limit((int) $form_state->getValue(['global', 'max_size_mb'])))
      ->set('consumers', $consumers)
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Opções de extensão, na ordem do catálogo central.
   *
   * @return array<string, string>
   */
  private function extensionOptions(): array {
    $options = [];
    foreach (self::catalog() as $extension) {
      $options[$extension] = strtoupper($extension);
    }
    return $options;
  }

  /**
   * Extensões marcadas a partir de uma lista separada por espaço.
   *
   * @return array<string, string>
   */
  private function selectedExtensions(string $configured): array {
    $requested = preg_split('/\s+/', strtolower(trim($configured))) ?: [];
    return array_intersect(self::catalog(), $requested);
  }

  /**
   * Extensões marcadas em um campo checkboxes, na ordem do catálogo.
   *
   * @return string[]
   */
  private function checkedExtensions(mixed $values): array {
    if (!is_array($values)) {
      return [];
    }
    $checked = array_keys(array_filter($values, static fn ($value) => $value !== 0 && $value !== '0' && $value !== NULL && $value !== ''));
    return array_values(array_intersect(self::catalog(), $checked));
  }

  /**
   * Catálogo completo do painel: imagens e documentos, na ordem central.
   *
   * @return string[]
   */
  private static function catalog(): array {
    return array_merge(explode(' ', UploadPolicy::EXTENSIONS), explode(' ', UploadPolicy::DOCUMENT_EXTENSIONS));
  }

  /**
   * Limita o valor de MB ao intervalo aceito pelo catálogo central.
   */
  private function limit(int $megabytes): int {
    return max(1, min(10, $megabytes));
  }

}
