<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Session\AccountProxyInterface;

/** Aba "Notificações por e-mail" em Minha conta > Configurações (preferência de e-mail da central de mensagens). */
final class SettingsTabsHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
  ) {}

  #[Hook('aculta_portal_settings_tabs_alter')]
  public function addCommunicationSettings(array &$tabs): void {
    // Shown to members who can message; the form itself shows when the feature is unavailable (controls disabled).
    if (!$this->currentUser->hasPermission('access aculta portal')
      || !$this->currentUser->hasPermission('use private messaging system')) {
      return;
    }

    $tabs['email'] = [
      'title' => (string) t('Notificações por e-mail'),
      'route' => 'aculta_messenger.communication_settings',
    ];
  }

}
