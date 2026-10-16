<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Controles administrativos do chat: grupos, limites de participantes e envios, e o tempo da limpeza.
 *
 * Os valores são lidos por Service\ConversationPolicy (um lugar só). Os defaults ficam em
 * config/install/aculta_messenger.settings.yml.
 */
final class MessengerSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['aculta_messenger.settings'];
  }

  public function getFormId(): string {
    return 'aculta_messenger_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_messenger.settings');
    $form['group_creation_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Usuários comuns podem criar grupos'),
      '#description' => $this->t('Desligado, a nova conversa aceita só uma pessoa.'),
      '#default_value' => (bool) $config->get('group_creation_enabled'),
    ];
    $form['group_max_members'] = [
      '#type' => 'number',
      '#title' => $this->t('Máximo de outras pessoas num grupo'),
      '#description' => $this->t('Além do autor. Entre 2 e 50.'),
      '#default_value' => (int) $config->get('group_max_members'),
      '#min' => 2,
      '#max' => 50,
      '#required' => TRUE,
    ];
    $form['retention_months'] = [
      '#type' => 'number',
      '#title' => $this->t('Meses de inatividade antes da limpeza'),
      '#description' => $this->t('Uma conversa sem mensagens por esse tempo é removida pelo cron. Entre 1 e 36.'),
      '#default_value' => (int) $config->get('retention_months'),
      '#min' => 1,
      '#max' => 36,
      '#required' => TRUE,
    ];
    $form['cleanup_batch'] = [
      '#type' => 'number',
      '#title' => $this->t('Conversas removidas por execução do cron'),
      '#description' => $this->t('Limita o trabalho de cada execução. Entre 1 e 500.'),
      '#default_value' => (int) $config->get('cleanup_batch'),
      '#min' => 1,
      '#max' => 500,
      '#required' => TRUE,
    ];
    $form['send_limit_per_hour'] = [
      '#type' => 'number',
      '#title' => $this->t('Mensagens por hora por conta'),
      '#description' => $this->t('Envios acima desse número em uma hora são recusados. Entre 1 e 1000.'),
      '#default_value' => (int) $config->get('send_limit_per_hour'),
      '#min' => 1,
      '#max' => 1000,
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aculta_messenger.settings')
      ->set('group_creation_enabled', (bool) $form_state->getValue('group_creation_enabled'))
      ->set('group_max_members', min(50, max(2, (int) $form_state->getValue('group_max_members'))))
      ->set('retention_months', min(36, max(1, (int) $form_state->getValue('retention_months'))))
      ->set('cleanup_batch', min(500, max(1, (int) $form_state->getValue('cleanup_batch'))))
      ->set('send_limit_per_hour', min(1000, max(1, (int) $form_state->getValue('send_limit_per_hour'))))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
