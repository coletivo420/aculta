<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Adds the email settings tab to account configuration.
 */
final class SettingsTabsHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Adds the shared email preferences route to settings navigation.
   */
  #[Hook('aculta_portal_settings_tabs_alter')]
  public function addCommunicationSettings(array &$tabs): void {
    // Email preferences also control non-messaging notice categories.
    if (!$this->currentUser->hasPermission('access aculta portal')) {
      return;
    }

    $tabs['email'] = [
      'title' => (string) $this->t('Notificações por e-mail'),
      'route' => 'aculta_messenger.communication_settings',
    ];
  }

}
