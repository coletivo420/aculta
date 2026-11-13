<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\aculta_donation\Hook\DonationThemeHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contrato do registro de temas do fluxo de doação, após a migração para classe de hook.
 */
#[Group('aculta_donation')]
final class DonationThemeHooksTest extends UnitTestCase {

  /**
   * Os templates do fluxo e os dois temas de pane do Commerce continuam registrados.
   */
  public function testThemeRegistryKeepsDonationTemplates(): void {
    $theme = (new DonationThemeHooks())->theme();

    $this->assertSame(
      [
        'aculta_donation_quick',
        'aculta_donation_donate',
        'aculta_donation_back',
        'commerce_checkout_pane__commerce_donation_pane',
        'commerce_checkout_pane__commerce_memorial_pane',
      ],
      array_keys($theme),
    );
    $this->assertSame(['links' => []], $theme['aculta_donation_quick']['variables']);
    $this->assertSame(['label' => NULL, 'href' => NULL], $theme['aculta_donation_donate']['variables']);
    $this->assertSame('commerce_checkout_pane', $theme['commerce_checkout_pane__commerce_donation_pane']['base hook']);
  }

}
