<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the optional, privacy-minimized short-link analytics.
 */
final class LinkAnalyticsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_links_analytics_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_links.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['analytics_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Habilitar contagem agregada de redirects curtos'),
      '#description' => $this->t('Registra GETs válidos de links curtos em agregados diários por destino. Não conta HEAD, não identifica visitantes e retém os agregados por 90 dias. Ative somente após validação da base legal e da política de privacidade.'),
      '#default_value' => (bool) $this->config('aculta_links.settings')->get('analytics_enabled'),
    ];
    return parent::buildForm($form, $form_state);
  }

}
