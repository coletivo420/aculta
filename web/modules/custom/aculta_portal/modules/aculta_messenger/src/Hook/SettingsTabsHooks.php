<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Session\AccountProxyInterface;

/** Adds communication settings when native Private Message email is available. */
final class SettingsTabsHooks {

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
  ) {}

  #[Hook('aculta_portal_settings_tabs_alter')]
  public function addCommunicationSettings(array &$tabs): void {
    if (!$this->currentUser->hasPermission('access aculta portal')
      || !$this->currentUser->hasPermission('use private messaging system')
      || !$this->configFactory->get('private_message.settings')->get('enable_notifications')) {
      return;
    }

    $tabs['messaging'] = [
      'title' => (string) t('Mensagens'),
      'route' => 'aculta_messenger.communication_settings',
    ];
  }

}
