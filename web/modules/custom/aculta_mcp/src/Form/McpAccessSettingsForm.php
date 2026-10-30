<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures individual Drupal accounts allowed to use ACULTA MCP remotely.
 */
final class McpAccessSettingsForm extends ConfigFormBase {

  /**
   * Constructs the MCP access settings form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_mcp_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_mcp.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_mcp.settings');
    $users = $this->entityTypeManager->getStorage('user')
      ->loadMultiple($config->get('authorized_user_ids') ?? []);

    $form['authorized_users'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Contas autorizadas'),
      '#description' => $this->t('Selecione contas Drupal individuais. Cada conta precisa autenticar-se por OAuth 2.1 para acesso externo; clientes internos podem usar uma sessão Drupal válida. A conta recebe somente a permissão de acesso ao MCP.'),
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#default_value' => array_values($users),
      '#selection_settings' => ['include_anonymous' => FALSE],
    ];

    $form['note'] = [
      '#type' => 'item',
      '#markup' => $this->t('Aplicativos OAuth precisam ser cadastrados separadamente por um administrador em Serviços web → OAuth. O registro dinâmico de aplicativos está desativado.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected = $form_state->getValue('authorized_users') ?? [];
    $selected_user_ids = array_values(array_unique(array_map(
      static fn(array $item): int => (int) $item['target_id'],
      $selected,
    )));
    sort($selected_user_ids);

    $config = $this->configFactory()->getEditable('aculta_mcp.settings');
    $previous_user_ids = array_map('intval', $config->get('authorized_user_ids') ?? []);
    $user_storage = $this->entityTypeManager->getStorage('user');
    $role_id = 'mcp_external';

    foreach (array_diff($previous_user_ids, $selected_user_ids) as $user_id) {
      $user = $user_storage->load($user_id);
      if ($user && $user->hasRole($role_id)) {
        $user->removeRole($role_id)->save();
      }
    }
    foreach (array_diff($selected_user_ids, $previous_user_ids) as $user_id) {
      $user = $user_storage->load($user_id);
      if ($user && !$user->hasRole($role_id)) {
        $user->addRole($role_id)->save();
      }
    }

    $config->set('authorized_user_ids', $selected_user_ids)->save();
    parent::submitForm($form, $form_state);
  }

}
