<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Protects the Commerce product card SDC and native render-array adapter.
 *
 * @group aculta420_v2
 */
final class ProductCardComponentTest extends UnitTestCase {

  /**
   * Verifies the strict SDC contract, story and local asset.
   */
  public function testProductCardContract(): void {
    $themeDir = dirname(__DIR__, 3);
    $componentDir = $themeDir . '/components/product-card';
    $component = Yaml::parseFile($componentDir . '/product-card.component.yml');
    self::assertFalse($component['props']['additionalProperties']);
    self::assertSame([], $component['props']['required'] ?? []);
    self::assertArrayHasKey('content', $component['slots']);
    self::assertFileExists($componentDir . '/product-card.twig');
    self::assertFileExists($componentDir . '/product-card.css');
    self::assertFileExists($componentDir . '/product-card.default.story.yml');
  }

  /**
   * Ensures Commerce render arrays are passed through slots without rebuilding.
   */
  public function testCommerceCatalogCompositionPreservesNativeFields(): void {
    $themeDir = dirname(__DIR__, 3);
    $hook = (string) file_get_contents($themeDir . '/src/Hook/PagePresentationHooks.php');
    self::assertStringContainsString("'#component' => 'aculta420_v2:product-card'", $hook);
    self::assertStringContainsString('($elements[\'#view_mode\'] ?? NULL) !== \'catalog\'', $hook);
    self::assertStringContainsString('unset($fields[\'variation_attributes\'])', $hook);
    self::assertStringContainsString("'#slots' => ['content' => \$fields]", $hook);
    self::assertFileDoesNotExist($themeDir . '/templates/commerce/commerce-product--produto-loja--catalog.html.twig');
  }

  /**
   * Ensures both catalog outputs keep the native Commerce view mode and grid.
   */
  public function testCatalogViewComposition(): void {
    $root = dirname(__DIR__, 7);
    $view = Yaml::parseFile($root . '/config/sync/views.view.aculta_shop_catalog.yml');
    $default = $view['display']['default']['display_options'];
    self::assertSame('ui_patterns', $default['style']['type']);
    self::assertSame('aculta420_v2:layout-grid', $default['style']['options']['ui_patterns']['ui_patterns']['component_id']);
    self::assertSame('entity:commerce_product', $default['row']['type']);
    self::assertSame('catalog', $default['row']['options']['view_mode']);
    self::assertSame(12, $default['pager']['options']['items_per_page']);
    self::assertSame('access content', $default['access']['options']['perm']);
    foreach (['block_catalog', 'page_catalog'] as $display) {
      self::assertTrue($view['display'][$display]['display_options']['defaults']['style'] ?? TRUE);
    }
  }

}
