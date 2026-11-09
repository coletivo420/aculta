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

    $config = $this->config('aculta_forum.settings');

    $form['reactions_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Reações por Emoji (Discourse Reactions)'),
      '#open' => TRUE,
    ];

    $form['reactions_fieldset']['reactions_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Habilitar reações por emoji nas discussões'),
      '#description' => $this->t('Permite que participantes autenticados reajam a comentários e publicações de discussão.'),
      '#default_value' => (bool) ($config->get('reactions_enabled') ?? TRUE),
    ];

    $allowedReactions = (array) ($config->get('allowed_reactions') ?? []);
    $defaultReaction = (string) ($config->get('default_reaction') ?? 'heart');

    $options = [];
    foreach ($allowedReactions as $reaction) {
      if (isset($reaction['id'], $reaction['label'])) {
        $options[$reaction['id']] = $reaction['label'] . ' (' . $reaction['id'] . ')';
      }
    }

    $form['reactions_fieldset']['default_reaction'] = [
      '#type' => 'select',
      '#title' => $this->t('Reação padrão de clique rápido'),
      '#options' => $options,
      '#default_value' => $defaultReaction,
      '#description' => $this->t('Reação aplicada diretamente ao clicar no botão principal de curtir.'),
    ];

    $form['reactions_fieldset']['reactions_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Ícone (Fluent Emoji)'),
        $this->t('Rótulo (Acessibilidade)'),
        $this->t('Ativo'),
      ],
      '#empty' => $this->t('Nenhuma reação configurada.'),
    ];

    foreach ($allowedReactions as $index => $item) {
      $id = $item['id'] ?? '';
      $form['reactions_fieldset']['reactions_table'][$index]['id'] = [
        '#markup' => '<code>' . htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') . '</code>',
      ];
      $form['reactions_fieldset']['reactions_table'][$index]['icon'] = [
        '#type' => 'textfield',
        '#default_value' => $item['icon'] ?? $id,
        '#size' => 20,
        '#required' => TRUE,
      ];
      $form['reactions_fieldset']['reactions_table'][$index]['label'] = [
        '#type' => 'textfield',
        '#default_value' => $item['label'] ?? '',
        '#size' => 30,
        '#required' => TRUE,
      ];
      $form['reactions_fieldset']['reactions_table'][$index]['enabled'] = [
        '#type' => 'checkbox',
        '#default_value' => (bool) ($item['enabled'] ?? TRUE),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('aculta_forum.settings');
    $config->set('edit_window_minutes', max(0, (int) $form_state->getValue('edit_window_minutes')));
    $config->set('reactions_enabled', (bool) $form_state->getValue('reactions_enabled'));
    $config->set('default_reaction', (string) $form_state->getValue('default_reaction'));

    $existing = (array) ($config->get('allowed_reactions') ?? []);
    $submittedTable = (array) $form_state->getValue('reactions_table');
    $updatedReactions = [];

    foreach ($existing as $index => $item) {
      if (isset($submittedTable[$index])) {
        $updatedReactions[] = [
          'id' => (string) $item['id'],
          'icon' => trim((string) ($submittedTable[$index]['icon'] ?? $item['icon'] ?? $item['id'])),
          'label' => trim((string) ($submittedTable[$index]['label'] ?? $item['label'] ?? '')),
          'enabled' => (bool) ($submittedTable[$index]['enabled'] ?? FALSE),
        ];
      }
    }
    if ($updatedReactions !== []) {
      $config->set('allowed_reactions', $updatedReactions);
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }


}
