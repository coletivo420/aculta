<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Edits the safe presentation copy for the private Account dashboard.
 */
final class AccountPresentationSettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'aculta_studio.account_presentation';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_studio_account_presentation_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return list<string>
   *   Editable configuration names.
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array<array-key, mixed>
   *   The completed form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);
    $form['introduction'] = [
      '#type' => 'item',
      '#title' => $this->t('Apresentação de Minha Conta'),
      '#description' => $this->t('Edite apenas os textos do painel. Identidade, experiências, permissões e render arrays continuam sob controle da Conta e dos módulos proprietários.'),
    ];

    $form['experiences_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título das experiências'),
      '#default_value' => $this->stringValue($config->get('experiences_title'), (string) $this->t('Suas experiências')),
      '#maxlength' => 120,
      '#required' => TRUE,
      '#config_target' => self::CONFIG_NAME . ':experiences_title',
    ];
    $form['experiences_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descrição das experiências'),
      '#default_value' => $this->stringValue($config->get('experiences_description'), (string) $this->t('Acesse as experiências da ACULTA e explore conteúdos, projetos e comunidades.')),
      '#maxlength' => 500,
      '#required' => TRUE,
      '#config_target' => self::CONFIG_NAME . ':experiences_description',
    ];
    $form['account_link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título do atalho Minha conta'),
      '#default_value' => $this->stringValue($config->get('account_link_title'), (string) $this->t('Minha conta')),
      '#maxlength' => 120,
      '#required' => TRUE,
      '#config_target' => self::CONFIG_NAME . ':account_link_title',
    ];
    $form['account_link_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Descrição do atalho Minha conta'),
      '#default_value' => $this->stringValue($config->get('account_link_description'), (string) $this->t('Dados, segurança e preferências')),
      '#maxlength' => 240,
      '#required' => TRUE,
      '#config_target' => self::CONFIG_NAME . ':account_link_description',
    ];
    $form['account_link_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome acessível do atalho Minha conta'),
      '#default_value' => $this->stringValue($config->get('account_link_label'), (string) $this->t('Minha Conta')),
      '#maxlength' => 120,
      '#required' => TRUE,
      '#config_target' => self::CONFIG_NAME . ':account_link_label',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns a stored string or its translated default.
   */
  private function stringValue(mixed $value, string $default): string {
    return is_string($value) ? $value : $default;
  }

}
