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
   *
   * @return list<string>
   *   Value returned by the method.
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_forum.settings'];
  }

  /**
   * Builds the form.
   *
   * @param array<string, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
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
      '#default_value' => max(0, $this->integerValue($this->config('aculta_forum.settings')->get('edit_window_minutes')) ?? 10),
      '#required' => TRUE,
    ];

    $config = $this->config('aculta_forum.settings');

    $form['threading_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Níveis de Resposta e Encadeamento'),
      '#open' => TRUE,
    ];

    $form['threading_fieldset']['reply_max_depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Quantidade máxima de níveis de resposta com recuo automático'),
      '#description' => $this->t('Define até que nível as respostas terão recuo visual automático em toda a discussão. Respostas além do limite máximo têm seu nível estrutural normalizado para o nível máximo permitido.'),
      '#options' => [
        0 => $this->t('Desligado (linear, sem recuo)'),
        2 => $this->t('Até 2 níveis de resposta com recuo automático (Padrão)'),
        3 => $this->t('Até 3 níveis de resposta com recuo automático'),
      ],
      '#default_value' => $this->replyMaxDepth($config->get('reply_max_depth')),
      '#required' => TRUE,
    ];

    $form['topic_moderation_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Política de Exclusão de Tópicos pelo Autor'),
      '#open' => TRUE,
    ];

    $form['topic_moderation_fieldset']['topic_author_delete_policy'] = [
      '#type' => 'radios',
      '#title' => $this->t('Regra de exclusão para o autor do tópico'),
      '#description' => $this->t('Define o comportamento quando o autor do tópico clica no botão Excluir no Cartão de Abertura.'),
      '#options' => [
        'moderation_queue' => $this->t('Fila de Moderação (o autor solicita a exclusão e o tópico aguarda decisão da moderação na fila) — Padrão'),
        'direct_delete' => $this->t('Exclusão direta (o autor pode apagar o próprio tópico imediatamente)'),
        'disabled' => $this->t('Desativado (o autor não pode apagar o tópico; apenas moderadores/administradores)'),
      ],
      '#default_value' => $this->stringValue($config->get('topic_author_delete_policy'), 'moderation_queue'),
      '#required' => TRUE,
    ];

    $form['reactions_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Reações por Emoji (Discourse Reactions)'),
      '#open' => TRUE,
    ];

    $form['reactions_fieldset']['reactions_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Habilitar reações por emoji nas discussões'),
      '#description' => $this->t('Permite que participantes autenticados reajam a comentários e publicações de discussão.'),
      '#default_value' => $this->booleanValue($config->get('reactions_enabled')) ?? TRUE,
    ];

    $allowedReactions = $this->allowedReactions($config->get('allowed_reactions'));
    $defaultReaction = $this->stringValue($config->get('default_reaction'), 'broccoli');

    $options = [];
    foreach ($allowedReactions as $reaction) {
      $options[$reaction['id']] = $reaction['label'] . ' (' . $reaction['id'] . ')';
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
      $form['reactions_fieldset']['reactions_table'][$index]['id'] = [
        '#markup' => '<code>' . htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') . '</code>',
      ];
      $form['reactions_fieldset']['reactions_table'][$index]['icon'] = [
        '#type' => 'textfield',
        '#default_value' => $item['icon'],
        '#size' => 20,
        '#required' => TRUE,
      ];
      $form['reactions_fieldset']['reactions_table'][$index]['label'] = [
        '#type' => 'textfield',
        '#default_value' => $item['label'],
        '#size' => 30,
        '#required' => TRUE,
      ];
      $form['reactions_fieldset']['reactions_table'][$index]['enabled'] = [
        '#type' => 'checkbox',
        '#default_value' => $item['enabled'],
      ];
    }

    $completeForm = parent::buildForm($form, $form_state);
    foreach ($completeForm as $key => $value) {
      if (is_string($key)) {
        $form[$key] = $value;
      }
    }
    return $form;
  }

  /**
   * Submits the form.
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('aculta_forum.settings');
    $minutesValue = $form_state->getValue('edit_window_minutes');
    $minutes = $this->integerValue($minutesValue ?? $config->get('edit_window_minutes') ?? 10);
    if ($minutes === NULL) {
      $form_state->setErrorByName('edit_window_minutes', $this->t('Informe um número inteiro válido.'));
      return;
    }

    $depthValue = $form_state->getValue('reply_max_depth');
    if ($depthValue === NULL) {
      $depth = $this->replyMaxDepth($config->get('reply_max_depth'));
    }
    else {
      $depth = $this->integerValue($depthValue);
      if ($depth === NULL || !in_array($depth, [0, 2, 3], TRUE)) {
        $form_state->setErrorByName('reply_max_depth', $this->t('Selecione uma quantidade de níveis válida.'));
        return;
      }
    }

    $policyValue = $form_state->getValue('topic_author_delete_policy');
    if ($policyValue === NULL) {
      $policyValue = $this->stringValue($config->get('topic_author_delete_policy'), 'moderation_queue');
    }
    if (!is_string($policyValue) || !in_array($policyValue, ['moderation_queue', 'direct_delete', 'disabled'], TRUE)) {
      $form_state->setErrorByName('topic_author_delete_policy', $this->t('Selecione uma política válida para exclusão de tópicos.'));
      return;
    }

    $reactionsEnabledValue = $form_state->getValue('reactions_enabled');
    if ($reactionsEnabledValue === NULL) {
      $reactionsEnabledValue = $config->get('reactions_enabled') ?? TRUE;
    }
    $reactionsEnabled = $this->booleanValue($reactionsEnabledValue);
    if ($reactionsEnabled === NULL) {
      $form_state->setErrorByName('reactions_enabled', $this->t('Informe se as reações devem ficar habilitadas.'));
      return;
    }

    $defaultReactionValue = $form_state->getValue('default_reaction');
    if ($defaultReactionValue === NULL) {
      $defaultReactionValue = $this->stringValue($config->get('default_reaction'), 'broccoli');
    }
    if (!is_string($defaultReactionValue)) {
      $form_state->setErrorByName('default_reaction', $this->t('Selecione uma reação padrão válida.'));
      return;
    }

    $existing = $this->allowedReactions($config->get('allowed_reactions'));
    if ($existing !== [] && !in_array($defaultReactionValue, array_column($existing, 'id'), TRUE)) {
      $form_state->setErrorByName('default_reaction', $this->t('A reação padrão deve estar na lista de reações permitidas.'));
      return;
    }

    $rawSubmittedTable = $form_state->getValue('reactions_table');
    $submittedTable = is_array($rawSubmittedTable) ? $rawSubmittedTable : [];
    $updatedReactions = [];

    foreach ($existing as $index => $item) {
      if (!array_key_exists($index, $submittedTable)) {
        continue;
      }

      $submitted = $submittedTable[$index];
      if (!is_array($submitted)) {
        $form_state->setErrorByName('reactions_table', $this->t('Os dados da lista de reações são inválidos.'));
        return;
      }

      $icon = $submitted['icon'] ?? $item['icon'];
      if (!is_string($icon)) {
        $form_state->setErrorByName('reactions_table][' . $index . '][icon', $this->t('Informe um ícone válido.'));
        return;
      }
      $label = $submitted['label'] ?? $item['label'];
      if (!is_string($label)) {
        $form_state->setErrorByName('reactions_table][' . $index . '][label', $this->t('Informe um rótulo válido.'));
        return;
      }
      $enabled = $this->booleanValue($submitted['enabled'] ?? FALSE);
      if ($enabled === NULL) {
        $form_state->setErrorByName('reactions_table][' . $index . '][enabled', $this->t('Informe um estado válido para a reação.'));
        return;
      }

      $updatedReactions[] = [
        'id' => $item['id'],
        'icon' => trim($icon),
        'label' => trim($label),
        'enabled' => $enabled,
      ];
    }

    $config->set('edit_window_minutes', max(0, $minutes));
    $config->set('reply_max_depth', $depth);
    $config->set('topic_author_delete_policy', $policyValue);
    $config->set('reactions_enabled', $reactionsEnabled);
    $config->set('default_reaction', $defaultReactionValue);
    if ($updatedReactions !== []) {
      $config->set('allowed_reactions', $updatedReactions);
    }

    $config->save();
    $parentForm = [];
    foreach ($form as $key => $value) {
      $parentForm[$key] = $value;
    }
    parent::submitForm($parentForm, $form_state);
  }

  /**
   * Normaliza as reações configuradas para um formato seguro para o formulário.
   *
   * @param mixed $value
   *   Valor vindo da configuração.
   *
   * @return list<array{id: non-empty-string, icon: string, label: string, enabled: bool}>
   *   Reações válidas para exibição e edição.
   */
  private function allowedReactions(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $reactions = [];
    foreach ($value as $reaction) {
      if (!is_array($reaction)) {
        continue;
      }
      $id = $reaction['id'] ?? NULL;
      $label = $reaction['label'] ?? NULL;
      if (!is_string($id) || $id === '' || !is_string($label)
        || in_array($id, ['dislike', 'undo', 'thumbs-down', 'thumbs-down-medium', 'thumbs_down', 'thumbs_down_medium'], TRUE)) {
        continue;
      }

      $icon = $reaction['icon'] ?? $id;
      if (!is_string($icon) || in_array($icon, ['thumbs-down', 'thumbs-down-medium'], TRUE)) {
        continue;
      }
      $enabled = $this->booleanValue($reaction['enabled'] ?? TRUE) ?? TRUE;
      $reactions[] = [
        'id' => $id,
        'icon' => $icon,
        'label' => $label,
        'enabled' => $enabled,
      ];
    }

    return $reactions;
  }

  /**
   * Retorna um inteiro validado a partir de valor misto de configuração ou formulário.
   */
  private function integerValue(mixed $value): ?int {
    if (is_int($value)) {
      return $value;
    }
    if (!is_string($value) || preg_match('/^-?\\d+$/D', $value) !== 1) {
      return NULL;
    }
    $integer = filter_var($value, FILTER_VALIDATE_INT);
    return is_int($integer) ? $integer : NULL;
  }

  /**
   * Limita o nível de resposta às opções suportadas pelo formulário.
   */
  private function replyMaxDepth(mixed $value): int {
    $depth = $this->integerValue($value) ?? 2;
    return in_array($depth, [0, 2, 3], TRUE) ? $depth : 2;
  }

  /**
   * Retorna booleano validado a partir de valores aceitos pelo Form API e configuração.
   */
  private function booleanValue(mixed $value): ?bool {
    if (is_bool($value)) {
      return $value;
    }
    if ($value === 1 || $value === '1') {
      return TRUE;
    }
    if ($value === 0 || $value === '0') {
      return FALSE;
    }
    return NULL;
  }

  /**
   * Retorna texto de configuração ou o padrão quando o valor não é texto.
   */
  private function stringValue(mixed $value, string $default): string {
    return is_string($value) ? $value : $default;
  }

}
