<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Painel administrativo do cadastro simplificado.
 *
 * Liga ou desliga a confirmação de e-mail e a criação de senha ao concluir uma compra como convidado.
 *
 * Desligado, o checkout segue como antes (o Commerce ainda cria a conta, se o fluxo permitir), mas não envia o e-mail
 * de confirmação nem leva a pessoa à etapa de senha. Os reenvios seguem o intervalo do requester de confirmação.
 */
final class SimplifiedAccountSettingsForm extends ConfigFormBase {

  public const CONFIG = 'aculta_portal.simplified_account';

  /**
   * Intervalo padrão entre reenvios de confirmação, em segundos (15 minutos).
   */
  public const DEFAULT_RESEND_WINDOW = 900;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_portal_simplified_account_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return list<string>
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array<mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $rawResendWindow = $this->config(self::CONFIG)->get('resend_window');
    $defaultResendWindow = is_numeric($rawResendWindow) ? (int) $rawResendWindow : self::DEFAULT_RESEND_WINDOW;

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cadastro simplificado habilitado'),
      '#description' => $this->t('Ligado, a conta criada na compra recebe um e-mail de confirmação e, depois de confirmar, cria a senha. Uma nova compra com o mesmo e-mail, ainda não confirmado, reenvia o e-mail respeitando o intervalo entre reenvios.'),
      '#default_value' => $this->config(self::CONFIG)->get('enabled') !== FALSE,
    ];
    $form['resend_window'] = [
      '#type' => 'number',
      '#title' => $this->t('Intervalo entre reenvios (segundos)'),
      '#description' => $this->t('Tempo mínimo entre dois e-mails de confirmação para a mesma conta. Padrão: 900 (15 minutos). Vale só para este fluxo.'),
      '#default_value' => $defaultResendWindow > 0 ? $defaultResendWindow : self::DEFAULT_RESEND_WINDOW,
      '#min' => 60,
      '#max' => 86400,
      '#step' => 1,
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rawResend = $form_state->getValue('resend_window');
    $resendWindow = is_numeric($rawResend) ? (int) $rawResend : self::DEFAULT_RESEND_WINDOW;

    $this->config(self::CONFIG)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('resend_window', $resendWindow)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
