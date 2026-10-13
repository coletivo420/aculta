<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Aba "Privacidade" em Minha conta > Configurações (o Portal monta as abas e chama o alter).
 */
final class SettingsTabsHooks {

  /**
   * Acrescenta a aba Privacidade depois de "Modo de cor".
   */
  #[Hook('aculta_portal_settings_tabs_alter')]
  public function settingsTabsAlter(array &$tabs): void {
    $tabs['privacy'] = ['title' => (string) t('Privacidade'), 'route' => 'aculta_user.configuracoes_privacidade'];
  }

}
