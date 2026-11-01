<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Appearance;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/** Configurações do Portal: padrão global do modo de cor, usado por quem não escolheu. */
final class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'aculta_portal_appearance_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['aculta_portal.appearance'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['default_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Modo de cor padrão do site'),
      '#description' => $this->t('Aplica-se a visitantes e a pessoas usuárias que ainda não escolheram um modo em Minha Conta > Configurações.'),
      '#options' => [
        'light' => $this->t('Claro'),
        'dark' => $this->t('Escuro'),
        'auto' => $this->t('Automático'),
      ],
      '#default_value' => $this->config('aculta_portal.appearance')->get('default_mode') ?? ColorModePreference::DEFAULT_MODE,
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aculta_portal.appearance')
      ->set('default_mode', (string) $form_state->getValue('default_mode'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
