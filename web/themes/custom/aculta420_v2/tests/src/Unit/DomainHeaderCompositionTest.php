<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta420_v2\Hook\PagePresentationHooks;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers domain header composition outside MAIN and without shared menu blocks.
 */
#[CoversNothing]
#[Group('aculta420_v2')]
final class DomainHeaderCompositionTest extends UnitTestCase {

  /**
   * Uses the Portal identity when no branding block or shared menu exists.
   */
  public function testDomainHeaderUsesNeutralIdentityWithoutSharedMenu(): void {
    $hooks = new PagePresentationHooks(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(ExtensionList::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $variables = $this->pageVariables();
    $method = new \ReflectionMethod(PagePresentationHooks::class, 'composeDomainHeader');
    $method->invokeArgs($hooks, [&$variables]);

    $page = $variables['page'] ?? NULL;
    self::assertIsArray($page);
    $pageHeader = $page['header'] ?? NULL;
    self::assertIsArray($pageHeader);
    $header = $pageHeader['aculta_domain_header'] ?? NULL;
    self::assertIsArray($header);
    self::assertSame('aculta420_v2:navigation-bar', $header['#component']);
    $slots = $header['#slots'] ?? NULL;
    self::assertIsArray($slots);
    $brand = $slots['brand'] ?? NULL;
    self::assertIsArray($brand);
    self::assertSame('Minha conta', $brand['#title']);
    self::assertInstanceOf(Url::class, $brand['#url']);
    self::assertSame('https://conta.example.invalid/', $brand['#url']->getUri());
    self::assertArrayNotHasKey('menu_items', $slots);
  }

  /**
   * Does not synthesize a header when its presentation contract is absent.
   */
  public function testDomainHeaderRequiresExplicitPresentationAndAbsoluteHomeUrl(): void {
    $hooks = new PagePresentationHooks(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(ExtensionList::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $variables = ['page' => ['header' => ['sentinel' => ['#value' => 'preserved']]], 'domain_presentation' => [
      'identity' => ['title' => 'Portal', 'home_url' => '/'],
      'layout' => ['show_site_header' => TRUE],
      'regions' => [],
    ]];
    $variables = $this->compose($hooks, $variables);
    $page = $variables['page'] ?? NULL;
    self::assertIsArray($page);
    $pageHeader = $page['header'] ?? NULL;
    self::assertIsArray($pageHeader);
    self::assertSame(['sentinel' => ['#value' => 'preserved']], $pageHeader);

    unset($variables['domain_presentation']);
    $variables = $this->compose($hooks, $variables);
    $page = $variables['page'] ?? NULL;
    self::assertIsArray($page);
    $pageHeader = $page['header'] ?? NULL;
    self::assertIsArray($pageHeader);
    self::assertSame(['sentinel' => ['#value' => 'preserved']], $pageHeader);
  }

  /**
   * Keeps the configured Drupal branding block in the SDC brand slot.
   */
  public function testDomainHeaderPreservesBrandingBlock(): void {
    $hooks = new PagePresentationHooks(
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(ExtensionList::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $variables = $this->pageVariables();
    $page = $variables['page'] ?? NULL;
    self::assertIsArray($page);
    $pageHeader = $page['header'] ?? NULL;
    self::assertIsArray($pageHeader);
    $pageHeader['branding'] = [
      '#plugin_id' => 'system_branding_block',
      '#weight' => -10,
      'site_name' => ['#plain_text' => 'Brand Drupal'],
    ];
    $page['header'] = $pageHeader;
    $variables['page'] = $page;
    $variables = $this->compose($hooks, $variables);

    $page = $variables['page'] ?? NULL;
    self::assertIsArray($page);
    $pageHeader = $page['header'] ?? NULL;
    self::assertIsArray($pageHeader);
    $header = $pageHeader['branding'] ?? NULL;
    self::assertIsArray($header);
    $slots = $header['#slots'] ?? NULL;
    self::assertIsArray($slots);
    self::assertSame(
      ['#plugin_id' => 'system_branding_block', '#weight' => -10, 'site_name' => ['#plain_text' => 'Brand Drupal']],
      $slots['brand'] ?? NULL,
    );
  }

  /**
   * Supplies the neutral Portal presentation to the theme hook.
   *
   * @return array<string, mixed>
   *   Mutable page variables.
   */
  private function pageVariables(): array {
    return [
      'page' => ['header' => []],
      'domain_presentation' => [
        'identity' => [
          'title' => 'Minha conta',
          'home_url' => 'https://conta.example.invalid/',
        ],
        'layout' => ['show_site_header' => TRUE, 'show_site_menu' => FALSE],
        'regions' => [],
      ],
    ];
  }

  /**
   * Applies the private theme composition hook to a mutable variables array.
   *
   * @param array<string, mixed> $variables
   *   Page variables.
   *
   * @return array<string, mixed>
   *   Variables after header composition.
   */
  private function compose(PagePresentationHooks $hooks, array $variables): array {
    $method = new \ReflectionMethod(PagePresentationHooks::class, 'composeDomainHeader');
    $method->invokeArgs($hooks, [&$variables]);
    return $variables;
  }

}
