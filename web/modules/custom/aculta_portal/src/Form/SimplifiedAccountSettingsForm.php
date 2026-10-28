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
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_portal_simplified_account_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cadastro simplificado habilitado'),
      '#description' => $this->t('Ligado, a conta criada na compra recebe um e-mail de confirmação e, depois de confirmar, cria a senha. Uma nova compra com o mesmo e-mail, ainda não confirmado, reenvia o e-mail respeitando o intervalo entre reenvios.'),
      '#default_value' => $this->config(self::CONFIG)->get('enabled') !== FALSE,
    ];
    $form['resumo'] = [
      '#markup' => '<p>' . $this->t('Intervalo entre reenvios: 15 minutos por conta. Os reenvios usam o modelo de e-mail de confirmação do aculta_mailer.') . '</p>',
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::CONFIG)->set('enabled', (bool) $form_state->getValue('enabled'))->save();
    parent::submitForm($form, $form_state);
  }

}
