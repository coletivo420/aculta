<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Hook;

use Drupal\aculta_studio\StudioPresenter;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Adds the Studio to the shared account navigation, as the other account sections.
 *
 * The section appears only when the account can see at least one tab, so an
 * account without any area never gets an empty page.
 */
final class StudioAccountHooks {

  public function __construct(
    private readonly StudioPresenter $presenter,
  ) {}

  /**
   * Adds the Studio section to the account shell.
   */
  #[Hook('aculta_portal_account_sections_alter')]
  public function addStudio(array &$sections, AccountInterface $account): void {
    if ($account->isAnonymous() || $this->presenter->visibleTabs($account) === []) {
      return;
    }
    $sections['aculta_studio.home'] = [
      'title' => new TranslatableMarkup('Estúdio'),
      'route' => 'aculta_studio.home',
      'icon' => 'pencil-square',
    ];
  }

}
