<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Presentation\DomainPresentation;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * The theme receives neutral data; Domain objects stay in the Portal.
 */
#[Group('aculta_portal')]
final class DomainPresentationContractTest extends UnitTestCase {

  /**
   * Builds a representative presentation contract for Wiki.
   */
  private function presentation(): DomainPresentation {
    return new DomainPresentation(
      [
        'site_key' => 'wiki',
        'title' => 'Wiki420',
        'short_title' => 'Wiki',
        'home_url' => 'https://wiki420.example.invalid/',
        'logo_alt' => 'Wiki420',
      ],
      [
        'brand_media' => NULL,
        'navigation' => NULL,
        'actions' => NULL,
        'wiki_search' => [
          'preview' => [
            '#props' => [
              'attributes' => new Attribute(),
            ],
          ],
        ],
        'showcase' => NULL,
      ],
      (new CacheableMetadata())
        ->addCacheContexts(['domain', 'url.site'])
        ->addCacheTags(['config:domain.record.wiki420_aculta_org']),
      [
        'institution_bar_account' => FALSE,
        'show_utility_bar' => TRUE,
        'links_page' => FALSE,
        'show_site_header' => TRUE,
        'show_site_menu' => FALSE,
      ],
      [
        [
          'site_key' => 'main',
          'title' => 'Portal ACULTA',
          'url' => 'https://aculta.example.invalid/',
          'current' => FALSE,
        ],
      ],
    );
  }

  /**
   * Ensures the neutral contract exposes only its four top-level sections.
   */
  public function testThemeArrayContainsOnlyIdentityRegionsAndLayout(): void {
    $this->assertSame(['identity', 'regions', 'layout', 'domain_sites'], array_keys($this->presentation()->toThemeArray()));
  }

  /**
   * Ensures the functional identity crosses the boundary under the new name.
   */
  public function testIdentityUsesSiteKeyWithoutPurposeAlias(): void {
    $identity = $this->presentation()->toThemeArray()['identity'];
    $this->assertIsArray($identity);
    $this->assertSame('wiki', $identity['site_key']);
    $this->assertArrayNotHasKey('purpose', $identity);
  }

  /**
   * Ensures every layout value is a boolean visibility flag.
   */
  public function testLayoutFlagsAreBooleanVisibilityOnly(): void {
    $layout = $this->presentation()->toThemeArray()['layout'];
    $this->assertIsArray($layout);
    $this->assertSame(
      ['institution_bar_account', 'show_utility_bar', 'links_page', 'show_site_header', 'show_site_menu'],
      array_keys($layout),
    );
    foreach ($layout as $flag) {
      $this->assertIsBool($flag);
    }
  }

  /**
   * Ensures only the Core Form API render helper crosses this contract.
   */
  public function testThemeArrayAllowsOnlyTheCoreAttributeRenderHelper(): void {
    $this->assertSame(
      ['regions.wiki_search.preview.#props.attributes' => Attribute::class],
      $this->objectPaths($this->presentation()->toThemeArray()),
    );
  }

  /**
   * Ensures the contract forwards cache contexts and tags unchanged.
   */
  public function testCacheabilityBubblesUnchanged(): void {
    $presentation = $this->presentation();
    $this->assertEqualsCanonicalizing(
      ['domain', 'url.site'],
      $presentation->getCacheContexts(),
    );
    $this->assertSame(
      ['config:domain.record.wiki420_aculta_org'],
      $presentation->getCacheTags(),
    );
  }

  /**
   * Recursively collects objects and their paths in the presentation array.
   *
   * @param array<array-key, mixed> $data
   *   Presentation values to inspect.
   * @return array<string, class-string>
   *   Object paths and their PHP classes.
   */
  private function objectPaths(array $data, string $path = ''): array {
    $objects = [];
    foreach ($data as $key => $value) {
      $childPath = $path === '' ? (string) $key : $path . '.' . (string) $key;
      if (is_object($value)) {
        $objects[$childPath] = get_class($value);
      }
      elseif (is_array($value)) {
        $objects += $this->objectPaths($value, $childPath);
      }
    }
    return $objects;
  }

}
