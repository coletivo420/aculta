<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Captcha\Form;

use Drupal\aculta_portal\Captcha\TurnstileToggle;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Caixa de ativação do Turnstile na administração do ACULTA Portal. */
final class TurnstileSettingsForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly TurnstileToggle $toggle,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_portal.turnstile_toggle'), $container->get('current_user'));
  }

  public function getFormId(): string {
    return 'aculta_portal_turnstile_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $keyText = $this->toggle->keyConfigured()
      ? $this->t('configurada neste ambiente')
      : $this->t('não configurada neste ambiente');
    $form['status'] = [
      '#type' => 'container',
      'state' => ['#markup' => '<p>' . $this->t('Turnstile: @state. Chave do ambiente: @key.', [
        '@state' => $this->toggle->isEnabled() ? $this->t('ativado') : $this->t('desativado'),
        '@key' => $keyText,
      ]) . '</p>'],
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Turnstile ativo'),
      '#description' => $this->t('Desafio anti-bot no cadastro, no login, na recuperação de senha e nos formulários de contato. Só pode ser ativado com a chave configurada.'),
      '#default_value' => $this->toggle->isEnabled(),
    ];
    $form['confirm_disable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Entendo que, sem o Turnstile, cadastro, login e recuperação de senha ficam sem proteção contra robôs.'),
      '#states' => ['visible' => [':input[name="enabled"]' => ['checked' => FALSE]]],
    ];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => $this->t('Salvar')]];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $enabled = (bool) $form_state->getValue('enabled');
    if ($enabled && !$this->toggle->keyConfigured()) {
      $form_state->setErrorByName('enabled', $this->t('A chave do Turnstile não está configurada neste ambiente. Cadastre-a em Credenciais do ambiente antes de ativar.'));
    }
    if (!$enabled && !$form_state->getValue('confirm_disable')) {
      $form_state->setErrorByName('confirm_disable', $this->t('Confirme que entende o risco para desativar o Turnstile.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enabled = (bool) $form_state->getValue('enabled');
    $this->toggle->setEnabled($enabled, $this->currentUser->getAccountName());
    $this->messenger()->addStatus($enabled
      ? $this->t('Turnstile ativado.')
      : $this->t('Turnstile desativado.'));
  }

}
