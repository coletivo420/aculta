<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário administrativo de configuração de papéis para criação de eventos e locais.
 */
final class EventSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly EventPermissionPolicy $permissionPolicy,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_events.permission_policy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_events.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_events.settings');
    $roles = $this->permissionPolicy->getAssignableRoles();

    $form['permissions_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Permissões de criação de eventos e locais'),
      '#description' => $this->t('Configure os papéis com permissão para cadastrar eventos e locais. Administradores possuem acesso contínuo.'),
      '#open' => TRUE,
    ];

    $form['permissions_section']['create_event_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Papéis autorizados a criar Eventos'),
      '#description' => $this->t('Usuários com estes papéis poderão criar novos eventos estruturados.'),
      '#options' => $roles,
      '#default_value' => (array) ($config->get('create_event_roles') ?? ['content_editor']),
      '#required' => TRUE,
    ];

    $form['permissions_section']['create_location_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Papéis autorizados a criar Locais'),
      '#description' => $this->t('Usuários com estes papéis poderão cadastrar novos locais reutilizáveis no catálogo e através do modal.'),
      '#options' => $roles,
      '#default_value' => (array) ($config->get('create_location_roles') ?? ['content_editor']),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $validRoles = array_keys($this->permissionPolicy->getAssignableRoles());

    foreach (['create_event_roles', 'create_location_roles'] as $field) {
      $selected = array_values(array_filter((array) $form_state->getValue($field)));
      foreach ($selected as $role) {
        if (!in_array($role, $validRoles, TRUE)) {
          $form_state->setErrorByName($field, (string) $this->t('O papel @role selecionado é inválido.', ['@role' => $role]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $eventRoles = array_values(array_filter((array) $form_state->getValue('create_event_roles')));
    $locationRoles = array_values(array_filter((array) $form_state->getValue('create_location_roles')));
    sort($eventRoles);
    sort($locationRoles);

    $this->config('aculta_events.settings')
      ->set('create_event_roles', $eventRoles)
      ->set('create_location_roles', $locationRoles)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
