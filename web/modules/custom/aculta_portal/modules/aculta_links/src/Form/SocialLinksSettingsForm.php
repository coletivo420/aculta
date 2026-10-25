<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the public social profile links shown in LINKS.
 */
final class SocialLinksSettingsForm extends ConfigFormBase {

  private const CHANNELS = [
    'instagram' => [
      'hosts' => ['instagram.com', 'www.instagram.com'],
    ],
    'youtube' => [
      'hosts' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
    ],
    'facebook' => [
      'hosts' => ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com'],
    ],
  ];

  /**
   * Returns the stable Form API identifier.
   */
  public function getFormId(): string {
    return 'aculta_links_social_links_settings';
  }

  /**
   * Returns the configuration edited by this form.
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_links.social_links'];
  }

  /**
   * Builds the configuration form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_links.social_links');
    $form['intro'] = [
      '#type' => 'item',
      '#title' => $this->t('Redes sociais da página LINKS'),
      '#description' => $this->t('Informe o endereço oficial do perfil em cada rede. Campos vazios não aparecem na página. Use endereços HTTPS.'),
    ];
    foreach (self::CHANNELS as $id => $channel) {
      $form[$id] = [
        '#type' => 'url',
        '#title' => $this->channelLabel($id),
        '#default_value' => $config->get($id) ?? '',
        '#maxlength' => 2048,
        '#description' => $this->t('Hosts aceitos: @hosts.', ['@hosts' => implode(', ', $channel['hosts'])]),
      ];
    }

    $form['buttons_heading'] = [
      '#type' => 'item',
      '#title' => $this->t('Botões da página LINKS'),
      '#description' => $this->t('“Visite nosso Site” é fixo e sempre aparece primeiro. Adicione botões abaixo com texto e link. Aceita endereços HTTPS ou caminhos internos iniciados por /.'),
    ];
    $form['fixed_button'] = [
      '#type' => 'item',
      '#title' => $this->t('Visite nosso Site'),
      '#description' => $this->t('Botão fixo — abre a página principal da ACULTA.'),
    ];
    $stored_buttons = $config->get('buttons') ?? [];
    $button_ids = $form_state->get('aculta_social_links_button_ids');
    if (!is_array($button_ids)) {
      $button_ids = array_keys($stored_buttons);
      $form_state->set('aculta_social_links_button_ids', $button_ids);
      $form_state->set('aculta_social_links_button_next_id', $button_ids === [] ? 0 : max(array_map('intval', $button_ids)) + 1);
    }

    $form['buttons_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['id' => 'aculta-social-links-buttons-wrapper'],
    ];
    $form['buttons_wrapper']['items'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    foreach ($button_ids as $id) {
      $button = $stored_buttons[$id] ?? [];
      $form['buttons_wrapper']['items'][$id] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'social-links-settings-button'],
        'text' => [
          '#type' => 'textfield',
          '#title' => $this->t('Texto do botão'),
          '#default_value' => $button['text'] ?? '',
          '#maxlength' => 80,
          '#required' => FALSE,
        ],
        'url' => [
          '#type' => 'textfield',
          '#title' => $this->t('Link'),
          '#default_value' => $button['url'] ?? '',
          '#maxlength' => 2048,
          '#required' => FALSE,
        ],
        'remove' => [
          '#type' => 'submit',
          '#value' => $this->t('Remover'),
          '#name' => 'remove_social_button_' . $id,
          '#submit' => ['aculta_links.social_links_form_callbacks:removeButton'],
          '#limit_validation_errors' => [],
          '#ajax' => [
            'callback' => 'aculta_links.social_links_form_callbacks:buttonsAjax',
            'wrapper' => 'aculta-social-links-buttons-wrapper',
          ],
        ],
      ];
    }
    $form['buttons_wrapper']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('+ Adicionar botão'),
      '#submit' => ['aculta_links.social_links_form_callbacks:addButton'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => 'aculta_links.social_links_form_callbacks:buttonsAjax',
        'wrapper' => 'aculta-social-links-buttons-wrapper',
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Validates configured URLs before saving.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (self::CHANNELS as $id => $channel) {
      $value = trim((string) $form_state->getValue($id));
      if ($value === '') {
        continue;
      }
      $parts = parse_url($value);
      $valid = filter_var($value, FILTER_VALIDATE_URL) !== FALSE
        && is_array($parts)
        && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
        && in_array(strtolower((string) ($parts['host'] ?? '')), $channel['hosts'], TRUE)
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && !isset($parts['port']);
      if (!$valid) {
        $form_state->setErrorByName($id, $this->t('Informe uma URL HTTPS válida de @channel.', ['@channel' => $this->channelLabel($id)]));
      }
    }
    $buttons = $form_state->getValue(['buttons_wrapper', 'items']) ?? [];
    foreach ($buttons as $id => $button) {
      $text = trim((string) ($button['text'] ?? ''));
      $uri = trim((string) ($button['url'] ?? ''));
      if ($text === '' && $uri === '') {
        continue;
      }
      if ($text === '') {
        $form_state->setErrorByName('buttons_wrapper][items][' . $id . '][text', $this->t('Informe o texto deste botão.'));
      }
      if (!$this->isAllowedButtonUrl($uri)) {
        $form_state->setErrorByName('buttons_wrapper][items][' . $id . '][url', $this->t('Informe um link HTTPS válido ou um caminho interno iniciado por uma única barra (/).'));
      }
    }
  }

  /**
   * Saves the new configuration and its temporary rollback copy.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory()->getEditable('aculta_links.social_links');
    foreach (array_keys(self::CHANNELS) as $id) {
      $config->set($id, trim((string) $form_state->getValue($id)));
    }
    $buttons = $form_state->getValue(['buttons_wrapper', 'items']) ?? [];
    $saved_buttons = [];
    foreach ($buttons as $button) {
      $text = trim((string) ($button['text'] ?? ''));
      $uri = trim((string) ($button['url'] ?? ''));
      if ($text !== '' && $uri !== '') {
        $saved_buttons[] = ['text' => $text, 'url' => $uri];
      }
    }
    $config->set('buttons', $saved_buttons);
    $config->save();
    // Keep the previous config object synchronized during the rollback window.
    $this->configFactory()
      ->getEditable('aculta_portal.social_links')
      ->setData($config->getRawData())
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Ensures configured button links stay within supported safe URL forms. */
  private function isAllowedButtonUrl(string $uri): bool {
    if ($uri === '' || preg_match('/[\x00-\x20\\\\]/', $uri)) {
      return FALSE;
    }
    if (str_starts_with($uri, '/') && !str_starts_with($uri, '//')) {
      return TRUE;
    }
    $parts = parse_url($uri);
    return filter_var($uri, FILTER_VALIDATE_URL) !== FALSE
      && is_array($parts)
      && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
      && isset($parts['host'])
      && !isset($parts['user'])
      && !isset($parts['pass'])
      && !isset($parts['port']);
  }

  /**
   * Returns a translatable social-channel label.
   */
  private function channelLabel(string $id): string {
    return match ($id) {
      'instagram' => (string) $this->t('Instagram'),
      'youtube' => (string) $this->t('YouTube'),
      'facebook' => (string) $this->t('Facebook'),
      default => (string) $this->t('Rede social'),
    };
  }

}
