<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures options owned by the public ACULTA Forum.
 */
final class ForumSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_forum.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['introduction'] = [
      '#type' => 'container',
      'text' => [
        '#plain_text' => $this->t('Estas opções se aplicam somente ao Fórum público Growroom420. As regras compartilhadas de participação ficam em ACULTA User; as discussões do LMS continuam sob moderação dos Educadores.'),
      ],
    ];
    $form['comment_move_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permitir mover comentários entre tópicos públicos'),
      '#description' => $this->t('Quando habilitada, pessoas com a permissão específica podem mover um comentário e suas respostas para outro tópico público compatível. A opção permanece desabilitada por padrão; tópicos e comentários do acervo histórico nunca são movidos por esta ferramenta.'),
      '#config_target' => 'aculta_forum.settings:comment_move_enabled',
    ];
    $form['edit_window_minutes'] = [
      '#type' => 'number',
      '#title' => $this->t('Prazo para edição de publicação após a primeira publicação (minutos)'),
      '#description' => $this->t('Aplica-se às publicações do Growroom420. O prazo padrão é de 10 minutos e não é reiniciado após uma edição. Zero desativa a edição pelo autor; pessoas com permissão administrativa continuam podendo editar.'),
      '#min' => 0,
      '#step' => 1,
      '#default_value' => max(0, (int) ($this->config('aculta_forum.settings')->get('edit_window_minutes') ?? 10)),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aculta_forum.settings')
      ->set('edit_window_minutes', max(0, (int) $form_state->getValue('edit_window_minutes')))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
