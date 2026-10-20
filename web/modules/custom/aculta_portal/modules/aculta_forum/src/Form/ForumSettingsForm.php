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

    return parent::buildForm($form, $form_state);
  }

}
