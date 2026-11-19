<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Appearance;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configurações do Portal: padrão global do modo de cor, usado por quem não escolheu. */
final class SettingsForm extends ConfigFormBase {

  /**
   * Retorna o identificador do formulário de configurações.
   *
   * @return string
   *   Identificador do formulário.
   */
  public function getFormId(): string {
    return 'aculta_portal_appearance_settings';
  }

  /**
   * Retorna os nomes das configurações editáveis.
   *
   * @return list<string>
   *   Nomes de configuração editáveis.
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_portal.appearance'];
  }

  /**
   * Monta o formulário do modo de cor padrão do site.
   *
   * @param array<string, mixed> $form
   *   Estrutura do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado atual do formulário.
   *
   * @return array<string, mixed>
   *   Estrutura do formulário renderizável.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $defaultMode = $this->config('aculta_portal.appearance')->get('default_mode');
    $form['default_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Modo de cor padrão do site'),
      '#description' => $this->t('Aplica-se a visitantes e a pessoas usuárias que ainda não escolheram um modo em Minha Conta > Configurações.'),
      '#options' => [
        'light' => $this->t('Claro'),
        'dark' => $this->t('Escuro'),
        'auto' => $this->t('Automático'),
      ],
      '#default_value' => is_string($defaultMode) ? $defaultMode : ColorModePreference::DEFAULT_MODE,
      '#required' => TRUE,
    ];
    /** @var array<string, mixed> $builtForm */
    $builtForm = parent::buildForm($form, $form_state);
    return $builtForm;
  }

  /**
   * Salva o modo de cor padrão escolhido.
   *
   * @param array<string, mixed> $form
   *   Estrutura do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado atual do formulário.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rawMode = $form_state->getValue('default_mode');
    $mode = is_string($rawMode) ? $rawMode : ColorModePreference::DEFAULT_MODE;
    $this->config('aculta_portal.appearance')
      ->set('default_mode', $mode)
      ->save();
  }

}
