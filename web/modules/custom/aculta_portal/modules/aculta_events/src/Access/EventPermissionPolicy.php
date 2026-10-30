<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\RoleInterface;

/**
 * Política central de permissões configuráveis para criação e publicação de eventos e locais.
 */
final class EventPermissionPolicy {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Verifica se a conta pode criar eventos.
   */
  public function canCreateEvent(AccountInterface $account): bool {
    if ($account->hasPermission('administer aculta events')) {
      return TRUE;
    }

    $config = $this->configFactory->get('aculta_events.settings');
    $roles = (array) ($config->get('create_event_roles') ?? ['content_editor']);
    return !empty(array_intersect($roles, $account->getRoles()));
  }

  /**
   * Verifica se a conta pode criar locais de eventos.
   */
  public function canCreateLocation(AccountInterface $account): bool {
    if ($account->hasPermission('administer aculta events')) {
      return TRUE;
    }

    $config = $this->configFactory->get('aculta_events.settings');
    $roles = (array) ($config->get('create_location_roles') ?? ['content_editor']);
    return !empty(array_intersect($roles, $account->getRoles()));
  }

  /**
   * Verifica se a conta pode publicar locais diretamente sem passar por rascunho.
   */
  public function canPublishLocation(AccountInterface $account): bool {
    return $account->hasPermission('administer aculta events')
      || $account->hasPermission('publish aculta events and locations');
  }

  /**
   * Retorna os papéis atribuíveis existentes no sistema, excluindo anônimo.
   *
   * @return array<string, string>
   *   Mapeamento de ID para o rótulo do papel.
   */
  public function getAssignableRoles(): array {
    if (!$this->entityTypeManager->hasDefinition('user_role')) {
      return [
        'content_editor' => 'Editor de conteúdo',
        'administrator' => 'Administrador',
      ];
    }

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $options = [];
    foreach ($roles as $role) {
      if ($role instanceof RoleInterface && $role->id() !== RoleInterface::ANONYMOUS_ID) {
        $options[$role->id()] = (string) $role->label();
      }
    }

    return $options;
  }

  /**
   * Retorna o AccessResult com cacheability para criação de eventos.
   */
  public function createEventAccessResult(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->canCreateEvent($account))
      ->cachePerPermissions()
      ->addCacheContexts(['user.roles'])
      ->addCacheTags(['config:aculta_events.settings']);
  }

  /**
   * Retorna o AccessResult com cacheability para criação de locais.
   */
  public function createLocationAccessResult(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->canCreateLocation($account))
      ->cachePerPermissions()
      ->addCacheContexts(['user.roles'])
      ->addCacheTags(['config:aculta_events.settings']);
  }

}
