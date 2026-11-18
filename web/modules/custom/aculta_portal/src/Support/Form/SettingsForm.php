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

  /**
   * Valores padrão de instalação (R$). */
  public const VALORES_PADRAO = [5, 10, 20, 50, 100];

  /**
   * Limite de cartões de valor na página. */
  public const MAXIMO_VALORES = 8;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_support_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return list<string>
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_portal.support'];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_portal.support');
    $amountsConfig = $config->get('amounts');
    $amountsParts = [];
    if (is_iterable($amountsConfig)) {
      foreach ($amountsConfig as $v) {
        if (is_int($v) || is_string($v) || is_float($v)) {
          $amountsParts[] = (string) $v;
        }
      }
    }
    if ($amountsParts === []) {
      foreach (self::VALORES_PADRAO as $v) {
        $amountsParts[] = (string) $v;
      }
    }
    $amountsStr = implode(', ', $amountsParts);

    $form['intro'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introdução pública'),
      '#default_value' => $config->get('intro') ?? '',
    ];
    $form['amounts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Valores padrão (em reais)'),
      '#description' => $this->t('Separados por vírgula, em ordem de exibição, por exemplo: 5, 10, 20, 50, 100. Máximo de @max valores. Valem quando não há campanha vigente.', ['@max' => self::MAXIMO_VALORES]),
      '#default_value' => $amountsStr,
      '#required' => TRUE,
    ];
    $form['custom_amount'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permitir valor personalizado ("Outro valor") quando não há campanha vigente'),
      '#default_value' => $config->get('custom_amount') ?? TRUE,
    ];
    /** @var array<string, mixed> $builtForm */
    $builtForm = parent::buildForm($form, $form_state);
    return $builtForm;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $rawAmounts = $form_state->getValue('amounts');
    $amountsStr = is_string($rawAmounts) ? $rawAmounts : '';
    $valores = $this->valoresDoTexto($amountsStr);
    if ($valores === NULL) {
      $form_state->setErrorByName('amounts', $this->t('Informe valores inteiros maiores que zero, separados por vírgula.'));
    }
    elseif (count($valores) > self::MAXIMO_VALORES) {
      $form_state->setErrorByName('amounts', $this->t('Informe no máximo @max valores.', ['@max' => self::MAXIMO_VALORES]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rawIntro = $form_state->getValue('intro');
    $introStr = is_string($rawIntro) ? $rawIntro : '';
    $rawAmounts = $form_state->getValue('amounts');
    $amountsStr = is_string($rawAmounts) ? $rawAmounts : '';
    $this->config('aculta_portal.support')
      ->set('intro', $introStr)
      ->set('amounts', $this->valoresDoTexto($amountsStr) ?? self::VALORES_PADRAO)
      ->set('custom_amount', (bool) $form_state->getValue('custom_amount'))
      ->save();
  }

  /**
   * Lista de valores sem repetição, na ordem informada. NULL quando o texto não é uma lista válida.
   *
   * @param string $texto
   *   Texto com valores separados por vírgula.
   *
   * @return int[]|null
   *   Valores inteiros únicos na ordem informada, ou NULL se o texto for inválido.
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
