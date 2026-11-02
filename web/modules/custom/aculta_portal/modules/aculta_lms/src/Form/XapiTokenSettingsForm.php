<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\aculta_lms\Xapi\XapiTokenSettings;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Validade do token de tentativa xAPI, em /painel-administrativo (domínio principal).
 *
 * O servidor valida o intervalo; o atributo `min` e `max` só orienta o usuário.
 */
final class XapiTokenSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_xapi_token_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [XapiTokenSettings::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Validade do token que autoriza o envio de declarações de uma tentativa. Quando expira, o conteúdo precisa de um novo lançamento. Não altera o prazo de retenção das declarações.') . '</p>',
    ];
    $form[XapiTokenSettings::KEY_TTL] = [
      '#type' => 'number',
      '#title' => $this->t('Validade do token (segundos)'),
      '#default_value' => XapiTokenSettings::ttlFrom($this->config(XapiTokenSettings::CONFIG_NAME)->get(XapiTokenSettings::KEY_TTL)),
      '#min' => XapiTokenSettings::MIN_TTL,
      '#max' => XapiTokenSettings::MAX_TTL,
      '#required' => TRUE,
      '#description' => $this->t('Padrão: @default segundos (30 minutos). Teto: @max segundos (2 horas).', [
        '@default' => XapiTokenSettings::DEFAULT_TTL,
        '@max' => XapiTokenSettings::MAX_TTL,
      ]),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $value = $form_state->getValue(XapiTokenSettings::KEY_TTL);
    if (!is_numeric($value) || (int) $value != $value || (int) $value < XapiTokenSettings::MIN_TTL || (int) $value > XapiTokenSettings::MAX_TTL) {
      $form_state->setErrorByName(XapiTokenSettings::KEY_TTL, $this->t('Informe um valor de @min a @max segundos.', [
        '@min' => XapiTokenSettings::MIN_TTL,
        '@max' => XapiTokenSettings::MAX_TTL,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory()->getEditable(XapiTokenSettings::CONFIG_NAME)
      ->set(XapiTokenSettings::KEY_TTL, (int) $form_state->getValue(XapiTokenSettings::KEY_TTL))
      ->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Validade do token salva.'));
  }

}
