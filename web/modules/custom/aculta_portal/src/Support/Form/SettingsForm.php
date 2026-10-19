<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Support\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configurações editoriais da página de apoio: introdução e valores padrão das doações.
 *
 * Os valores padrão valem quando não há campanha vigente (campanhas ficam no submódulo aculta_donation).
 */
final class SettingsForm extends ConfigFormBase {

  /** Valores padrão de instalação (R$). */
  public const VALORES_PADRAO = [5, 10, 20, 50, 100];

  /** Limite de cartões de valor na página. */
  public const MAXIMO_VALORES = 8;

  public function getFormId(): string {
    return 'aculta_support_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['aculta_portal.support'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_portal.support');
    $form['intro'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introdução pública'),
      '#default_value' => $config->get('intro') ?? '',
    ];
    $form['amounts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Valores padrão (em reais)'),
      '#description' => $this->t('Separados por vírgula, em ordem de exibição, por exemplo: 5, 10, 20, 50, 100. Máximo de @max valores. Valem quando não há campanha vigente.', ['@max' => self::MAXIMO_VALORES]),
      '#default_value' => implode(', ', $config->get('amounts') ?? self::VALORES_PADRAO),
      '#required' => TRUE,
    ];
    $form['custom_amount'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permitir valor personalizado ("Outro valor") quando não há campanha vigente'),
      '#default_value' => $config->get('custom_amount') ?? TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $valores = $this->valoresDoTexto((string) $form_state->getValue('amounts'));
    if ($valores === NULL) {
      $form_state->setErrorByName('amounts', $this->t('Informe valores inteiros maiores que zero, separados por vírgula.'));
    }
    elseif (count($valores) > self::MAXIMO_VALORES) {
      $form_state->setErrorByName('amounts', $this->t('Informe no máximo @max valores.', ['@max' => self::MAXIMO_VALORES]));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aculta_portal.support')
      ->set('intro', (string) $form_state->getValue('intro'))
      ->set('amounts', $this->valoresDoTexto((string) $form_state->getValue('amounts')) ?? self::VALORES_PADRAO)
      ->set('custom_amount', (bool) $form_state->getValue('custom_amount'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Lista de valores sem repetição, na ordem informada. NULL quando o texto não é uma lista válida.
   *
   * @return int[]|null
   */
  public static function valoresDoTexto(string $texto): ?array {
    $partes = array_filter(array_map('trim', explode(',', $texto)), static fn (string $p): bool => $p !== '');
    if ($partes === []) {
      return NULL;
    }
    $valores = [];
    foreach ($partes as $parte) {
      if (!ctype_digit($parte) || (int) $parte <= 0) {
        return NULL;
      }
      $valores[(int) $parte] = (int) $parte;
    }
    return array_values($valores);
  }

}
