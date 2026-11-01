<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Presentation\DomainPresentation;
use Drupal\Core\Cache\CacheableMetadata;
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
        'purpose' => 'wiki',
        'title' => 'Wiki420',
        'short_title' => 'Wiki',
        'home_url' => 'https://wiki420.example.invalid/',
        'logo_alt' => 'Wiki420',
      ],
      [
        'brand_media' => NULL,
        'navigation' => NULL,
        'actions' => NULL,
        'wiki_search' => NULL,
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
      ],
    );
  }

  /**
   * Ensures the neutral contract exposes only its three top-level sections.
   */
  public function testThemeArrayContainsOnlyIdentityRegionsAndLayout(): void {
    $this->assertSame(['identity', 'regions', 'layout'], array_keys($this->presentation()->toThemeArray()));
  }

  /**
   * Ensures every layout value is a boolean visibility flag.
   */
  public function testLayoutFlagsAreBooleanVisibilityOnly(): void {
    $layout = $this->presentation()->toThemeArray()['layout'];
    $this->assertSame(
      ['institution_bar_account', 'show_utility_bar', 'links_page', 'show_site_header'],
      array_keys($layout),
    );
    foreach ($layout as $flag) {
      $this->assertIsBool($flag);
    }
  }

  /**
   * Ensures Portal objects never cross into the theme contract.
   */
  public function testThemeArrayHasNoObjects(): void {
    $this->assertFalse($this->containsObject($this->presentation()->toThemeArray()));
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
   * Recursively detects objects in the serialized presentation array.
   */
  private function containsObject(array $data): bool {
    foreach ($data as $value) {
      if (is_object($value)) {
        return TRUE;
      }
      if (is_array($value) && $this->containsObject($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
