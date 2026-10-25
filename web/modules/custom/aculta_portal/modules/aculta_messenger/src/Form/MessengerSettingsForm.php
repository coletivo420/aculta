<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Administrative communication policies for messages and notice email.
 *
 * Conversation controls are read by ConversationPolicy. Defaults live in
 * config/install/aculta_messenger.settings.yml.
 */
final class MessengerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_messenger.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_messenger_settings';
  }

  /**
   * {@inheritdoc}
   */
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
    $form['email_modes'] = [
      '#type' => 'details',
      '#title' => $this->t('Modos de e-mail permitidos por categoria'),
      '#description' => $this->t('A pessoa sempre pode escolher sem e-mail. Desmarque resumo ou envio direto para remover essa opção em todas as contas; os avisos no site continuam ativos.'),
      '#open' => FALSE,
    ];
    $form['email_modes']['private_message_digest_anchor'] = [
      '#type' => 'date',
      '#title' => $this->t('Data inicial do resumo semanal de mensagens privadas'),
      '#description' => $this->t('O primeiro resumo pode ser enviado nessa data ou depois dela. Para cada conta, os resumos seguintes respeitam sete dias desde o último envio.'),
      '#default_value' => $config->get('private_message_digest_anchor') ?: '2026-10-26',
      '#states' => [
        'visible' => [
          ':input[name="email_modes[' . AvisoPreferencias::EMAIL_PRIVATE_MESSAGES . '][' . AvisoPreferencias::EMAIL_DIGEST . ']"]' => ['checked' => TRUE],
        ],
      ],
    ];
    foreach (AvisoPreferencias::EMAIL_CATEGORIES as $category) {
      $configured = $config->get('email_modes.' . $category);
      $configured = is_array($configured) ? $configured : [];
      $options = [
        AvisoPreferencias::EMAIL_IMMEDIATE => $this->t('Envio direto'),
      ];
      if (!in_array($category, AvisoPreferencias::EMAIL_IMMEDIATE_ONLY_CATEGORIES, TRUE)) {
        $digestLabel = $category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES
          ? $this->t('Resumo semanal')
          : $this->t('Resumo diário');
        $options = [AvisoPreferencias::EMAIL_DIGEST => $digestLabel] + $options;
      }
      $defaultModes = [];
      if (!in_array($category, AvisoPreferencias::EMAIL_IMMEDIATE_ONLY_CATEGORIES, TRUE)
        && (!array_key_exists('digest', $configured) || (bool) $configured['digest'])) {
        $defaultModes[] = AvisoPreferencias::EMAIL_DIGEST;
      }
      if (!array_key_exists('immediate', $configured) || (bool) $configured['immediate']) {
        $defaultModes[] = AvisoPreferencias::EMAIL_IMMEDIATE;
      }
      $form['email_modes'][$category] = [
        '#type' => 'checkboxes',
        '#title' => $this->emailCategoryTitle($category),
        '#options' => $options,
        '#default_value' => $defaultModes,
      ];
      if ($category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES) {
        $form['email_modes'][$category . '_default_mode'] = [
          '#type' => 'select',
          '#title' => $this->t('Padrão para contas sem preferência de mensagens privadas'),
          '#options' => [
            AvisoPreferencias::EMAIL_OFF => $this->t('Somente no site'),
            AvisoPreferencias::EMAIL_DIGEST => $this->t('Resumo semanal por e-mail'),
            AvisoPreferencias::EMAIL_IMMEDIATE => $this->t('No site e por e-mail'),
          ],
          '#default_value' => in_array(
            $configured['default_mode'] ?? NULL,
            [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE],
            TRUE,
          )
            ? $configured['default_mode']
            : AvisoPreferencias::EMAIL_IMMEDIATE,
          '#states' => [
            'disabled' => [
              ':input[name="email_modes[' . $category . '][' . AvisoPreferencias::EMAIL_IMMEDIATE . ']"]' => ['checked' => FALSE],
              ':input[name="email_modes[' . $category . '][' . AvisoPreferencias::EMAIL_DIGEST . ']"]' => ['checked' => FALSE],
            ],
          ],
        ];
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $emailModes = [];
    foreach (AvisoPreferencias::EMAIL_CATEGORIES as $category) {
      $selected = array_filter((array) $form_state->getValue(['email_modes', $category]));
      $emailModes[$category] = [
        'digest' => !in_array($category, AvisoPreferencias::EMAIL_IMMEDIATE_ONLY_CATEGORIES, TRUE)
        && in_array(AvisoPreferencias::EMAIL_DIGEST, $selected, TRUE),
        'immediate' => in_array(AvisoPreferencias::EMAIL_IMMEDIATE, $selected, TRUE),
      ];
      if ($category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES) {
        $defaultMode = $form_state->getValue(['email_modes', $category . '_default_mode']);
        $emailModes[$category]['default_mode'] = match (TRUE) {
          $defaultMode === AvisoPreferencias::EMAIL_IMMEDIATE && $emailModes[$category]['immediate'] => AvisoPreferencias::EMAIL_IMMEDIATE,
          $defaultMode === AvisoPreferencias::EMAIL_DIGEST && $emailModes[$category]['digest'] => AvisoPreferencias::EMAIL_DIGEST,
          default => AvisoPreferencias::EMAIL_OFF,
        };
      }
    }
    $this->config('aculta_messenger.settings')
      ->set('group_creation_enabled', (bool) $form_state->getValue('group_creation_enabled'))
      ->set('group_max_members', min(50, max(2, (int) $form_state->getValue('group_max_members'))))
      ->set('retention_months', min(36, max(1, (int) $form_state->getValue('retention_months'))))
      ->set('cleanup_batch', min(500, max(1, (int) $form_state->getValue('cleanup_batch'))))
      ->set('send_limit_per_hour', min(1000, max(1, (int) $form_state->getValue('send_limit_per_hour'))))
      ->set(
        'private_message_digest_anchor',
        (string) $form_state->getValue(['email_modes', 'private_message_digest_anchor']),
      )
      ->set('email_modes', $emailModes)
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Returns the translated administrative label for one notice category.
   */
  private function emailCategoryTitle(string $category): string {
    return match ($category) {
      'forum_resposta' => (string) $this->t('Respostas do Fórum'),
      'forum_atualizacao' => (string) $this->t('Atualizações do Fórum'),
      'aprendizagem' => (string) $this->t('Aprendizagem'),
      'wiki' => (string) $this->t('Wiki'),
      'loja' => (string) $this->t('Loja'),
      'doacao' => (string) $this->t('Doações'),
      AvisoPreferencias::EMAIL_PRIVATE_MESSAGES => (string) $this->t('Mensagens privadas'),
      AvisoPreferencias::EMAIL_FRIENDSHIP => (string) $this->t('Amizades'),
      default => throw new \InvalidArgumentException('Categoria de aviso inválida.'),
    };
  }

}
