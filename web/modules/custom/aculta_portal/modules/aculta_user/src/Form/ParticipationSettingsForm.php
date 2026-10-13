<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Painel de regras de participação (plano aculta_user, seção E7).
 */
final class ParticipationSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['aculta_user.settings'];
  }

  public function getFormId(): string {
    return 'aculta_user_participacao_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_user.settings');
    $form['topico_limite_ativo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exigir comentários publicados para criar tópico'),
      '#default_value' => (bool) $config->get('topico_limite_ativo'),
    ];
    $form['topico_comentarios_minimos'] = [
      '#type' => 'number',
      '#title' => $this->t('Comentários publicados necessários para criar tópico'),
      '#default_value' => (int) $config->get('topico_comentarios_minimos'),
      '#min' => 0,
    ];
    $form['primeiro_comentario_moderado'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Primeiro comentário de quem não tem comentário publicado passa por moderação'),
      '#default_value' => (bool) $config->get('primeiro_comentario_moderado'),
    ];
    $form['bloqueio_ate_aprovacao'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bloquear novas publicações enquanto o primeiro comentário aguarda aprovação'),
      '#default_value' => (bool) $config->get('bloqueio_ate_aprovacao'),
    ];
    $roles = array_diff_key(user_role_names(TRUE), ['anonymous' => TRUE]);
    $form['papeis_isentos'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Papéis isentos das regras'),
      '#options' => $roles,
      '#default_value' => array_values(array_intersect((array) $config->get('papeis_isentos'), array_keys($roles))),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aculta_user.settings')
      ->set('topico_limite_ativo', (bool) $form_state->getValue('topico_limite_ativo'))
      ->set('topico_comentarios_minimos', max(0, (int) $form_state->getValue('topico_comentarios_minimos')))
      ->set('primeiro_comentario_moderado', (bool) $form_state->getValue('primeiro_comentario_moderado'))
      ->set('bloqueio_ate_aprovacao', (bool) $form_state->getValue('bloqueio_ate_aprovacao'))
      ->set('papeis_isentos', array_values(array_filter((array) $form_state->getValue('papeis_isentos'))))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
