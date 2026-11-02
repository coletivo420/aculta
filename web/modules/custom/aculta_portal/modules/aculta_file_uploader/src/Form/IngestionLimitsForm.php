<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Form;

use Drupal\aculta_file_uploader\IngestionLimits;
use Drupal\aculta_file_uploader\IngestionLimitsReader;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Painel de limites de ingestão, subaba de /painel-administrativo/aculta/uploads.
 *
 * O servidor valida os tetos: o atributo `max` do campo só orienta o usuário.
 */
final class IngestionLimitsForm extends ConfigFormBase {

  /**
   * Rótulos dos campos, em megabytes.
   *
   * @var array<string, string>
   */
  private const LABELS = [
    IngestionLimits::DMARC_MAX_REPORT_MB => 'Relatório DMARC descompactado (MB)',
    IngestionLimits::IMAP_MAX_MESSAGE_MB => 'Mensagem IMAP (MB)',
    IngestionLimits::H5P_MAX_ENTRY_MB => 'Pacote H5P: uma entrada (MB)',
    IngestionLimits::H5P_MAX_TOTAL_MB => 'Pacote H5P: total descompactado (MB)',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_file_uploader_ingestion_limits_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [IngestionLimitsReader::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $limits = IngestionLimits::fromArray($this->config(IngestionLimitsReader::CONFIG_NAME)->get() ?? []);
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Tamanhos máximos para arquivos recebidos de fora: relatórios de e-mail, mensagens e pacotes H5P. Não altera o limite de upload por usuário.') . '</p>',
    ];
    foreach (self::LABELS as $key => $label) {
      $form[$key] = [
        '#type' => 'number',
        '#title' => $this->t($label),
        '#default_value' => $limits->megabytes($key),
        '#min' => 1,
        '#max' => IngestionLimits::CEILINGS[$key],
        '#required' => TRUE,
        '#description' => $this->t('Padrão: @default MB. Teto: @ceiling MB.', [
          '@default' => IngestionLimits::DEFAULTS[$key],
          '@ceiling' => IngestionLimits::CEILINGS[$key],
        ]),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach (array_keys(self::LABELS) as $key) {
      $value = $form_state->getValue($key);
      if (!is_numeric($value) || (int) $value != $value || (int) $value < 1 || (int) $value > IngestionLimits::CEILINGS[$key]) {
        $form_state->setErrorByName($key, $this->t('Informe um valor de 1 a @ceiling MB.', ['@ceiling' => IngestionLimits::CEILINGS[$key]]));
      }
    }
    if ((int) $form_state->getValue(IngestionLimits::H5P_MAX_ENTRY_MB) > (int) $form_state->getValue(IngestionLimits::H5P_MAX_TOTAL_MB)) {
      $form_state->setErrorByName(IngestionLimits::H5P_MAX_ENTRY_MB, $this->t('Uma entrada do pacote não pode passar do total descompactado.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory()->getEditable(IngestionLimitsReader::CONFIG_NAME);
    foreach (array_keys(self::LABELS) as $key) {
      $config->set($key, (int) $form_state->getValue($key));
    }
    $config->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Limites de ingestão salvos.'));
  }

}
